<?php
include("conexion.php");

if (isset($_POST['ticket_id'], $_POST['categoria'])) {
    $id = $_POST['ticket_id'];
    $categoria = $_POST['categoria'];

    $stmt = $conexion->prepare("UPDATE tickets SET categoria = ? WHERE id = ?");
    $stmt->bind_param("si", $categoria, $id);

    if ($stmt->execute()) {
        echo "✔ Categoría actualizada";
    } else {
        echo "Error al actualizar: " . $stmt->error;
    }

    $stmt->close();
} else {
    echo "Parámetros inválidos";
}
?>
