<?php
require_once 'conexion.php';
require_once 'auth.php';

verificarSesion();
$datosUsuario = obtenerDatosUsuario();

// Obtener parámetros de filtro
$tipoReporte = $_GET['tipo'] ?? 'clientes';
$idVendedor = $_GET['id_vendedor'] ?? '';
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registrosPorPagina = 20;

// Función para obtener vendedores activos
function obtenerVendedores($conn, $idSucursal = null) {
    $sql = "SELECT IdVendedor, Nombre FROM VendedoresCRM WHERE Activo = 1";
    $params = [];

    if ($idSucursal !== null) {
        $sql .= " AND IdSucursal = ?";
        $params[] = $idSucursal;
    }

    $sql .= " ORDER BY Nombre";
    $stmt = sqlsrv_query($conn, $sql, $params);
    $vendedores = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $vendedores[] = $row;
        }
    }
    return $vendedores;
}

// Función para reporte de clientes
function reporteClientes($conn, $idVendedor = '', $idSucursal = null, $pagina = 1, $registrosPorPagina = 20) {
    $whereVendedor = '';
    $whereSucursal = '';
    $params = [];

    if (!empty($idVendedor)) {
        $whereVendedor = "AND cv.IdVendedor = ?";
        $params[] = $idVendedor;
    }

    if ($idSucursal !== null) {
        $whereSucursal = "AND c.IdSucursal = ?";
        $params[] = $idSucursal;
    }

    // Calcular offset
    $offset = ($pagina - 1) * $registrosPorPagina;

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
            WHERE c.Activo = 1 $whereVendedor $whereSucursal
            GROUP BY c.IdCliente, c.Nombre, c.Telefono, c.Email, v.Nombre, cv.FechaAsignacion
            ORDER BY c.Nombre
            OFFSET $offset ROWS
            FETCH NEXT $registrosPorPagina ROWS ONLY";

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
function reporteVendedores($conn, $idSucursal = null, $pagina = 1, $registrosPorPagina = 20) {
    $whereSucursal = '';
    $params = [];

    if ($idSucursal !== null) {
        $whereSucursal = "AND v.IdSucursal = ?";
        $params[] = $idSucursal;
    }

    // Calcular offset
    $offset = ($pagina - 1) * $registrosPorPagina;

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
            WHERE v.Activo = 1 $whereSucursal
            GROUP BY v.IdVendedor, v.Nombre
            ORDER BY v.Nombre
            OFFSET $offset ROWS
            FETCH NEXT $registrosPorPagina ROWS ONLY";

    $stmt = sqlsrv_query($conn, $sql, $params);
    $resultados = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $resultados[] = $row;
        }
    }
    return $resultados;
}

// Función para reporte de seguimientos
function reporteSeguimientos($conn, $idVendedor = '', $idSucursal = null, $pagina = 1, $registrosPorPagina = 20) {
    $whereVendedor = '';
    $whereSucursal = '';
    $params = [];
    $whereConditions = [];

    if (!empty($idVendedor)) {
        $whereVendedor = "s.IdVendedor = ?";
        $whereConditions[] = $whereVendedor;
        $params[] = $idVendedor;
    }

    if ($idSucursal !== null) {
        $whereSucursal = "c.IdSucursal = ?";
        $whereConditions[] = $whereSucursal;
        $params[] = $idSucursal;
    }

    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }

    // Calcular offset
    $offset = ($pagina - 1) * $registrosPorPagina;

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
            $whereClause
            ORDER BY s.Fecha DESC
            OFFSET $offset ROWS
            FETCH NEXT $registrosPorPagina ROWS ONLY";

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

// Función para reporte de usuarios
function reporteUsuarios($conn, $idSucursal = null, $pagina = 1, $registrosPorPagina = 20) {
    $whereSucursal = '';
    $params = [];

    if ($idSucursal !== null) {
        $whereSucursal = "AND u.IdSucursal = ?";
        $params[] = $idSucursal;
    }

    // Calcular offset
    $offset = ($pagina - 1) * $registrosPorPagina;

    $sql = "SELECT
                u.IdUsuario,
                u.Usuario,
                u.Nombre,
                u.Rol,
                u.Activo,
                v.Nombre as NombreVendedor,
                s.Nombre as NombreSucursal
            FROM UsuariosCRM u
            LEFT JOIN VendedoresCRM v ON u.IdVendedor = v.IdVendedor
            LEFT JOIN SucursalesCRM s ON u.IdSucursal = s.IdSucursal
            WHERE 1=1 $whereSucursal
            ORDER BY u.Nombre
            OFFSET $offset ROWS
            FETCH NEXT $registrosPorPagina ROWS ONLY";

    $stmt = sqlsrv_query($conn, $sql, $params);
    $resultados = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $resultados[] = $row;
        }
    }
    return $resultados;
}

// Funciones para contar total de registros
function contarClientes($conn, $idVendedor = '', $idSucursal = null) {
    $whereVendedor = '';
    $whereSucursal = '';
    $params = [];

    if (!empty($idVendedor)) {
        $whereVendedor = "AND cv.IdVendedor = ?";
        $params[] = $idVendedor;
    }

    if ($idSucursal !== null) {
        $whereSucursal = "AND c.IdSucursal = ?";
        $params[] = $idSucursal;
    }

    $sql = "SELECT COUNT(DISTINCT c.IdCliente) as total
            FROM ClientesCRM c
            LEFT JOIN ClientesVendedoresCRM cv ON c.IdCliente = cv.IdCliente AND cv.Activo = 1
            WHERE c.Activo = 1 $whereVendedor $whereSucursal";

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt !== false) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        return $row['total'];
    }
    return 0;
}

function contarVendedores($conn, $idSucursal = null) {
    $whereSucursal = '';
    $params = [];

    if ($idSucursal !== null) {
        $whereSucursal = "AND IdSucursal = ?";
        $params[] = $idSucursal;
    }

    $sql = "SELECT COUNT(*) as total FROM VendedoresCRM WHERE Activo = 1 $whereSucursal";
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt !== false) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        return $row['total'];
    }
    return 0;
}

function contarSeguimientos($conn, $idVendedor = '', $idSucursal = null) {
    $whereConditions = [];
    $params = [];

    if (!empty($idVendedor)) {
        $whereConditions[] = "s.IdVendedor = ?";
        $params[] = $idVendedor;
    }

    if ($idSucursal !== null) {
        $whereConditions[] = "c.IdSucursal = ?";
        $params[] = $idSucursal;
    }

    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }

    $sql = "SELECT COUNT(*) as total
            FROM SeguimientosCRM s
            INNER JOIN ClientesCRM c ON s.IdCliente = c.IdCliente
            $whereClause";

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt !== false) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        return $row['total'];
    }
    return 0;
}

function contarUsuarios($conn, $idSucursal = null) {
    $whereSucursal = '';
    $params = [];

    if ($idSucursal !== null) {
        $whereSucursal = "AND IdSucursal = ?";
        $params[] = $idSucursal;
    }

    $sql = "SELECT COUNT(*) as total FROM UsuariosCRM WHERE 1=1 $whereSucursal";
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt !== false) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        return $row['total'];
    }
    return 0;
}

// Determinar el filtro de sucursal según el rol
$idSucursalFiltro = null;
if (esSupervisor()) {
    // Supervisores solo ven datos de su sucursal
    $idSucursalFiltro = $datosUsuario['id_sucursal'];
}

// Obtener datos según el tipo de reporte
$vendedores = obtenerVendedores($conn, $idSucursalFiltro);
$datos = [];
$totalRegistros = 0;

switch ($tipoReporte) {
    case 'clientes':
        $datos = reporteClientes($conn, $idVendedor, $idSucursalFiltro, $paginaActual, $registrosPorPagina);
        $totalRegistros = contarClientes($conn, $idVendedor, $idSucursalFiltro);
        break;
    case 'vendedores':
        $datos = reporteVendedores($conn, $idSucursalFiltro, $paginaActual, $registrosPorPagina);
        $totalRegistros = contarVendedores($conn, $idSucursalFiltro);
        break;
    case 'seguimientos':
        $datos = reporteSeguimientos($conn, $idVendedor, $idSucursalFiltro, $paginaActual, $registrosPorPagina);
        $totalRegistros = contarSeguimientos($conn, $idVendedor, $idSucursalFiltro);
        break;
    case 'usuarios':
        $datos = reporteUsuarios($conn, $idSucursalFiltro, $paginaActual, $registrosPorPagina);
        $totalRegistros = contarUsuarios($conn, $idSucursalFiltro);
        break;
}

// Calcular total de páginas
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);
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

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
            padding: 1rem 0;
        }

        .pagination a,
        .pagination span {
            padding: 0.5rem 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            text-decoration: none;
            color: #495057;
            transition: all 0.3s;
        }

        .pagination a:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination .active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }

        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .pagination-info {
            text-align: center;
            color: #6c757d;
            margin-top: 1rem;
            font-size: 0.9rem;
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
            <?php if (esSupervisor() && $datosUsuario['sucursal_nombre']): ?>
                | Sucursal: <?php echo htmlspecialchars($datosUsuario['sucursal_nombre']); ?>
            <?php endif; ?>
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
                                <option value="usuarios" <?php echo $tipoReporte == 'usuarios' ? 'selected' : ''; ?>>Usuarios</option>
                            </select>
                        </div>

                        <?php if ($tipoReporte != 'vendedores' && $tipoReporte != 'usuarios'): ?>
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

                        <?php if ($totalPaginas > 1): ?>
                        <div class="pagination">
                            <?php if ($paginaActual > 1): ?>
                                <a href="?tipo=<?php echo $tipoReporte; ?>&id_vendedor=<?php echo $idVendedor; ?>&pagina=<?php echo $paginaActual - 1; ?>">← Anterior</a>
                            <?php else: ?>
                                <span class="disabled">← Anterior</span>
                            <?php endif; ?>

                            <?php
                            $rango = 2;
                            $inicio = max(1, $paginaActual - $rango);
                            $fin = min($totalPaginas, $paginaActual + $rango);

                            if ($inicio > 1) {
                                echo '<a href="?tipo=' . $tipoReporte . '&id_vendedor=' . $idVendedor . '&pagina=1">1</a>';
                                if ($inicio > 2) echo '<span>...</span>';
                            }

                            for ($i = $inicio; $i <= $fin; $i++) {
                                if ($i == $paginaActual) {
                                    echo '<span class="active">' . $i . '</span>';
                                } else {
                                    echo '<a href="?tipo=' . $tipoReporte . '&id_vendedor=' . $idVendedor . '&pagina=' . $i . '">' . $i . '</a>';
                                }
                            }

                            if ($fin < $totalPaginas) {
                                if ($fin < $totalPaginas - 1) echo '<span>...</span>';
                                echo '<a href="?tipo=' . $tipoReporte . '&id_vendedor=' . $idVendedor . '&pagina=' . $totalPaginas . '">' . $totalPaginas . '</a>';
                            }
                            ?>

                            <?php if ($paginaActual < $totalPaginas): ?>
                                <a href="?tipo=<?php echo $tipoReporte; ?>&id_vendedor=<?php echo $idVendedor; ?>&pagina=<?php echo $paginaActual + 1; ?>">Siguiente →</a>
                            <?php else: ?>
                                <span class="disabled">Siguiente →</span>
                            <?php endif; ?>
                        </div>
                        <div class="pagination-info">
                            Página <?php echo $paginaActual; ?> de <?php echo $totalPaginas; ?> (Total: <?php echo $totalRegistros; ?> registros)
                        </div>
                        <?php endif; ?>
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

                        <?php if ($totalPaginas > 1): ?>
                        <div class="pagination">
                            <?php if ($paginaActual > 1): ?>
                                <a href="?tipo=<?php echo $tipoReporte; ?>&pagina=<?php echo $paginaActual - 1; ?>">← Anterior</a>
                            <?php else: ?>
                                <span class="disabled">← Anterior</span>
                            <?php endif; ?>

                            <?php
                            $rango = 2;
                            $inicio = max(1, $paginaActual - $rango);
                            $fin = min($totalPaginas, $paginaActual + $rango);

                            if ($inicio > 1) {
                                echo '<a href="?tipo=' . $tipoReporte . '&pagina=1">1</a>';
                                if ($inicio > 2) echo '<span>...</span>';
                            }

                            for ($i = $inicio; $i <= $fin; $i++) {
                                if ($i == $paginaActual) {
                                    echo '<span class="active">' . $i . '</span>';
                                } else {
                                    echo '<a href="?tipo=' . $tipoReporte . '&pagina=' . $i . '">' . $i . '</a>';
                                }
                            }

                            if ($fin < $totalPaginas) {
                                if ($fin < $totalPaginas - 1) echo '<span>...</span>';
                                echo '<a href="?tipo=' . $tipoReporte . '&pagina=' . $totalPaginas . '">' . $totalPaginas . '</a>';
                            }
                            ?>

                            <?php if ($paginaActual < $totalPaginas): ?>
                                <a href="?tipo=<?php echo $tipoReporte; ?>&pagina=<?php echo $paginaActual + 1; ?>">Siguiente →</a>
                            <?php else: ?>
                                <span class="disabled">Siguiente →</span>
                            <?php endif; ?>
                        </div>
                        <div class="pagination-info">
                            Página <?php echo $paginaActual; ?> de <?php echo $totalPaginas; ?> (Total: <?php echo $totalRegistros; ?> registros)
                        </div>
                        <?php endif; ?>
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

                        <?php if ($totalPaginas > 1): ?>
                        <div class="pagination">
                            <?php if ($paginaActual > 1): ?>
                                <a href="?tipo=<?php echo $tipoReporte; ?>&id_vendedor=<?php echo $idVendedor; ?>&pagina=<?php echo $paginaActual - 1; ?>">← Anterior</a>
                            <?php else: ?>
                                <span class="disabled">← Anterior</span>
                            <?php endif; ?>

                            <?php
                            $rango = 2;
                            $inicio = max(1, $paginaActual - $rango);
                            $fin = min($totalPaginas, $paginaActual + $rango);

                            if ($inicio > 1) {
                                echo '<a href="?tipo=' . $tipoReporte . '&id_vendedor=' . $idVendedor . '&pagina=1">1</a>';
                                if ($inicio > 2) echo '<span>...</span>';
                            }

                            for ($i = $inicio; $i <= $fin; $i++) {
                                if ($i == $paginaActual) {
                                    echo '<span class="active">' . $i . '</span>';
                                } else {
                                    echo '<a href="?tipo=' . $tipoReporte . '&id_vendedor=' . $idVendedor . '&pagina=' . $i . '">' . $i . '</a>';
                                }
                            }

                            if ($fin < $totalPaginas) {
                                if ($fin < $totalPaginas - 1) echo '<span>...</span>';
                                echo '<a href="?tipo=' . $tipoReporte . '&id_vendedor=' . $idVendedor . '&pagina=' . $totalPaginas . '">' . $totalPaginas . '</a>';
                            }
                            ?>

                            <?php if ($paginaActual < $totalPaginas): ?>
                                <a href="?tipo=<?php echo $tipoReporte; ?>&id_vendedor=<?php echo $idVendedor; ?>&pagina=<?php echo $paginaActual + 1; ?>">Siguiente →</a>
                            <?php else: ?>
                                <span class="disabled">Siguiente →</span>
                            <?php endif; ?>
                        </div>
                        <div class="pagination-info">
                            Página <?php echo $paginaActual; ?> de <?php echo $totalPaginas; ?> (Total: <?php echo $totalRegistros; ?> registros)
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($tipoReporte == 'usuarios'): ?>
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($datos); ?></div>
                    <div class="stat-label">Total Usuarios</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count(array_filter($datos, function($u) { return $u['Rol'] == 'admin'; })); ?></div>
                    <div class="stat-label">Administradores</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count(array_filter($datos, function($u) { return $u['Rol'] == 'supervisor'; })); ?></div>
                    <div class="stat-label">Supervisores</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count(array_filter($datos, function($u) { return $u['Rol'] == 'vendedor'; })); ?></div>
                    <div class="stat-label">Vendedores</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Reporte de Usuarios</h2>
                </div>
                <div class="table-container">
                    <?php if (empty($datos)): ?>
                        <div class="no-data">No se encontraron datos para mostrar</div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Nombre</th>
                                    <th>Rol</th>
                                    <th>Vendedor Asociado</th>
                                    <th>Sucursal</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($datos as $usuario): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($usuario['Usuario']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['Nombre']); ?></td>
                                    <td>
                                        <span class="badge <?php
                                            echo $usuario['Rol'] == 'admin' ? 'badge-success' :
                                                ($usuario['Rol'] == 'supervisor' ? 'badge-info' : 'badge-secondary');
                                        ?>">
                                            <?php echo ucfirst($usuario['Rol']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($usuario['NombreVendedor'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['NombreSucursal'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $usuario['Activo'] ? 'badge-success' : 'badge-secondary'; ?>">
                                            <?php echo $usuario['Activo'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?php if ($totalPaginas > 1): ?>
                        <div class="pagination">
                            <?php if ($paginaActual > 1): ?>
                                <a href="?tipo=<?php echo $tipoReporte; ?>&pagina=<?php echo $paginaActual - 1; ?>">← Anterior</a>
                            <?php else: ?>
                                <span class="disabled">← Anterior</span>
                            <?php endif; ?>

                            <?php
                            $rango = 2;
                            $inicio = max(1, $paginaActual - $rango);
                            $fin = min($totalPaginas, $paginaActual + $rango);

                            if ($inicio > 1) {
                                echo '<a href="?tipo=' . $tipoReporte . '&pagina=1">1</a>';
                                if ($inicio > 2) echo '<span>...</span>';
                            }

                            for ($i = $inicio; $i <= $fin; $i++) {
                                if ($i == $paginaActual) {
                                    echo '<span class="active">' . $i . '</span>';
                                } else {
                                    echo '<a href="?tipo=' . $tipoReporte . '&pagina=' . $i . '">' . $i . '</a>';
                                }
                            }

                            if ($fin < $totalPaginas) {
                                if ($fin < $totalPaginas - 1) echo '<span>...</span>';
                                echo '<a href="?tipo=' . $tipoReporte . '&pagina=' . $totalPaginas . '">' . $totalPaginas . '</a>';
                            }
                            ?>

                            <?php if ($paginaActual < $totalPaginas): ?>
                                <a href="?tipo=<?php echo $tipoReporte; ?>&pagina=<?php echo $paginaActual + 1; ?>">Siguiente →</a>
                            <?php else: ?>
                                <span class="disabled">Siguiente →</span>
                            <?php endif; ?>
                        </div>
                        <div class="pagination-info">
                            Página <?php echo $paginaActual; ?> de <?php echo $totalPaginas; ?> (Total: <?php echo $totalRegistros; ?> registros)
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
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