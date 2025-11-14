<?php
$serverName = "KWSERVIFACT"; // Nombre del servidor SQL Server
$connectionOptions = array(
    "Database" => "CotizaKW", // Nombre de la base de datos
    "Uid" => "sa", // Usuario de la base de datos
    "PWD" => "f4cturAs" // Contraseña del usuario
);

// Establecer la conexión
$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}
?>
