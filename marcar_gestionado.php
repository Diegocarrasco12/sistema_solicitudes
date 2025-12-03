<?php
include("conexion.php");

// Zona horaria correcta para todo este flujo
date_default_timezone_set('America/Santiago');

// Incluir calcular_minutos_habiles SOLO si existe, para no romper nada
if (file_exists(__DIR__ . "/calcular_minutos_habiles.php")) {
    include_once __DIR__ . "/calcular_minutos_habiles.php";
}

$ticketId  = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
$usuario   = $_POST['usuario_asignado'] ?? '';
$categoria = $_POST['categoria'] ?? null;

if (!$ticketId || !$usuario || !$categoria) {
    die("❌ Datos incompletos.");
}

/*********************************************************
 * 1) COMPORTAMIENTO ANTIGUO: segundos desde fecha_creacion
 *********************************************************/
$stmt = $conexion->prepare("SELECT fecha_creacion FROM tickets WHERE id = ?");
$stmt->bind_param("i", $ticketId);
$stmt->execute();
$res    = $stmt->get_result();
$ticket = $res->fetch_assoc();
$stmt->close();

if (!$ticket) {
    die("❌ No se encontró el ticket.");
}

$fecha_creacion   = strtotime($ticket['fecha_creacion']);
$ahora            = time();
$segundos_totales = max(0, $ahora - $fecha_creacion);

$h_seg = floor($segundos_totales / 3600);
$m_seg = floor(($segundos_totales % 3600) / 60);
$s_seg = $segundos_totales % 60;
$tiempo_legible_seg = sprintf('%02dh %02dm %02ds', $h_seg, $m_seg, $s_seg);

/*********************************************************
 * 2) INTENTAR USAR LA LÓGICA NUEVA (ticket_tramos + minutos hábiles)
 *********************************************************/
$usa_tramos              = false;
$minutos_habiles_totales = null;

if (function_exists('calcularMinutosHabiles')) {
    $check = $conexion->query("SHOW TABLES LIKE 'ticket_tramos'");
    if ($check && $check->num_rows > 0) {
        $usa_tramos = true;
    }
}

if ($usa_tramos) {
    // 2.a) Cerrar tramo abierto (si existe)
    if ($stmt = $conexion->prepare("
        SELECT id, fecha_inicio
        FROM ticket_tramos
        WHERE ticket_id = ? AND fecha_fin IS NULL
        ORDER BY id DESC
        LIMIT 1
    ")) {
        $stmt->bind_param("i", $ticketId);
        $stmt->execute();
        $resTramo = $stmt->get_result();
        $rowTramo = $resTramo->fetch_assoc();
        $stmt->close();

        if ($rowTramo) {
            $fecha_inicio = $rowTramo['fecha_inicio'];
            $fecha_fin    = date("Y-m-d H:i:s");

            // Minutos hábiles de ese tramo
            $min_tramo = (int)calcularMinutosHabiles($fecha_inicio, $fecha_fin);

            // Cerrar tramo → usamos la **misma columna** que en ticket_tramos (minutos_habiles)
            $stmt = $conexion->prepare("
                UPDATE ticket_tramos
                SET fecha_fin = ?, minutos_habiles = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sii", $fecha_fin, $min_tramo, $rowTramo['id']);
            $stmt->execute();
            $stmt->close();
        }
    }

    // 2.b) Sumar minutos hábiles de todos los tramos
    if ($stmt = $conexion->prepare("
        SELECT SUM(minutos_habiles) AS total
        FROM ticket_tramos
        WHERE ticket_id = ?
    ")) {
        $stmt->bind_param("i", $ticketId);
        $stmt->execute();
        $resTotal = $stmt->get_result();
        $rowTotal = $resTotal->fetch_assoc();
        $stmt->close();

        $minutos_habiles_totales = isset($rowTotal['total']) ? (int)$rowTotal['total'] : 0;
    }
}

/*********************************************************
 * 3) ARMAR VALORES FINALES A GUARDAR
 *********************************************************/

// Por defecto usamos la lógica antigua (segundos desde creación)
$tiempo_gestionado         = $segundos_totales;
$tiempo_gestionado_seg     = $segundos_totales;
$tiempo_gestionado_legible = $tiempo_legible_seg;

// Si tenemos minutos hábiles válidos, los usamos en tiempo_gestionado
if ($usa_tramos && $minutos_habiles_totales !== null && $minutos_habiles_totales > 0) {
    $tiempo_gestionado     = $minutos_habiles_totales;  // minutos hábiles
    $tiempo_gestionado_seg = $segundos_totales;         // segundos crudos

    $h_min = floor($minutos_habiles_totales / 60);
    $m_min = $minutos_habiles_totales % 60;
    $tiempo_gestionado_legible = sprintf('%02dh %02dm', $h_min, $m_min);
}

/*********************************************************
 * 4) UPDATE FINAL DEL TICKET
 *********************************************************/
$stmt = $conexion->prepare("
    UPDATE tickets
    SET estado = 'Gestionado',
        estado_ticket = 'Gestionado',
        usuario_asignado = ?,
        categoria = ?,
        tiempo_gestionado = ?,
        tiempo_gestionado_segundos = ?,
        tiempo_gestionado_legible = ?
    WHERE id = ?
");
$stmt->bind_param(
    "ssiisi",
    $usuario,
    $categoria,
    $tiempo_gestionado,
    $tiempo_gestionado_seg,
    $tiempo_gestionado_legible,
    $ticketId
);

if ($stmt->execute()) {
    echo "✔ Ticket gestionado correctamente.";
} else {
    echo "❌ Error al actualizar el ticket: " . $conexion->error;
}
$stmt->close();
?>
