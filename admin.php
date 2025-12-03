<?php
// Reconfigurar duración de la cookie y regenerarla para extender sesión a 30 días
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

// Refrescar manualmente la cookie de sesión
setcookie(session_name(), session_id(), [
    'expires' => time() + 60 * 60 * 24 * 30,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// ⚠️ No-cache para que F5 siempre muestre datos frescos
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isset($_SESSION['rut']) || empty($_SESSION['rut'])) {
    header("Location: index.php");
    exit();
}

include("conexion.php");
$rut = $_SESSION['rut'];
$query = "SELECT nivel_admin FROM usuarios WHERE rut = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("s", $rut);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    die("Acceso denegado.");
}

$nivel_admin = (int)$row['nivel_admin'];
$stmt->close();

$es_nivel_1 = $nivel_admin === 1;
$es_nivel_2 = $nivel_admin === 2;
$es_nivel_3 = $nivel_admin === 3;

// Mostrar botón solo si es nivel 1 o 2
if (!$es_nivel_3) {
    echo '<div style="position: fixed; top: 70px; right: 20px; z-index: 1000;">
        <form method="post" action="logout.php" style="margin: 0;">
            <button 
                type="submit" 
                style="background-color: #dc3545; color: white; padding: 10px 16px; font-size: 14px; border-radius: 5px; cursor: pointer; border: none; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);">
                🔓 Cerrar Sesión
            </button>
        </form>
    </div>';
}

// Continúa con el HTML
echo "<html><head><style>";
echo "body::before { content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: url('tkt.jpg') no-repeat center center fixed; background-size: cover; filter: blur(8px); z-index: -1; }";
echo "body { font-family: Arial, sans-serif; margin: 0; padding: 0 15px; position: relative; z-index: 0; }";
echo "header { text-align: center; background-color: rgba(0,0,0,0.7); color: white; padding: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.3); margin-bottom: 20px; }";
echo "main { display: flex; flex-direction: column; gap: 30px; align-items: center; }";
echo ".categoria { background: rgba(255,255,255,0.95); border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 20px; width: 95%; max-width: 1300px; overflow-x: auto; min-height: 200px; }";
echo ".categoria h2 { border-bottom: 2px solid #007BFF; padding-bottom: 10px; margin-bottom: 15px; color: #333; font-size: 20px; }";
echo "table { width: 100%; border-collapse: collapse; min-width: 1000px; }";
echo "thead tr, tbody tr { width: 100%; table-layout: auto; }";
echo "th, td { padding: 8px; border-bottom: 1px solid #ddd; text-align: left; font-size: 12.5px; vertical-align: middle; white-space: normal; word-wrap: break-word; }";
echo "th { background-color: #007BFF; color: white; font-weight: bold; }";
echo ".gestionado-row { background: #e0e0e0 !important; }";
echo "button { background: #007BFF; color: white; border: none; padding: 6px 12px; border-radius: 5px; cursor: pointer; font-size: 14px; }";
echo "button:hover { background: #0056b3; }";
echo ".contador { display: inline-block; padding: 4px 8px; border-radius: 5px; font-weight: bold; color: white; font-size: 12px; }";
echo "select { font-size: 14px; padding: 6px; width: 100%; max-width: 180px; }";
echo ".registro-boton { position: fixed; top: 20px; right: 20px; background-color: #28a745; color: white; padding: 10px 16px; font-size: 14px; border-radius: 5px; cursor: pointer; z-index: 1000; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2); }";
echo ".registro-boton:hover { background-color: #218838; }";
echo "#popupForm { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0, 0, 0, 0.95); padding: 30px; border-radius: 10px; color: white; z-index: 2000; width: 90%; max-width: 400px; box-shadow: 0 8px 16px rgba(0,0,0,0.4); }";
echo "#popupForm input { width: 100%; padding: 10px; margin: 10px 0; font-size: 14px; border-radius: 5px; border: 1px solid #ccc; }";
echo "#popupForm button { margin-top: 10px; }";
echo "#chatPopup { display: none; position: fixed; top: 10%; left: 50%; transform: translateX(-50%); background: #ffffff; color: #000000; padding: 20px; border-radius: 10px; width: 90%; max-width: 500px; box-shadow: 0 0 15px rgba(0,0,0,0.6); z-index: 10000; }";
echo "#chatContenido { max-height: 400px; overflow-y: auto; background: #f4f4f4; padding: 10px; margin-bottom: 10px; font-size: 14px; }";
echo ".burbuja { margin: 6px 0; padding: 10px; border-radius: 10px; max-width: 90%; display: inline-block; }";
echo ".usuario { background: #d1ecf1; text-align: left; }";
echo ".admin { background: #d4edda; text-align: right; float: right; }";


echo "</style></head><body>";

echo "<header><h1>Panel de Administración de Tickets</h1></header><main>";
if ($nivel_admin !== 3) {
    echo '<div style="text-align: right; margin-bottom: 20px;">
    <a href="admin_usuarios.php" 
       style="background-color: #007BFF; color: white; padding: 10px 20px; 
              text-decoration: none; border-radius: 6px; font-size: 14px;
              box-shadow: 0 4px 8px rgba(0,0,0,0.2); transition: background 0.3s;"
       onmouseover="this.style.backgroundColor=\'#0056b3\'"
       onmouseout="this.style.backgroundColor=\'#007BFF\'">
        👥 Administrar Usuarios
    </a>
</div>';
    echo '<button onclick="openPopup()" class="registro-boton">Registrar Nuevo Usuario</button>';
}

include('conexion.php');

// Consulta de tickets
$query = "
SELECT * FROM tickets
ORDER BY 
  CASE 
    WHEN estado_ticket = 'Ingresado' AND (usuario_asignado IS NULL OR usuario_asignado = '') THEN 0
    ELSE 1
  END,
  id ASC
";
    
$result = $conexion->query($query);
if (!$result) die("Error en la consulta: " . $conexion->error);

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

function generarCodigoTicket($tipo, $id) {
    global $conexion;
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

// Organizar por tipo
$ticketsPorCategoria = ["Incidencia" => [], "Reclamo" => [], "Solicitud" => [], "Gestionado" => []];
$usuarios = ["Diego Carrasco","Josman Lara","Juan Rangel"];

while ($ticket = $result->fetch_assoc()) {
    if ($ticket["estado"] === "Gestionado") {
        $ticketsPorCategoria["Gestionado"][] = $ticket;
    } else {
        $ticketsPorCategoria[$ticket["tipo"]][] = $ticket;
    }
}

// Renderizar tablas
foreach ($ticketsPorCategoria as $categoria => $tickets) {
       if ($categoria === 'Gestionado') {
       $tickets = array_slice(array_reverse($tickets), 0, 10);
    }
echo "<div class='categoria'>";
echo "<div class='d-flex justify-content-between align-items-center mb-2 px-2'>";
echo "<h2 class='m-0' style='flex: 1;'>$categoria</h2>";
echo "</div>";

echo "<table><thead><tr>
        <th>N° Ticket</th><th>Nombre</th><th>Teléfono</th><th>Correo</th><th>Empresa</th>
        <th>Detalle</th><th>Adjunto</th><th>Tiempo</th><th>Asignar Usuario</th><th>Categoría</th><th>Estado</th><th>Acción</th>
      </tr></thead><tbody>";

if (count($tickets) === 0) {
    echo "<tr><td colspan='12' style='text-align:center; font-style: italic;'>No hay tickets en esta categoría.</td></tr>";
}

foreach ($tickets as $ticket) {
    list($horas, $min, $seg) = tiempoTranscurrido($ticket["fecha_creacion"]);
    $color = colorContador($horas);
    $estadoTicket = $ticket["estado_ticket"] ?? "Ingresado";
    $usuarioAsignado = $ticket["usuario_asignado"];
    $id = $ticket['id'];
    $tipoOriginal = $ticket['tipo'];

    $claseFila = ($categoria === 'Gestionado') ? 'gestionado-row' : '';
    // ⬇️ id de fila para live-sync (visual no-op)
    echo "<tr id='row_$id' class='$claseFila'>";
    echo "<td>" . generarCodigoTicket($ticket["tipo"], $ticket["id"]) . "</td>";
    echo "<td>{$ticket["nombre"]}</td><td>{$ticket["telefono"]}</td><td>{$ticket["correo"]}</td><td>{$ticket["empresa"]}</td>";
    echo "<td><button style='padding:4px 8px; font-size:12px;' onclick=\"mostrarChat($id)\">💬</button></td>";
    echo "<td>" . (!empty($ticket["archivo"]) ? "<a href='{$ticket["archivo"]}' target='_blank'>Ver archivo</a>" : "Sin archivo") . "</td>";

    // Tiempo transcurrido
    if ($ticket["estado"] === "Gestionado" && isset($ticket["tiempo_gestionado"])) {
        $tiempo_fijo = gmdate("H\h i\m s\s", $ticket["tiempo_gestionado"]);
        echo "<td><span class='contador' style='background:gray;' data-activo='0'>$tiempo_fijo</span></td>";
    } else {
        $fechaInicio = date("c", strtotime($ticket["fecha_creacion"]));
        echo "<td><span class='contador' style='background:$color;' data-inicio='$fechaInicio' data-activo='1'>{$horas}h {$min}m {$seg}s</span></td>";
    }

    // Asignar Usuario (sin confirmación en el select)
    if ($categoria === "Gestionado" || $nivel_admin === 3) {
        echo "<td><span>$usuarioAsignado</span></td>";
    } else {
        echo "<td>";
        // mantenemos la estructura if/else pero ambos sin confirm
        if (!empty($usuarioAsignado)) {
            echo "<select id='usuario_asignado_$id' onchange=\"asignarUsuario($id, this.value)\"><option value=''>Seleccione un usuario</option>";
        } else {
            echo "<select id='usuario_asignado_$id' onchange=\"asignarUsuario($id, this.value)\"><option value=''>Seleccione un usuario</option>";
        }

        foreach ($usuarios as $u) {
            $selected = ($u === $usuarioAsignado) ? "selected" : "";
            echo "<option value='$u' $selected>$u</option>";
        }

        echo "</select>";
        echo "</td>";
    }

    // Categoría
    if ($categoria === "Gestionado" || $nivel_admin === 3) {
        echo "<td>" . (!empty($ticket["categoria"]) ? htmlspecialchars($ticket["categoria"]) : "<em>Sin categoría</em>") . "</td>";
    } else {
        echo "<td><select id='categoria_$id' onchange=\"asignarCategoria($id, this.value)\">";
        $categorias = [
            "Camaras XVR", "ESKO", "FPS Web", "FPS Station", "FPS Desktop", "Paletizado", "Global Vision",
            "Power BI", "Proyectos", "QCS", "SAP", "Soporte TI", "SQL Server"
        ];
        echo "<option value=''>Seleccione categoría</option>";
        foreach ($categorias as $cat) {
            $selected = ($ticket["categoria"] === $cat) ? "selected" : "";
            echo "<option value='$cat' $selected>$cat</option>";
        }
        echo "</select></td>";
    }

    // Estado (sin confirm en el select, y con id para live-sync)
    if ($categoria === "Gestionado" || $nivel_admin === 3) {
        echo "<td>$estadoTicket</td>";
    } else {
        echo "<td><select id='estado_$id' onchange=\"cambiarEstadoManual($id, this.value)\">";
        foreach (['Ingresado', 'Asignado', 'En curso','Detenido','Prueba y Aceptacion'] as $estado) {
            $selected = ($estado === $estadoTicket) ? "selected" : "";
            echo "<option value=\"$estado\" $selected>$estado</option>";
        }
        echo "</select></td>";
    }

    // Botón final
    echo "<td>";
    if ($categoria === "Gestionado" && $nivel_admin !== 3) {
        echo "<button onclick=\"confirmarReabrir($id, '$tipoOriginal')\">Reabrir</button>";
    } elseif ($categoria !== "Gestionado" && $nivel_admin !== 3) {
        echo "<button onclick=\"confirmarCierre($id)\">Cerrado</button>";
    } else {
        echo "<span style='color: gray;'>Sin acciones</span>";
    }
    echo "</td>";
    echo "</tr>";
  
}
echo "</tbody></table></div>";

}

if ($categoria === 'Gestionado') {
    echo "<div style='text-align: right; margin-top: 10px;'>
            <button onclick=\"window.open('historial.php', '_blank')\"
                style=\"background: #007BFF; color: white; border: none; padding: 6px 12px; border-radius: 5px; cursor: pointer; font-size: 14px;\">
                📄 Ver historial
            </button>
          </div>";
}

// Botón flotante y popup de registro
echo '<button onclick="openPopup()" class="registro-boton">Registrar Nuevo Usuario</button>';
echo '<div id="popupForm">
    <h2>Registrar Nuevo Usuario</h2>
    <form method="POST" action="register_user.php">
        <input type="text" name="nombre_completo" placeholder="Nombre Completo" required>
        <input type="text" name="rut" placeholder="RUT" required>
        <input type="email" name="correo_electronico" placeholder="Correo Electrónico" required>
        <input type="text" name="telefono" placeholder="Teléfono" required>
        <input type="password" name="clave" placeholder="Clave de Acceso" required>
        <button type="submit">Registrar</button>
        <button type="button" onclick="closePopup()">Cancelar</button>
    </form>
</div>';
?>
<script>
// === NUEVO: funciones sin reload y sin confirm en selects (guardan un campo a la vez) ===
function asignarUsuario(id, usuario) {
    if (usuario !== "") {
        fetch('update_ticket_field.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(id) +
                  '&field=usuario_asignado' +
                  '&value=' + encodeURIComponent(usuario)
        })
        .then(r => r.json())
        .then(d => { if (!d.ok) alert('No se pudo asignar el usuario'); })
        .catch(() => alert('Error de red al asignar usuario'));
    }
}
function cambiarEstadoManual(id, estado) {
    fetch('update_ticket_field.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id) +
              '&field=estado_ticket' + // cambia a 'estado' si tu columna se llama así
              '&value=' + encodeURIComponent(estado)
    })
    .then(r => r.json())
    .then(d => { if (!d.ok) alert('No se pudo cambiar el estado'); })
    .catch(() => alert('Error de red al cambiar estado'));
}
// ✅ Global: asignar categoría sin reload
function asignarCategoria(id, categoria) {
    if (categoria !== "") {
        fetch('update_ticket_field.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(id) +
                  '&field=categoria' +
                  '&value=' + encodeURIComponent(categoria)
        })
        .then(r => r.json())
        .then(d => { if (!d.ok) alert('No se pudo actualizar la categoría'); })
        .catch(() => alert('Error de red al cambiar categoría'));
    }
}

function confirmarCierre(id) {
    if (confirm("¿Estás seguro de que deseas cerrar este ticket?")) {
        marcarComoGestionado(id);
    }
}
function confirmarReabrir(id, tipo) {
    if (confirm("¿Deseas reabrir este ticket y enviarlo al estado 'Ingresado'?")) {
        reabrirTicket(id, tipo);
    }
}
function marcarComoGestionado(id) {
    const usuario = document.getElementById('usuario_asignado_' + id).value;
    const categoria = document.getElementById("categoria_" + id)?.value;

    if (!usuario) {
        alert("Debe seleccionar un usuario.");
        return;
    }

    if (!categoria || categoria === "") {
        alert("Debe seleccionar una categoría.");
        return;
    }

    fetch('marcar_gestionado.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ticket_id=' + id + 
              '&usuario_asignado=' + encodeURIComponent(usuario) + 
              '&categoria=' + encodeURIComponent(categoria)
    }).then(response => response.text())
      .then(data => {
          console.log(data);  // Puedes eliminar esto luego si todo funciona bien
          location.reload();
      });
}

function reabrirTicket(id, tipo) {
    fetch('reabrir_ticket.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ticket_id=' + id + '&tipo=' + encodeURIComponent(tipo)
    }).then(() => location.reload());
}
function actualizarContadores() {
    const ahora = new Date();
    document.querySelectorAll('.contador').forEach(function(span) {
        if (span.dataset.activo === "0") return;

        const inicio = new Date(span.dataset.inicio);
        const diff = ahora - inicio;

        const horas = Math.floor(diff / (1000 * 60 * 60));
        const minutos = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const segundos = Math.floor((diff % (1000 * 60)) / 1000);

        let color = "green";
        if (horas >= 4) color = "red";
        else if (horas >= 2) color = "orange";

        span.innerText = `${horas}h ${minutos}m ${segundos}s`;
        span.style.background = color;
    });
}
function openPopup() {
    document.getElementById('popupForm').style.display = 'block';
}
function closePopup() {
    document.getElementById('popupForm').style.display = 'none';
}

let ticketIdActual = null;

function mostrarChat(id) {
    ticketIdActual = id;

    fetch('ver_chat_ticket.php?id=' + id)
        .then(res => res.text())
        .then(html => {
            document.getElementById('chatContenido').innerHTML = html;
            document.getElementById('chatPopup').style.display = 'block';
            document.getElementById('respuestaAdmin').value = '';
            document.getElementById('btnEnviarResp').disabled = false;

            // Evento ENTER para enviar
            const textarea = document.getElementById('respuestaAdmin');
            if (textarea) {
                textarea.onkeydown = function (event) {
                    if (event.key === 'Enter' && !event.shiftKey) {
                        event.preventDefault();
                        enviarRespuesta();
                    }
                }
            }
        });
}

function cerrarChat() {
    document.getElementById('chatPopup').style.display = 'none';
}

function enviarRespuesta() {
    const textarea = document.getElementById('respuestaAdmin');
    const mensaje = textarea.value.trim();
    const archivoInput = document.getElementById('archivoAdjuntoChat');
    const archivo = archivoInput.files[0];
    const chat = document.getElementById('chatContenido');
    const boton = document.getElementById('btnEnviarResp');

    // Previene doble envío
    if (boton.disabled) return;

    if (mensaje === '' && !archivo) {
        alert("Escribe una respuesta o adjunta un archivo.");
        return;
    }

    // Mostrar el mensaje al instante en el chat solo si hay texto
    if (mensaje !== '') {
        const nuevaBurbuja = document.createElement('div');
        nuevaBurbuja.className = "burbuja admin";
        nuevaBurbuja.innerText = mensaje;
        chat.appendChild(nuevaBurbuja);
        chat.scrollTop = chat.scrollHeight;
    }

    // Vacía los campos y desactiva el botón
    textarea.value = '';
    archivoInput.value = '';
    boton.disabled = true;
    boton.innerText = "Enviando...";

    // Envío con FormData
    const formData = new FormData();
    formData.append('id', ticketIdActual);
    formData.append('mensaje', mensaje);
    if (archivo) {
        formData.append('archivo', archivo);
    }

    fetch('responder_ticket.php', {
        method: 'POST',
        body: formData
        // IMPORTANTE: NO agregar headers aquí, el navegador lo hace automáticamente para FormData
    })
    .then(res => res.text())
    .then(response => {
        boton.disabled = false;
        boton.innerText = "Enviar Respuesta";

        // Opcional: muestra mensaje de éxito o error del backend
        if (response && response.trim().substring(0, 1) === '✔') {
            // Recarga el chat para mostrar la nueva respuesta o archivo adjunto
            mostrarChat(ticketIdActual);
        } else if (response && response.trim() !== "") {
            alert("Servidor: " + response);
        } else {
            mostrarChat(ticketIdActual);
        }
    })
    .catch(error => {
        boton.disabled = false;
        boton.innerText = "Enviar Respuesta";
        alert("Error al enviar la respuesta: " + error);
    });

    // (Se mantiene tu función anidada tal cual, sin tocarla)
    function asignarCategoria(id, categoria) {
        if (categoria !== "") {
            fetch('cambiar_categoria.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ticket_id=' + id + '&categoria=' + encodeURIComponent(categoria)
            }).then(() => location.reload());
        }
    }
}

// Iniciar contador tras carga del DOM
document.addEventListener('DOMContentLoaded', function () {
    actualizarContadores();
    setInterval(actualizarContadores, 1000);
});
</script>

<!-- POPUP DE CHAT -->
<div id="chatPopup" style="display:none; position:fixed; top:10%; left:50%; transform:translateX(-50%); background:#ffffff; color:#000; padding:20px; border-radius:15px; width:90%; max-width:500px; box-shadow:0 4px 12px rgba(0,0,0,0.3); z-index:10000;">
    <div id="chatContenido"></div>
<?php if ($nivel_admin !== 3): ?>
    <textarea id="respuestaAdmin" placeholder="Escribe tu respuesta..." style="width:100%; height:80px; margin-top:10px;"></textarea>
    <input type="file" id="archivoAdjuntoChat" style="...">
    <button id="btnEnviarResp" onclick="enviarRespuesta()" style="...">Enviar Respuesta</button>
<?php else: ?>
    <p style="margin:10px 0; color:gray;"><em>No tienes permisos para responder.</em></p>
<?php endif; ?>
<button onclick="cerrarChat()">Cerrar</button>

</div>

<!-- ESTILOS DEL POPUP -->
<style>
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
        width: 90%;
        max-width: 500px;
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
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .usuario {
        background-color: #e9ecef;
        color: #000;
        align-self: flex-start;
        text-align: left;
    }

    .admin {
        background-color: #d4edda;
        color: #000;
        align-self: flex-end;
        text-align: right;
    }

    .mensaje strong {
        display: block;
        font-weight: bold;
        margin-bottom: 4px;
    }
</style>

<!-- Live sync (2s) y overrides seguros -->
<script src="admin_live.js?v=1"></script>

</body></html>
