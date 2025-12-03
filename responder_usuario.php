<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';
include("conexion.php");

$id_ticket = isset($_POST['id']) ? intval($_POST['id']) : 0;
$mensaje = isset($_POST['mensaje']) ? trim($_POST['mensaje']) : '';

// --- NUEVO: Procesar archivo adjunto ---
$archivo_url = null;
if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === 0) {
    $dir = 'uploads_chat/';
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    $nombreArchivo = uniqid() . "_" . basename($_FILES['archivo']['name']);
    $rutaArchivo = $dir . $nombreArchivo;
    if (move_uploaded_file($_FILES['archivo']['tmp_name'], $rutaArchivo)) {
        $archivo_url = $rutaArchivo;
    }
}
// --- FIN NUEVO ---

if ($id_ticket <= 0 || (empty($mensaje) && !$archivo_url)) { // Cambia aquí para permitir mensaje vacío si hay archivo
    echo "Datos inválidos.";
    exit;
}

// --- CAMBIADO: Insertar respuesta con archivo en DB ---
$stmt = $conexion->prepare("INSERT INTO respuestas_ticket (id_ticket, remitente, mensaje, archivo) VALUES (?, 'usuario', ?, ?)");
$stmt->bind_param("iss", $id_ticket, $mensaje, $archivo_url);
$stmt->execute();

// Obtener datos del ticket para enviar correo
$stmt = $conexion->prepare("SELECT correo, nombre, numero_ticket FROM tickets WHERE id = ?");
$stmt->bind_param("i", $id_ticket);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

$correo_admin = "jlara@pharpack.cl";
$nombre_usuario = $row['nombre'];
$numero_ticket  = $row['numero_ticket'];

$mail = new PHPMailer(true);


$URL_BASE = 'http://192.168.1.70/'; // <-- Cambia esto si usas otro dominio o IP

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'jlara@pharpack.cl';
    $mail->Password = 'hlbi rhkf obtm yrch';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('jlara@pharpack.cl', 'Ticketera Usuario');
    $mail->addAddress($correo_admin);
    $mail->isHTML(true);
    $mail->Subject = "Nueva respuesta de usuario en Ticket $numero_ticket";

    $link_archivo = $archivo_url ? "<p><strong>Archivo adjunto:</strong> 
        <a href='{$URL_BASE}{$archivo_url}' target='_blank'>" . 
        htmlspecialchars(basename($archivo_url)) . "</a></p>" : "";

    $mail->Body = "
        <p>El usuario <strong>$nombre_usuario</strong> ha respondido al ticket <strong>$numero_ticket</strong>:</p>
        <blockquote>$mensaje</blockquote>
        $link_archivo
    ";

    $mail->send();
    echo "Respuesta enviada.";
} catch (Exception $e) {
    echo "Error al enviar correo: {$mail->ErrorInfo}";
}

?>
