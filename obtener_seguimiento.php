<?php
// obtener_seguimiento.php - Obtener datos de un seguimiento específico
require_once 'auth.php';
require_once 'conexion.php';
verificarSesion();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID no proporcionado']);
    exit;
}

$idSeguimiento = $_GET['id'];
$usuario = obtenerDatosUsuario();

// Construir consulta según el rol del usuario
if (esVendedor()) {
    $sql = "SELECT s.*, c.Nombre as NombreCliente 
            FROM SeguimientosCRM s 
            INNER JOIN ClientesCRM c ON s.IdCliente = c.IdCliente 
            WHERE s.IdSeguimiento = ? AND s.IdVendedor = ?";
    $params = array($idSeguimiento, $usuario['id_vendedor']);
} else {
    $sql = "SELECT s.*, c.Nombre as NombreCliente, v.Nombre as NombreVendedor 
            FROM SeguimientosCRM s 
            INNER JOIN ClientesCRM c ON s.IdCliente = c.IdCliente 
            INNER JOIN VendedoresCRM v ON s.IdVendedor = v.IdVendedor 
            WHERE s.IdSeguimiento = ?";
    $params = array($idSeguimiento);
}

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    $seguimiento = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    
    if ($seguimiento) {
        // Formatear fechas para JSON
        if ($seguimiento['Fecha']) {
            $seguimiento['Fecha'] = $seguimiento['Fecha']->format('d/m/Y H:i');
        }
        if ($seguimiento['ProximaAccion']) {
            $seguimiento['ProximaAccion'] = $seguimiento['ProximaAccion']->format('Y-m-d\TH:i');
        }
        if ($seguimiento['FechaRegistro']) {
            $seguimiento['FechaRegistro'] = $seguimiento['FechaRegistro']->format('d/m/Y H:i');
        }
        
        echo json_encode([
            'success' => true, 
            'seguimiento' => $seguimiento
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'Seguimiento no encontrado o sin permisos'
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'error' => 'Error en la consulta: ' . print_r(sqlsrv_errors(), true)
    ]);
}
?>