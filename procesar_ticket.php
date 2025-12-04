<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

error_reporting(E_ALL);
ini_set('display_errors', 1);

include("conexion.php");
// Zona horaria correcta
date_default_timezone_set('America/Santiago');

// Asegurar zona horaria también en MySQL
$conexion->query("SET time_zone = '-03:00'");



// ====== NUEVO: área y categoría ======
$area_id      = isset($_POST['area_id']) ? (int)$_POST['area_id'] : 1; // 1 = TI, 2 = Servicios Generales
$categoria_id = (isset($_POST['categoria_id']) && $_POST['categoria_id'] !== '') ? (int)$_POST['categoria_id'] : null;

// ====== Datos existentes ======
$nombre   = isset($_POST['nombre']) ? trim($_POST['nombre']) : "";
$telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : "";
$correo   = isset($_POST['correo']) ? trim($_POST['correo']) : "";
$rut      = isset($_POST['rut']) ? trim($_POST['rut']) : "";
$tipo     = isset($_POST['tipo']) ? trim($_POST['tipo']) : "";
$empresa  = isset($_POST['empresa']) ? trim($_POST['empresa']) : "";
$detalle  = isset($_POST['detalle']) ? trim($_POST['detalle']) : "";
$fecha_creacion = date("Y-m-d H:i:s");

if (empty($nombre) || empty($telefono) || empty($correo) || empty($rut) || empty($tipo) || empty($empresa) || empty($detalle)) {
    die("Error: Algunos campos del formulario están vacíos.");
}

function limpiarNombreArchivo($nombreOriginal) {
    $nombreSinExt = pathinfo($nombreOriginal, PATHINFO_FILENAME);
    $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
    $nombreLimpio = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nombreSinExt);
    return "uploads/" . time() . "_" . $nombreLimpio . "." . $extension;
}

$archivo = "";
if (isset($_FILES["archivo"]) && $_FILES["archivo"]["error"] == 0) {
    $nombre_archivo = limpiarNombreArchivo($_FILES["archivo"]["name"]);
    $ruta_fisica = __DIR__ . "/" . $nombre_archivo;

    $carpeta = dirname($ruta_fisica);
    if (!file_exists($carpeta)) {
        mkdir($carpeta, 0777, true);
    }

    if (move_uploaded_file($_FILES["archivo"]["tmp_name"], $ruta_fisica)) {
        $archivo = $nombre_archivo;
    } else {
        die("Error al subir el archivo.");
    }
}

// ====== NUEVO: calcular fecha de vencimiento para SG ======
require_once 'helpers_sla.php';
$fecha_vencimiento = ($area_id === 2)
  ? sg_calcular_vencimiento(new DateTime('now'))->format('Y-m-d H:i:s')
  : null;

// ====== INSERT (ahora con area_id, categoria_id, fecha_vencimiento) ======
$sql = "INSERT INTO tickets
  (numero_ticket, nombre, rut, telefono, correo, tipo, empresa, detalle, archivo, fecha_creacion,
   area_id, categoria_id, fecha_vencimiento)
VALUES
  (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conexion->prepare($sql);
if (!$stmt) {
    die("Error al preparar el INSERT: " . $conexion->error);
}

$numero_ticket_vacio = ''; // luego lo actualizas como ya haces
$stmt->bind_param(
    'ssssssssssiis',
    $numero_ticket_vacio, // 1  string
    $nombre,              // 2  string
    $rut,                 // 3  string
    $telefono,            // 4  string
    $correo,              // 5  string
    $tipo,                // 6  string
    $empresa,             // 7  string
    $detalle,             // 8  string
    $archivo,             // 9  string
    $fecha_creacion,      // 10 string
    $area_id,             // 11 int
    $categoria_id,        // 12 int (puede ser null)
    $fecha_vencimiento    // 13 string (puede ser null)
);

if ($stmt->execute()) {
    $id_ticket = $conexion->insert_id;

    // ====== Numeración igual que hoy ======
    $stmtNum = $conexion->prepare("SELECT id FROM tickets WHERE tipo = ? ORDER BY id ASC");
    $stmtNum->bind_param("s", $tipo);
    $stmtNum->execute();
    $result = $stmtNum->get_result();

    $numero = 0;
    while ($row = $result->fetch_assoc()) {
        $numero++;
        if ($row['id'] == $id_ticket) break;
    }
    $stmtNum->close();

    $prefijos = ["Incidencia" => "INC", "Reclamo" => "REC", "Solicitud" => "SOL"];
    $prefijo = isset($prefijos[$tipo]) ? $prefijos[$tipo] : "TCK";
    $numero_ticket = $prefijo . str_pad($numero, 8, "0", STR_PAD_LEFT);

    $conexion->query("UPDATE tickets SET numero_ticket = '$numero_ticket' WHERE id = $id_ticket");

    // ====== NUEVO: notificar a Servicios Generales si aplica ======
    if ($area_id === 2) {
        require_once 'notify_sg.php';
        notificar_nuevo_ticket_sg($id_ticket, $conexion);
    }

    // ====== Correos existentes (igual que hoy) ======
    require 'PHPMailer/PHPMailer.php';
    require 'PHPMailer/SMTP.php';
    require 'PHPMailer/Exception.php';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'jlara@pharpack.cl';
        $mail->Password = 'hlbi rhkf obtm yrch';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('jlara@pharpack.cl', 'Soporte TI');
        $mail->addAddress($correo, $nombre);

        $mail->isHTML(true);
        $mail->Subject = "Creacion de Ticket: $numero_ticket";
        $mail->Body = "
            <h2>Tu ticket ha sido registrado correctamente</h2>
            <p><strong>Nº Ticket:</strong> $numero_ticket</p>
            <p><strong>Nombre:</strong> $nombre</p>
            <p><strong>Teléfono:</strong> $telefono</p>
            <p><strong>Correo:</strong> $correo</p>
            <p><strong>Tipo:</strong> $tipo</p>
            <p><strong>Empresa:</strong> $empresa</p>
            <p><strong>Detalle:</strong> $detalle</p>
            <p><em>Gracias por contactarnos. Pronto nos comunicaremos contigo.<br>Atte. Soporte TI</em></p>
        ";
        $mail->send();
    } catch (Exception $e) {
        error_log("Error al enviar correo al usuario: {$mail->ErrorInfo}");
    }

    // Enviar copia al administrador (igual que hoy)
    try {
        $mailAdmin = new PHPMailer(true);
        $mailAdmin->isSMTP();
        $mailAdmin->Host = 'smtp.gmail.com';
        $mailAdmin->SMTPAuth = true;
        $mailAdmin->Username = 'jlara@pharpack.cl';
        $mailAdmin->Password = 'hlbi rhkf obtm yrch';
        $mailAdmin->SMTPSecure = 'tls';
        $mailAdmin->Port = 587;

        $mailAdmin->setFrom('jlara@pharpack.cl', 'Soporte TI');
        $mailAdmin->addAddress('soportefaret@faret.cl', 'Soporte Faret');

        $mailAdmin->isHTML(true);
        $mailAdmin->Subject = "Nuevo ticket generado: $numero_ticket";
        $mailAdmin->Body = "
            <h3>Se ha registrado un nuevo ticket</h3>
            <p><strong>Nº Ticket:</strong> $numero_ticket</p>
            <p><strong>Nombre:</strong> $nombre</p>
            <p><strong>RUT:</strong> $rut</p>
            <p><strong>Teléfono:</strong> $telefono</p>
            <p><strong>Correo:</strong> $correo</p>
            <p><strong>Tipo:</strong> $tipo</p>
            <p><strong>Empresa:</strong> $empresa</p>
            <p><strong>Detalle:</strong> $detalle</p>" .
            (!empty($archivo) ? "<p><strong>Archivo adjunto:</strong> $archivo</p>" : "") . "
        ";
        $mailAdmin->send();
    } catch (Exception $e) {
        error_log("Error al enviar correo al administrador: {$mailAdmin->ErrorInfo}");
    }

    echo "<script>alert('Ticket enviado correctamente.'); window.location.href='formulario.php';</script>";
} else {
    die("Error al guardar el ticket: " . $conexion->error);
}
