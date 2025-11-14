<?php
// Incluir archivos necesarios
require_once 'auth.php';
require_once 'conexion.php';

// Verificar sesión
verificarSesion();

// Obtener datos del usuario
$datosUsuario = obtenerDatosUsuario();

// Procesar acciones
$accion = $_GET['accion'] ?? 'listar';
$mensaje = '';

switch($accion) {
    case 'crear':
        if ($_POST) {
            try {
                // Validar campos obligatorios
                if (empty($_POST['nombre'])) {
                    $mensaje = "<div class='alert alert-danger'>El nombre es obligatorio</div>";
                    break;
                }
                
                // Preparar los parámetros
                $nombre = trim($_POST['nombre']);
                $telefono = !empty($_POST['telefono']) ? trim($_POST['telefono']) : null;
                $email = !empty($_POST['email']) ? trim($_POST['email']) : null;
                
                // SQL con parámetros explícitos
                $sql = "INSERT INTO ClientesCRM (Nombre, Telefono, Email, Activo) VALUES (?, ?, ?, 1)";
                $params = array($nombre, $telefono, $email);
                
                // Ejecutar la consulta
                $stmt = sqlsrv_prepare($conn, $sql, $params);
                
                if ($stmt === false) {
                    throw new Exception("Error preparando la consulta: " . print_r(sqlsrv_errors(), true));
                }
                
                $result = sqlsrv_execute($stmt);
                
                if ($result === false) {
                    $errors = sqlsrv_errors();
                    throw new Exception("Error ejecutando la consulta: " . print_r($errors, true));
                }
                
                $mensaje = "<div class='alert alert-success'>Cliente creado exitosamente</div>";
                
            } catch(Exception $e) {
                $mensaje = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
                error_log("Error creando cliente: " . $e->getMessage());
            }
        }
        break;
        
    case 'editar':
        if ($_POST) {
            try {
                // Validar campos obligatorios
                if (empty($_POST['nombre']) || empty($_POST['id'])) {
                    $mensaje = "<div class='alert alert-danger'>El nombre y el ID son obligatorios</div>";
                    break;
                }
                
                $nombre = trim($_POST['nombre']);
                $telefono = !empty($_POST['telefono']) ? trim($_POST['telefono']) : null;
                $email = !empty($_POST['email']) ? trim($_POST['email']) : null;
                $id = (int)$_POST['id'];
                
                $sql = "UPDATE ClientesCRM SET Nombre=?, Telefono=?, Email=? WHERE IdCliente=?";
                $params = array($nombre, $telefono, $email, $id);
                
                $stmt = sqlsrv_prepare($conn, $sql, $params);
                
                if ($stmt === false) {
                    throw new Exception("Error preparando la consulta: " . print_r(sqlsrv_errors(), true));
                }
                
                $result = sqlsrv_execute($stmt);
                
                if ($result === false) {
                    $errors = sqlsrv_errors();
                    throw new Exception("Error ejecutando la consulta: " . print_r($errors, true));
                }
                
                $mensaje = "<div class='alert alert-success'>Cliente actualizado exitosamente</div>";
                
            } catch(Exception $e) {
                $mensaje = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
                error_log("Error actualizando cliente: " . $e->getMessage());
            }
        }
        break;
        
    case 'eliminar':
        if (isset($_GET['id'])) {
            try {
                $id = (int)$_GET['id'];
                $sql = "UPDATE ClientesCRM SET Activo=0 WHERE IdCliente=?";
                $stmt = sqlsrv_prepare($conn, $sql, array($id));
                
                if ($stmt === false) {
                    throw new Exception("Error preparando la consulta: " . print_r(sqlsrv_errors(), true));
                }
                
                if (sqlsrv_execute($stmt)) {
                    $mensaje = "<div class='alert alert-success'>Cliente desactivado exitosamente</div>";
                } else {
                    $errors = sqlsrv_errors();
                    throw new Exception("Error ejecutando la consulta: " . print_r($errors, true));
                }
            } catch(Exception $e) {
                $mensaje = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
                error_log("Error desactivando cliente: " . $e->getMessage());
            }
        }
        break;
        
    case 'activar':
        if (isset($_GET['id'])) {
            try {
                $id = (int)$_GET['id'];
                $sql = "UPDATE ClientesCRM SET Activo=1 WHERE IdCliente=?";
                $stmt = sqlsrv_prepare($conn, $sql, array($id));
                
                if ($stmt === false) {
                    throw new Exception("Error preparando la consulta: " . print_r(sqlsrv_errors(), true));
                }
                
                if (sqlsrv_execute($stmt)) {
                    $mensaje = "<div class='alert alert-success'>Cliente activado exitosamente</div>";
                } else {
                    $errors = sqlsrv_errors();
                    throw new Exception("Error ejecutando la consulta: " . print_r($errors, true));
                }
            } catch(Exception $e) {
                $mensaje = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
                error_log("Error activando cliente: " . $e->getMessage());
            }
        }
        break;
        
    case 'asignar_vendedor':
        if ($_POST) {
            try {
                $idCliente = (int)$_POST['id_cliente'];
                $idVendedor = (int)$_POST['id_vendedor'];
                
                // Verificar si ya existe la asignación
                $sqlVerificar = "SELECT COUNT(*) as total FROM ClientesVendedoresCRM WHERE IdCliente=? AND IdVendedor=? AND Activo=1";
                $stmtVerificar = sqlsrv_prepare($conn, $sqlVerificar, array($idCliente, $idVendedor));
                sqlsrv_execute($stmtVerificar);
                $row = sqlsrv_fetch_array($stmtVerificar, SQLSRV_FETCH_ASSOC);
                
                if ($row['total'] == 0) {
                    $sql = "INSERT INTO ClientesVendedoresCRM (IdCliente, IdVendedor, Activo) VALUES (?, ?, 1)";
                    $stmt = sqlsrv_prepare($conn, $sql, array($idCliente, $idVendedor));
                    
                    if (sqlsrv_execute($stmt)) {
                        $mensaje = "<div class='alert alert-success'>Vendedor asignado exitosamente</div>";
                    } else {
                        $errors = sqlsrv_errors();
                        throw new Exception("Error ejecutando la consulta: " . print_r($errors, true));
                    }
                } else {
                    $mensaje = "<div class='alert alert-warning'>El vendedor ya está asignado a este cliente</div>";
                }
            } catch(Exception $e) {
                $mensaje = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
                error_log("Error asignando vendedor: " . $e->getMessage());
            }
        }
        break;
}

// Obtener lista de clientes
$busqueda = $_GET['buscar'] ?? '';
$filtro_activo = $_GET['filtro'] ?? '1';

$sql = "SELECT IdCliente, Nombre, Telefono, Email, Activo FROM ClientesCRM WHERE 1=1";
$params = array();

if (!empty($busqueda)) {
    $sql .= " AND (Nombre LIKE ? OR Email LIKE ? OR Telefono LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

if ($filtro_activo !== 'todos') {
    $sql .= " AND Activo = ?";
    $params[] = (int)$filtro_activo;
}

$sql .= " ORDER BY Nombre";

$stmt = sqlsrv_prepare($conn, $sql, $params);
if ($stmt === false) {
    die("Error preparando consulta de clientes: " . print_r(sqlsrv_errors(), true));
}

if (sqlsrv_execute($stmt) === false) {
    die("Error ejecutando consulta de clientes: " . print_r(sqlsrv_errors(), true));
}

$clientes = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $clientes[] = $row;
}

// Obtener vendedores para asignación
$sqlVendedores = "SELECT IdVendedor, Nombre FROM VendedoresCRM WHERE Activo=1 ORDER BY Nombre";
$stmtVendedores = sqlsrv_query($conn, $sqlVendedores);

$vendedores = array();
if ($stmtVendedores !== false) {
    while ($row = sqlsrv_fetch_array($stmtVendedores, SQLSRV_FETCH_ASSOC)) {
        $vendedores[] = $row;
    }
}

// Obtener cliente específico para edición
$cliente_editar = null;
if ($accion == 'editar' && isset($_GET['id'])) {
    $sqlEditar = "SELECT * FROM ClientesCRM WHERE IdCliente=?";
    $stmtEditar = sqlsrv_prepare($conn, $sqlEditar, array((int)$_GET['id']));
    if ($stmtEditar !== false && sqlsrv_execute($stmtEditar)) {
        $cliente_editar = sqlsrv_fetch_array($stmtEditar, SQLSRV_FETCH_ASSOC);
    }
}

// Función para debug (puedes removerla después)
function debug_post() {
    if ($_POST) {
        error_log("POST Data: " . print_r($_POST, true));
    }
}
debug_post();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card { margin-bottom: 20px; }
        .btn-sm { margin: 2px; }
        .cliente-info { padding: 10px; border-left: 4px solid #007bff; }
        .cliente-inactivo { opacity: 0.6; background-color: #f8f9fa; }
        .user-info { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <!-- Información del usuario -->
                <div class="user-info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">Bienvenido, <?php echo htmlspecialchars($datosUsuario['nombre']); ?></h6>
                            <small>Rol: <?php echo ucfirst($datosUsuario['rol']); ?> | Usuario: <?php echo htmlspecialchars($datosUsuario['usuario']); ?></small>
                        </div>
                        <div>
                            <a href="logout.php" class="btn btn-light btn-sm">
                                <i class="fas fa-sign-out-alt"></i> Salir
                            </a>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-users"></i> Gestión de Clientes</h2>
                    <div>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-home"></i> Inicio
                        </a>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCliente">
                            <i class="fas fa-plus"></i> Nuevo Cliente
                        </button>
                    </div>
                </div>

                <?php echo $mensaje; ?>

                <!-- Filtros y Búsqueda -->
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Buscar:</label>
                                <input type="text" name="buscar" class="form-control" 
                                       placeholder="Nombre, email o teléfono..." 
                                       value="<?php echo htmlspecialchars($busqueda); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Estado:</label>
                                <select name="filtro" class="form-select">
                                    <option value="1" <?php echo $filtro_activo == '1' ? 'selected' : ''; ?>>Solo Activos</option>
                                    <option value="0" <?php echo $filtro_activo == '0' ? 'selected' : ''; ?>>Solo Inactivos</option>
                                    <option value="todos" <?php echo $filtro_activo == 'todos' ? 'selected' : ''; ?>>Todos</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-info">
                                        <i class="fas fa-search"></i> Buscar
                                    </button>
                                    <a href="clientes.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Limpiar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Lista de Clientes -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i> Lista de Clientes 
                            <span class="badge bg-primary"><?php echo count($clientes); ?></span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Teléfono</th>
                                        <th>Email</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($clientes)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <i class="fas fa-inbox fa-2x text-muted"></i>
                                                <p class="mt-2 text-muted">No se encontraron clientes</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($clientes as $cliente): ?>
                                            <tr class="<?php echo !$cliente['Activo'] ? 'cliente-inactivo' : ''; ?>">
                                                <td><?php echo $cliente['IdCliente']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($cliente['Nombre']); ?></strong>
                                                </td>
                                                <td>
                                                    <i class="fas fa-phone"></i> 
                                                    <?php echo htmlspecialchars($cliente['Telefono'] ?? 'N/A'); ?>
                                                </td>
                                                <td>
                                                    <i class="fas fa-envelope"></i> 
                                                    <?php echo htmlspecialchars($cliente['Email'] ?? 'N/A'); ?>
                                                </td>
                                                <td>
                                                    <?php if ($cliente['Activo']): ?>
                                                        <span class="badge bg-success">Activo</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Inactivo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-warning" 
                                                                onclick="editarCliente(<?php echo htmlspecialchars(json_encode($cliente)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        
                                                        <?php if (function_exists('esAdmin') && (esAdmin() || esVendedor())): ?>
                                                        <button class="btn btn-sm btn-info" 
                                                                onclick="asignarVendedor(<?php echo $cliente['IdCliente']; ?>, '<?php echo htmlspecialchars($cliente['Nombre']); ?>')">
                                                            <i class="fas fa-user-tie"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($cliente['Activo']): ?>
                                                            <a href="?accion=eliminar&id=<?php echo $cliente['IdCliente']; ?>" 
                                                               class="btn btn-sm btn-danger" 
                                                               onclick="return confirm('¿Desactivar este cliente?')">
                                                                <i class="fas fa-times"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="?accion=activar&id=<?php echo $cliente['IdCliente']; ?>" 
                                                               class="btn btn-sm btn-success" 
                                                               onclick="return confirm('¿Activar este cliente?')">
                                                                <i class="fas fa-check"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Crear/Editar Cliente -->
    <div class="modal fade" id="modalCliente" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="formCliente" action="?accion=crear">
                    <div class="modal-header">
                        <h5 class="modal-title" id="tituloModal">
                            <i class="fas fa-user-plus"></i> Nuevo Cliente
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="clienteId" value="">
                        
                        <div class="mb-3">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" id="clienteNombre" class="form-control" required maxlength="100" placeholder="Ingrese el nombre del cliente">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" id="clienteTelefono" class="form-control" maxlength="50" placeholder="Ingrese el teléfono">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="clienteEmail" class="form-control" maxlength="100" placeholder="ejemplo@correo.com">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para Asignar Vendedor -->
    <?php if (function_exists('esAdmin') && (esAdmin() || esVendedor())): ?>
    <div class="modal fade" id="modalVendedor" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="?accion=asignar_vendedor">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-user-tie"></i> Asignar Vendedor
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id_cliente" id="vendedorClienteId" value="">
                        
                        <div class="mb-3">
                            <label class="form-label">Cliente:</label>
                            <p class="form-control-plaintext" id="vendedorClienteNombre"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Vendedor *</label>
                            <select name="id_vendedor" class="form-select" required>
                                <option value="">Seleccione un vendedor...</option>
                                <?php foreach ($vendedores as $vendedor): ?>
                                    <option value="<?php echo $vendedor['IdVendedor']; ?>">
                                        <?php echo htmlspecialchars($vendedor['Nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Asignar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para editar cliente
        function editarCliente(cliente) {
            document.getElementById('tituloModal').innerHTML = '<i class="fas fa-edit"></i> Editar Cliente';
            document.getElementById('formCliente').action = '?accion=editar';
            document.getElementById('clienteId').value = cliente.IdCliente;
            document.getElementById('clienteNombre').value = cliente.Nombre;
            document.getElementById('clienteTelefono').value = cliente.Telefono || '';
            document.getElementById('clienteEmail').value = cliente.Email || '';
            
            var modal = new bootstrap.Modal(document.getElementById('modalCliente'));
            modal.show();
        }

        // Función para asignar vendedor
        function asignarVendedor(idCliente, nombreCliente) {
            document.getElementById('vendedorClienteId').value = idCliente;
            document.getElementById('vendedorClienteNombre').textContent = nombreCliente;
            
            var modal = new bootstrap.Modal(document.getElementById('modalVendedor'));
            modal.show();
        }

        // Limpiar formulario al cerrar modal
        document.getElementById('modalCliente').addEventListener('hidden.bs.modal', function() {
            document.getElementById('formCliente').reset();
            document.getElementById('formCliente').action = '?accion=crear';
            document.getElementById('tituloModal').innerHTML = '<i class="fas fa-user-plus"></i> Nuevo Cliente';
            document.getElementById('clienteId').value = '';
        });

        // Auto-cerrar alertas después de 5 segundos
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            });
        }, 5000);

        // Validación del formulario antes del envío
        document.getElementById('formCliente').addEventListener('submit', function(e) {
            var nombre = document.getElementById('clienteNombre').value.trim();
            if (nombre === '') {
                e.preventDefault();
                alert('El nombre es obligatorio');
                return false;
            }
        });
    </script>
</body>
</html>