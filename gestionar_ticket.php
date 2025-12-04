<?php
include("conexion.php");

// Zona horaria correcta
date_default_timezone_set('America/Santiago');

// Revisa ID recibido
if (isset($_POST['ticket_id'])) {
    $ticket_id = intval($_POST['ticket_id']);

    // Buscar ticket
    $query = "SELECT * FROM tickets WHERE id = $ticket_id";
    $resultado = $conexion->query($query);

    if ($resultado->num_rows > 0) {

        $ticket = $resultado->fetch_assoc();

        /********************************************
         * 1) CERRAR TRAMO ABIERTO (SI EXISTE)
         ********************************************/
        $tramo = $conexion->query("
            SELECT id, fecha_inicio
            FROM ticket_tramos
            WHERE ticket_id = $ticket_id
              AND fecha_fin IS NULL
            ORDER BY id DESC
            LIMIT 1
        ")->fetch_assoc();

        if ($tramo) {

            $fecha_inicio = $tramo['fecha_inicio'];
            $fecha_fin    = date("Y-m-d H:i:s");

            // Cálculo rápido: diferencia en minutos
            $minutos = intval((strtotime($fecha_fin) - strtotime($fecha_inicio)) / 60);

            // Cerrar tramo
            $conexion->query("
                UPDATE ticket_tramos
                SET fecha_fin = '$fecha_fin',
                    estado_fin = 'Cerrado',
                    minutos_habiles = $minutos
                WHERE id = {$tramo['id']}
            ");
        }

        /********************************************
         * 2) ACTUALIZAR EL TICKET A GESTIONADO
         ********************************************/
        $sql = "
            UPDATE tickets 
            SET estado = 'Gestionado',
                estado_ticket = 'Gestionado',
                tiempo_finalizado = NOW()
            WHERE id = $ticket_id
        ";

        if ($conexion->query($sql) === TRUE) {
            echo "success";
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
