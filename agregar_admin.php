<?php
include("conexion.php");

if (!empty($_POST['rut'])) {
    $rut = $_POST['rut'];
    $stmt = $conexion->prepare("UPDATE usuarios SET es_admin = 1 WHERE rut = ?");
    $stmt->bind_param("s", $rut);
    $stmt->execute();
    $stmt->close();
}
