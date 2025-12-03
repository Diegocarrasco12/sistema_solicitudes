<?php
/**
 * asignar_tecnico.php
 * Asigna un técnico a un ticket (filtra solo técnicos del área del ticket).
 *
 * Requisitos:
 *  - db.php -> expone $mysqli (mysqli conectado)
 *  - auth.php -> función usuario_es_admin() o $_SESSION['es_admin'] = 1
 */

declare(strict_types=1);
session_start();

require_once 'db.php';
if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
  http_response_code(500);
  echo 'Conexión a base de datos no disponible';
  exit;
}

// --- Verificación de permisos (admin) ---
$es_admin = false;
if (file_exists(__DIR__ . '/auth.php')) {
  require_once 'auth.php';
  if (function_exists('usuario_es_admin')) {
    $es_admin = (bool)usuario_es_admin();
  }
}
if (!$es_admin && isset($_SESSION['es_admin'])) {
  $es_admin = ($_SESSION['es_admin'] == 1);
}
if (!$es_admin) {
  header('Location: login.php');
  exit;
}

// --- Validar parámetro ID del ticket ---
$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($ticketId <= 0) {
  http_response_code(400);
  echo 'ID de ticket inválido.';
  exit;
}

// --- Cargar ticket y su área ---
$sqlTicket = "SELECT t.id, t.numero_ticket, t.asunto, t.area_id, a.nombre AS area_nombre, t.tecnico_id
              FROM tickets t
              JOIN areas a ON a.id = t.area_id
              WHERE t.id = ?";
$st = $mysqli->prepare($sqlTicket);
$st->bind_param('i', $ticketId);
$st->execute();
$ticket = $st->get_result()->fetch_assoc();
$st->close();

if (!$ticket) {
  http_response_code(404);
  echo 'Ticket no encontrado.';
  exit;
}

$areaId = (int)$ticket['area_id'];

// --- Si viene POST, validar y actualizar ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $tecnico_id = isset($_POST['tecnico_id']) ? (int)$_POST['tecnico_id'] : 0;

  // Validar que el técnico pertenece al área del ticket y está activo
  $sqlVal = "SELECT id FROM tecnicos WHERE id=? AND area_id=? AND activo=1";
  $sv = $mysqli->prepare($sqlVal);
  $sv->bind_param('ii', $tecnico_id, $areaId);
  $sv->execute();
  $okTec = (bool)$sv->get_result()->fetch_assoc();
  $sv->close();

  if (!$okTec) {
    $error = 'Selecciona un técnico válido para el área ' . htmlspecialchars($ticket['area_nombre']);
  } else {
    $up = $mysqli->prepare("UPDATE tickets SET tecnico_id=? WHERE id=?");
    $up->bind_param('ii', $tecnico_id, $ticketId);
    if ($up->execute()) {
      // Redirigir de vuelta al panel correspondiente
      if ($ticket['area_nombre'] === 'Servicios Generales') {
        header('Location: sg_admin.php');
      } else {
        header('Location: admin.php');
      }
      exit;
    } else {
      $error = 'No se pudo actualizar el ticket. Intente nuevamente.';
    }
    $up->close();
  }
}

// --- Cargar técnicos del área para el selector ---
$tecnicos = [];
$sqlTec = "SELECT id, nombre FROM tecnicos WHERE area_id=? AND activo=1 ORDER BY nombre";
$st2 = $mysqli->prepare($sqlTec);
$st2->bind_param('i', $areaId);
$st2->execute();
$resTec = $st2->get_result();
while ($row = $resTec->fetch_assoc()) {
  $tecnicos[] = ['id' => (int)$row['id'], 'nombre' => $row['nombre']];
}
$st2->close();

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Asignar técnico — Ticket <?= htmlspecialchars($ticket['numero_ticket'] ?: ('#'.$ticketId)) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{
      --bg:#0f172a; --card:#111827; --muted:#9ca3af; --text:#e5e7eb;
      --primary:#2563eb; --border:#1f2937;
    }
    *{box-sizing:border-box}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; background:var(--bg); color:var(--text); margin:0; padding:24px}
    .wrap{max-width:700px; margin:0 auto}
    .card{background:var(--card); border:1px solid var(--border); border-radius:12px; padding:16px}
    h1{margin:0 0 12px; font-size:20px}
    .muted{color:var(--muted); font-size:13px; margin-bottom:16px}
    label{display:block; font-size:13px; color:#cbd5e1; margin-bottom:6px}
    select{width:100%; padding:10px 12px; border-radius:10px; border:1px solid var(--border); background:#0b1220; color:var(--text)}
    .actions{margin-top:16px; display:flex; gap:8px}
    button,.btn{cursor:pointer; padding:10px 14px; border-radius:10px; border:1px solid var(--border); background:#0b1220; color:var(--text); text-decoration:none}
    .btn-primary{background:var(--primary); border-color:transparent}
    .error{background:#7f1d1d; border:1px solid #991b1b; color:#fecaca; padding:10px 12px; border-radius:10px; margin-bottom:12px}
    .row{display:grid; grid-template-columns: 1fr 1fr; gap:10px}
    .back{color:#93c5fd; text-decoration:none}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>Asignar técnico</h1>
      <div class="muted">
        Área: <strong><?= htmlspecialchars($ticket['area_nombre']) ?></strong> ·
        Ticket: <strong><?= htmlspecialchars($ticket['numero_ticket'] ?: ('#'.$ticketId)) ?></strong>
        <?php if (!empty($ticket['asunto'])): ?> · Asunto: <strong><?= htmlspecialchars($ticket['asunto']) ?></strong><?php endif; ?>
      </div>

      <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <label for="tecnico_id">Técnico</label>
        <select name="tecnico_id" id="tecnico_id" required>
          <option value="">-- Selecciona técnico --</option>
          <?php foreach ($tecnicos as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= ($ticket['tecnico_id'] == $t['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($t['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <div class="actions">
          <button type="submit" class="btn btn-primary">Guardar</button>
          <?php if ($ticket['area_nombre'] === 'Servicios Generales'): ?>
            <a href="sg_admin.php" class="btn">Cancelar</a>
          <?php else: ?>
            <a href="admin.php" class="btn">Cancelar</a>
          <?php endif; ?>
        </div>
      </form>

      <div style="margin-top:10px">
        <?php if ($ticket['area_nombre'] === 'Servicios Generales'): ?>
          <a class="back" href="sg_admin.php">&larr; Volver al panel SG</a>
        <?php else: ?>
          <a class="back" href="admin.php">&larr; Volver al panel</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
