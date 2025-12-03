<?php

/**
 * sg_admin.php – Panel Administración Servicios Generales
 * Versión con:
 *  ✓ Modal "Ver ticket" usando datos del row (sin AJAX)
 *  ✓ Asignar técnico en línea
 *  ✓ Cambiar estado en línea
 *  ✓ Topbar con saludo y botón cerrar sesión
 *  ✓ Estilos similares al panel TI (fondo tkt + card blanca)
 *  ✓ Sesión con cookie extendida y no-cache
 */

declare(strict_types=1);

// ============================================================
// CONFIGURACIÓN DE SESIÓN (EXTENSIÓN A 30 DÍAS + NO-CACHE)
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 30, // 30 días
    'path'     => '/',
    'secure'   => false,             // ponlo true si todo va por HTTPS
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}

session_start();

// Refrescar manualmente la cookie de sesión para extender su vida útil
setcookie(session_name(), session_id(), [
  'expires'  => time() + 60 * 60 * 24 * 30,
  'path'     => '/',
  'secure'   => false,
  'httponly' => true,
  'samesite' => 'Lax',
]);

// Evitar cacheo del panel de administración
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once 'conexion.php';   // sesión TI
require_once 'db_sg.php';      // DB SG real
require_once 'helpers_sla.php';

// ============================================================
// VALIDAR ADMIN SG
// ============================================================

$es_admin_sg = false;
$rut = $_SESSION['rut'] ?? '';

if ($rut !== '') {
  $stmt = $mysqli_sg->prepare("SELECT nombre FROM admin_sg WHERE rut = ? AND activo = 1 LIMIT 1");
  $stmt->bind_param("s", $rut);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();
  if ($res) {
    $es_admin_sg  = true;
    $nombre_admin = $res['nombre'];
  }
  $stmt->close();
}

if (!$es_admin_sg) {
  header("Location: index.php");
  exit;
}

// ============================================================
// ACCIONES POST (asignación y estado)
// ============================================================

$estadosPosibles = ['Ingresado', 'Abierto', 'En Proceso', 'Pendiente', 'Cerrado', 'Rechazado'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $accion    = $_POST['accion'] ?? '';
  $ticket_id = (int)($_POST['ticket_id'] ?? 0);

  if ($ticket_id > 0) {

    if ($accion === 'update_estado') {
      $nuevo_estado = trim($_POST['nuevo_estado']);

      if (in_array($nuevo_estado, $estadosPosibles, true)) {

        if ($nuevo_estado === 'Cerrado') {
          // Guardar fecha de cierre
          $stmt = $mysqli_sg->prepare("
        UPDATE tickets_servicios 
        SET estado_ticket = ?, fecha_cierre = NOW() 
        WHERE id = ?
      ");
          $stmt->bind_param("si", $nuevo_estado, $ticket_id);
        } else {
          // Quitar fecha de cierre al reabrir
          $stmt = $mysqli_sg->prepare("
        UPDATE tickets_servicios 
        SET estado_ticket = ?, fecha_cierre = NULL 
        WHERE id = ?
      ");
          $stmt->bind_param("si", $nuevo_estado, $ticket_id);
        }

        $stmt->execute();
        $stmt->close();
      }
    }


    if ($accion === 'asignar_tecnico') {
      $tid = ($_POST['tecnico_id'] !== '') ? (int)$_POST['tecnico_id'] : null;

      if ($tid !== null) {
        $stmt = $mysqli_sg->prepare("UPDATE tickets_servicios SET tecnico_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $tid, $ticket_id);
      } else {
        $stmt = $mysqli_sg->prepare("UPDATE tickets_servicios SET tecnico_id = NULL WHERE id = ?");
        $stmt->bind_param("i", $ticket_id);
      }
      $stmt->execute();
      $stmt->close();
    }

    //────────── RESPONDER TICKET POR CORREO ──────────
    if ($accion === 'responder_ticket' && $ticket_id > 0) {

      $correo         = trim($_POST['correo_destino']);
      $mensaje        = trim($_POST['respuesta']);
      $numero_ticket  = $_POST['numero_ticket'] ?? '';
      $detalle_ticket = $_POST['detalle_ticket'] ?? '';

      if ($correo !== '' && $mensaje !== '') {

        require_once __DIR__ . '/PHPMailer/PHPMailer.php';
        require_once __DIR__ . '/PHPMailer/SMTP.php';
        require_once __DIR__ . '/PHPMailer/Exception.php';

        try {
          $mail = new PHPMailer\PHPMailer\PHPMailer(true);

          $mail->isSMTP();
          $mail->Host       = 'smtp.gmail.com';
          $mail->SMTPAuth   = true;
          $mail->Username   = 'jlara@pharpack.cl';
          $mail->Password   = 'hlbi rhkf obtm yrch';
          $mail->SMTPSecure = 'tls';
          $mail->Port       = 587;
          $mail->CharSet    = 'UTF-8';

          $mail->setFrom('jlara@pharpack.cl', 'Servicios Generales');
          $mail->addAddress($correo);

          $mail->Subject = "Actualización de ticket";
          $mail->Body = "
    <strong>Ticket:</strong> {$numero_ticket}<br><br>
    
    <strong>Detalle de la solicitud:</strong><br>
    " . nl2br($detalle_ticket) . "<br><br>

    <strong>Respuesta del área de Servicios Generales:</strong><br>
    " . nl2br($mensaje) . "
";

          $mail->isHTML(true);

          $mail->send();
        } catch (Exception $e) {
          error_log("Error al enviar respuesta SG: " . $e->getMessage());
        }
      }
    }
  }

  header("Location: sg_admin.php");
  exit;
}

// ============================================================
// FILTROS
// ============================================================

$buscar    = trim($_GET['q'] ?? '');
$estado    = trim($_GET['estado'] ?? '');
$categoria = (int)($_GET['categoria'] ?? 0);
$orden     = trim($_GET['orden'] ?? 'recientes');

// ============================================================
// CATEGORÍAS + TÉCNICOS
// ============================================================

$categorias = [];
$r = $mysqli_sg->query("SELECT id, nombre FROM categorias_sg WHERE activo = 1 ORDER BY nombre ASC");
while ($row = $r->fetch_assoc()) {
  $categorias[$row['id']] = $row['nombre'];
}

$tecnicos = [];
$tq = $mysqli_sg->query("SELECT id, nombre FROM tecnicos_sg WHERE activo = 1 ORDER BY nombre ASC");
while ($t = $tq->fetch_assoc()) {
  $tecnicos[$t['id']] = $t['nombre'];
}
// ============================================================
// LEER HOJA GOOGLE FORMS Y MARCAR TICKETS CON INFORME
// ============================================================

$informe_ok = [];

// URL CSV de la hoja de respuestas (REEMPLAZA por tu link CSV)
$csv_url = "https://docs.google.com/spreadsheets/d/1hsM9e7nFwNkBP0uO5y2BTOUEbRfpp3jfTupWuz3Byn4/export?format=csv";

// Leer CSV
if (($handle = fopen($csv_url, "r")) !== false) {

  $header = fgetcsv($handle); // descartar encabezados

  $fila = 2; // empieza en la fila 2 porque la 1 es el encabezado

  while (($data = fgetcsv($handle)) !== false) {

    $ticket_form = trim($data[1] ?? '');

    if ($ticket_form !== '') {
      // guardar fila exacta en el sheet
      $informe_ok[$ticket_form] = $fila;
    }

    $fila++;
  }

  fclose($handle);
}

// ============================================================
// CONSULTA PRINCIPAL
// ============================================================

$where  = ["1=1"];
$where[] = "t.estado_ticket <> 'Cerrado'";
$params = [];
$types  = '';

if ($buscar !== '') {
  $where[] = "(t.tipo LIKE CONCAT('%', ?, '%')
                 OR t.detalle LIKE CONCAT('%', ?, '%')
                 OR t.numero_ticket LIKE CONCAT('%', ?, '%'))";
  $params = array_merge($params, [$buscar, $buscar, $buscar]);
  $types .= "sss";
}

if ($estado !== '') {
  $where[]  = "t.estado_ticket = ?";
  $params[] = $estado;
  $types   .= "s";
}

if ($categoria > 0) {
  $where[]  = "t.categoria_id = ?";
  $params[] = $categoria;
  $types   .= "i";
}

$orderBy = ($orden === 'vencimiento')
  ? "t.fecha_vencimiento IS NULL, t.fecha_vencimiento ASC, t.id DESC"
  : "t.id DESC";

$sql = "
SELECT t.*, c.nombre AS categoria, tec.nombre AS tecnico
FROM tickets_servicios t
LEFT JOIN categorias_sg c  ON c.id  = t.categoria_id
LEFT JOIN tecnicos_sg   tec ON tec.id = t.tecnico_id
WHERE " . implode(" AND ", $where) . "
ORDER BY $orderBy
LIMIT 20
";

$stmt = $mysqli_sg->prepare($sql);
if ($types !== '') {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result();
// ============================================================
// CONSULTA: TICKETS CERRADOS (HISTORIAL)
// ============================================================

$sqlHist = "
  SELECT t.*, c.nombre AS categoria, tec.nombre AS tecnico
  FROM tickets_servicios t
  LEFT JOIN categorias_sg c  ON c.id  = t.categoria_id
  LEFT JOIN tecnicos_sg   tec ON tec.id = t.tecnico_id
  WHERE t.estado_ticket = 'Cerrado'
  ORDER BY t.id DESC
";

$historial = $mysqli_sg->query($sqlHist);

?>
<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Panel Servicios Generales</title>

  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

    :root {
      --bg: #0b1120;
      --card: #ffffff;
      --muted: #6c757d;
      --text: #111827;
      --primary: #007BFF;
      --border: #dee2e6;
      --ok: #16a34a;
      --warn: #ca8a04;
      --bad: #dc2626;
      --off: #6b7280;

      --radius: 20px;
      --radius-small: 12px;
      --shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
      --font-size: 15px;
    }

    * {
      box-sizing: border-box;
      font-family: 'Inter', sans-serif;
    }

    body {
      margin: 0;
      font-size: var(--font-size);
      color: var(--text);
      position: relative;
      z-index: 0;
      padding: 0 15px;
    }

    /* Fondo con blur como en admin TI */
    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: url('tkt.jpg') no-repeat center center fixed;
      background-size: cover;
      filter: blur(8px);
      z-index: -1;
    }

    /* ---------------- TOPBAR ---------------- */

    .topbar {
      width: 100%;
      background: rgba(0, 0, 0, 0.8);
      padding: 18px 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      position: sticky;
      top: 0;
      z-index: 50;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
    }

    .topbar-content {
      max-width: 1100px;
      margin: auto;
      padding: 0 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      color: #ffffff;
    }

    .hello {
      font-size: 17px;
      font-weight: 600;
    }

    .logout-btn {
      padding: 10px 20px;
      border-radius: 30px;
      background: #dc3545;
      border: none;
      color: #ffffff;
      font-size: 14px;
      transition: 0.2s;
      text-decoration: none;
      display: inline-block;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    .logout-btn:hover {
      background: #c82333;
    }

    /* ---------------- WRAPPER ---------------- */

    .wrap {
      max-width: 1100px;
      margin: 30px auto;
      padding: 0 5px 40px;
    }

    h1 {
      font-size: 24px;
      margin-bottom: 20px;
      font-weight: 700;
      color: #ffffff;
      text-align: center;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.4);
    }

    /* ---------------- CARD / TABLA ---------------- */

    .card {
      background: rgba(255, 255, 255, 0.95);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 20px;
      box-shadow: var(--shadow);
      overflow-x: auto;
    }

    .table-card {
      border-radius: 15px;
      overflow: hidden;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 900px;
    }

    th {
      padding: 10px 8px;
      font-size: 12.5px;
      color: #ffffff;
      text-transform: none;
      letter-spacing: 0;
      background: var(--primary);
      text-align: left;
      white-space: nowrap;
    }

    td {
      padding: 8px 8px;
      font-size: 12.5px;
      border-bottom: 1px solid var(--border);
      color: var(--text);
      vertical-align: middle;
    }

    tr:hover td {
      background: #f8f9fa;
    }

    /* ---------------- SELECTS ---------------- */

    select {
      padding: 6px 10px;
      border-radius: 6px;
      border: 1px solid #ced4da;
      background: #ffffff;
      color: #212529;
      font-size: 12.5px;
      max-width: 180px;
    }

    select:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
    }

    /* ---------------- BADGES ---------------- */

    .badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 10px;
      font-size: 11px;
      font-weight: 700;
      color: #ffffff;
    }

    .green {
      background: var(--ok);
    }

    .yellow {
      background: var(--warn);
    }

    .red {
      background: var(--bad);
    }

    .grey {
      background: var(--off);
    }

    /* ---------------- ACCIONES ---------------- */

    .row-actions a {
      cursor: pointer;
      color: var(--primary);
      font-weight: 600;
      font-size: 13px;
      text-decoration: none;
    }

    .row-actions a:hover {
      text-decoration: underline;
    }

    /* ---------------- MODAL ---------------- */

    .modal {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.65);
      display: none;
      align-items: center;
      justify-content: center;
      padding: 20px;
      z-index: 1000;
    }

    .modal-content {
      background: #ffffff;
      padding: 24px;
      border-radius: 15px;
      max-width: 500px;
      width: 95%;
      border: 1px solid var(--border);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
      position: relative;
      animation: fadeIn 0.2s ease-out;
      color: #212529;
      font-size: 14px;
    }

    .close-btn {
      position: absolute;
      top: 10px;
      right: 14px;
      font-size: 20px;
      cursor: pointer;
      color: #888;
      transition: 0.2s;
    }

    .close-btn:hover {
      color: #000;
    }

    textarea {
      width: 100%;
      padding: 10px;
      border-radius: 8px;
      background: #ffffff;
      border: 1px solid #ced4da;
      color: #212529;
      font-size: 13px;
      resize: vertical;
      min-height: 90px;
    }

    button.btn-primary {
      background: var(--primary);
      border: none;
      color: white;
      padding: 8px 16px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: 0.2s;
    }

    button.btn-primary:hover {
      background: #0056b3;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: scale(0.97);
      }

      to {
        opacity: 1;
        transform: scale(1);
      }
    }

    @media (max-width: 768px) {
      .wrap {
        margin-top: 20px;
        padding: 0 0 30px;
      }

      h1 {
        font-size: 20px;
      }

      table {
        min-width: 700px;
      }
    }

    /* VISOR DE IMAGEN */
    #visor-imagen {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.8);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 2000;
    }

    #visor-imagen img {
      max-width: 90%;
      max-height: 90%;
      border-radius: 10px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
      animation: fadeIn 0.2s ease-out;
    }
  </style>

</head>

<body>

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-content">
      <div class="hello">Hola, <?= htmlspecialchars($nombre_admin) ?></div>
      <a class="logout-btn" href="logout.php">Cerrar sesión</a>
    </div>
  </div>

  <div class="wrap">
    <h1>Panel Servicios Generales</h1>
    <!-- BOTÓN REGISTRAR NUEVO USUARIO -->
    <div style="text-align:right; margin:15px 0;">
      <button onclick="abrirModalRegistro()"
        style="
      background:#007BFF;
      color:white;
      padding:10px 20px;
      font-size:14px;
      border:none;
      border-radius:8px;
      cursor:pointer;
      box-shadow:0 3px 8px rgba(0,0,0,0.3);
      font-weight:600;
    ">
        Registrar Nuevo Usuario
      </button>
    </div>

    <div style="text-align:right; margin-bottom:15px;">
      <button onclick="toggleHistorial()"
        style="
      background:#6610f2;
      color:white;
      padding:10px 20px;
      border:none;
      border-radius:8px;
      cursor:pointer;
      font-size:14px;
      font-weight:600;
      box-shadow:0 3px 8px rgba(0,0,0,0.3);
    ">
        Ver historial Ticket Cerrados
      </button>
    </div>

    <div class="card table-card">
      <table>
        <thead>
          <tr>
            <th>N° Ticket</th>
            <th>Tipo / Categoría</th>
            <th>Técnico</th>
            <th>Estado</th>
            <th>Creado</th>
            <th>Vence</th>
            <th>Semáforo</th>
            <th>Informe</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows->num_rows === 0): ?>
            <tr>
              <td colspan="8" style="text-align:center;color:#6c757d;">No hay tickets</td>
            </tr>

          <?php else: ?>
            <?php while ($r = $rows->fetch_assoc()):
              $color = color_semaforo($r['fecha_vencimiento']);
            ?>
              <tr>

                <td><strong><?= $r['numero_ticket'] ?></strong></td>

                <td>
                  <strong><?= $r['tipo'] ?></strong><br>
                  <small><?= $r['categoria'] ?></small>
                </td>

                <td>
                  <form method="post">
                    <input type="hidden" name="accion" value="asignar_tecnico">
                    <input type="hidden" name="ticket_id" value="<?= $r['id'] ?>">
                    <select name="tecnico_id" onchange="this.form.submit()">
                      <option value="">Sin asignar</option>
                      <?php foreach ($tecnicos as $tid => $nom): ?>
                        <option value="<?= $tid ?>" <?= $r['tecnico_id'] == $tid ? 'selected' : '' ?>><?= $nom ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                </td>

                <td>
                  <form method="post">
                    <input type="hidden" name="accion" value="update_estado">
                    <input type="hidden" name="ticket_id" value="<?= $r['id'] ?>">
                    <select name="nuevo_estado" onchange="this.form.submit()">
                      <?php foreach ($estadosPosibles as $e): ?>
                        <option value="<?= $e ?>" <?= $r['estado_ticket'] == $e ? 'selected' : '' ?>><?= $e ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                </td>

                <td><?= $r['fecha_creacion'] ?></td>
                <td><?= $r['fecha_vencimiento'] ?></td>

                <td>
                  <span class="badge <?= $color ?>">
                    <?php
                    if ($color === 'green') echo 'Dentro del plazo';
                    elseif ($color === 'yellow') echo 'Próximo a vencer';
                    elseif ($color === 'red') echo 'Vencido';
                    else echo strtoupper($color);
                    ?>
                  </span>
                </td>
                <td>
                  <?php
                  $num = $r['numero_ticket'];

                  if (isset($informe_ok[$num])) {

                    $fila = $informe_ok[$num];
                    $sheet_url = "https://docs.google.com/spreadsheets/d/1hsM9e7nFwNkBP0uO5y2BTOUEbRfpp3jfTupWuz3Byn4/edit#gid=0&range=A{$fila}";

                    echo "<a href='{$sheet_url}' target='_blank' style='text-decoration:none;'>
                <span class='badge green'>OK</span>
              </a>";
                  } else {
                    echo '<span class="badge red">Pendiente</span>';
                  }
                  ?>
                </td>


                <td class="row-actions">
                  <a onclick='verTicket(<?= json_encode($r, JSON_UNESCAPED_UNICODE) ?>)'>Ver</a>
                </td>

              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
  <!-- ===================== HISTORIAL DE TICKETS CERRADOS ===================== -->
  <div id="bloqueHistorial" style="display:none; margin-top:20px;">
    <!-- BOTÓN EXPORTAR HISTORIAL A EXCEL -->
    <div style="text-align:right; margin-bottom:15px;">
      <a href="sg_exportar_historial.php"
        style="
        background:#198754;
        color:white;
        padding:10px 20px;
        border:none;
        border-radius:8px;
        cursor:pointer;
        font-size:14px;
        font-weight:600;
        box-shadow:0 3px 8px rgba(0,0,0,0.3);
        text-decoration:none;
     ">
        Exportar Historial a Excel
      </a>
    </div>
    <div class="card table-card">
      <h2 style="margin-bottom:15px; font-size:18px; color:#111827; text-align:center;">
        Historial de Tickets Cerrados
      </h2>

      <table>
        <thead>
          <tr>
            <th>N° Ticket</th>
            <th>Tipo / Categoría</th>
            <th>Técnico</th>
            <th>Cerrado</th>
            <th>Reabrir Ticket</th>
          </tr>
        </thead>
        <tbody>

          <?php if ($historial->num_rows === 0): ?>
            <tr>
              <td colspan="5" style="text-align:center; color:#6c757d;">No hay tickets cerrados</td>
            </tr>
          <?php else: ?>
            <?php while ($h = $historial->fetch_assoc()): ?>
              <tr>
                <td><strong><?= $h['numero_ticket'] ?></strong></td>

                <td>
                  <strong><?= $h['tipo'] ?></strong><br>
                  <small><?= $h['categoria'] ?></small>
                </td>

                <td><?= $h['tecnico'] ?: 'Sin asignar' ?></td>

                <td><?= $h['fecha_cierre'] ?></td>

                <td>
                  <form method="post">
                    <input type="hidden" name="accion" value="update_estado">
                    <input type="hidden" name="ticket_id" value="<?= $h['id'] ?>">
                    <input type="hidden" name="nuevo_estado" value="Ingresado">
                    <button class="btn-primary" style="padding:5px 12px;">Reabrir Ticket</button>
                  </form>
                </td>

              </tr>
            <?php endwhile; ?>
          <?php endif; ?>

        </tbody>
      </table>
    </div>

  </div>


  <!-- MODAL RESPUESTA Y VISUALIZACIÓN -->
  <div id="modal" class="modal" style="display:none;">

    <div class="modal-content">
      <span class="close-btn" onclick="cerrarModal()">&times;</span>

      <h2 id="mtitulo" style="margin-top:0;margin-bottom:10px;"></h2>

      <p><strong>Tipo:</strong> <span id="mtipo"></span></p>
      <p><strong>Estado:</strong> <span id="mestado"></span></p>
      <p><strong>Categoría:</strong> <span id="mcategoria"></span></p>
      <p><strong>Solicitante:</strong> <span id="mnombre"></span></p>
      <p><strong>Correo:</strong> <span id="mcorreo"></span></p>
      <p><strong>Detalle:</strong></p>
      <p id="mdetalle" style="white-space:pre-wrap;"></p>
      <!-- ARCHIVOS ADJUNTOS -->
      <div id="mfiles" style="margin-top:15px;"></div>
      <!-- BOTÓN WHATSAPP -->
      <div style="margin-top:20px; text-align:center;">
        <a id="btn-wsp" href="#" target="_blank"
          style="background:#25D366; padding:10px 20px; border-radius:8px; 
            color:white; font-weight:600; text-decoration:none;
            display:inline-block; font-size:14px;">
          Enviar a Técnico por WhatsApp
        </a>
      </div>


      <hr style="margin:20px 0; opacity:0.2;">

      <!-- FORMULARIO PARA RESPUESTA -->
      <form method="post">
        <input type="hidden" name="accion" value="responder_ticket">
        <input type="hidden" id="resp_id" name="ticket_id">
        <input type="hidden" id="resp_correo" name="correo_destino">
        <input type="hidden" id="resp_numero" name="numero_ticket">
        <input type="hidden" id="resp_detalle" name="detalle_ticket">

        <label><strong>Respuesta al solicitante:</strong></label>
        <textarea name="respuesta" rows="4"></textarea>

        <div style="text-align:right; margin-top:14px;">
          <button class="btn-primary" type="submit">Enviar respuesta</button>
        </div>
      </form>

    </div>
  </div>

  <script>
    function verTicket(d) {
      // datos
      document.getElementById('mtitulo').innerText = "Ticket " + d.numero_ticket;
      document.getElementById('mtipo').innerText = d.tipo;
      document.getElementById('mestado').innerText = d.estado_ticket;
      document.getElementById('mcategoria').innerText = d.categoria;
      document.getElementById('mnombre').innerText = d.nombre;
      document.getElementById('mcorreo').innerText = d.correo;
      document.getElementById('mdetalle').innerText = d.detalle;

      document.getElementById('resp_id').value = d.id;
      document.getElementById('resp_correo').value = d.correo;
      document.getElementById('resp_numero').value = d.numero_ticket;
      document.getElementById('resp_detalle').value = d.detalle;

      // ------- ARCHIVOS -------
      const cont = document.getElementById('mfiles');
      cont.innerHTML = "";

      ["archivo1", "archivo2", "archivo3"].forEach(campo => {
        if (d[campo]) {
          const url = d[campo];
          cont.innerHTML += `
        <div style="margin-bottom:10px;">
          <img src="${url}" onclick="abrirVisor('${url}')"
               style="max-width:120px; cursor:pointer; border-radius:8px; border:1px solid #ddd;">
          <br>
          <a href="${url}" target="_blank" style="font-size:12px;">Abrir en nueva pestaña</a>
        </div>
      `;
        }
      });
      // ---------- WHATSAPP PRE-FILLED FORM ------------
      let urlForm = "https://docs.google.com/forms/d/e/1FAIpQLSdfNN5bEsnTVdRb5wtlBfNk3k73PyeqIScPs0LWkos5ZsXQZA/viewform";

      let query =
        "?usp=pp_url" +
        "&entry.1823819194=" + encodeURIComponent(d.numero_ticket) +
        "&entry.816109385=" + encodeURIComponent(d.tecnico) +
        "&entry.1393044561=" + encodeURIComponent(d.tipo) +
        "&entry.1041232367=" + encodeURIComponent(d.categoria) +
        "&entry.1970726944=" + encodeURIComponent(d.nombre) +
        "&entry.2038140455=" + encodeURIComponent("Innpack") +
        "&entry.213613610=" + encodeURIComponent(d.detalle);

      let formCompleto = urlForm + query;

      // insertar en el botón
      document.getElementById('btn-wsp').href =
        "https://wa.me/?text=" + encodeURIComponent(
          "Estimado técnico, nuevo ticket asignado:\n\n" +
          "Ticket: " + d.numero_ticket + "\n" +
          "Tipo: " + d.tipo + "\n" +
          "Categoría: " + d.categoria + "\n" +
          "Solicitante: " + d.nombre + "\n" +
          "Empresa: " + (d.empresa ?? "Sin empresa") + "\n" +
          "Detalle: " + d.detalle + "\n\n" +
          "Responder formulario aquí:\n" + formCompleto
        );


      // mostrar modal
      document.getElementById('modal').style.display = 'flex';
    }

    function abrirVisor(url) {
      document.getElementById('visor-img').src = url;
      document.getElementById('visor-imagen').style.display = 'flex';
    }

    function cerrarVisor() {
      document.getElementById('visor-imagen').style.display = 'none';
    }

    function cerrarModal() {
      document.getElementById('modal').style.display = 'none';
    }

    function toggleHistorial() {
      let h = document.getElementById("bloqueHistorial");
      h.style.display = (h.style.display === "none") ? "block" : "none";
    }

    function abrirModalRegistro() {
      document.getElementById("modalRegistro").style.display = "flex";
    }

    function cerrarModalRegistro() {
      document.getElementById("modalRegistro").style.display = "none";
    }
  </script>
  <!-- MODAL REGISTRO DE USUARIO (heredado desde TI) -->
  <div id="modalRegistro" class="modal" style="display:none;">

    <div class="modal-content" style="max-width:450px;">
      <span class="close-btn" onclick="cerrarModalRegistro()">&times;</span>

      <h2 style="margin-top:0; margin-bottom:15px;">Registrar Nuevo Usuario</h2>

      <form method="POST" action="register_user.php">

        <label>Nombre completo</label>
        <input type="text" name="nombre_completo" required
          style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc; margin-bottom:10px;">

        <label>RUT</label>
        <input type="text" name="rut" required
          style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc; margin-bottom:10px;">

        <label>Correo electrónico</label>
        <input type="email" name="correo_electronico" required
          style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc; margin-bottom:10px;">

        <label>Teléfono</label>
        <input type="text" name="telefono" required
          style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc; margin-bottom:10px;">

        <label>Contraseña</label>
        <input type="password" name="clave" required
          style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc; margin-bottom:20px;">

        <button class="btn-primary" type="submit"
          style="width:100%; padding:10px; font-size:15px;">
          Registrar
        </button>
      </form>

    </div>
  </div>

  <div id="visor-imagen" onclick="cerrarVisor()">
    <img id="visor-img">
  </div>
</body>

</html>