<?php
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/send_wsp.php'; // ← NUEVO: para enviar WhatsApp

// **Asegurar zona horaria correcta para PHP**
date_default_timezone_set('America/Santiago');

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['rut'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
    exit;
}

$id    = isset($_POST['id']) ? intval($_POST['id']) : 0;
$field = isset($_POST['field']) ? $_POST['field'] : '';
$value = isset($_POST['value']) ? $_POST['value'] : '';

if ($id <= 0 || $field === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Parámetros inválidos']);
    exit;
}

// ⚠️ Ajusta estos nombres según tus columnas reales
$allowed = ['usuario_asignado', 'categoria', 'estado_ticket'];


// ← usa 'estado' si así se llama
if (!in_array($field, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Campo no permitido']);
    exit;
}

// ========== Generar código de ticket (igual que admin.php) ==========
function generarCodigoTicketBackend($tipo, $id, $conexion)
{
    $prefijos = [
        'Incidencia' => 'INC',
        'Reclamo'    => 'REC',
        'Solicitud'  => 'SOL'
    ];
    $prefijo = $prefijos[$tipo] ?? 'TCK';

    $stmt = $conexion->prepare("SELECT id FROM tickets WHERE tipo = ? ORDER BY id ASC");
    $stmt->bind_param("s", $tipo);
    $stmt->execute();
    $result = $stmt->get_result();

    $numero = 0;
    while ($row = $result->fetch_assoc()) {
        $numero++;
        if ((int)$row['id'] === $id) break;
    }
    $stmt->close();

    return $prefijo . str_pad($numero, 8, "0", STR_PAD_LEFT);
}

if ($field === 'usuario_asignado' && $value !== '') {
    // 1) asigna usuario
    $stmt = $conexion->prepare('UPDATE tickets SET usuario_asignado = ? WHERE id = ?');
    $stmt->bind_param('si', $value, $id);
    $ok1 = $stmt->execute();
    $stmt->close();

    // 2) si estaba Ingresado → promueve a Asignado (sin tocar otros estados)
    $conexion->query("
      UPDATE tickets 
      SET estado_ticket = 'Asignado'
      WHERE id = {$id}
        AND (estado_ticket IS NULL OR estado_ticket = '' OR estado_ticket = 'Ingresado')
  ");

    // ========== NUEVO: Enviar WhatsApp al técnico ==========
    try {
        // 1) Traer datos del ticket
        $stmt2 = $conexion->prepare("
          SELECT id, nombre, rut, telefono, correo, tipo, empresa, detalle
          FROM tickets
          WHERE id = ?
          LIMIT 1
      ");
        $stmt2->bind_param('i', $id);
        $stmt2->execute();
        $resTicket = $stmt2->get_result();
        $ticket = $resTicket->fetch_assoc();
        $stmt2->close();

        if ($ticket) {
            // 2) Numero de ticket
            $codigoTicket = generarCodigoTicketBackend($ticket['tipo'], (int)$ticket['id'], $conexion);

            // 3) Armar mensaje
            $mensaje  = "Estimado técnico, nuevo ticket asignado:\n\n";
            $mensaje .= "N° Ticket: {$codigoTicket}\n\n";
            $mensaje .= "Nombre: {$ticket['nombre']}\n";
            $mensaje .= "RUT: {$ticket['rut']}\n";
            $mensaje .= "Teléfono: {$ticket['telefono']}\n";
            $mensaje .= "Correo: {$ticket['correo']}\n";
            $mensaje .= "Tipo: {$ticket['tipo']}\n";
            $mensaje .= "Empresa: {$ticket['empresa']}\n";
            $mensaje .= "Detalle: {$ticket['detalle']}\n";

            // 4) chatId según el nombre del técnico ($value)
            $chatId = obtenerChatIdTecnico($value);

            if ($chatId) {
                enviarWhatsApp($chatId, $mensaje);
            } else {
                error_log("No existe chatId para técnico: " . $value);
            }
        }
    } catch (Throwable $e) {
        error_log("Error WhatsApp: " . $e->getMessage());
    }

    echo json_encode(['ok' => $ok1]);
    exit;
}

/******************************************************
 *  NUEVO BLOQUE: MANEJO DE TRAMOS POR CAMBIO DE ESTADO
 ******************************************************/
if ($field === 'estado_ticket') {



    /**********************
     * 1. Traer estado actual
     **********************/
    $stmt = $conexion->prepare("SELECT estado_ticket, usuario_asignado, categoria FROM tickets WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$res) {
        echo json_encode(['ok' => false, 'msg' => 'Ticket no existe']);
        exit;
    }

    $estado_anterior  = $res['estado_ticket'] ?? '';
    $tecnico_actual   = $res['usuario_asignado'] ?? null;
    $categoria_actual = $res['categoria'] ?? null;

    /************************************************
     * 2. Obtener tramo abierto (si existe uno)
     ************************************************/
    $stmt = $conexion->prepare("
        SELECT id, fecha_inicio 
        FROM ticket_tramos 
        WHERE ticket_id = ? AND fecha_fin IS NULL 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $tramo_abierto = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    /************************************************
     * 3. Si hay tramo abierto → cerrarlo
     ************************************************/
    if ($tramo_abierto) {

        $fecha_inicio = $tramo_abierto['fecha_inicio'];
        $fecha_fin    = date("Y-m-d H:i:s");   // ahora respetando America/Santiago

        // ---- Función para calcular minutos hábiles ----
        function calcular_minutos_habiles($inicio, $fin)
        {
            $ini = new DateTime($inicio);
            $fn  = new DateTime($fin);

            $min = 0;
            $laboral_ini = new DateTime($ini->format("Y-m-d 07:30:00"));
            $laboral_fin = new DateTime($ini->format("Y-m-d 18:30:00"));

            while ($ini < $fn) {

                // sábado=6, domingo=0
                $dow = (int)$ini->format("w");
                $es_laboral = ($dow >= 1 && $dow <= 5);

                if ($es_laboral) {
                    if ($ini >= $laboral_ini && $ini <= $laboral_fin) {
                        $min++;
                    }
                }

                // avanzar 1 minuto
                $ini->modify("+1 minute");

                // si cambia el día → recalcular bloque laboral
                if ($ini->format("H:i") === "00:00") {
                    $laboral_ini = new DateTime($ini->format("Y-m-d 07:30:00"));
                    $laboral_fin = new DateTime($ini->format("Y-m-d 18:30:00"));
                }
            }

            return $min;
        }

        $minutos = calcular_minutos_habiles($fecha_inicio, $fecha_fin);

        // Cerrar tramo
        $stmt = $conexion->prepare("
            UPDATE ticket_tramos 
            SET fecha_fin = ?, estado_fin = ?, minutos_habiles = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssii", $fecha_fin, $value, $minutos, $tramo_abierto['id']);
        $stmt->execute();
        $stmt->close();
    }

    /************************************************
     * 4. Crear nuevo tramo para el nuevo estado
     ************************************************/

    // NO CREAR NUEVO TRAMO SI EL TICKET SE CIERRA
    if ($value !== 'Cerrado' && $value !== 'Gestionado') {

        $fecha_inicio_nuevo = date("Y-m-d H:i:s");
        $detenido_flag      = ($value === 'Detenido') ? 1 : 0;

        $stmt = $conexion->prepare("
        INSERT INTO ticket_tramos
        (ticket_id, estado_inicio, fecha_inicio, tecnico_asignado, categoria_en_tramo, detenido)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
        $stmt->bind_param(
            "issssi",
            $id,
            $value,
            $fecha_inicio_nuevo,
            $tecnico_actual,
            $categoria_actual,
            $detenido_flag
        );
        $stmt->execute();
        $stmt->close();
    }
}
/************* FIN BLOQUE NUEVO DE TRAMOS *************/

// Update genérico de 1 campo. No toca los demás.
$sql  = "UPDATE tickets SET {$field} = ? WHERE id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param('si', $value, $id);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['ok' => $ok]);
