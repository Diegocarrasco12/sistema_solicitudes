<?php
include("conexion.php");

$ticketId = $_POST['ticket_id'];
$usuario = $_POST['usuario_asignado'];
$categoria = $_POST['categoria'] ?? null;  // ✅ NUEVO

// Obtener fecha de creación del ticket
$stmt = $conexion->prepare("SELECT fecha_creacion FROM tickets WHERE id = ?");
$stmt->bind_param("i", $ticketId);
$stmt->execute();
$result = $stmt->get_result();
$ticket = $result->fetch_assoc();
$stmt->close();

if ($ticket) {
    $fecha_creacion = strtotime($ticket['fecha_creacion']);
    $ahora = time();
    $tiempo_gestionado = $ahora - $fecha_creacion;

    // Convertir a formato legible
    $horas = floor($tiempo_gestionado / 3600);
    $minutos = floor(($tiempo_gestionado % 3600) / 60);
    $segundos = $tiempo_gestionado % 60;
    $tiempo_legible = sprintf('%02dh %02dm %02ds', $horas, $minutos, $segundos);

    // ✅ Se agrega categoría al UPDATE
    $query = "UPDATE tickets 
              SET estado = 'Gestionado', estado_ticket = 'Gestionado', usuario_asignado = ?, 
                  tiempo_gestionado = ?, tiempo_gestionado_legible = ?, categoria = ?
              WHERE id = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("sissi", $usuario, $tiempo_gestionado, $tiempo_legible, $categoria, $ticketId);

    if ($stmt->execute()) {
        echo "✔ Ticket gestionado correctamente.";
    } else {
        echo "Error al actualizar el ticket: " . $conexion->error;
    }

    $stmt->close();
} else {
    echo "No se encontró el ticket.";
}
?>
