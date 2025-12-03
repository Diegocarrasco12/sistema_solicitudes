<?php
include("conexion.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = $_POST["ticket_id"] ?? $_POST["id_ticket"] ?? null;
    $tipo = $_POST["tipo"];

    if (!is_numeric($id) || empty($tipo)) {
        http_response_code(400);
        echo "Parámetros inválidos.";
        exit;
    }

    // Recuperar la categoría actual del ticket
    $stmt = $conexion->prepare("SELECT categoria FROM tickets WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($categoria);
    $stmt->fetch();
    $stmt->close();

    // Reabrir el ticket y restaurar la categoría
    $stmt = $conexion->prepare("UPDATE tickets SET estado = '', estado_ticket = 'Ingresado', categoria = ? WHERE id = ?");
    $stmt->bind_param("si", $categoria, $id);

    if ($stmt->execute()) {
        echo "Ticket reabierto correctamente.";
    } else {
        http_response_code(500);
        echo "Error al reabrir el ticket.";
    }

    $stmt->close();
    $conexion->close();
}
?>
