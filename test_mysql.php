<?php
$conexion = new mysqli("localhost", "root", "Admin123", "tickets_db");

if ($conexion->connect_error) {
    die("Fallo en la conexión: " . $conexion->connect_error);
}
echo "Conexión exitosa a MySQL desde IIS con PHP!";
?>