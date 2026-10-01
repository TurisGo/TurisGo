<?php
$servidor = "localhost";
$usuario_bd = "root";
$password_bd = "";
$nombre_bd = "basedatos_turisgo";

$conexion = mysqli_connect($servidor, $usuario_bd, $password_bd, $nombre_bd);

if (!$conexion) {
    die("La conexión ha fallado: " . mysqli_connect_error());
}

mysqli_set_charset($conexion, "utf8mb4");
