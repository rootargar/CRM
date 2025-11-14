<?php
// usuarios.php
require_once 'conexion.php';

// Procesar acciones
if ($_POST) {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion == 'agregar') {
        $usuario = trim($_POST['usuario']);
        $clave = trim($_POST['clave']);
        $nombre = trim($_POST['nombre']);
        $rol = $_POST['rol'];
        $idVendedor = ($_POST['idVendedor'] != '') ? $_POST['idVendedor'] : null;
        $idSucursal = ($_POST['idSucursal'] != '') ? $_POST['idSucursal'] : null;

        // Verificar si el usuario ya existe
        $sqlVerificar = "SELECT COUNT(*) as existe FROM UsuariosCRM WHERE Usuario = ?";
        $stmt = sqlsrv_prepare($conn, $sqlVerificar, array($usuario));
        sqlsrv_execute($stmt);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

        if ($row['existe'] == 0) {
            // Insertar nuevo usuario
            $sqlInsert = "INSERT INTO UsuariosCRM (Usuario, Clave, Nombre, Rol, IdVendedor, IdSucursal, Activo)
                         VALUES (?, ?, ?, ?, ?, ?, 1)";
            $params = array($usuario, $clave, $nombre, $rol, $idVendedor, $idSucursal);
            $stmt = sqlsrv_prepare($conn, $sqlInsert, $params);

            if (sqlsrv_execute($stmt)) {
                $mensaje = "Usuario agregado correctamente";
                $tipoMensaje = "success";
            } else {
                $mensaje = "Error al agregar usuario";
                $tipoMensaje = "error";
            }
        } else {
            $mensaje = "El nombre de usuario ya existe";
            $tipoMensaje = "warning";
        }
    }
    elseif ($accion == 'editar') {
        $idUsuario = $_POST['idUsuario'];
        $usuario = trim($_POST['usuario']);
        $nombre = trim($_POST['nombre']);
        $rol = $_POST['rol'];
        $idVendedor = ($_POST['idVendedor'] != '') ? $_POST['idVendedor'] : null;
        $idSucursal = ($_POST['idSucursal'] != '') ? $_POST['idSucursal'] : null;

        // Verificar si el usuario ya existe (excluyendo el actual)
        $sqlVerificar = "SELECT COUNT(*) as existe FROM UsuariosCRM
                        WHERE Usuario = ? AND IdUsuario != ?";
        $stmt = sqlsrv_prepare($conn, $sqlVerificar, array($usuario, $idUsuario));
        sqlsrv_execute($stmt);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

        if ($row['existe'] == 0) {
            // Actualizar usuario
            $sqlUpdate = "UPDATE UsuariosCRM SET Usuario = ?, Nombre = ?, Rol = ?, IdVendedor = ?, IdSucursal = ?
                         WHERE IdUsuario = ?";
            $params = array($usuario, $nombre, $rol, $idVendedor, $idSucursal, $idUsuario);
            $stmt = sqlsrv_prepare($conn, $sqlUpdate, $params);

            if (sqlsrv_execute($stmt)) {
                $mensaje = "Usuario actualizado correctamente";
                $tipoMensaje = "success";
            } else {
                $mensaje = "Error al actualizar usuario";
                $tipoMensaje = "error";
            }
        } else {
            $mensaje = "El nombre de usuario ya existe";
            $tipoMensaje = "warning";
        }
    }
    elseif ($accion == 'cambiar_clave') {
        $idUsuario = $_POST['idUsuario'];
        $nuevaClave = trim($_POST['nueva_clave']);
        
        $sqlUpdate = "UPDATE UsuariosCRM SET Clave = ? WHERE IdUsuario = ?";
        $stmt = sqlsrv_prepare($conn, $sqlUpdate, array($nuevaClave, $idUsuario));
        
        if (sqlsrv_execute($stmt)) {
            $mensaje = "Contraseña cambiada correctamente";
            $tipoMensaje = "success";
        } else {
            $mensaje = "Error al cambiar contraseña";
            $tipoMensaje = "error";
        }
    }
    elseif ($accion == 'cambiar_estado') {
        $idUsuario = $_POST['idUsuario'];
        $nuevoEstado = $_POST['nuevo_estado'];
        
        $sqlUpdate = "UPDATE UsuariosCRM SET Activo = ? WHERE IdUsuario = ?";
        $stmt = sqlsrv_prepare($conn, $sqlUpdate, array($nuevoEstado, $idUsuario));
        
        if (sqlsrv_execute($stmt)) {
            $estadoTexto = ($nuevoEstado == 1) ? "activado" : "desactivado";
            $mensaje = "Usuario $estadoTexto correctamente";
            $tipoMensaje = "success";
        } else {
            $mensaje = "Error al cambiar estado del usuario";
            $tipoMensaje = "error";
        }
    }
}

// Obtener acción para mostrar formularios
$mostrarFormulario = $_GET['accion'] ?? 'listar';
$idUsuarioEditar = $_GET['id'] ?? 0;

// Obtener usuario para editar si es necesario
$usuarioEditar = null;
if ($mostrarFormulario == 'editar' && $idUsuarioEditar > 0) {
    $sqlUsuario = "SELECT * FROM UsuariosCRM WHERE IdUsuario = ?";
    $stmt = sqlsrv_prepare($conn, $sqlUsuario, array($idUsuarioEditar));
    sqlsrv_execute($stmt);
    $usuarioEditar = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

// Obtener lista de vendedores para el select
$sqlVendedores = "SELECT IdVendedor, Nombre FROM VendedoresCRM WHERE Activo = 1 ORDER BY Nombre";
$stmtVendedores = sqlsrv_query($conn, $sqlVendedores);

// Obtener lista de sucursales para el select
$sqlSucursales = "SELECT IdSucursal, Nombre FROM SucursalesCRM WHERE Activo = 1 ORDER BY IdSucursal";
$stmtSucursales = sqlsrv_query($conn, $sqlSucursales);

// Obtener lista de usuarios
$sqlUsuarios = "SELECT u.IdUsuario, u.Usuario, u.Nombre, u.Rol, u.Activo, u.IdVendedor, u.IdSucursal,
                       v.Nombre as NombreVendedor,
                       s.Nombre as NombreSucursal
                FROM UsuariosCRM u
                LEFT JOIN VendedoresCRM v ON u.IdVendedor = v.IdVendedor
                LEFT JOIN SucursalesCRM s ON u.IdSucursal = s.IdSucursal
                ORDER BY u.Nombre";
$stmtUsuarios = sqlsrv_query($conn, $sqlUsuarios);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
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
        h1, h3 {
            color: #333;
            text-align: center;
        }
        .menu-botones {
            text-align: center;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            border: none;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn:hover {
            opacity: 0.8;
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
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-row {
            display: flex;
            gap: 20px;
        }
        .form-row .form-group {
            flex: 1;
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
        .estado {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .activo {
            background-color: #d4edda;
            color: #155724;
        }
        .inactivo {
            background-color: #f8d7da;
            color: #721c24;
        }
        .btn-small {
            padding: 4px 8px;
            font-size: 12px;
            margin: 2px;
        }
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
            margin: 15% auto;
            padding: 20px;
            border-radius: 5px;
            width: 400px;
            max-width: 90%;
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
    <div class="container">
        <h1>Gestión de Usuarios</h1>
        
        <?php if (isset($mensaje)): ?>
            <div class="mensaje <?php echo $tipoMensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <!-- Menú de navegación -->
        <div class="menu-botones">
            <a href="?accion=listar" class="btn btn-primary">Ver Usuarios</a>
            <a href="?accion=agregar" class="btn btn-success">Agregar Usuario</a>
            <a href="index.php" class="btn btn-secondary">Menú Principal</a>
        </div>
        
        <?php if ($mostrarFormulario == 'agregar'): ?>
            <!-- Formulario Agregar Usuario -->
            <div class="formulario">
                <h3>Agregar Nuevo Usuario</h3>
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="agregar">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="usuario">Nombre de Usuario:</label>
                            <input type="text" name="usuario" id="usuario" required maxlength="50">
                        </div>
                        
                        <div class="form-group">
                            <label for="clave">Contraseña:</label>
                            <input type="password" name="clave" id="clave" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nombre">Nombre Completo:</label>
                            <input type="text" name="nombre" id="nombre" required maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="rol">Rol:</label>
                            <select name="rol" id="rol" required>
                                <option value="">-- Seleccionar rol --</option>
                                <option value="admin">Administrador</option>
                                <option value="vendedor">Vendedor</option>
                                <option value="supervisor">Supervisor</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="idVendedor">Asociar con Vendedor (opcional):</label>
                            <select name="idVendedor" id="idVendedor">
                                <option value="">-- Sin asociar --</option>
                                <?php
                                // Reset vendedores query
                                $stmtVendedores = sqlsrv_query($conn, $sqlVendedores);
                                while ($vendedor = sqlsrv_fetch_array($stmtVendedores, SQLSRV_FETCH_ASSOC)):
                                ?>
                                    <option value="<?php echo $vendedor['IdVendedor']; ?>">
                                        <?php echo htmlspecialchars($vendedor['Nombre']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="idSucursal">Asignar Sucursal (opcional):</label>
                            <select name="idSucursal" id="idSucursal">
                                <option value="">-- Todas las sucursales --</option>
                                <?php
                                // Reset sucursales query
                                $stmtSucursales = sqlsrv_query($conn, $sqlSucursales);
                                while ($sucursal = sqlsrv_fetch_array($stmtSucursales, SQLSRV_FETCH_ASSOC)):
                                ?>
                                    <option value="<?php echo $sucursal['IdSucursal']; ?>">
                                        <?php echo htmlspecialchars($sucursal['Nombre']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success">Guardar Usuario</button>
                    <a href="?accion=listar" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
            
        <?php elseif ($mostrarFormulario == 'editar' && $usuarioEditar): ?>
            <!-- Formulario Editar Usuario -->
            <div class="formulario">
                <h3>Editar Usuario</h3>
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="idUsuario" value="<?php echo $usuarioEditar['IdUsuario']; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="usuario">Nombre de Usuario:</label>
                            <input type="text" name="usuario" id="usuario" required maxlength="50" 
                                   value="<?php echo htmlspecialchars($usuarioEditar['Usuario']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="nombre">Nombre Completo:</label>
                            <input type="text" name="nombre" id="nombre" required maxlength="100"
                                   value="<?php echo htmlspecialchars($usuarioEditar['Nombre']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="rol">Rol:</label>
                            <select name="rol" id="rol" required>
                                <option value="admin" <?php echo ($usuarioEditar['Rol'] == 'admin') ? 'selected' : ''; ?>>Administrador</option>
                                <option value="vendedor" <?php echo ($usuarioEditar['Rol'] == 'vendedor') ? 'selected' : ''; ?>>Vendedor</option>
                                <option value="supervisor" <?php echo ($usuarioEditar['Rol'] == 'supervisor') ? 'selected' : ''; ?>>Supervisor</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="idVendedor">Asociar con Vendedor:</label>
                            <select name="idVendedor" id="idVendedor">
                                <option value="">-- Sin asociar --</option>
                                <?php
                                // Reset vendedores query
                                $stmtVendedores = sqlsrv_query($conn, $sqlVendedores);
                                while ($vendedor = sqlsrv_fetch_array($stmtVendedores, SQLSRV_FETCH_ASSOC)):
                                ?>
                                    <option value="<?php echo $vendedor['IdVendedor']; ?>"
                                            <?php echo ($usuarioEditar['IdVendedor'] == $vendedor['IdVendedor']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($vendedor['Nombre']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="idSucursal">Asignar Sucursal:</label>
                        <select name="idSucursal" id="idSucursal">
                            <option value="">-- Todas las sucursales --</option>
                            <?php
                            // Reset sucursales query
                            $stmtSucursales = sqlsrv_query($conn, $sqlSucursales);
                            while ($sucursal = sqlsrv_fetch_array($stmtSucursales, SQLSRV_FETCH_ASSOC)):
                            ?>
                                <option value="<?php echo $sucursal['IdSucursal']; ?>"
                                        <?php echo ($usuarioEditar['IdSucursal'] == $sucursal['IdSucursal']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sucursal['Nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-success">Actualizar Usuario</button>
                    <a href="?accion=listar" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        <?php endif; ?>
        
        <?php if ($mostrarFormulario == 'listar'): ?>
            <!-- Lista de Usuarios -->
            <div>
                <h3>Lista de Usuarios</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Nombre</th>
                            <th>Rol</th>
                            <th>Vendedor Asociado</th>
                            <th>Sucursal</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (sqlsrv_has_rows($stmtUsuarios)): ?>
                            <?php while ($usuario = sqlsrv_fetch_array($stmtUsuarios, SQLSRV_FETCH_ASSOC)): ?>
                                <tr>
                                    <td><?php echo $usuario['IdUsuario']; ?></td>
                                    <td><?php echo htmlspecialchars($usuario['Usuario']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['Nombre']); ?></td>
                                    <td><?php echo ucfirst($usuario['Rol']); ?></td>
                                    <td><?php echo $usuario['NombreVendedor'] ? htmlspecialchars($usuario['NombreVendedor']) : '-'; ?></td>
                                    <td><?php echo $usuario['NombreSucursal'] ? htmlspecialchars($usuario['NombreSucursal']) : '<em style="color:#999;">Todas</em>'; ?></td>
                                    <td>
                                        <span class="estado <?php echo $usuario['Activo'] ? 'activo' : 'inactivo'; ?>">
                                            <?php echo $usuario['Activo'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?accion=editar&id=<?php echo $usuario['IdUsuario']; ?>"
                                           class="btn btn-warning btn-small">Editar</a>

                                        <button onclick="cambiarClave(<?php echo $usuario['IdUsuario']; ?>)"
                                                class="btn btn-primary btn-small">Cambiar Clave</button>

                                        <form method="POST" style="display: inline;"
                                              onsubmit="return confirm('¿Está seguro de cambiar el estado?');">
                                            <input type="hidden" name="accion" value="cambiar_estado">
                                            <input type="hidden" name="idUsuario" value="<?php echo $usuario['IdUsuario']; ?>">
                                            <input type="hidden" name="nuevo_estado" value="<?php echo $usuario['Activo'] ? 0 : 1; ?>">
                                            <button type="submit" class="btn <?php echo $usuario['Activo'] ? 'btn-danger' : 'btn-success'; ?> btn-small">
                                                <?php echo $usuario['Activo'] ? 'Desactivar' : 'Activar'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: #666;">
                                    No hay usuarios registrados
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal para cambiar contraseña -->
    <div id="modalClave" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal()">&times;</span>
            <h3>Cambiar Contraseña</h3>
            <form method="POST" id="formClave">
                <input type="hidden" name="accion" value="cambiar_clave">
                <input type="hidden" name="idUsuario" id="idUsuarioModal">
                
                <div class="form-group">
                    <label for="nueva_clave">Nueva Contraseña:</label>
                    <input type="password" name="nueva_clave" id="nueva_clave" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label for="confirmar_clave">Confirmar Contraseña:</label>
                    <input type="password" id="confirmar_clave" required minlength="6">
                </div>
                
                <button type="submit" class="btn btn-success">Cambiar Contraseña</button>
                <button type="button" class="btn btn-secondary" onclick="cerrarModal()">Cancelar</button>
            </form>
        </div>
    </div>

    <script>
        // Función para abrir modal de cambio de contraseña
        function cambiarClave(idUsuario) {
            document.getElementById('idUsuarioModal').value = idUsuario;
            document.getElementById('modalClave').style.display = 'block';
        }
        
        // Función para cerrar modal
        function cerrarModal() {
            document.getElementById('modalClave').style.display = 'none';
            document.getElementById('formClave').reset();
        }
        
        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            var modal = document.getElementById('modalClave');
            if (event.target == modal) {
                cerrarModal();
            }
        }
        
        // Validar confirmación de contraseña
        document.getElementById('formClave').onsubmit = function(e) {
            var nueva = document.getElementById('nueva_clave').value;
            var confirmar = document.getElementById('confirmar_clave').value;
            
            if (nueva !== confirmar) {
                alert('Las contraseñas no coinciden');
                e.preventDefault();
                return false;
            }
        }
        
        // Auto-refresh después de mensajes
        <?php if (isset($mensaje)): ?>
            setTimeout(function() {
                window.location.href = '?accion=listar';
            }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>