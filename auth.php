<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function verificarSesion() {
    if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
        header("Location: login.php");
        exit();
    }
}

function obtenerDatosUsuario() {
    return [
        'id_usuario' => $_SESSION['usuario_id'] ?? null,
        'usuario' => $_SESSION['usuario'] ?? null,
        'nombre' => $_SESSION['nombre'] ?? null,
        'rol' => $_SESSION['rol'] ?? null,
        'id_vendedor' => $_SESSION['id_vendedor'] ?? null,
        'id_sucursal' => $_SESSION['id_sucursal'] ?? null,
        'sucursal_nombre' => $_SESSION['sucursal_nombre'] ?? null
    ];
}

function esAdmin() {
    return $_SESSION['rol'] === 'admin';
}

function esVendedor() {
    return $_SESSION['rol'] === 'vendedor';
}

function esSupervisor() {
    return $_SESSION['rol'] === 'supervisor';
}

function cerrarSesion() {
    session_destroy();
    header("Location: login.php");
    exit();
}
?>