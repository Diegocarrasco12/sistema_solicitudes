<?php
if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 30,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
}
session_start();

setcookie(session_name(), session_id(), [
  'expires' => time() + 60 * 60 * 24 * 30,
  'path' => '/',
  'secure' => false,
  'httponly' => true,
  'samesite' => 'Lax'
]);

include 'conexion.php';

$ticket_number = $_GET['ticket_number'] ?? '';
$texto = $_GET['texto'] ?? '';
$tipo = $_GET['tipo'] ?? '';
$usuario = $_GET['usuario'] ?? '';
$fecha = $_GET['fecha'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

/************************************
 *   NUEVO: Cargar tramos de todos los tickets
 ************************************/
$tramos_por_ticket = [];

$qTramos = "
    SELECT ticket_id, estado_inicio, estado_fin, fecha_inicio, fecha_fin, minutos_habiles
    FROM ticket_tramos
    ORDER BY id ASC
";
$rTramos = mysqli_query($conexion, $qTramos);

while ($t = mysqli_fetch_assoc($rTramos)) {
  $id = $t['ticket_id'];
  if (!isset($tramos_por_ticket[$id])) {
    $tramos_por_ticket[$id] = [];
  }
  $tramos_por_ticket[$id][] = $t;
}

/************************************/

$condiciones = ["estado_ticket = 'Gestionado'"];
if ($ticket_number !== '') {
  $condiciones[] = "numero_ticket LIKE '%" . mysqli_real_escape_string($conexion, $ticket_number) . "%'";
}
if ($texto !== '') {
  $condiciones[] = "detalle LIKE '%" . mysqli_real_escape_string($conexion, $texto) . "%'";
}
if ($tipo !== '') {
  $condiciones[] = "tipo = '" . mysqli_real_escape_string($conexion, $tipo) . "'";
}
if ($usuario !== '') {
  $condiciones[] = "usuario_asignado = '" . mysqli_real_escape_string($conexion, $usuario) . "'";
}
if ($fecha !== '') {
  $condiciones[] = "DATE(fecha_creacion) = '" . mysqli_real_escape_string($conexion, $fecha) . "'";
}

$cond_sql = " WHERE " . implode(' AND ', $condiciones);

$total_query = "SELECT COUNT(*) as total FROM tickets $cond_sql";
$total_result = mysqli_query($conexion, $total_query);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_rows / $limit);

$query = "SELECT * FROM tickets $cond_sql ORDER BY fecha_creacion DESC LIMIT $limit OFFSET $offset";
$resultado = mysqli_query($conexion, $query);
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Historial de Tickets</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
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

    body {
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
      background-color: rgba(255, 255, 255, 0.9);
    }

    header {
      text-align: center;
      background-color: rgba(0, 0, 0, 0.7);
      color: white;
      padding: 20px 0;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
      margin-bottom: 20px;
    }

    .categoria {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 15px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      padding: 20px;
      width: 95%;
      max-width: 1300px;
      margin: 0 auto 40px auto;
    }

    th,
    td {
      padding: 8px;
      border-bottom: 1px solid #ddd;
      text-align: center;
      font-size: 13px;
      vertical-align: middle;
      white-space: nowrap;
    }

    th {
      background-color: #007BFF;
      color: white;
    }

    .btn-sm {
      font-size: 12px;
      padding: 4px 8px;
    }

    #chatPopup {
      display: none;
      position: fixed;
      top: 10%;
      left: 50%;
      transform: translateX(-50%);
      background: #ffffff;
      padding: 20px;
      border-radius: 15px;
      width: 90%;
      max-width: 500px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
      z-index: 10000;
    }

    #chatContenido {
      max-height: 400px;
      overflow-y: auto;
      background: #f4f4f4;
      padding: 10px;
      margin-bottom: 10px;
      font-size: 14px;
    }
  </style>
</head>

<body>
  <header>
    <h2>Historial de Tickets Gestionados</h2>
  </header>

  <div class="categoria">
    <form method="GET" class="row g-2 mb-3 align-items-end">
      <div class="col-md-2">
        <input type="number" name="ticket_number" class="form-control form-control-sm" placeholder="Nº Ticket" value="<?php echo htmlspecialchars($ticket_number); ?>">
      </div>
      <div class="col-md-3">
        <input type="text" name="texto" class="form-control form-control-sm" placeholder="Buscar en detalle" value="<?php echo htmlspecialchars($texto); ?>">
      </div>
      <div class="col-md-2">
        <input type="text" name="usuario" class="form-control form-control-sm" placeholder="Usuario asignado" value="<?php echo htmlspecialchars($usuario); ?>">
      </div>
      <div class="col-md-2">
        <input type="date" name="fecha" class="form-control form-control-sm" value="<?php echo htmlspecialchars($fecha); ?>">
      </div>
      <div class="col-md-2">
        <select name="tipo" class="form-select form-select-sm">
          <option value="">Todos los tipos</option>
          <option value="Incidencia" <?php if ($tipo == 'Incidencia') echo 'selected'; ?>>Incidencia</option>
          <option value="Reclamo" <?php if ($tipo == 'Reclamo') echo 'selected'; ?>>Reclamo</option>
          <option value="Solicitud" <?php if ($tipo == 'Solicitud') echo 'selected'; ?>>Solicitud</option>
        </select>
      </div>
      <div class="col-md-1 d-grid">
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
      </div>
      <div class="col-12 d-flex justify-content-end gap-2 mt-2">
        <a href="historial.php" class="btn btn-secondary btn-sm">Eliminar Filtros</a>
        <a href="exportar_historial.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success btn-sm">Exportar</a>
      </div>
    </form>

    <div style="overflow-x: auto;">
      <table class="table table-striped">
        <thead>
          <tr>
            <th>Nº Ticket</th>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Nombre</th>
            <th>Correo</th>
            <th>Teléfono</th>
            <th>Empresa</th>
            <th>Asignado</th>
            <th>Detalle</th>
            <th>Acciones</th> <!-- MOVIDO -->
            <th>Tramos</th> <!-- AL FINAL -->
            <th>Total</th> <!-- AL FINAL -->
          </tr>
        </thead>

        <tbody>
          <?php while ($row = mysqli_fetch_assoc($resultado)): ?>

            <?php
            // Procesar tramos del ticket
            $idT = $row['id'];
            $tramos = $tramos_por_ticket[$idT] ?? [];

            $lista_tramos = "";
            $total = 0;

            foreach ($tramos as $t) {
              $min = (int)$t['minutos_habiles'];
              $total += $min;

              $ini = substr($t['fecha_inicio'], 11, 5);
              $fin = $t['fecha_fin'] ? substr($t['fecha_fin'], 11, 5) : '...';

              $estadoIni = $t['estado_inicio'] ?: '—';
              $estadoFin = $t['estado_fin'] ?: '—';

              $lista_tramos .= "
    <strong>$estadoIni → $estadoFin</strong><br>
    $ini → $fin ({$min}m)
    <br><br>
";
            }

            $h = floor($total / 60);
            $m = $total % 60;
            $total_legible = "{$h}h {$m}m";
            ?>

            <tr>
              <td><?php echo htmlspecialchars($row['numero_ticket']); ?></td>
              <td><?php echo htmlspecialchars($row['fecha_creacion']); ?></td>
              <td><?php echo htmlspecialchars($row['tipo']); ?></td>
              <td><?php echo htmlspecialchars($row['nombre']); ?></td>
              <td><?php echo htmlspecialchars($row['correo']); ?></td>
              <td><?php echo htmlspecialchars($row['telefono']); ?></td>
              <td><?php echo htmlspecialchars($row['empresa']); ?></td>
              <td><?php echo htmlspecialchars($row['usuario_asignado'] ?: 'Sin asignar'); ?></td>

              <td>
                <button class="btn btn-info btn-sm text-white"
                  onclick="verDetalle('<?php echo $row['id']; ?>')">
                  Ver Detalle
                </button>
              </td>

              <!-- ACCIONES PRIMERO -->
              <td>
                <button class="btn btn-warning btn-sm w-100"
                  onclick="reabrirTicket('<?php echo $row['id']; ?>', '<?php echo $row['tipo']; ?>')">
                  Reabrir
                </button>
              </td>

              <!-- TRAMOS AL FINAL -->
              <td style="font-size:11px; text-align:left;">
                <?php echo $lista_tramos ?: "<em>Sin tramos</em>"; ?>
              </td>

              <!-- TOTAL AL FINAL -->
              <td><strong><?php echo $total_legible; ?></strong></td>

            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>


    <div class="text-center mt-3">
      <nav>
        <ul class="pagination justify-content-center">
          <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
              <a class="page-link" href="?<?php $params = $_GET;
                                          $params['page'] = $i;
                                          echo http_build_query($params); ?>"><?php echo $i; ?></a>
            </li>
          <?php endfor; ?>

        </ul>
      </nav>
    </div>
  </div>

  <div id="chatPopup">
    <div id="chatContenido"></div>
    <div class="text-end">
      <button class="btn btn-danger btn-sm" onclick="$('#chatPopup').hide();">Cerrar</button>
    </div>
  </div>

  <script>
    function verDetalle(id) {
      fetch('ver_chat_ticket.php?id=' + id)
        .then(response => response.text())
        .then(data => {
          document.getElementById('chatContenido').innerHTML = data;
          document.getElementById('chatPopup').style.display = 'block';
        });
    }

    function reabrirTicket(id, tipo) {
      if (!confirm('¿Deseas reabrir este ticket?')) return;
      fetch('reabrir_ticket.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'id_ticket=' + encodeURIComponent(id) + '&tipo=' + encodeURIComponent(tipo)
      }).then(() => location.reload());
    }
  </script>

  <script>
    let tiempoInactivo = 300000;
    let temporizador = setTimeout(expirarSesion, tiempoInactivo);

    ['mousemove', 'keydown', 'click'].forEach(evento => {
      document.addEventListener(evento, () => {
        clearTimeout(temporizador);
        temporizador = setTimeout(expirarSesion, tiempoInactivo);
      });
    });

    function expirarSesion() {
      window.location.href = "index.php";
    }
  </script>
</body>

</html>