<?php
include("conexion.php");

// Asegurar zona horaria
date_default_timezone_set('America/Santiago');

$id      = $_POST['ticket_id'];
$estado  = $_POST['nuevo_estado'];
$usuario = isset($_POST['usuario_asignado']) ? $_POST['usuario_asignado'] : null;

// Validación: no permitir Gestionado sin usuario asignado
if ($estado === 'Gestionado' && !$usuario) {
    echo "Error: Debes asignar un usuario antes de marcar como gestionado.";
    exit;
}

/****************************************************
 * FUNCIÓN REAL DE CÁLCULO DE MINUTOS HÁBILES
 * (MISMA QUE SE USA EN update_ticket_field)
 ****************************************************/
function calcular_minutos_habiles($inicio, $fin) {

    $ini = new DateTime($inicio);
    $fn  = new DateTime($fin);

    $min = 0;
    $laboral_ini = new DateTime($ini->format("Y-m-d 07:30:00"));
    $laboral_fin = new DateTime($ini->format("Y-m-d 18:30:00"));

    while ($ini < $fn) {

        // domingo=0, sábado=6
        $dow = (int)$ini->format("w");
        $es_laboral = ($dow >= 1 && $dow <= 5);

        if ($es_laboral) {
            if ($ini >= $laboral_ini && $ini <= $laboral_fin) {
                $min++;
            }
        }

        // avanzar 1 minuto
        $ini->modify("+1 minute");

        // si cambió el día, recalcular límites laborales
        if ($ini->format("H:i") === "00:00") {
            $laboral_ini = new DateTime($ini->format("Y-m-d 07:30:00"));
            $laboral_fin = new DateTime($ini->format("Y-m-d 18:30:00"));
        }
    }

    return $min;
}

/****************************************************
 * 1) CERRAR TRAMO ABIERTO SI CAMBIA A CERRADO/GESTIONADO
 ****************************************************/
if ($estado === 'Cerrado' || $estado === 'Gestionado') {

    // Buscar tramo abierto
    $stmt2 = $conexion->prepare("
        SELECT id, fecha_inicio
        FROM ticket_tramos
        WHERE ticket_id = ? AND fecha_fin IS NULL
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();
    $tramo = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();

    if ($tramo) {

        $fecha_inicio = $tramo['fecha_inicio'];
        $fecha_fin    = date("Y-m-d H:i:s");

        // Calcular minutos hábiles reales
        $minutos = calcular_minutos_habiles($fecha_inicio, $fecha_fin);

        // Cerrar tramo
        $stmt3 = $conexion->prepare("
            UPDATE ticket_tramos
            SET fecha_fin = ?, estado_fin = ?, minutos_habiles = ?
            WHERE id = ?
        ");
        $stmt3->bind_param("ssii", $fecha_fin, $estado, $minutos, $tramo['id']);
        $stmt3->execute();
        $stmt3->close();
    }
}

/****************************************************
 * 2) ACTUALIZAR ESTADO Y USUARIO DEL TICKET
 ****************************************************/
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
