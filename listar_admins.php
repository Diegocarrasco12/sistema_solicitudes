<?php
include("conexion.php");

$resultado = $conexion->query("SELECT rut, nombre_completo AS nombre FROM usuarios WHERE es_admin = 1");
$admins = [];

while ($row = $resultado->fetch_assoc()) {
    $admins[] = $row;
}

echo json_encode($admins);
