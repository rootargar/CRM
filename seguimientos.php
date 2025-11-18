<?php
// seguimientos.php - Gestión de seguimientos (visitas y llamadas) - CORREGIDO
require_once 'auth.php';
require_once 'conexion.php';
verificarSesion();

$usuario = obtenerDatosUsuario();

// Función para convertir fecha simple (solo fecha, sin hora)
function convertirFechaParaSQL($fechaInput) {
    if (empty($fechaInput)) {
        return null;
    }
    
    // Limpiar la cadena de fecha
    $fechaInput = trim($fechaInput);
    
    // Debug: mostrar fecha recibida
    error_log("Fecha recibida: '$fechaInput'");
    
    // Si ya viene en formato YYYY-MM-DD, usarla directamente
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInput)) {
        return $fechaInput . ' 12:00:00';
    }
    
    // Array de formatos de fecha (sin hora)
    $formatosPosibles = [
        'Y-m-d',                // 2025-06-27 (HTML5 date)
        'd/m/Y',                // 27/06/2025
        'm/d/Y',                // 06/27/2025 (formato US)
        'd-m-Y',                // 27-06-2025
        'Y/m/d',                // 2025/06/27
    ];
    
    // Intentar parsear con cada formato
    foreach ($formatosPosibles as $formato) {
        $fecha = DateTime::createFromFormat($formato, $fechaInput);
        if ($fecha !== false) {
            // Verificar que la fecha parseada sea válida y no tenga errores
            $errores = DateTime::getLastErrors();
            if ($errores && $errores['warning_count'] == 0 && $errores['error_count'] == 0) {
                $fechaFormateada = $fecha->format('Y-m-d 12:00:00');
                error_log("Fecha convertida con formato '$formato': '$fechaFormateada'");
                return $fechaFormateada;
            }
        }
    }
    
    // Intentar crear DateTime directamente
    try {
        $fecha = new DateTime($fechaInput);
        $fechaFormateada = $fecha->format('Y-m-d 12:00:00');
        error_log("Fecha convertida con DateTime: '$fechaFormateada'");
        return $fechaFormateada;
    } catch (Exception $e) {
        error_log("Error con DateTime: " . $e->getMessage());
    }
    
    // Si todo falla, registrar el error para debugging
    error_log("ERROR: No se pudo convertir la fecha: '$fechaInput'");
    return null;
}

// Procesar formulario de nuevo seguimiento
if ($_POST && isset($_POST['accion']) && $_POST['accion'] == 'nuevo') {
    $idCliente = $_POST['id_cliente'];
    $tipo = $_POST['tipo'];
    
    // Usar solo la fecha sin hora
    $fecha = convertirFechaParaSQL($_POST['fecha']);
    
    $motivo = $_POST['motivo'];
    $resultado = $_POST['resultado'];
    $observaciones = $_POST['observaciones'];
    
    // Procesar próxima acción (también solo fecha)
    $proximaAccion = !empty($_POST['proxima_accion']) ? convertirFechaParaSQL($_POST['proxima_accion']) : null;
    
    $estado = $_POST['estado'];
    
    $idVendedor = esVendedor() ? $usuario['id_vendedor'] : $_POST['id_vendedor'];
    
    // Validar que la fecha se convirtió correctamente
    if ($fecha === null) {
        $error = "Error: Formato de fecha inválido. Fecha recibida: '" . htmlspecialchars($_POST['fecha']) . "'";
    } else {
        // Debug: mostrar los valores que se van a insertar
        error_log("Insertando - Cliente: $idCliente, Vendedor: $idVendedor, Tipo: $tipo, Fecha: $fecha");
        
        $sql = "INSERT INTO SeguimientosCRM (IdCliente, IdVendedor, Tipo, Fecha, Motivo, Resultado, Observaciones, ProximaAccion, Estado) 
                VALUES (?, ?, ?, CONVERT(DATETIME, ?, 120), ?, ?, ?, " . 
                ($proximaAccion ? "CONVERT(DATETIME, ?, 120)" : "NULL") . ", ?)";
        
        $params = array($idCliente, $idVendedor, $tipo, $fecha, $motivo, $resultado, $observaciones);
        
        if ($proximaAccion) {
            $params[] = $proximaAccion;
        }
        
        $params[] = $estado;
        
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt) {
            $mensaje = "Seguimiento registrado exitosamente";
            sqlsrv_free_stmt($stmt);
        } else {
            $error = "Error al registrar el seguimiento: " . print_r(sqlsrv_errors(), true);
            error_log("Error SQL: " . print_r(sqlsrv_errors(), true));
        }
    }
}

// Procesar actualización de seguimiento
if ($_POST && isset($_POST['accion']) && $_POST['accion'] == 'actualizar') {
    $idSeguimiento = $_POST['id_seguimiento'];
    $estado = $_POST['estado'];
    $resultado = $_POST['resultado'];
    $observaciones = $_POST['observaciones'];
    
    // Procesar próxima acción (solo fecha)
    $proximaAccion = !empty($_POST['proxima_accion']) ? convertirFechaParaSQL($_POST['proxima_accion']) : null;
    
    $sql = "UPDATE SeguimientosCRM SET Estado = ?, Resultado = ?, Observaciones = ?, ProximaAccion = " . 
           ($proximaAccion ? "CONVERT(DATETIME, ?, 120)" : "NULL") . " WHERE IdSeguimiento = ?";
    
    $params = array($estado, $resultado, $observaciones);
    
    if ($proximaAccion) {
        $params[] = $proximaAccion;
    }
    
    $params[] = $idSeguimiento;
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt) {
        $mensaje = "Seguimiento actualizado exitosamente";
        sqlsrv_free_stmt($stmt);
    } else {
        $error = "Error al actualizar el seguimiento: " . print_r(sqlsrv_errors(), true);
    }
}

// Obtener seguimientos según el rol y sucursal
if (esVendedor()) {
    // Vendedores ven solo sus seguimientos (ya está filtrado por IdVendedor)
    $sql = "SELECT s.*, c.Nombre as NombreCliente, v.Nombre as NombreVendedor,
                   suc.Nombre as NombreSucursal
            FROM SeguimientosCRM s
            INNER JOIN ClientesCRM c ON s.IdCliente = c.IdCliente
            INNER JOIN VendedoresCRM v ON s.IdVendedor = v.IdVendedor
            LEFT JOIN SucursalesCRM suc ON v.IdSucursal = suc.IdSucursal
            WHERE s.IdVendedor = ?
            ORDER BY s.Fecha DESC";
    $params = array($usuario['id_vendedor']);
    $stmt = sqlsrv_query($conn, $sql, $params);
} else {
    // Admin: si tiene sucursal asignada, filtrar por sucursal; si no, ver todos
    if (!empty($usuario['id_sucursal'])) {
        // Admin con sucursal asignada: ver seguimientos de vendedores de su sucursal
        $sql = "SELECT s.*, c.Nombre as NombreCliente, v.Nombre as NombreVendedor,
                       suc.Nombre as NombreSucursal
                FROM SeguimientosCRM s
                INNER JOIN ClientesCRM c ON s.IdCliente = c.IdCliente
                INNER JOIN VendedoresCRM v ON s.IdVendedor = v.IdVendedor
                LEFT JOIN SucursalesCRM suc ON v.IdSucursal = suc.IdSucursal
                WHERE v.IdSucursal = ? OR v.IdSucursal IS NULL
                ORDER BY s.Fecha DESC";
        $params = array($usuario['id_sucursal']);
        $stmt = sqlsrv_query($conn, $sql, $params);
    } else {
        // Admin sin sucursal asignada: ver todos los seguimientos
        $sql = "SELECT s.*, c.Nombre as NombreCliente, v.Nombre as NombreVendedor,
                       suc.Nombre as NombreSucursal
                FROM SeguimientosCRM s
                INNER JOIN ClientesCRM c ON s.IdCliente = c.IdCliente
                INNER JOIN VendedoresCRM v ON s.IdVendedor = v.IdVendedor
                LEFT JOIN SucursalesCRM suc ON v.IdSucursal = suc.IdSucursal
                ORDER BY s.Fecha DESC";
        $stmt = sqlsrv_query($conn, $sql);
    }
}

$seguimientos = array();
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $seguimientos[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

// Obtener clientes para el select
if (esVendedor()) {
    $sql = "SELECT DISTINCT c.IdCliente, c.Nombre
            FROM ClientesCRM c
            INNER JOIN ClientesVendedoresCRM cv ON c.IdCliente = cv.IdCliente
            WHERE cv.IdVendedor = ? AND cv.Activo = 1 AND c.Activo = 1
            ORDER BY c.Nombre";
    $params = array($usuario['id_vendedor']);
    $stmt = sqlsrv_query($conn, $sql, $params);
} else {
    // Admin: si tiene sucursal asignada, mostrar clientes de vendedores de su sucursal
    if (!empty($usuario['id_sucursal'])) {
        $sql = "SELECT DISTINCT c.IdCliente, c.Nombre
                FROM ClientesCRM c
                INNER JOIN ClientesVendedoresCRM cv ON c.IdCliente = cv.IdCliente
                INNER JOIN VendedoresCRM v ON cv.IdVendedor = v.IdVendedor
                WHERE c.Activo = 1 AND (v.IdSucursal = ? OR v.IdSucursal IS NULL)
                ORDER BY c.Nombre";
        $params = array($usuario['id_sucursal']);
        $stmt = sqlsrv_query($conn, $sql, $params);
    } else {
        $sql = "SELECT IdCliente, Nombre FROM ClientesCRM WHERE Activo = 1 ORDER BY Nombre";
        $stmt = sqlsrv_query($conn, $sql);
    }
}

$clientes = array();
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $clientes[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

// Obtener vendedores para admin
$vendedores = array();
if (esAdmin()) {
    // Admin: si tiene sucursal asignada, mostrar solo vendedores de su sucursal
    if (!empty($usuario['id_sucursal'])) {
        $sql = "SELECT IdVendedor, Nombre FROM VendedoresCRM
                WHERE Activo = 1 AND (IdSucursal = ? OR IdSucursal IS NULL)
                ORDER BY Nombre";
        $params = array($usuario['id_sucursal']);
        $stmt = sqlsrv_query($conn, $sql, $params);
    } else {
        $sql = "SELECT IdVendedor, Nombre FROM VendedoresCRM WHERE Activo = 1 ORDER BY Nombre";
        $stmt = sqlsrv_query($conn, $sql);
    }
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $vendedores[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Seguimientos - CRM</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            background: #f8f9fa; 
        }
        .header { 
            margin-bottom: 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .btn {
            padding: 10px 20px;
            background: #5DADE2;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 0 5px;
        }
        .btn:hover { background: #3498DB; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #1e7e34; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .btn-small { padding: 5px 10px; font-size: 12px; }
        
        .form-container { 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: none;
        }
        .form-row { 
            display: flex; 
            gap: 15px; 
            margin-bottom: 15px; 
        }
        .form-group { 
            flex: 1; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 5px; 
            font-weight: bold; 
        }
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%; 
            padding: 8px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            box-sizing: border-box;
        }
        .form-group textarea { 
            height: 80px; 
            resize: vertical; 
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            background: white; 
            border-radius: 8px; 
            overflow: hidden; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        th, td { 
            padding: 12px; 
            border-bottom: 1px solid #ddd; 
            text-align: left; 
        }
        th { 
            background-color: #f8f9fa; 
            font-weight: bold; 
        }
        tr:hover { background-color: #f5f5f5; }
        
        .alert { 
            padding: 15px; 
            margin-bottom: 20px; 
            border-radius: 4px; 
        }
        .alert-success { 
            background-color: #d4edda; 
            border: 1px solid #c3e6cb; 
            color: #155724; 
        }
        .alert-danger { 
            background-color: #f8d7da; 
            border: 1px solid #f5c6cb; 
            color: #721c24; 
        }
        
        .badge { 
            padding: 4px 8px; 
            border-radius: 12px; 
            font-size: 12px; 
            font-weight: bold; 
        }
        .badge-pendiente { background: #ffc107; color: #212529; }
        .badge-completado { background: #28a745; color: white; }
        .badge-cancelado { background: #dc3545; color: white; }
        .badge-visita { background: #17a2b8; color: white; }
        .badge-llamada { background: #6f42c1; color: white; }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: black;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Seguimientos - <?php echo htmlspecialchars($usuario['nombre']); ?></h2>
        <div>
            <a href="dashboard.php" class="btn">← Volver</a>
            <button class="btn btn-success" onclick="toggleFormulario()">+ Nuevo Seguimiento</button>
        </div>
    </div>

    <?php if (isset($mensaje)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Formulario para nuevo seguimiento -->
    <div id="formularioNuevo" class="form-container">
        <h3>Nuevo Seguimiento</h3>
        <form method="POST">
            <input type="hidden" name="accion" value="nuevo">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="id_cliente">Cliente:</label>
                    <select name="id_cliente" id="id_cliente" required>
                        <option value="">Seleccionar cliente...</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['IdCliente']; ?>">
                                <?php echo htmlspecialchars($cliente['Nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if (esAdmin()): ?>
                <div class="form-group">
                    <label for="id_vendedor">Vendedor:</label>
                    <select name="id_vendedor" id="id_vendedor" required>
                        <option value="">Seleccionar vendedor...</option>
                        <?php foreach ($vendedores as $vendedor): ?>
                            <option value="<?php echo $vendedor['IdVendedor']; ?>">
                                <?php echo htmlspecialchars($vendedor['Nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="tipo">Tipo:</label>
                    <select name="tipo" id="tipo" required>
                        <option value="">Seleccionar...</option>
                        <option value="Visita">Visita</option>
                        <option value="Llamada">Llamada</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="fecha">Fecha:</label>
                    <input type="date" name="fecha" id="fecha" required 
                           value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="estado">Estado:</label>
                    <select name="estado" id="estado" required>
                        <option value="Pendiente">Pendiente</option>
                        <option value="Completado">Completado</option>
                        <option value="Cancelado">Cancelado</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="proxima_accion">Próxima acción:</label>
                    <input type="date" name="proxima_accion" id="proxima_accion">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="motivo">Motivo:</label>
                    <input type="text" name="motivo" id="motivo" maxlength="255" 
                           placeholder="Motivo del seguimiento">
                </div>
                
                <div class="form-group">
                    <label for="resultado">Resultado:</label>
                    <input type="text" name="resultado" id="resultado" maxlength="255" 
                           placeholder="Resultado obtenido">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="observaciones">Observaciones:</label>
                    <textarea name="observaciones" id="observaciones" 
                              placeholder="Observaciones adicionales..."></textarea>
                </div>
            </div>
            
            <div class="form-row">
                <button type="submit" class="btn btn-success">Guardar Seguimiento</button>
                <button type="button" class="btn" onclick="toggleFormulario()">Cancelar</button>
            </div>
        </form>
    </div>

    <!-- Tabla de seguimientos -->
    <div class="table-container">
        <h3>Lista de Seguimientos</h3>
        <table>
            <thead>
                <tr>
                    <th>Cliente</th>
                    <?php if (!esVendedor()): ?>
                        <th>Vendedor</th>
                    <?php endif; ?>
                    <th>Tipo</th>
                    <th>Fecha</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                    <th>Próxima Acción</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($seguimientos)): ?>
                    <tr>
                        <td colspan="<?php echo esVendedor() ? '7' : '8'; ?>" style="text-align: center; color: #666;">
                            No hay seguimientos registrados
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($seguimientos as $seguimiento): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($seguimiento['NombreCliente']); ?></td>
                            <?php if (!esVendedor()): ?>
                                <td><?php echo htmlspecialchars($seguimiento['NombreVendedor']); ?></td>
                            <?php endif; ?>
                            <td>
                                <span class="badge badge-<?php echo strtolower($seguimiento['Tipo']); ?>">
                                    <?php echo htmlspecialchars($seguimiento['Tipo']); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                if ($seguimiento['Fecha'] && is_object($seguimiento['Fecha'])) {
                                    echo $seguimiento['Fecha']->format('d/m/Y');
                                } elseif ($seguimiento['Fecha']) {
                                    // Si viene como string, intentar convertir
                                    $fecha = new DateTime($seguimiento['Fecha']);
                                    echo $fecha->format('d/m/Y');
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($seguimiento['Motivo'] ?? ''); ?></td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($seguimiento['Estado']); ?>">
                                    <?php echo htmlspecialchars($seguimiento['Estado']); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                if ($seguimiento['ProximaAccion'] && is_object($seguimiento['ProximaAccion'])) {
                                    echo $seguimiento['ProximaAccion']->format('d/m/Y');
                                } elseif ($seguimiento['ProximaAccion']) {
                                    // Si viene como string, intentar convertir
                                    $fecha = new DateTime($seguimiento['ProximaAccion']);
                                    echo $fecha->format('d/m/Y');
                                }
                                ?>
                            </td>
                            <td>
                                <button class="btn btn-warning btn-small" 
                                        onclick="editarSeguimiento(<?php echo $seguimiento['IdSeguimiento']; ?>)">
                                    Editar
                                </button>
                                <button class="btn btn-small" 
                                        onclick="verDetalle(<?php echo $seguimiento['IdSeguimiento']; ?>)">
                                    Ver
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal para editar seguimiento -->
    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal()">&times;</span>
            <h3>Editar Seguimiento</h3>
            <form id="formEditar" method="POST">
                <input type="hidden" name="accion" value="actualizar">
                <input type="hidden" name="id_seguimiento" id="edit_id_seguimiento">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_estado">Estado:</label>
                        <select name="estado" id="edit_estado" required>
                            <option value="Pendiente">Pendiente</option>
                            <option value="Completado">Completado</option>
                            <option value="Cancelado">Cancelado</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_proxima_accion">Próxima acción:</label>
                        <input type="date" name="proxima_accion" id="edit_proxima_accion">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_resultado">Resultado:</label>
                        <input type="text" name="resultado" id="edit_resultado" maxlength="255">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_observaciones">Observaciones:</label>
                        <textarea name="observaciones" id="edit_observaciones"></textarea>
                    </div>
                </div>
                
                <div class="form-row">
                    <button type="submit" class="btn btn-success">Actualizar</button>
                    <button type="button" class="btn" onclick="cerrarModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para ver detalle -->
    <div id="modalDetalle" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModalDetalle()">&times;</span>
            <h3>Detalle del Seguimiento</h3>
            <div id="detalleContenido"></div>
        </div>
    </div>

    <script>
        function toggleFormulario() {
            const form = document.getElementById('formularioNuevo');
            form.style.display = form.style.display === 'none' || form.style.display === '' ? 'block' : 'none';
        }

        function editarSeguimiento(idSeguimiento) {
            // Hacer petición AJAX para obtener datos del seguimiento
            fetch('obtener_seguimiento.php?id=' + idSeguimiento)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_id_seguimiento').value = data.seguimiento.IdSeguimiento;
                        document.getElementById('edit_estado').value = data.seguimiento.Estado;
                        document.getElementById('edit_resultado').value = data.seguimiento.Resultado || '';
                        document.getElementById('edit_observaciones').value = data.seguimiento.Observaciones || '';
                        
                        if (data.seguimiento.ProximaAccion) {
                            // Convertir fecha para input tipo date
                            const fecha = new Date(data.seguimiento.ProximaAccion);
                            const year = fecha.getFullYear();
                            const month = String(fecha.getMonth() + 1).padStart(2, '0');
                            const day = String(fecha.getDate()).padStart(2, '0');
                            document.getElementById('edit_proxima_accion').value = `${year}-${month}-${day}`;
                        }
                        
                        document.getElementById('modalEditar').style.display = 'block';
                    } else {
                        alert('Error al cargar los datos del seguimiento');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar los datos del seguimiento');
                });
        }

        function verDetalle(idSeguimiento) {
            fetch('obtener_seguimiento.php?id=' + idSeguimiento + '&detalle=1')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const s = data.seguimiento;
                        const html = `
                            <p><strong>Cliente:</strong> ${s.NombreCliente}</p>
                            ${s.NombreVendedor ? `<p><strong>Vendedor:</strong> ${s.NombreVendedor}</p>` : ''}
                            <p><strong>Tipo:</strong> ${s.Tipo}</p>
                            <p><strong>Fecha:</strong> ${s.Fecha}</p>
                            <p><strong>Estado:</strong> ${s.Estado}</p>
                            <p><strong>Motivo:</strong> ${s.Motivo || 'N/A'}</p>
                            <p><strong>Resultado:</strong> ${s.Resultado || 'N/A'}</p>
                            <p><strong>Observaciones:</strong> ${s.Observaciones || 'N/A'}</p>
                            <p><strong>Próxima Acción:</strong> ${s.ProximaAccion || 'N/A'}</p>
                            <p><strong>Fecha de Registro:</strong> ${s.FechaRegistro}</p>
                        `;
                        document.getElementById('detalleContenido').innerHTML = html;
                        document.getElementById('modalDetalle').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar el detalle');
                });
        }

        function cerrarModal() {
            document.getElementById('modalEditar').style.display = 'none';
        }

        function cerrarModalDetalle() {
            document.getElementById('modalDetalle').style.display = 'none';
        }

        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modalEditar = document.getElementById('modalEditar');
            const modalDetalle = document.getElementById('modalDetalle');
            if (event.target == modalEditar) {
                modalEditar.style.display = 'none';
            }
            if (event.target == modalDetalle) {
                modalDetalle.style.display = 'none';
            }
        }
    </script>
</body>
</html>