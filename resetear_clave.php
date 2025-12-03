<?php
include("conexion.php");

if (!isset($_POST['id'])) {
    echo "ID no recibido.";
    exit;
}

$id = intval($_POST['id']);
$nueva_clave = "solicitudes123"; // Contraseña en texto plano

$sql = "UPDATE usuarios SET clave = '$nueva_clave', cambio_clave = 0 WHERE id = $id";

if ($conexion->query($sql) === TRUE) {
    echo "✅ Contraseña reseteada a 'solicitudes123'.";
} else {
    echo "❌ Error al resetear la clave: " . $conexion->error;
}
?>
