<?php
include("conexion.php");

// Zona horaria correcta
date_default_timezone_set('America/Santiago');

/**
 * Opcional: si algún día creas un archivo separado con esta función,
 * se incluirá aquí sin romper nada.
 */
if (file_exists(__DIR__ . "/calcular_minutos_habiles.php")) {
    include_once __DIR__ . "/calcular_minutos_habiles.php";
}

/**
 * Definir la función calcular_minutos_habiles solo si aún no existe.
 * Es la misma lógica que usas en cambiar_estado.php / update_ticket_field.php
 */
if (!function_exists('calcular_minutos_habiles')) {
    function calcular_minutos_habiles($inicio, $fin)
    {
        $ini = new DateTime($inicio);
        $fn  = new DateTime($fin);

        $min = 0;
        $laboral_ini = new DateTime($ini->format("Y-m-d 07:30:00"));
        $laboral_fin = new DateTime($ini->format("Y-m-d 18:30:00"));

        while ($ini < $fn) {

            // 0 = domingo, 6 = sábado
            $dow = (int)$ini->format("w");
            $es_laboral = ($dow >= 1 && $dow <= 5);

            if ($es_laboral) {
                if ($ini >= $laboral_ini && $ini <= $laboral_fin) {
                    $min++;
                }
            }

            // avanzar 1 minuto
            $ini->modify("+1 minute");

            // si cambió el día, recalculamos el rango laboral
            if ($ini->format("H:i") === "00:00") {
                $laboral_ini = new DateTime($ini->format("Y-m-d 07:30:00"));
                $laboral_fin = new DateTime($ini->format("Y-m-d 18:30:00"));
            }
        }

        return $min;
    }
}

$ticketId  = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
$usuario   = $_POST['usuario_asignado'] ?? '';
$categoria = $_POST['categoria'] ?? null;

if (!$ticketId || !$usuario || !$categoria) {
    die("❌ Datos incompletos.");
}

/*********************************************************
 * 1) Lógica antigua → segundos desde creación
 *********************************************************/
$stmt = $conexion->prepare("SELECT fecha_creacion FROM tickets WHERE id = ?");
$stmt->bind_param("i", $ticketId);
$stmt->execute();
$res    = $stmt->get_result();
$ticket = $res->fetch_assoc();
$stmt->close();

if (!$ticket) {
    die("❌ Ticket no encontrado.");
}

$fecha_creacion   = strtotime($ticket['fecha_creacion']);
$ahora            = time();
$segundos_totales = max(0, $ahora - $fecha_creacion);

$h_seg = floor($segundos_totales / 3600);
$m_seg = floor(($segundos_totales % 3600) / 60);
$s_seg = $segundos_totales % 60;
$tiempo_legible_seg = sprintf('%02dh %02dm %02ds', $h_seg, $m_seg, $s_seg);

/*********************************************************
 * 2) Nueva lógica → cerrar tramo abierto + sumar minutos hábiles
 *********************************************************/
$usa_tramos = false;
$minutos_habiles_totales = null;

// ✅ CORRECCIÓN: usar el nombre correcto de la función
$check = $conexion->query("SHOW TABLES LIKE 'ticket_tramos'");
if ($check && $check->num_rows > 0 && function_exists('calcular_minutos_habiles')) {
    $usa_tramos = true;
}

if ($usa_tramos) {

    /**** 2.a Cerrar tramo abierto ****/
    $stmt = $conexion->prepare("
        SELECT id, fecha_inicio 
        FROM ticket_tramos
        WHERE ticket_id = ? AND fecha_fin IS NULL
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $ticketId);
    $stmt->execute();
    $resTramo = $stmt->get_result();
    $rowTramo = $resTramo->fetch_assoc();
    $stmt->close();

    if ($rowTramo) {
        $fecha_inicio = $rowTramo['fecha_inicio'];
        $fecha_fin    = date("Y-m-d H:i:s");

        // Minutos hábiles del tramo final
        $min_tramo = (int)calcular_minutos_habiles($fecha_inicio, $fecha_fin);

        // Cerrar tramo final y marcar estado_fin
        $stmt = $conexion->prepare("
            UPDATE ticket_tramos
            SET fecha_fin = ?, 
                minutos_habiles = ?, 
                estado_fin = 'Cerrado'
            WHERE id = ?
        ");
        $stmt->bind_param("sii", $fecha_fin, $min_tramo, $rowTramo['id']);
        $stmt->execute();
        $stmt->close();
    }

    /**** 2.b Sumar minutos hábiles de todos los tramos ****/
    $stmt = $conexion->prepare("
        SELECT SUM(minutos_habiles) AS total
        FROM ticket_tramos
        WHERE ticket_id = ?
    ");
    $stmt->bind_param("i", $ticketId);
    $stmt->execute();
    $resTotal = $stmt->get_result();
    $rowTotal = $resTotal->fetch_assoc();
    $stmt->close();

    $minutos_habiles_totales = (int)$rowTotal['total'];
}

/*********************************************************
 * 3) Determinar tiempo final
 *********************************************************/
$tiempo_gestionado         = $segundos_totales;  // por defecto (segundos naturales)
$tiempo_gestionado_seg     = $segundos_totales;
$tiempo_gestionado_legible = $tiempo_legible_seg;

// Si hay tramos y suman más de 0, usamos los minutos hábiles
if ($usa_tramos && $minutos_habiles_totales > 0) {
    $tiempo_gestionado = $minutos_habiles_totales; // aquí guardas minutos hábiles
    $tiempo_gestionado_seg = $segundos_totales;    // mantienes también segundos reales

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
