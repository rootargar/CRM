<?php
// dashboard.php - Panel principal del CRM
require_once 'auth.php';
require_once 'conexion.php';
verificarSesion();

$usuario = obtenerDatosUsuario();

// Obtener estadísticas según el rol
$estadisticas = array();

if (esVendedor()) {
    // Estadísticas para vendedor
    $idVendedor = $usuario['id_vendedor'];

    // Total de clientes asignados
    $sql = "SELECT COUNT(*) as total FROM ClientesVendedoresCRM cv
            INNER JOIN ClientesCRM c ON cv.IdCliente = c.IdCliente
            WHERE cv.IdVendedor = ? AND cv.Activo = 1 AND c.Activo = 1";
    $stmt = sqlsrv_query($conn, $sql, array($idVendedor));
    $estadisticas['clientes'] = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)['total'] : 0;
    if ($stmt) sqlsrv_free_stmt($stmt);

    // Seguimientos del mes actual
    $sql = "SELECT COUNT(*) as total FROM SeguimientosCRM
            WHERE IdVendedor = ? AND MONTH(Fecha) = MONTH(GETDATE()) AND YEAR(Fecha) = YEAR(GETDATE())";
    $stmt = sqlsrv_query($conn, $sql, array($idVendedor));
    $estadisticas['seguimientos_mes'] = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)['total'] : 0;
    if ($stmt) sqlsrv_free_stmt($stmt);

    // Seguimientos pendientes
    $sql = "SELECT COUNT(*) as total FROM SeguimientosCRM
            WHERE IdVendedor = ? AND Estado = 'Pendiente'";
    $stmt = sqlsrv_query($conn, $sql, array($idVendedor));
    $estadisticas['pendientes'] = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)['total'] : 0;
    if ($stmt) sqlsrv_free_stmt($stmt);

    // Próximas acciones (próximos 7 días)
    $sql = "SELECT COUNT(*) as total FROM SeguimientosCRM
            WHERE IdVendedor = ? AND ProximaAccion IS NOT NULL
            AND ProximaAccion BETWEEN GETDATE() AND DATEADD(day, 7, GETDATE())";
    $stmt = sqlsrv_query($conn, $sql, array($idVendedor));
    $estadisticas['proximas_acciones'] = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)['total'] : 0;
    if ($stmt) sqlsrv_free_stmt($stmt);

} elseif (esSupervisor()) {
    // Estadísticas para supervisor (filtrado por sucursal)
    $idSucursal = $usuario['id_sucursal'];

    // Total de clientes de la sucursal
    $sql = "SELECT COUNT(*) as total FROM ClientesCRM WHERE Activo = 1 AND IdSucursal = ?";
    $stmt = sqlsrv_query($conn, $sql, array($idSucursal));
    $estadisticas['clientes'] = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)['total'] : 0;
    if ($stmt) sqlsrv_free_stmt($stmt);

    // Total de vendedores de la sucursal
    $sql = "SELECT COUNT(*) as total FROM VendedoresCRM WHERE Activo = 1 AND IdSucursal = ?";
    $stmt = sqlsrv_query($conn, $sql, array($idSucursal));
    $estadisticas['vendedores'] = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)['total'] : 0;
    if ($stmt) sqlsrv_free_stmt($stmt);

    // Seguimientos del mes de la sucursal
    $sql = "SELECT COUNT(*) as total FROM SeguimientosCRM s
            INNER JOIN ClientesCRM c ON s.IdCliente = c.IdCliente
            WHERE c.IdSucursal = ? AND MONTH(s.Fecha) = MONTH(GETDATE()) AND YEAR(s.Fecha) = YEAR(GETDATE())";
    $stmt = sqlsrv_query($conn, $sql, array($idSucursal));
    $estadisticas['seguimientos_mes'] = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)['total'] : 0;
    if ($stmt) sqlsrv_free_stmt($stmt);

    // Seguimientos pendientes de la sucursal
    $sql = "SELECT COUNT(*) as total FROM SeguimientosCRM s
            INNER JOIN ClientesCRM c ON s.IdCliente = c.IdCliente
            WHERE c.IdSucursal = ? AND s.Estado = 'Pendiente'";
    $stmt = sqlsrv_query($conn, $sql, array($idSucursal));
    $estadisticas['pendientes'] = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)['total'] : 0;
    if ($stmt) sqlsrv_free_stmt($stmt);

} else {
    // Estadísticas para administrador

    // Total de clientes
    $sql = "SELECT COUNT(*) as total FROM ClientesCRM WHERE Activo = 1";
    $stmt = sqlsrv_query($conn, $sql);
    $estadisticas['clientes'] = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)['total'] : 0;
    if ($stmt) sqlsrv_free_stmt($stmt);

    // Total de vendedores
    $sql = "SELECT COUNT(*) as total FROM VendedoresCRM WHERE Activo = 1";
    $stmt = sqlsrv_query($conn, $sql);
    $estadisticas['vendedores'] = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)['total'] : 0;
    if ($stmt) sqlsrv_free_stmt($stmt);

    // Seguimientos del mes
    $sql = "SELECT COUNT(*) as total FROM SeguimientosCRM
            WHERE MONTH(Fecha) = MONTH(GETDATE()) AND YEAR(Fecha) = YEAR(GETDATE())";
    $stmt = sqlsrv_query($conn, $sql);
    $estadisticas['seguimientos_mes'] = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)['total'] : 0;
    if ($stmt) sqlsrv_free_stmt($stmt);

    // Seguimientos pendientes (todos)
    $sql = "SELECT COUNT(*) as total FROM SeguimientosCRM WHERE Estado = 'Pendiente'";
    $stmt = sqlsrv_query($conn, $sql);
    $estadisticas['pendientes'] = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)['total'] : 0;
    if ($stmt) sqlsrv_free_stmt($stmt);
}

// Obtener actividad reciente según el rol
$actividad_reciente = array();

if (esVendedor()) {
    $sql = "SELECT TOP 10 s.*, c.Nombre as NombreCliente
            FROM SeguimientosCRM s
            INNER JOIN ClientesCRM c ON s.IdCliente = c.IdCliente
            WHERE s.IdVendedor = ?
            ORDER BY s.FechaRegistro DESC";
    $stmt = sqlsrv_query($conn, $sql, array($usuario['id_vendedor']));
} elseif (esSupervisor()) {
    $sql = "SELECT TOP 10 s.*, c.Nombre as NombreCliente, v.Nombre as NombreVendedor
            FROM SeguimientosCRM s
            INNER JOIN ClientesCRM c ON s.IdCliente = c.IdCliente
            INNER JOIN VendedoresCRM v ON s.IdVendedor = v.IdVendedor
            WHERE c.IdSucursal = ?
            ORDER BY s.FechaRegistro DESC";
    $stmt = sqlsrv_query($conn, $sql, array($usuario['id_sucursal']));
} else {
    $sql = "SELECT TOP 10 s.*, c.Nombre as NombreCliente, v.Nombre as NombreVendedor
            FROM SeguimientosCRM s
            INNER JOIN ClientesCRM c ON s.IdCliente = c.IdCliente
            INNER JOIN VendedoresCRM v ON s.IdVendedor = v.IdVendedor
            ORDER BY s.FechaRegistro DESC";
    $stmt = sqlsrv_query($conn, $sql);
}

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $actividad_reciente[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

// Obtener próximas acciones
$proximas_acciones = array();

if (esVendedor()) {
    $sql = "SELECT s.*, c.Nombre as NombreCliente
            FROM SeguimientosCRM s
            INNER JOIN ClientesCRM c ON s.IdCliente = c.IdCliente
            WHERE s.IdVendedor = ? AND s.ProximaAccion IS NOT NULL
            AND s.ProximaAccion >= GETDATE()
            ORDER BY s.ProximaAccion ASC";
    $stmt = sqlsrv_query($conn, $sql, array($usuario['id_vendedor']));
} elseif (esSupervisor()) {
    $sql = "SELECT TOP 15 s.*, c.Nombre as NombreCliente, v.Nombre as NombreVendedor
            FROM SeguimientosCRM s
            INNER JOIN ClientesCRM c ON s.IdCliente = c.IdCliente
            INNER JOIN VendedoresCRM v ON s.IdVendedor = v.IdVendedor
            WHERE c.IdSucursal = ? AND s.ProximaAccion IS NOT NULL AND s.ProximaAccion >= GETDATE()
            ORDER BY s.ProximaAccion ASC";
    $stmt = sqlsrv_query($conn, $sql, array($usuario['id_sucursal']));
} else {
    $sql = "SELECT TOP 15 s.*, c.Nombre as NombreCliente, v.Nombre as NombreVendedor
            FROM SeguimientosCRM s
            INNER JOIN ClientesCRM c ON s.IdCliente = c.IdCliente
            INNER JOIN VendedoresCRM v ON s.IdVendedor = v.IdVendedor
            WHERE s.ProximaAccion IS NOT NULL AND s.ProximaAccion >= GETDATE()
            ORDER BY s.ProximaAccion ASC";
    $stmt = sqlsrv_query($conn, $sql);
}

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $proximas_acciones[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

// Estadísticas por tipo de seguimiento (para admin y supervisor)
$stats_tipos = array();
if (esAdmin()) {
    $sql = "SELECT Tipo, COUNT(*) as total
            FROM SeguimientosCRM
            WHERE MONTH(Fecha) = MONTH(GETDATE()) AND YEAR(Fecha) = YEAR(GETDATE())
            GROUP BY Tipo";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $stats_tipos[$row['Tipo']] = $row['total'];
        }
        sqlsrv_free_stmt($stmt);
    }
} elseif (esSupervisor()) {
    $sql = "SELECT Tipo, COUNT(*) as total
            FROM SeguimientosCRM s
            INNER JOIN ClientesCRM c ON s.IdCliente = c.IdCliente
            WHERE c.IdSucursal = ? AND MONTH(s.Fecha) = MONTH(GETDATE()) AND YEAR(s.Fecha) = YEAR(GETDATE())
            GROUP BY Tipo";
    $stmt = sqlsrv_query($conn, $sql, array($usuario['id_sucursal']));
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $stats_tipos[$row['Tipo']] = $row['total'];
        }
        sqlsrv_free_stmt($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CRM</title>
   <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #ECF0F1 0%, #BDC3C7 100%);
        min-height: 100vh;
        color: #333;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    .header {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(8px);
        border-radius: 15px;
        padding: 20px 30px;
        margin-bottom: 30px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .header h1 {
        color: #444;
        font-size: 2em;
        font-weight: 600;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .user-avatar {
        width: 50px;
        height: 50px;
        background: linear-gradient(45deg, #5DADE2, #3498DB);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-weight: bold;
        font-size: 1.2em;
    }

    .logout-btn {
        background: #95A5A6;
        color: #fff;
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .logout-btn:hover {
        background: #7F8C8D;
        transform: translateY(-2px);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(8px);
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
        border: 1px solid #eee;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
    }

    .stat-card .icon {
        font-size: 2.5em;
        margin-bottom: 15px;
    }

    .stat-card .number {
        font-size: 2.3em;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .stat-card .label {
        color: #777;
        font-size: 1.1em;
    }

    .stat-clientes { border-left: 5px solid #5DADE2; }
    .stat-clientes .icon, .stat-clientes .number { color: #3498DB; }

    .stat-vendedores { border-left: 5px solid #95A5A6; }
    .stat-vendedores .icon, .stat-vendedores .number { color: #7F8C8D; }

    .stat-seguimientos { border-left: 5px solid #85C1E9; }
    .stat-seguimientos .icon, .stat-seguimientos .number { color: #5DADE2; }

    .stat-pendientes { border-left: 5px solid #BDC3C7; }
    .stat-pendientes .icon, .stat-pendientes .number { color: #95A5A6; }

    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }

    .content-section {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(8px);
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
    }

    .content-section h3 {
        color: #444;
        margin-bottom: 20px;
        font-size: 1.3em;
        border-bottom: 2px solid #ddd;
        padding-bottom: 10px;
    }

    .activity-item {
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 10px;
        background: #f1f3f5;
        border-left: 4px solid #5DADE2;
        transition: all 0.3s ease;
    }

    .activity-item:hover {
        background: #ECF0F1;
        transform: translateX(5px);
    }

    .activity-item .time {
        color: #888;
        font-size: 0.9em;
        margin-bottom: 5px;
    }

    .activity-item .desc {
        font-weight: 500;
    }

    .quick-actions {
        grid-column: 1 / -1;
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        justify-content: center;
    }

    .action-btn {
        background: linear-gradient(45deg, #5DADE2, #3498DB);
        color: #fff;
        padding: 15px 30px;
        border: none;
        border-radius: 25px;
        font-size: 1.1em;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
    }

    .action-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(52, 152, 219, 0.5);
    }

    .action-btn.secondary {
        background: linear-gradient(45deg, #BDC3C7, #95A5A6);
        box-shadow: 0 4px 15px rgba(149, 165, 166, 0.3);
    }

    .action-btn.secondary:hover {
        box-shadow: 0 8px 25px rgba(149, 165, 166, 0.5);
    }

    .badge {
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 0.8em;
        font-weight: bold;
    }

    .badge-pendiente { background: #fff8e1; color: #8c7e63; }
    .badge-completado { background: #e8f5e9; color: #388e3c; }
    .badge-visita { background: #e3f2fd; color: #1976d2; }
    .badge-llamada { background: #ede7f6; color: #5e35b1; }

    .no-data {
        text-align: center;
        color: #888;
        font-style: italic;
        padding: 20px;
    }

    @media (max-width: 768px) {
        .content-grid {
            grid-template-columns: 1fr;
        }

        .header {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }

        .quick-actions {
            flex-direction: column;
            align-items: center;
        }
    }
</style>

</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>Dashboard CRM</h1>
                <p>Bienvenido, <?php echo htmlspecialchars($usuario['nombre']); ?>
                   <small>(<?php echo ucfirst($usuario['rol']); ?>)</small>
                   <?php if (esSupervisor() && $usuario['sucursal_nombre']): ?>
                   <br><small>Sucursal: <?php echo htmlspecialchars($usuario['sucursal_nombre']); ?></small>
                   <?php endif; ?>
                </p>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($usuario['nombre'], 0, 2)); ?>
                </div>
                <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card stat-clientes">
                <div class="icon">👥</div>
                <div class="number"><?php echo $estadisticas['clientes']; ?></div>
                <div class="label">Clientes <?php echo esVendedor() ? 'Asignados' : 'Activos'; ?></div>
            </div>
            
            <?php if (esAdmin() || esSupervisor()): ?>
            <div class="stat-card stat-vendedores">
                <div class="icon">🏢</div>
                <div class="number"><?php echo $estadisticas['vendedores']; ?></div>
                <div class="label">Vendedores <?php echo esSupervisor() ? 'de Sucursal' : 'Activos'; ?></div>
            </div>
            <?php endif; ?>
            
            <div class="stat-card stat-seguimientos">
                <div class="icon">📊</div>
                <div class="number"><?php echo $estadisticas['seguimientos_mes']; ?></div>
                <div class="label">Seguimientos este mes</div>
            </div>
            
            <div class="stat-card stat-pendientes">
                <div class="icon">⏰</div>
                <div class="number"><?php echo $estadisticas['pendientes']; ?></div>
                <div class="label">Seguimientos Pendientes</div>
            </div>
            
            <?php if (esVendedor() && isset($estadisticas['proximas_acciones'])): ?>
            <div class="stat-card stat-pendientes">
                <div class="icon">📅</div>
                <div class="number"><?php echo $estadisticas['proximas_acciones']; ?></div>
                <div class="label">Próximas Acciones (7 días)</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Contenido principal -->
        <div class="content-grid">
            <!-- Actividad reciente -->
            <div class="content-section">
                <h3>📈 Actividad Reciente</h3>
                <?php if (empty($actividad_reciente)): ?>
                    <div class="no-data">No hay actividad reciente</div>
                <?php else: ?>
                    <?php foreach ($actividad_reciente as $actividad): ?>
                        <div class="activity-item">
                            <div class="time">
                                <?php echo $actividad['FechaRegistro']->format('d/m/Y H:i'); ?>
                            </div>
                            <div class="desc">
                                <span class="badge badge-<?php echo strtolower($actividad['Tipo']); ?>">
                                    <?php echo $actividad['Tipo']; ?>
                                </span>
                                con <strong><?php echo htmlspecialchars($actividad['NombreCliente']); ?></strong>
                                <?php if (!esVendedor() && isset($actividad['NombreVendedor'])): ?>
                                    <br><small>Por: <?php echo htmlspecialchars($actividad['NombreVendedor']); ?></small>
                                <?php endif; ?>
                                <br><small><?php echo htmlspecialchars($actividad['Motivo'] ?? 'Sin motivo especificado'); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Próximas acciones -->
            <div class="content-section">
                <h3>📅 Próximas Acciones</h3>
                <?php if (empty($proximas_acciones)): ?>
                    <div class="no-data">No hay acciones programadas</div>
                <?php else: ?>
                    <?php foreach (array_slice($proximas_acciones, 0, 8) as $accion): ?>
                        <div class="activity-item">
                            <div class="time">
                                <?php echo $accion['ProximaAccion']->format('d/m/Y H:i'); ?>
                            </div>
                            <div class="desc">
                                <strong><?php echo htmlspecialchars($accion['NombreCliente']); ?></strong>
                                <?php if (!esVendedor() && isset($accion['NombreVendedor'])): ?>
                                    <br><small>Vendedor: <?php echo htmlspecialchars($accion['NombreVendedor']); ?></small>
                                <?php endif; ?>
                                <br><small><?php echo htmlspecialchars($accion['Observaciones'] ?? 'Sin observaciones'); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Acciones rápidas -->
        <div class="content-section">
            <h3>🚀 Acciones Rápidas</h3>
            <div class="quick-actions">
                <a href="seguimientos.php" class="action-btn">
                    📋 Gestionar Seguimientos
                </a>

                <?php if (esAdmin() || esSupervisor()): ?>
                <a href="clientes.php" class="action-btn secondary">
                    👥 Gestionar Clientes
                </a>
                <a href="vendedores.php" class="action-btn secondary">
                    🏢 Gestionar Vendedores
                </a>
                <a href="reportes.php" class="action-btn">
                    📊 Ver Reportes
                </a>
                <a href="repvendedor.php" class="action-btn">
                    📈 Reportes de Vendedores
                </a>
                <a href="asignar.php" class="action-btn secondary">
                    🔗 Asignar Clientes
                </a>
                <?php elseif (esVendedor()): ?>
                <a href="repvendedor.php" class="action-btn">
                    📈 Mis Reportes
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ((esAdmin() || esSupervisor()) && !empty($stats_tipos)): ?>
        <!-- Estadísticas adicionales para admin y supervisor -->
        <div class="content-section">
            <h3>📊 Estadísticas del Mes por Tipo</h3>
            <div class="stats-grid">
                <?php foreach ($stats_tipos as $tipo => $total): ?>
                <div class="stat-card">
                    <div class="icon"><?php echo $tipo == 'Visita' ? '🏠' : '📞'; ?></div>
                    <div class="number"><?php echo $total; ?></div>
                    <div class="label"><?php echo $tipo . 's'; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Actualizar estadísticas cada 5 minutos
        setInterval(function() {
            location.reload();
        }, 300000);

        // Animación de números al cargar
        document.addEventListener('DOMContentLoaded', function() {
            const numbers = document.querySelectorAll('.stat-card .number');
            numbers.forEach(number => {
                const target = parseInt(number.textContent);
                let current = 0;
                const increment = target / 20;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        number.textContent = target;
                        clearInterval(timer);
                    } else {
                        number.textContent = Math.floor(current);
                    }
                }, 50);
            });
        });
    </script>
</body>
</html>