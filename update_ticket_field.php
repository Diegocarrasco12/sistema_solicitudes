<?php
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/send_wsp.php'; // ← NUEVO: para enviar WhatsApp

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
$allowed = ['usuario_asignado', 'categoria', 'estado_ticket']; // ← usa 'estado' si así se llama
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
  //    Cambia 'estado_ticket' por 'estado' si corresponde
  $conexion->query("UPDATE tickets SET estado_ticket = 'Asignado'
                    WHERE id = {$id} AND (estado_ticket IS NULL OR estado_ticket = '' OR estado_ticket = 'Ingresado')");
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

// Update genérico de 1 campo. No toca los demás.
$sql = "UPDATE tickets SET {$field} = ? WHERE id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param('si', $value, $id);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['ok' => $ok]);
