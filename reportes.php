<?php
require_once 'conexion.php';
require_once 'auth.php';

verificarSesion();
$datosUsuario = obtenerDatosUsuario();

// Obtener parámetros de filtro
$tipoReporte = $_GET['tipo'] ?? 'clientes';
$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');
$idVendedor = $_GET['id_vendedor'] ?? '';

// Función para obtener vendedores activos
function obtenerVendedores($conn) {
    $sql = "SELECT IdVendedor, Nombre FROM VendedoresCRM WHERE Activo = 1 ORDER BY Nombre";
    $stmt = sqlsrv_query($conn, $sql);
    $vendedores = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $vendedores[] = $row;
        }
    }
    return $vendedores;
}

// Función para reporte de clientes
function reporteClientes($conn, $fechaInicio, $fechaFin, $idVendedor = '') {
    $whereVendedor = '';
    $params = [$fechaInicio, $fechaFin];
    
    if (!empty($idVendedor)) {
        $whereVendedor = "AND cv.IdVendedor = ?";
        $params[] = $idVendedor;
    }
    
    $sql = "SELECT 
                c.IdCliente,
                c.Nombre,
                c.Telefono,
                c.Email,
                v.Nombre as NombreVendedor,
                cv.FechaAsignacion,
                COUNT(s.IdSeguimiento) as TotalSeguimientos,
                MAX(s.Fecha) as UltimoSeguimiento
            FROM ClientesCRM c
            LEFT JOIN ClientesVendedoresCRM cv ON c.IdCliente = cv.IdCliente AND cv.Activo = 1
            LEFT JOIN VendedoresCRM v ON cv.IdVendedor = v.IdVendedor
            LEFT JOIN SeguimientosCRM s ON c.IdCliente = s.IdCliente 
                AND s.Fecha BETWEEN ? AND DATEADD(day, 1, ?)
            WHERE c.Activo = 1 $whereVendedor
            GROUP BY c.IdCliente, c.Nombre, c.Telefono, c.Email, v.Nombre, cv.FechaAsignacion
            ORDER BY c.Nombre";
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    $resultados = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if ($row['UltimoSeguimiento']) {
                $row['UltimoSeguimiento'] = $row['UltimoSeguimiento']->format('Y-m-d H:i');
            }
            if ($row['FechaAsignacion']) {
                $row['FechaAsignacion'] = $row['FechaAsignacion']->format('Y-m-d');
            }
            $resultados[] = $row;
        }
    }
    return $resultados;
}

// Función para reporte de vendedores
function reporteVendedores($conn, $fechaInicio, $fechaFin) {
    $sql = "SELECT 
                v.IdVendedor,
                v.Nombre,
                COUNT(DISTINCT cv.IdCliente) as TotalClientes,
                COUNT(s.IdSeguimiento) as TotalSeguimientos,
                SUM(CASE WHEN s.Tipo = 'Visita' THEN 1 ELSE 0 END) as TotalVisitas,
                SUM(CASE WHEN s.Tipo = 'Llamada' THEN 1 ELSE 0 END) as TotalLlamadas,
                SUM(CASE WHEN s.Estado = 'Completado' THEN 1 ELSE 0 END) as SeguimientosCompletados
            FROM VendedoresCRM v
            LEFT JOIN ClientesVendedoresCRM cv ON v.IdVendedor = cv.IdVendedor AND cv.Activo = 1
            LEFT JOIN SeguimientosCRM s ON v.IdVendedor = s.IdVendedor 
                AND s.Fecha BETWEEN ? AND DATEADD(day, 1, ?)
            WHERE v.Activo = 1
            GROUP BY v.IdVendedor, v.Nombre
            ORDER BY v.Nombre";
    
    $stmt = sqlsrv_query($conn, $sql, [$fechaInicio, $fechaFin]);
    $resultados = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $resultados[] = $row;
        }
    }
    return $resultados;
}

// Función para reporte de seguimientos
function reporteSeguimientos($conn, $fechaInicio, $fechaFin, $idVendedor = '') {
    $whereVendedor = '';
    $params = [$fechaInicio, $fechaFin];
    
    if (!empty($idVendedor)) {
        $whereVendedor = "AND s.IdVendedor = ?";
        $params[] = $idVendedor;
    }
    
    $sql = "SELECT 
                s.IdSeguimiento,
                c.Nombre as NombreCliente,
                v.Nombre as NombreVendedor,
                s.Tipo,
                s.Fecha,
                s.Motivo,
                s.Resultado,
                s.Estado,
                s.ProximaAccion
            FROM SeguimientosCRM s
            INNER JOIN ClientesCRM c ON s.IdCliente = c.IdCliente
            INNER JOIN VendedoresCRM v ON s.IdVendedor = v.IdVendedor
            WHERE s.Fecha BETWEEN ? AND DATEADD(day, 1, ?) $whereVendedor
            ORDER BY s.Fecha DESC";
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    $resultados = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $row['Fecha'] = $row['Fecha']->format('Y-m-d H:i');
            if ($row['ProximaAccion']) {
                $row['ProximaAccion'] = $row['ProximaAccion']->format('Y-m-d H:i');
            }
            $resultados[] = $row;
        }
    }
    return $resultados;
}

// Obtener datos según el tipo de reporte
$vendedores = obtenerVendedores($conn);
$datos = [];

switch ($tipoReporte) {
    case 'clientes':
        $datos = reporteClientes($conn, $fechaInicio, $fechaFin, $idVendedor);
        break;
    case 'vendedores':
        $datos = reporteVendedores($conn, $fechaInicio, $fechaFin);
        break;
    case 'seguimientos':
        $datos = reporteSeguimientos($conn, $fechaInicio, $fechaFin, $idVendedor);
        break;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - CRM</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .header .user-info {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .card-header h2 {
            color: #495057;
            margin-bottom: 1rem;
        }

        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #495057;
        }

        .form-group select,
        .form-group input {
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: border-color 0.3s;
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.25);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-left: 0.5rem;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .table-container {
            padding: 1.5rem;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            position: sticky;
            top: 0;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        .badge {
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge-secondary {
            background: #e2e3e5;
            color: #383d41;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .no-data {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            color: white;
            text-decoration: none;
            opacity: 0.9;
            transition: opacity 0.3s;
        }

        .back-link:hover {
            opacity: 1;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 0.5rem;
            }
            
            .filters {
                grid-template-columns: 1fr;
            }
            
            .header {
                padding: 1rem;
            }
            
            .table-container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="dashboard.php" class="back-link">← Volver al inicio</a>
        <h1>Reportes del Sistema</h1>
        <div class="user-info">
            Usuario: <?php echo htmlspecialchars($datosUsuario['nombre']); ?> | 
            Rol: <?php echo htmlspecialchars($datosUsuario['rol']); ?>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>Filtros de Reporte</h2>
                <form method="GET" action="">
                    <div class="filters">
                        <div class="form-group">
                            <label for="tipo">Tipo de Reporte</label>
                            <select name="tipo" id="tipo" onchange="this.form.submit()">
                                <option value="clientes" <?php echo $tipoReporte == 'clientes' ? 'selected' : ''; ?>>Clientes</option>
                                <option value="vendedores" <?php echo $tipoReporte == 'vendedores' ? 'selected' : ''; ?>>Vendedores</option>
                                <option value="seguimientos" <?php echo $tipoReporte == 'seguimientos' ? 'selected' : ''; ?>>Seguimientos</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="fecha_inicio">Fecha Inicio</label>
                            <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?php echo $fechaInicio; ?>">
                        </div>

                        <div class="form-group">
                            <label for="fecha_fin">Fecha Fin</label>
                            <input type="date" name="fecha_fin" id="fecha_fin" value="<?php echo $fechaFin; ?>">
                        </div>

                        <?php if ($tipoReporte != 'vendedores'): ?>
                        <div class="form-group">
                            <label for="id_vendedor">Vendedor</label>
                            <select name="id_vendedor" id="id_vendedor">
                                <option value="">Todos los vendedores</option>
                                <?php foreach ($vendedores as $vendedor): ?>
                                <option value="<?php echo $vendedor['IdVendedor']; ?>" 
                                        <?php echo $idVendedor == $vendedor['IdVendedor'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vendedor['Nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary">Generar Reporte</button>
                    <button type="button" class="btn btn-secondary" onclick="window.print()">Imprimir</button>
                </form>
            </div>
        </div>

        <?php if ($tipoReporte == 'clientes'): ?>
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($datos); ?></div>
                    <div class="stat-label">Total Clientes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo array_sum(array_column($datos, 'TotalSeguimientos')); ?></div>
                    <div class="stat-label">Total Seguimientos</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Reporte de Clientes</h2>
                </div>
                <div class="table-container">
                    <?php if (empty($datos)): ?>
                        <div class="no-data">No se encontraron datos para mostrar</div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Teléfono</th>
                                    <th>Email</th>
                                    <th>Vendedor</th>
                                    <th>Fecha Asignación</th>
                                    <th>Seguimientos</th>
                                    <th>Último Seguimiento</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($datos as $cliente): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cliente['Nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($cliente['Telefono'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($cliente['Email'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($cliente['NombreVendedor'] ?? 'Sin asignar'); ?></td>
                                    <td><?php echo $cliente['FechaAsignacion'] ?? 'N/A'; ?></td>
                                    <td><span class="badge badge-info"><?php echo $cliente['TotalSeguimientos']; ?></span></td>
                                    <td><?php echo $cliente['UltimoSeguimiento'] ?? 'Sin seguimientos'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($tipoReporte == 'vendedores'): ?>
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($datos); ?></div>
                    <div class="stat-label">Total Vendedores</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo array_sum(array_column($datos, 'TotalClientes')); ?></div>
                    <div class="stat-label">Clientes Asignados</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo array_sum(array_column($datos, 'TotalSeguimientos')); ?></div>
                    <div class="stat-label">Total Seguimientos</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Reporte de Vendedores</h2>
                </div>
                <div class="table-container">
                    <?php if (empty($datos)): ?>
                        <div class="no-data">No se encontraron datos para mostrar</div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Vendedor</th>
                                    <th>Clientes</th>
                                    <th>Seguimientos</th>
                                    <th>Visitas</th>
                                    <th>Llamadas</th>
                                    <th>Completados</th>
                                    <th>% Efectividad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($datos as $vendedor): ?>
                                <?php 
                                $efectividad = $vendedor['TotalSeguimientos'] > 0 ? 
                                    round(($vendedor['SeguimientosCompletados'] / $vendedor['TotalSeguimientos']) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($vendedor['Nombre']); ?></td>
                                    <td><span class="badge badge-info"><?php echo $vendedor['TotalClientes']; ?></span></td>
                                    <td><span class="badge badge-secondary"><?php echo $vendedor['TotalSeguimientos']; ?></span></td>
                                    <td><span class="badge badge-warning"><?php echo $vendedor['TotalVisitas']; ?></span></td>
                                    <td><span class="badge badge-info"><?php echo $vendedor['TotalLlamadas']; ?></span></td>
                                    <td><span class="badge badge-success"><?php echo $vendedor['SeguimientosCompletados']; ?></span></td>
                                    <td><?php echo $efectividad; ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($tipoReporte == 'seguimientos'): ?>
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($datos); ?></div>
                    <div class="stat-label">Total Seguimientos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count(array_filter($datos, function($s) { return $s['Tipo'] == 'Visita'; })); ?></div>
                    <div class="stat-label">Visitas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count(array_filter($datos, function($s) { return $s['Tipo'] == 'Llamada'; })); ?></div>
                    <div class="stat-label">Llamadas</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Reporte de Seguimientos</h2>
                </div>
                <div class="table-container">
                    <?php if (empty($datos)): ?>
                        <div class="no-data">No se encontraron datos para mostrar</div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Vendedor</th>
                                    <th>Tipo</th>
                                    <th>Motivo</th>
                                    <th>Resultado</th>
                                    <th>Estado</th>
                                    <th>Próxima Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($datos as $seguimiento): ?>
                                <tr>
                                    <td><?php echo $seguimiento['Fecha']; ?></td>
                                    <td><?php echo htmlspecialchars($seguimiento['NombreCliente']); ?></td>
                                    <td><?php echo htmlspecialchars($seguimiento['NombreVendedor']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $seguimiento['Tipo'] == 'Visita' ? 'badge-warning' : 'badge-info'; ?>">
                                            <?php echo $seguimiento['Tipo']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($seguimiento['Motivo'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($seguimiento['Resultado'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $seguimiento['Estado'] == 'Completado' ? 'badge-success' : 'badge-warning'; ?>">
                                            <?php echo $seguimiento['Estado']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $seguimiento['ProximaAccion'] ?? 'Sin programar'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-submit form when dates change
        document.getElementById('fecha_inicio').addEventListener('change', function() {
            this.form.submit();
        });
        
        document.getElementById('fecha_fin').addEventListener('change', function() {
            this.form.submit();
        });
        
        // Auto-submit form when vendor selection changes (if visible)
        const vendedorSelect = document.getElementById('id_vendedor');
        if (vendedorSelect) {
            vendedorSelect.addEventListener('change', function() {
                this.form.submit();
            });
        }
    </script>
</body>
</html>