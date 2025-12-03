<?php
include("conexion.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $es_admin = $_POST['es_admin'];
    $es_admin = ($es_admin == '1') ? 1 : 0;

    $stmt = $conexion->prepare("UPDATE usuarios SET es_admin = ? WHERE id = ?");
    $stmt->bind_param("ii", $es_admin, $id);

    if ($stmt->execute()) {
        echo "Actualizado correctamente.";
    } else {
        echo "Error al actualizar: " . $stmt->error;
    }
    $stmt->close();
}
?>
