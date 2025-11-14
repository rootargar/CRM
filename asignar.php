<?php
// asignar_clientes.php
require_once 'conexion.php';

// Procesar acciones
if ($_POST) {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion == 'asignar') {
        $idCliente = $_POST['idCliente'];
        $idVendedor = $_POST['idVendedor'];
        
        // Verificar si ya existe la asignación
        $sqlVerificar = "SELECT COUNT(*) as existe FROM ClientesVendedoresCRM 
                        WHERE IdCliente = ? AND IdVendedor = ? AND Activo = 1";
        $stmt = sqlsrv_prepare($conn, $sqlVerificar, array($idCliente, $idVendedor));
        $result = sqlsrv_execute($stmt);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        
        if ($row['existe'] == 0) {
            // Insertar nueva asignación
            $sqlInsert = "INSERT INTO ClientesVendedoresCRM (IdCliente, IdVendedor, FechaAsignacion, Activo) 
                         VALUES (?, ?, GETDATE(), 1)";
            $stmt = sqlsrv_prepare($conn, $sqlInsert, array($idCliente, $idVendedor));
            
            if (sqlsrv_execute($stmt)) {
                $mensaje = "Cliente asignado correctamente";
                $tipoMensaje = "success";
            } else {
                $mensaje = "Error al asignar cliente";
                $tipoMensaje = "error";
            }
        } else {
            $mensaje = "El cliente ya está asignado a este vendedor";
            $tipoMensaje = "warning";
        }
    } 
    elseif ($accion == 'desasignar') {
        $idCliente = $_POST['idCliente'];
        $idVendedor = $_POST['idVendedor'];
        
        // Desactivar la asignación
        $sqlUpdate = "UPDATE ClientesVendedoresCRM SET Activo = 0 
                     WHERE IdCliente = ? AND IdVendedor = ?";
        $stmt = sqlsrv_prepare($conn, $sqlUpdate, array($idCliente, $idVendedor));
        
        if (sqlsrv_execute($stmt)) {
            $mensaje = "Cliente desasignado correctamente";
            $tipoMensaje = "success";
        } else {
            $mensaje = "Error al desasignar cliente";
            $tipoMensaje = "error";
        }
    }
}

// Obtener lista de clientes activos
$sqlClientes = "SELECT IdCliente, Nombre, Telefono, Email FROM ClientesCRM WHERE Activo = 1 ORDER BY Nombre";
$stmtClientes = sqlsrv_query($conn, $sqlClientes);

// Obtener lista de vendedores activos
$sqlVendedores = "SELECT IdVendedor, Nombre FROM VendedoresCRM WHERE Activo = 1 ORDER BY Nombre";
$stmtVendedores = sqlsrv_query($conn, $sqlVendedores);

// Obtener asignaciones actuales
$sqlAsignaciones = "SELECT cv.IdCliente, cv.IdVendedor, c.Nombre as NombreCliente, 
                           v.Nombre as NombreVendedor, cv.FechaAsignacion
                    FROM ClientesVendedoresCRM cv
                    INNER JOIN ClientesCRM c ON cv.IdCliente = c.IdCliente
                    INNER JOIN VendedoresCRM v ON cv.IdVendedor = v.IdVendedor
                    WHERE cv.Activo = 1 AND c.Activo = 1 AND v.Activo = 1
                    ORDER BY c.Nombre, v.Nombre";
$stmtAsignaciones = sqlsrv_query($conn, $sqlAsignaciones);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignar Clientes</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .formulario {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        select, input[type="submit"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        input[type="submit"] {
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            margin-top: 10px;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
        .btn-desasignar {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-desasignar:hover {
            background-color: #c82333;
        }
        .mensaje {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .form-row {
            display: flex;
            gap: 20px;
        }
        .form-row .form-group {
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Asignar Clientes a Vendedores</h1>
        
        <?php if (isset($mensaje)): ?>
            <div class="mensaje <?php echo $tipoMensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <!-- Formulario de Asignación -->
        <div class="formulario">
            <h3>Nueva Asignación</h3>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="asignar">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="idCliente">Seleccionar Cliente:</label>
                        <select name="idCliente" id="idCliente" required>
                            <option value="">-- Seleccione un cliente --</option>
                            <?php while ($cliente = sqlsrv_fetch_array($stmtClientes, SQLSRV_FETCH_ASSOC)): ?>
                                <option value="<?php echo $cliente['IdCliente']; ?>">
                                    <?php echo htmlspecialchars($cliente['Nombre']); ?> 
                                    (<?php echo htmlspecialchars($cliente['Telefono']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="idVendedor">Seleccionar Vendedor:</label>
                        <select name="idVendedor" id="idVendedor" required>
                            <option value="">-- Seleccione un vendedor --</option>
                            <?php while ($vendedor = sqlsrv_fetch_array($stmtVendedores, SQLSRV_FETCH_ASSOC)): ?>
                                <option value="<?php echo $vendedor['IdVendedor']; ?>">
                                    <?php echo htmlspecialchars($vendedor['Nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <input type="submit" value="Asignar Cliente">
            </form>
        </div>
        
        <!-- Tabla de Asignaciones Actuales -->
        <div>
            <h3>Asignaciones Actuales</h3>
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th>Fecha Asignación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (sqlsrv_has_rows($stmtAsignaciones)): ?>
                        <?php while ($asignacion = sqlsrv_fetch_array($stmtAsignaciones, SQLSRV_FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($asignacion['NombreCliente']); ?></td>
                                <td><?php echo htmlspecialchars($asignacion['NombreVendedor']); ?></td>
                                <td><?php echo $asignacion['FechaAsignacion']->format('d/m/Y H:i'); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('¿Está seguro de desasignar este cliente?');">
                                        <input type="hidden" name="accion" value="desasignar">
                                        <input type="hidden" name="idCliente" value="<?php echo $asignacion['IdCliente']; ?>">
                                        <input type="hidden" name="idVendedor" value="<?php echo $asignacion['IdVendedor']; ?>">
                                        <button type="submit" class="btn-desasignar">Desasignar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: #666;">
                                No hay asignaciones registradas
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="dashboard.php" style="text-decoration: none; background-color: #6c757d; color: white; padding: 10px 20px; border-radius: 4px;">
                Volver al Menú Principal
            </a>
        </div>
    </div>

    <script>
        // Función para auto-refresh después de 3 segundos si hay mensaje
        <?php if (isset($mensaje)): ?>
            setTimeout(function() {
                window.location.href = window.location.pathname;
            }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>