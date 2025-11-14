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
        'id_usuario' => $_SESSION['usuario_id'],
        'usuario' => $_SESSION['usuario'],
        'nombre' => $_SESSION['nombre'],
        'rol' => $_SESSION['rol'],
        'id_vendedor' => $_SESSION['id_vendedor']
    ];
}

function esAdmin() {
    return $_SESSION['rol'] === 'admin';
}

function esVendedor() {
    return $_SESSION['rol'] === 'vendedor';
}

function cerrarSesion() {
    session_destroy();
    header("Location: login.php");
    exit();
}
?>