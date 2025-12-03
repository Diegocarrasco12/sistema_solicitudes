<?php
session_start();

// Tiempo de inactividad permitido en segundos (5 minutos)
$inactividad_maxima = 300;

// Verificar si ya existe el tiempo de última actividad
if (isset($_SESSION['ultimo_acceso'])) {
    $tiempo_inactivo = time() - $_SESSION['ultimo_acceso'];

    if ($tiempo_inactivo > $inactividad_maxima) {
        // Cierra sesión por inactividad
        session_unset();
        session_destroy();
        header("Location: index.php?expirado=1");
        exit();
    }
}

// Actualiza el tiempo de última actividad
$_SESSION['ultimo_acceso'] = time();

// Validar sesión activa
if (!isset($_SESSION['rut']) || empty($_SESSION['rut'])) {
    header("Location: index.php");
    exit();
}

// Datos del usuario en sesión
$nombre   = $_SESSION['nombre'];
$telefono = $_SESSION['telefono'];
$correo   = $_SESSION['correo'];
$rut      = $_SESSION['rut'];

include("conexion.php");
$query = "SELECT es_admin FROM usuarios WHERE rut = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("s", $rut);
$stmt->execute();
$resultado = $stmt->get_result();
$fila = $resultado->fetch_assoc();
$es_admin = $fila && $fila['es_admin'] == 1;
$stmt->close();
;

$usuarios_registrados = [];
if ($es_admin) {
    $consulta = "SELECT DISTINCT rut, nombre_completo, correo_electronico, telefono 
                 FROM usuarios 
                 ORDER BY nombre_completo ASC";
    $resultado = $conexion->query($consulta);
    if ($resultado) {
        while ($fila = $resultado->fetch_assoc()) {
            $usuarios_registrados[] = [
                'nombre'   => $fila['nombre_completo'],
                'rut'      => $fila['rut'],
                'correo'   => $fila['correo_electronico'],
                'telefono' => $fila['telefono']
            ];
        }
    } else {
        die("Error al consultar la tabla de usuarios: " . $conexion->error);
    }
}


// Funciones
function tiempoTranscurrido($fecha_creacion) {
    $inicio = new DateTime($fecha_creacion);
    $actual = new DateTime();
    $diff = $inicio->diff($actual);
    return [$diff->h + ($diff->days * 24), $diff->i, $diff->s];
}

function colorContador($horas) {
    return $horas < 2 ? "green" : ($horas < 4 ? "orange" : "red");
}

function generarCodigoTicket($tipo, $id, $conexion) {
    $prefijos = ["Incidencia" => "INC", "Reclamo" => "REC", "Solicitud" => "SOL"];
    $prefijo = isset($prefijos[$tipo]) ? $prefijos[$tipo] : "TCK";

    $stmt = $conexion->prepare("SELECT id FROM tickets WHERE tipo = ? ORDER BY id ASC");
    $stmt->bind_param("s", $tipo);
    $stmt->execute();
    $result = $stmt->get_result();

    $numero = 0;
    while ($row = $result->fetch_assoc()) {
        $numero++;
        if ($row['id'] == $id) break;
    }

    $stmt->close();
    return $prefijo . str_pad($numero, 8, "0", STR_PAD_LEFT);
}

// Obtener historial de tickets (con filtro por estado y pendientes primero)
$tickets_usuario = [];

// Normalizamos y validamos filtro de estado recibido por GET ('' = todos)
$ESTADOS_PERMITIDOS = ['', 'Ingresado', 'Asignado', 'En curso', 'Cerrado'];
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
if (!in_array($estado, $ESTADOS_PERMITIDOS, true)) { $estado = ''; }

// Construimos SQL parametrizada
$sql = "SELECT id, tipo, empresa, detalle, archivo, estado_ticket, fecha_creacion
        FROM tickets
        WHERE rut = ?";

if ($estado !== '') {
    $sql .= " AND estado_ticket = ?";
}

// Orden: pendientes primero (Ingresado/Asignado/En curso), luego el resto; dentro de cada grupo, los más nuevos primero
$sql .= " ORDER BY
            CASE WHEN estado_ticket IN ('Ingresado','Asignado','En curso') THEN 0 ELSE 1 END,
            fecha_creacion DESC";

$stmt = $conexion->prepare($sql);
if ($estado !== '') {
    $stmt->bind_param("ss", $rut, $estado);
} else {
    $stmt->bind_param("s", $rut);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $tickets_usuario[] = $row;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Generador de Tickets</title>
<style>
    * {
        box-sizing: border-box;
        font-family: Arial, sans-serif;
    }

    body::before {
        content: "";
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: url('assets/img/tkt.jpg') no-repeat center center fixed;
        background-size: cover;
        filter: blur(8px);
        z-index: -1;
    }

    body {
        color: #fff;
        text-align: center;
        margin: 0;
        padding: 0;
        min-height: 100vh;
    }

   .logo {
    width: 50%;
    max-width: 400px;
    margin: 20px auto;
    display: block;
}

    @media (max-width: 768px) {
    .logo {
        width: 90%;
        max-width: 300px;
        background-color: white;
        padding: 10px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
}

    .login-menu {
        position: absolute;
        top: 10px;
        right: 20px;
    }

    .login-button {
        background: none;
        border: none;
        cursor: pointer;
    }

    .login-container {
        display: none;
        position: absolute;
        top: 70px;
        right: 0px;
        background: rgba(0, 0, 0, 0.9);
        padding: 10px;
        border-radius: 0px;
        width: 180px;
    }

    .container {
        max-width: 700px;
        margin: auto;
        padding: 20px;
        background-color: rgba(0, 0, 0, 0.7);
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.5);
        border-radius: 10px;
    }

    form {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 3px;
    }

    input, select, textarea, button {
        width: 100%;
        padding: 8px;
        border: 2px solid #ccc;
        border-radius: 5px;
        font-size: 16px;
    }

    input[readonly] {
        background-color: #e0e0e0;
    }

    button {
        background-color: #007BFF;
        color: white;
        font-weight: bold;
        border: 3px solid
        cursor: pointer;
    }

    button:hover {
        background-color: #0056b3;
    }

   

    .categoria {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 15px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        padding: 20px;
        width: 95%;
        max-width: 1300px;
        overflow-x: auto;
        margin: 40px auto;
    }

    .categoria h2 {
        border-bottom: 2px solid #007BFF;
        padding-bottom: 10px;
        margin-bottom: 15px;
        color: #333;
        font-size: 20px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1000px;
    }

    th, td {
        padding: 10px;
        border-bottom: 1px solid #ddd;
        text-align: left;
        font-size: 14px;
        background: #fff;
        color: #000;
    }

    th {
        background-color: #007BFF;
        color: white;
        font-weight: bold;
    }

    .contador {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 5px;
        font-weight: bold;
        color: white;
        font-size: 12px;
    }

    /* Chat estilo moderno */
    #chatPopup {
        display: none;
        position: fixed;
        top: 10%;
        left: 50%;
        transform: translateX(-50%);
        background: #ffffff;
        color: #000000;
        padding: 20px;
        border-radius: 15px;
        width: 70%;
        max-width: 300px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        z-index: 10000;
    }

    #chatContenido {
        max-height: 400px;
        overflow-y: auto;
        background: #f9f9f9;
        padding: 15px;
        margin-bottom: 10px;
        font-size: 14px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

.mensaje {
    max-width: 75%;
    padding: 10px 15px;
    border-radius: 20px;
    font-size: 14px;
    line-height: 1.4;
    word-wrap: break-word;
    display: inline-block;
    clear: both;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.usuario {
    background-color: #dbeeff;
    color: #000;
    align-self: flex-start;
    text-align: left;
}

.admin {
    background-color: #4a4a4a;
    color: #fff;
    align-self: flex-end;
    text-align: right;
}

    .mensaje strong {
        display: block;
        font-weight: bold;
        margin-bottom: 4px;
    }

@media (max-width: 768px) {
    .container {
        width: 95%;
        padding: 15px;
    }

    .categoria {
        width: 98%;
        padding: 15px;
        overflow-x: auto;
    }

    .sidebar {
        position: relative;
        top: unset;
        left: unset;
        transform: none;
        width: 100%;
        border-radius: 0;
        margin-top: 20px;
        box-shadow: none;
        text-align: center;
    }

    .sidebar h2 {
        font-size: 16px;
    }

    .sidebar ul li {
        margin: 5px 0;
    }

    .login-menu {
        position: static;
        text-align: center;
        margin-bottom: 10px;
    }

    .login-container {
        position: static;
        width: 100%;
        padding: 10px;
        margin-bottom: 15px;
    }

    .logo {
        width: 90%;
        max-width: 300px;
    }

    table {
        font-size: 13px;
        min-width: unset;
    }

    th, td {
        padding: 6px;
    }

    button {
        padding: 10px;
        font-size: 14px;
    }

    .mensaje {
        font-size: 13px;
    }
}

@media (max-width: 480px) {
    .login-menu img {
        width: 120px;
    }

    .login-container input,
    .login-container button {
        font-size: 14px;
    }

    textarea, select, input {
        font-size: 14px;
        padding: 8px;
    }

    .categoria h2 {
        font-size: 18px;
    }

    .sidebar ul li a {
        font-size: 14px;
    }

    .mensaje {
        font-size: 12px;
    }
}
@media (max-width: 768px) {
    table, thead, tbody, th, td, tr {
        display: block;
        width: 100%;
    }

    thead {
        display: none;
    }

    tbody tr {
        margin-bottom: 15px;
        background: #fff;
        color: #000;
        border-radius: 10px;
        padding: 10px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }

    tbody td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px;
        border: none;
        font-size: 14px;
        border-bottom: 1px solid #ddd;
    }

    tbody td:last-child {
        border-bottom: none;
    }

    tbody td::before {
        content: attr(data-label);
        font-weight: bold;
        flex-basis: 50%;
        color: #333;
    }

    .categoria h2 {
        font-size: 20px;
        text-align: center;
    }
}
.usuario-menu {
    position: absolute;
    top: 10px;
    right: 20px;
    z-index: 1001;
    background-color: rgba(0, 0, 0, 0.7);
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: bold;
    color: white;
    display: flex;
    align-items: center;
}

.usuario-menu button {
    background: none;
    border: none;
    color: white;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

#menuUsuario {
    display: none;
    position: absolute;
    right: 0;
    top: 35px;
    background-color: #222;
    color: white;
    border: 1px solid #ccc;
    border-radius: 5px;
    padding: 10px;
    z-index: 1002;
}

@media (max-width: 768px) {
    .usuario-menu {
        position: relative;
        top: 0;
        right: 0;
        left: 0;
        margin: 10px auto;
        justify-content: center;
        text-align: center;
    }

    #menuUsuario {
        right: auto;
        left: 50%;
        transform: translateX(-50%);
    }
}

#chatPopup {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #f8f9fa;
    color: #222;
    padding: 10px 5px 8px 5px;
    border-radius: 15px;
    width: 95vw;
    max-width: 400px;
    height: 80vh;
    max-height: 560px;
    min-height: 340px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.25);
    z-index: 10000;
    flex-direction: column;
    justify-content: flex-start;
    align-items: stretch;
    overflow: hidden;
}

@media (max-width: 450px) {
    #chatPopup {
        width: 99vw;
        max-width: 99vw;
        min-width: 97vw;
        height: 50vh;
        max-height: 85vh;
        min-height: 230px;
        padding: 3vw 2vw;
        border-radius: 0 0 18px 18px;
        left: 50%;
        top: 80vw;
        transform: translate(-50%, -20%);
    }
    #chatContenido {
        max-height: 45vh;
        min-height: 90px;
    }
    .chat-botones button {
        font-size: 13px;
        padding: 9px 0;
    }
    #chatPopup textarea {
        font-size: 13px;
        min-height: 36px;
        max-height: 90px;
    }
}

@media (max-width: 430px) {
    #chatPopup {
        padding: 2vw 0vw;
        border-radius: 0;
        width: 100vw;
        max-width: 100vw;
        height: 93vh;
        max-height: 99vh;
        left: 50%;
        top: 50vw;
        transform: translate(-50%, 0%);
    }
}


#chatContenido {
    flex: 1 1 auto;
    overflow-y: auto;
    background: #fff;
    padding: 7px;
    margin-bottom: 8px;
    border-radius: 8px;
    font-size: 15px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.07);
    min-height: 60px;
    max-height: 330px;
}
#chatPopup textarea {
    width: 100%;
    min-height: 38px;
    max-height: 100px;
    background: #f4f4f4;
    color: #222;
    border: 1px solid #bbb;
    border-radius: 7px;
    padding: 7px;
    margin-bottom: 10px;
    font-size: 15px;
    resize: none;
    outline: none;
}


.chat-botones {
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.chat-botones button {
    flex: 1 1 45%;
    background: #007bff;
    color: #fff;
    font-weight: bold;
    border: none;
    padding: 10px 0;
    border-radius: 6px;
    cursor: pointer;
    font-size: 15px;
    transition: background 0.18s;
}
.chat-botones button:last-child {
    background: #dc3545;
}
.chat-botones button:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.mensaje {
    max-width: 80%;
    padding: 10px 14px;
    border-radius: 16px;
    font-size: 15px;
    line-height: 1.4;
    word-wrap: break-word;
    display: inline-block;
    clear: both;
    box-shadow: 0 1px 3px rgba(0,0,0,0.07);
    margin-bottom: 1px;
}

.usuario {
    background: #e0f3ff;
    color: #222;
    align-self: flex-start;
    text-align: left;
}

.admin {
    background: #cce5ff;
    color: #222;
    align-self: flex-end;
    text-align: right;
}

.mensaje strong {
    display: block;
    font-weight: bold;
    margin-bottom: 3px;
    font-size: 13px;
}

@media (max-width: 600px) {
    #chatPopup {
        width: 99vw;
        max-width: 100vw;
        min-height: 60vw;
        max-height: 98vh;
        padding: 8px 2px 8px 2px;
    }
    #chatContenido {
        font-size: 14px;
        padding: 5px;
        max-height: 48vw;
    }
    .chat-botones button {
        padding: 9px 0;
        font-size: 14px;
    }
    #chatPopup textarea {
        padding: 7px;
        font-size: 14px;
    }
}


</style>

<script>
function toggleLogin() {
    const loginForm = document.getElementById('login-container');
    loginForm.style.display = loginForm.style.display === 'block' ? 'none' : 'block';
}

function login(event) {
    event.preventDefault();
    const user = document.getElementById('username').value.trim();
    const pass = document.getElementById('password').value.trim();

    // Lista de usuarios admin y contraseñas
    const admins = {
      'greyes': 'admin',
      'jrangel': 'admin',
      'snunez': 'admin',
      'jlara': 'admin'
    };

    if (admins[user] && admins[user] === pass) {
        window.open('admin.php', '_blank');
    } else {
        alert('Usuario o contraseña incorrectos');
    }
}
</script>
</head>
<body>
<?php if (!$es_admin): ?>
<div class="usuario-menu">
    <div id="usuarioDropdown" style="position: relative;">
        <button onclick="toggleUsuarioMenu()" style="background-color: transparent; border: none; color: white; font-size: 16px; font-weight: bold; cursor: pointer;">
            Bienvenid@  <?php echo htmlspecialchars($nombre); ?> ▼
        </button>
        <div id="menuUsuario" style="display: none; position: absolute; right: 0; background-color: #222; color: black; border: 1px solid #ccc; border-radius: 5px; padding: 10px;">
            <form action="logout.php" method="post" style="margin: 0;">
                <button type="submit" style="background-color: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 5px; font-weight: bold; cursor: pointer;">
                    Cerrar Sesión
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>



<?php if ($es_admin): ?>
<!-- Contenedor fijo para admin con botón de acceso directo y cierre de sesión -->
<div class="d-flex flex-column align-items-end align-items-md-start gap-2 p-2" style="position: absolute; top: 10px; right: 10px; z-index: 1000;">

    <!-- Botón: Ir al panel de administración -->
    <a href="admin.php" target="_blank" class="btn btn-dark fw-bold w-100" style="padding: 8px 16px; font-size: 14px;">
        👤 Administrador
    </a>

    <!-- Botón: Cerrar sesión -->
    <form action="logout.php" method="post" class="w-100">
        <button type="submit" class="btn btn-danger fw-bold w-100">
            Cerrar sesión
        </button>
    </form>

</div>
<?php endif; ?>





<img src="assets/img/logo.png" alt="Logo" class="logo">

<div class="container">
    <form id="formTicket" method="POST" action="procesar_ticket.php" enctype="multipart/form-data">
        <!-- Nombre: solo editable por administradores -->
      <?php if ($es_admin): ?>
    <select name="nombre" id="nombreAdmin" class="form-control" onchange="actualizarDatosUsuario()" required>
        <option value="">Seleccionar usuario registrado</option>
        <?php foreach ($usuarios_registrados as $usuario): ?>
            <option 
                value="<?php echo htmlspecialchars($usuario['nombre']); ?>" 
                data-rut="<?php echo $usuario['rut']; ?>"
                data-correo="<?php echo $usuario['correo']; ?>"
                data-telefono="<?php echo $usuario['telefono']; ?>"
            >
                <?php echo htmlspecialchars($usuario['nombre']); ?>
            </option>
        <?php endforeach; ?>
    </select>
<?php else: ?>
    <input 
        type="text" 
        name="nombre" 
        value="<?php echo $nombre; ?>" 
        placeholder="Escribe aquí tu nombre" 
        readonly
    >
<?php endif; ?>


        <!-- RUT: solo editable por administradores -->
     <input 
    type="text" 
    id="rut" 
    name="rut" 
    value="<?php echo $rut; ?>" 
    placeholder="RUT del usuario" 
    readonly
>

      
        <!-- Teléfono: editable por todos -->
<input 
    type="tel" 
    id="telefono" 
    name="telefono" 
    value="<?php echo $telefono; ?>" 
    placeholder="Ingresa tu número de teléfono"
    required
>


<input 
    type="email" 
    id="correo" 
    name="correo" 
    value="<?php echo $correo; ?>" 
    placeholder="Ingresa tu correo electrónico"
    required
>

        <!-- Tipo: editable por todos -->
        <select name="tipo" required>
            <option value="">Seleccionar Tipo</option>
            <option value="Solicitud">Solicitud</option>
            <option value="Incidencia">Incidencia</option>
            <option value="Reclamo">Reclamo</option>
        </select>

        <!-- ===== NUEVO: Campos ocultos para mantener compatibilidad ===== -->
        <input type="hidden" name="area_id" value="1">
        <input type="hidden" name="categoria_id" value="">
        <!-- ===== /NUEVO ===== -->



        <!-- Empresa: editable por todos -->
        <select name="empresa" required>
            <option value="">Seleccionar Empresa</option>
            <option value="Faret">Faret</option>
            <option value="Innpack">Innpack</option>
            <option value="SFM">SFM</option>
        </select>

        <!-- Detalle: editable por todos -->
        <textarea 
            name="detalle" 
            placeholder="Detalle del problema o solicitud (máx. 500 caracteres)" 
            maxlength="500" 
            required
        ></textarea>

        <!-- Archivo: editable por todos -->
     <!-- Campo para subir archivo con restricciones -->
<div class="mb-3">
  <label for="archivoAdjunto" class="form-label text-white">Adjuntar archivo (PDF, Word o imagen | máx. 5MB)</label>
  <input 
    type="file" 
    id="archivoAdjunto" 
    name="archivo" 
    class="form-control mb-2"
    accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx"
    onchange="validarArchivo(this)"
    
  >
  <small class="text-muted">
    Tamaño máximo: 5MB. Formatos permitidos: JPG, PNG, GIF, PDF, DOC, DOCX.
  </small>
</div>


        <!-- Botón siempre activo -->
        <button type="submit" id="btnCrearTicket">Crear</button>
    </form>
</div>




<div class="categoria">
    <h2>Historial de Tickets</h2>
    <?php
    // ---- Filtro de estado (seguro) ----
    $ESTADOS_PERMITIDOS = ['','Ingresado','Asignado','En curso','Cerrado']; // '' = Todos
    $estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
    if (!in_array($estado, $ESTADOS_PERMITIDOS, true)) { $estado = ''; }
    ?>

    <!-- Glosario conciso de estados -->
    <div style="margin: 6px 0 12px 0; font-size:13px; color:#444; background:#f8f9fa; padding:6px 10px; border-radius:6px;">
    <strong>¿Qué significa cada estado?</strong><br>
    <b>Ingresado:</b> ticket creado, aún sin técnico asignado. |
    <b>Asignado:</b> ya tiene técnico, pero aún no iniciado. |
    <b>En curso:</b> técnico trabajando en la solicitud.
    </div>

    <!-- Filtro por estado -->
    <form method="get" style="display:flex; gap:8px; align-items:center; margin-bottom:10px;">
    <label for="f_estado" style="font-size:13px; white-space:nowrap;">Filtrar por estado:</label>
    <select id="f_estado" name="estado" onchange="this.form.submit()" style="padding:4px 8px;">
        <option value="" <?= $estado===''?'selected':''; ?>>Todos</option>
        <option value="Ingresado" <?= $estado==='Ingresado'?'selected':''; ?>>Ingresado</option>
        <option value="Asignado"  <?= $estado==='Asignado'?'selected':''; ?>>Asignado</option>
        <option value="En curso"  <?= $estado==='En curso'?'selected':''; ?>>En curso</option>
        <option value="Cerrado"   <?= $estado==='Cerrado'?'selected':''; ?>>Cerrado</option>
    </select>
    <!-- Si usas más parámetros en la URL, repítelos aquí como <input type="hidden"> -->
    </form>

    <table>
        <thead>
            <tr>
                <th>N° Ticket</th>
                <th>Tipo</th>
                <th>Empresa</th>
                <th>Detalle</th>
                <th>Adjunto</th>
                <th>Estado</th>
                <th>Fecha</th>
            </tr>
        </thead>
<tbody>
<?php foreach ($tickets_usuario as $ticket):
    $codigo = generarCodigoTicket($ticket['tipo'], $ticket['id'], $conexion);
?>
<tr>
    <td data-label="N° Ticket"><?php echo $codigo; ?></td>
    <td data-label="Tipo"><?php echo $ticket['tipo']; ?></td>
    <td data-label="Empresa"><?php echo $ticket['empresa']; ?></td>
    <td data-label="Detalle">
        <button style='padding:4px 8px; font-size:12px;' onclick="mostrarChat(<?= $ticket['id'] ?>, '<?= $ticket['estado_ticket'] ?>')">💬</button>
  <td data-label="Adjunto">
    <?php
        if (!empty($ticket["archivo"])) {
                $rutaRelativa = '/' . ltrim($ticket["archivo"], '/');
                echo '<a href="' . htmlspecialchars($rutaRelativa) . '" target="_blank">Ver</a>';
        } else {
            echo '-';
        }
    ?>
</td>

    <td data-label="Estado">
  <?php echo ($ticket['estado_ticket'] == 'Gestionado') ? 'Cerrado' : $ticket['estado_ticket']; ?>
</td>

    <td data-label="Fecha"><?php echo date('Y-m-d', strtotime($ticket['fecha_creacion'])); ?></td>
</tr>
<?php endforeach; ?>
</tbody>

    </table>
</div>

<!-- Modal Bootstrap para el Chat -->
<div class="modal fade" id="chatModal" tabindex="-1" aria-labelledby="chatModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="chatModalLabel">Conversación del Ticket</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div id="chatContenido" class="mb-3" style="max-height: 300px; overflow-y: auto;"></div>

        <textarea id="respuestaUsuario" class="form-control mb-2" placeholder="Escribe tu respuesta..." rows="3"></textarea>
        <input type="file" id="archivoAdjuntoChat" name="archivo" class="form-control mb-2"
       accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx"
       onchange="validarArchivo(this)" required>

<small class="text-muted">
  Tamaño máximo: 5MB. Formatos permitidos: JPG, PNG, GIF, PDF, DOC, DOCX.
</small>

        <div class="d-flex justify-content-between">
          <button id="btnEnviarRespUsuario" onclick="enviarRespuestaUsuario()" class="btn btn-primary w-50 me-2">Enviar</button>
          <button type="button" class="btn btn-secondary w-50" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>

    </div>
  </div>
</div>


<script>
let idTicketActual = 0;
function mostrarChat(id, estado) {
    idTicketActual = id;

    // Imprime para ver qué valor llega
    console.log('Estado recibido:', estado);

    fetch('ver_chat_ticket.php?id=' + id)
        .then(res => res.text())
        .then(html => {
            document.getElementById('chatContenido').innerHTML = html;
            let modal = new bootstrap.Modal(document.getElementById('chatModal'));
            modal.show();

            document.getElementById('respuestaUsuario').value = '';

            // Normaliza el estado
            const estadoClean = (estado || '').trim().toLowerCase();
            const puedeResponder =
                estadoClean === 'ingresado' ||
                estadoClean === 'asignado' ||
                estadoClean === 'en curso';

            document.getElementById('respuestaUsuario').disabled = !puedeResponder;
            document.getElementById('btnEnviarRespUsuario').style.display = puedeResponder ? 'inline-block' : 'none';
        });
}





function cerrarChat() {
    document.getElementById('chatPopup').style.display = 'none';
}

let enviando = false;
function enviarRespuestaUsuario() {
    if (enviando) return; // previene doble envío

    const textarea = document.getElementById('respuestaUsuario');
    const mensaje = textarea.value.trim();
    const archivoInput = document.getElementById('archivoAdjuntoChat');
    const archivo = archivoInput.files[0];

    if (mensaje === '' && !archivo) {
        alert("Debes escribir una respuesta o adjuntar un archivo.");
        return;
    }

    // Muestra el mensaje instantáneamente si hay texto
    if (mensaje !== '') {
        const chat = document.getElementById('chatContenido');
        const nuevaBurbuja = document.createElement('div');
        nuevaBurbuja.className = "mensaje usuario";
        nuevaBurbuja.innerHTML = "<strong><?php echo addslashes($nombre); ?>:</strong> " + mensaje;
        chat.appendChild(nuevaBurbuja);
        chat.scrollTop = chat.scrollHeight;
    }

    textarea.value = '';
    textarea.disabled = true;
document.getElementById('btnEnviarRespUsuario').disabled = true;
enviando = true;

// Reactiva después de 10 segundos
setTimeout(() => {
    textarea.disabled = false;
    document.getElementById('btnEnviarRespUsuario').disabled = false;
    enviando = false;
}, 10000);

    // NUEVO: Envío con FormData
    const formData = new FormData();
    formData.append('id', idTicketActual);
    formData.append('mensaje', mensaje);
    if (archivo) {
        formData.append('archivo', archivo);
    }

    fetch('responder_usuario.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.text())
    .then(resp => {
        mostrarChat(idTicketActual, 'Asignado');
        enviando = false;
        archivoInput.value = ''; // Limpia el input de archivo
    })
    .catch(() => {
        alert('Error al enviar el mensaje. Intenta de nuevo.');
        enviando = false;
        textarea.disabled = false;
        document.getElementById('btnEnviarRespUsuario').disabled = false;
    });
}


</script>

<script>
function toggleUsuarioMenu() {
    const menu = document.getElementById('menuUsuario');
    menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
}

// Cierra el menú si haces clic fuera
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('usuarioDropdown');
    const menu = document.getElementById('menuUsuario');
    if (menu && !dropdown.contains(event.target)) {
        menu.style.display = 'none';
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const textarea = document.getElementById('respuestaUsuario');
    textarea.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey && !textarea.disabled) {
            e.preventDefault();

            // Enviar tanto el texto como el archivo (usa el mismo flujo)
            enviarRespuestaUsuario();
        }
    });

    // Permite enviar también si hay archivo adjunto pero el textarea está vacío
    const archivoInput = document.getElementById('archivoAdjuntoChat');
    archivoInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !textarea.disabled) {
            e.preventDefault();
            enviarRespuestaUsuario();
        }
    });
});
</script>
<script>
function actualizarDatosUsuario() {
    const select = document.getElementById('nombreAdmin');
    const rut = select.selectedOptions[0].getAttribute('data-rut');
    const correo = select.selectedOptions[0].getAttribute('data-correo');
    const telefono = select.selectedOptions[0].getAttribute('data-telefono');

    document.getElementById('rut').value = rut;
    document.getElementById('correo').value = correo;
    document.getElementById('telefono').value = telefono;
}
</script>


<script>
let tiempoInactivo = 300000; // 5 minutos en milisegundos

let temporizador = setTimeout(expirarSesion, tiempoInactivo);

// Reinicia el contador si el usuario mueve el mouse o presiona teclas
['mousemove', 'keydown', 'click'].forEach(evento => {
    document.addEventListener(evento, () => {
        clearTimeout(temporizador);
        temporizador = setTimeout(expirarSesion, tiempoInactivo);
    });
});

function expirarSesion() {
    window.location.href = "index.php"; // o window.close(); si se permite
}
</script>

<script>
function validarArchivo(input) {
  const archivo = input.files[0];
  if (!archivo) return;

  const maxMB = 5;
  const extensionesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
  const extension = archivo.name.split('.').pop().toLowerCase();

  if (!extensionesPermitidas.includes(extension)) {
    alert("Formato no permitido. Solo se aceptan imágenes, PDF y documentos Word.");
    input.value = ""; // limpia el campo
    return;
  }

  if (archivo.size > maxMB * 1024 * 1024) {
    alert("El archivo supera el tamaño máximo de 5MB.");
    input.value = ""; // limpia el campo
    return;
  }
}
</script>
<script>
function validarArchivo(input) {
  const archivo = input.files[0];
  if (!archivo) return;

  const maxSize = 5 * 1024 * 1024; // 5MB
  const tiposPermitidos = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'image/jpeg', 'image/png', 'image/gif', 'image/webp'
  ];

  if (!tiposPermitidos.includes(archivo.type)) {
    alert('Formato no permitido. Solo se aceptan PDF, Word o imágenes.');
    input.value = '';
    return;
  }

  if (archivo.size > maxSize) {
    alert('El archivo excede el tamaño máximo de 5MB.');
    input.value = '';
  }
}
</script>

<script>
  const chatModal = document.getElementById('chatModal');

  chatModal.addEventListener('hidden.bs.modal', function () {
    document.body.classList.remove('modal-open');
    document.body.style.overflow = 'auto'; // restaurar scroll en página principal
    const backdrop = document.querySelector('.modal-backdrop');
    if (backdrop) backdrop.remove(); // eliminar fondo oscuro si no se remueve solo
  });
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("formTicket");
    const boton = document.getElementById("btnCrearTicket");

    if (form && boton) {
        form.addEventListener("submit", function () {
            boton.disabled = true;
            boton.innerText = "Enviando...";

            // Rehabilita el botón después de 5 segundos
            setTimeout(() => {
                boton.disabled = false;
                boton.innerText = "Crear";
            }, 5000);
        });
    }
});
</script>

</body>
</html>