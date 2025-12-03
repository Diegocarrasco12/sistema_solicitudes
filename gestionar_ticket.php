<?php
include("conexion.php");

//revisa id
if (isset($_POST['ticket_id'])) {
    $ticket_id = intval($_POST['ticket_id']);

    // extrae de bd
    $query = "SELECT * FROM tickets WHERE id = $ticket_id";
    $resultado = $conexion->query($query);

    if ($resultado->num_rows > 0) {
        $ticket = $resultado->fetch_assoc();

        // Mmueve ticket
        $sql = "UPDATE tickets SET estado = 'Gestionado', tiempo_finalizado = NOW() WHERE id = $ticket_id";

        if ($conexion->query($sql) === TRUE) {
            echo "success"; // responde 
        } else {
            echo "Error al actualizar el ticket: " . $conexion->error;
        }
    } else {
        echo "Error: No se encontró el ticket.";
    }
} else {
    echo "Error: No se recibió un ID de ticket válido.";
}

$conexion->close();
?>
