<?php
include("conexion.php");

$id = $_POST['ticket_id'];
$estado = $_POST['nuevo_estado'];
$usuario = isset($_POST['usuario_asignado']) ? $_POST['usuario_asignado'] : null;

// Verificamos si el estado es "Gestionado" y si el ticket está en "En Curso" y tiene un usuario asignado
if ($estado === 'Gestionado' && !$usuario) {
    echo "Error: Debes asignar un usuario antes de marcar como gestionado.";
    exit;
}

// Preparar la consulta para actualizar el estado y el usuario asignado si es necesario
if ($usuario) {
    $stmt = $conexion->prepare("UPDATE tickets SET estado_ticket = ?, usuario_asignado = ? WHERE id = ?");
    $stmt->bind_param("ssi", $estado, $usuario, $id);
} else {
    $stmt = $conexion->prepare("UPDATE tickets SET estado_ticket = ? WHERE id = ?");
    $stmt->bind_param("si", $estado, $id);
}

if ($stmt->execute()) {
    echo "OK";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
?>
