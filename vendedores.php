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

// Debug: Mostrar información del POST
if ($_POST) {
    $mensaje .= "<div class='alert alert-info'>DEBUG: Se recibió POST con datos: " . print_r($_POST, true) . "</div>";
}

$mensaje .= "<div class='alert alert-info'>DEBUG: Acción actual: " . $accion . "</div>";

switch($accion) {
    case 'crear':
        $mensaje .= "<div class='alert alert-warning'>DEBUG: Entrando al case 'crear'</div>";
        if ($_POST) {
            $mensaje .= "<div class='alert alert-warning'>DEBUG: POST existe, procesando...</div>";
            try {
                $nombre = trim($_POST['nombre']);
                $mensaje .= "<div class='alert alert-info'>DEBUG: Nombre recibido: '" . htmlspecialchars($nombre) . "'</div>";
                
                if (empty($nombre)) {
                    $mensaje .= "<div class='alert alert-danger'>El nombre del vendedor es requerido</div>";
                } else {
                    $mensaje .= "<div class='alert alert-info'>DEBUG: Nombre válido, preparando consulta...</div>";
                    $sql = "INSERT INTO VendedoresCRM (Nombre) VALUES (?)";
                    $mensaje .= "<div class='alert alert-info'>DEBUG: SQL: " . $sql . "</div>";
                    
                    $stmt = sqlsrv_prepare($conn, $sql, array($nombre));
                    
                    if ($stmt === false) {
                        $errors = sqlsrv_errors();
                        $mensaje .= "<div class='alert alert-danger'>Error preparando consulta: " . print_r($errors, true) . "</div>";
                    } else {
                        $mensaje .= "<div class='alert alert-info'>DEBUG: Consulta preparada OK, ejecutando...</div>";
                        
                        if (sqlsrv_execute($stmt)) {
                            $mensaje .= "<div class='alert alert-success'>¡Vendedor creado exitosamente!</div>";
                        } else {
                            $errors = sqlsrv_errors();
                            $mensaje .= "<div class='alert alert-danger'>Error ejecutando consulta: " . print_r($errors, true) . "</div>";
                        }
                    }
                }
            } catch(Exception $e) {
                $mensaje .= "<div class='alert alert-danger'>Excepción: " . $e->getMessage() . "</div>";
            }
        } else {
            $mensaje .= "<div class='alert alert-warning'>DEBUG: No hay datos POST</div>";
        }
        break;
        
    case 'editar':
        if ($_POST) {
            try {
                $nombre = trim($_POST['nombre']);
                $id = $_POST['id'];
                if (empty($nombre)) {
                    $mensaje = "<div class='alert alert-danger'>El nombre del vendedor es requerido</div>";
                } else {
                    $sql = "UPDATE VendedoresCRM SET Nombre=? WHERE IdVendedor=?";
                    $stmt = sqlsrv_prepare($conn, $sql, array($nombre, $id));
                    
                    if (sqlsrv_execute($stmt)) {
                        $mensaje = "<div class='alert alert-success'>Vendedor actualizado exitosamente</div>";
                    } else {
                        $errors = sqlsrv_errors();
                        $mensaje = "<div class='alert alert-danger'>Error al actualizar vendedor: " . print_r($errors, true) . "</div>";
                    }
                }
            } catch(Exception $e) {
                $mensaje = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
            }
        }
        break;
        
        case 'eliminar':
        if (isset($_GET['id'])) {
            try {
                // Verificar si el vendedor tiene clientes asignados
                $sqlVerificar = "SELECT COUNT(*) as total FROM ClientesVendedoresCRM WHERE IdVendedor=? AND Activo=1";
                $stmtVerificar = sqlsrv_prepare($conn, $sqlVerificar, array($_GET['id']));
                sqlsrv_execute($stmtVerificar);
                $row = sqlsrv_fetch_array($stmtVerificar, SQLSRV_FETCH_ASSOC);
                
                if ($row['total'] > 0) {
                    $mensaje = "<div class='alert alert-warning'>No se puede desactivar el vendedor porque tiene clientes asignados</div>";
                } else {
                    $sql = "UPDATE VendedoresCRM SET Activo=0 WHERE IdVendedor=?";
                    $stmt = sqlsrv_prepare($conn, $sql, array($_GET['id']));
                    
                    if (sqlsrv_execute($stmt)) {
                        $mensaje = "<div class='alert alert-success'>Vendedor desactivado exitosamente</div>";
                    } else {
                        $errors = sqlsrv_errors();
                        $mensaje = "<div class='alert alert-danger'>Error al desactivar vendedor: " . print_r($errors, true) . "</div>";
                    }
                }
            } catch(Exception $e) {
                $mensaje = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
            }
        }
        break;
        
    case 'activar':
        if (isset($_GET['id'])) {
            try {
                $sql = "UPDATE VendedoresCRM SET Activo=1 WHERE IdVendedor=?";
                $stmt = sqlsrv_prepare($conn, $sql, array($_GET['id']));
                
                if (sqlsrv_execute($stmt)) {
                    $mensaje = "<div class='alert alert-success'>Vendedor activado exitosamente</div>";
                } else {
                    $mensaje = "<div class='alert alert-danger'>Error al activar vendedor</div>";
                }
            } catch(Exception $e) {
                $mensaje = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
            }
        }
        break;

    case 'quitar_cliente':
        if (isset($_GET['id_vendedor']) && isset($_GET['id_cliente'])) {
            try {
                $sql = "UPDATE ClientesVendedoresCRM SET Activo=0 WHERE IdVendedor=? AND IdCliente=?";
                $stmt = sqlsrv_prepare($conn, $sql, array($_GET['id_vendedor'], $_GET['id_cliente']));
                
                if (sqlsrv_execute($stmt)) {
                    $mensaje = "<div class='alert alert-success'>Cliente removido del vendedor exitosamente</div>";
                } else {
                    $mensaje = "<div class='alert alert-danger'>Error al remover cliente</div>";
                }
            } catch(Exception $e) {
                $mensaje = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
            }
        }
        break;
}

// Obtener lista de vendedores
$busqueda = $_GET['buscar'] ?? '';
$filtro_activo = $_GET['filtro'] ?? '1';

$sql = "SELECT v.IdVendedor, v.Nombre, v.Activo,
               COUNT(CASE WHEN cv.Activo = 1 THEN 1 END) as TotalClientes
        FROM VendedoresCRM v
        LEFT JOIN ClientesVendedoresCRM cv ON v.IdVendedor = cv.IdVendedor
        WHERE 1=1";
$params = array();

if (!empty($busqueda)) {
    $sql .= " AND v.Nombre LIKE ?";
    $params[] = "%$busqueda%";
}

if ($filtro_activo !== 'todos') {
    $sql .= " AND v.Activo = ?";
    $params[] = $filtro_activo;
}

$sql .= " GROUP BY v.IdVendedor, v.Nombre, v.Activo ORDER BY v.Nombre";

$stmt = sqlsrv_prepare($conn, $sql, $params);
sqlsrv_execute($stmt);

$vendedores = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $vendedores[] = $row;
}

// Obtener vendedor específico para edición
$vendedor_editar = null;
if ($accion == 'editar' && isset($_GET['id'])) {
    $sqlEditar = "SELECT * FROM VendedoresCRM WHERE IdVendedor=?";
    $stmtEditar = sqlsrv_prepare($conn, $sqlEditar, array($_GET['id']));
    sqlsrv_execute($stmtEditar);
    $vendedor_editar = sqlsrv_fetch_array($stmtEditar, SQLSRV_FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Vendedores - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card { margin-bottom: 20px; }
        .btn-sm { margin: 2px; }
        .vendedor-info { padding: 10px; border-left: 4px solid #28a745; }
        .vendedor-inactivo { opacity: 0.6; background-color: #f8f9fa; }
        .user-info { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .stats-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 10px;
        }
        .cliente-item {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 8px;
            margin: 2px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
                    <h2><i class="fas fa-user-tie"></i> Gestión de Vendedores</h2>
                    <div>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-home"></i> Inicio
                        </a>
                        <a href="clientes.php" class="btn btn-info">
                            <i class="fas fa-users"></i> Clientes
                        </a>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalVendedor">
                            <i class="fas fa-plus"></i> Nuevo Vendedor
                        </button>
                    </div>
                </div>

                <?php echo $mensaje; ?>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h3><?php echo count(array_filter($vendedores, function($v) { return $v['Activo']; })); ?></h3>
                                <p class="mb-0">Vendedores Activos</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h3><?php echo array_sum(array_column($vendedores, 'TotalClientes')); ?></h3>
                                <p class="mb-0">Total Asignaciones</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h3><?php echo count($vendedores); ?></h3>
                                <p class="mb-0">Total Vendedores</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h3><?php 
                                    $activos = array_filter($vendedores, function($v) { return $v['Activo'] && $v['TotalClientes'] > 0; });
                                    echo count($activos);
                                ?></h3>
                                <p class="mb-0">Con Clientes</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros y Búsqueda -->
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Buscar:</label>
                                <input type="text" name="buscar" class="form-control" 
                                       placeholder="Nombre del vendedor..." 
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
                                    <a href="vendedores.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Limpiar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Lista de Vendedores -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i> Lista de Vendedores 
                            <span class="badge bg-primary"><?php echo count($vendedores); ?></span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Clientes Asignados</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($vendedores)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <i class="fas fa-inbox fa-2x text-muted"></i>
                                                <p class="mt-2 text-muted">No se encontraron vendedores</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($vendedores as $vendedor): ?>
                                            <tr class="<?php echo !$vendedor['Activo'] ? 'vendedor-inactivo' : ''; ?>">
                                                <td><?php echo $vendedor['IdVendedor']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($vendedor['Nombre']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $vendedor['TotalClientes']; ?> clientes</span>
                                                    <?php if ($vendedor['TotalClientes'] > 0): ?>
                                                        <button class="btn btn-sm btn-outline-info ms-2" 
                                                                onclick="verClientes(<?php echo $vendedor['IdVendedor']; ?>, '<?php echo htmlspecialchars($vendedor['Nombre']); ?>')">
                                                            <i class="fas fa-eye"></i> Ver
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($vendedor['Activo']): ?>
                                                        <span class="badge bg-success">Activo</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Inactivo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-warning" 
                                                                onclick="editarVendedor(<?php echo htmlspecialchars(json_encode($vendedor)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        
                                                        <?php if ($vendedor['Activo']): ?>
                                                            <a href="?accion=eliminar&id=<?php echo $vendedor['IdVendedor']; ?>" 
                                                               class="btn btn-sm btn-danger" 
                                                               onclick="return confirm('¿Desactivar este vendedor?')">
                                                                <i class="fas fa-times"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="?accion=activar&id=<?php echo $vendedor['IdVendedor']; ?>" 
                                                               class="btn btn-sm btn-success" 
                                                               onclick="return confirm('¿Activar este vendedor?')">
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

    <!-- Modal para Crear/Editar Vendedor -->
    <div class="modal fade" id="modalVendedor" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="formVendedor" action="?accion=crear">
                    <div class="modal-header">
                        <h5 class="modal-title" id="tituloModal">
                            <i class="fas fa-user-plus"></i> Nuevo Vendedor
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="vendedorId" value="">
                        
                        <div class="mb-3">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" id="vendedorNombre" class="form-control" required maxlength="100" placeholder="Ingrese el nombre del vendedor">
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

    <!-- Modal para Ver Clientes del Vendedor -->
    <div class="modal fade" id="modalClientes" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tituloModalClientes">
                        <i class="fas fa-users"></i> Clientes Asignados
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="listaClientes">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin fa-2x"></i>
                            <p>Cargando clientes...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para editar vendedor
        function editarVendedor(vendedor) {
            document.getElementById('tituloModal').innerHTML = '<i class="fas fa-edit"></i> Editar Vendedor';
            document.getElementById('formVendedor').action = '?accion=editar';
            document.getElementById('vendedorId').value = vendedor.IdVendedor;
            document.getElementById('vendedorNombre').value = vendedor.Nombre;
            
            var modal = new bootstrap.Modal(document.getElementById('modalVendedor'));
            modal.show();
        }

        // Función para ver clientes del vendedor
        function verClientes(idVendedor, nombreVendedor) {
            document.getElementById('tituloModalClientes').innerHTML = 
                '<i class="fas fa-users"></i> Clientes de ' + nombreVendedor;
            
            // Cargar clientes via AJAX
            fetch('?accion=obtener_clientes&id_vendedor=' + idVendedor)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('listaClientes').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('listaClientes').innerHTML = 
                        '<div class="alert alert-danger">Error al cargar clientes</div>';
                });
            
            var modal = new bootstrap.Modal(document.getElementById('modalClientes'));
            modal.show();
        }

        // Limpiar formulario al cerrar modal
        document.getElementById('modalVendedor').addEventListener('hidden.bs.modal', function() {
            document.getElementById('formVendedor').reset();
            document.getElementById('formVendedor').action = '?accion=crear';
            document.getElementById('tituloModal').innerHTML = '<i class="fas fa-user-plus"></i> Nuevo Vendedor';
        });

        // Auto-cerrar alertas después de 5 segundos
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>

<?php
// AJAX para obtener clientes del vendedor
if ($accion == 'obtener_clientes' && isset($_GET['id_vendedor'])) {
    $idVendedor = $_GET['id_vendedor'];
    
    $sqlClientes = "
        SELECT c.IdCliente, c.Nombre, c.Telefono, c.Email, cv.FechaAsignacion
        FROM ClientesCRM c
        INNER JOIN ClientesVendedoresCRM cv ON c.IdCliente = cv.IdCliente
        WHERE cv.IdVendedor = ? AND cv.Activo = 1 AND c.Activo = 1
        ORDER BY cv.FechaAsignacion DESC";
    
    $stmtClientes = sqlsrv_prepare($conn, $sqlClientes, array($idVendedor));
    sqlsrv_execute($stmtClientes);
    
    $clientesVendedor = array();
    while ($row = sqlsrv_fetch_array($stmtClientes, SQLSRV_FETCH_ASSOC)) {
        $clientesVendedor[] = $row;
    }
    
    if (empty($clientesVendedor)) {
        echo '<div class="alert alert-info">Este vendedor no tiene clientes asignados</div>';
    } else {
        echo '<div class="row">';
        foreach ($clientesVendedor as $cliente) {
            $fechaAsignacion = $cliente['FechaAsignacion'] ? $cliente['FechaAsignacion']->format('d/m/Y') : 'N/A';
            echo '<div class="col-12 mb-2">';
            echo '<div class="cliente-item">';
            echo '<div>';
            echo '<strong>' . htmlspecialchars($cliente['Nombre']) . '</strong><br>';
            echo '<small class="text-muted">';
            echo '<i class="fas fa-phone"></i> ' . htmlspecialchars($cliente['Telefono'] ?? 'N/A') . ' | ';
            echo '<i class="fas fa-envelope"></i> ' . htmlspecialchars($cliente['Email'] ?? 'N/A') . '<br>';
            echo '<i class="fas fa-calendar"></i> Asignado: ' . $fechaAsignacion;
            echo '</small>';
            echo '</div>';
                            if (esAdmin()) {
                                echo '<div>';
                                echo '<a href="?accion=quitar_cliente&id_vendedor=' . $idVendedor . '&id_cliente=' . $cliente['IdCliente'] . '" ';
                                echo 'class="btn btn-sm btn-outline-danger" ';
                                echo 'onclick="return confirm(\'¿Quitar este cliente del vendedor?\')">';
                                echo '<i class="fas fa-times"></i>';
                                echo '</a>';
                                echo '</div>';
                            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
    exit;
}
?>