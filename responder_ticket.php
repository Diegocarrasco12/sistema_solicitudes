<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';
include("conexion.php");

$id_ticket = isset($_POST['id']) ? intval($_POST['id']) : 0;
$mensaje = isset($_POST['mensaje']) ? trim($_POST['mensaje']) : '';

// Procesar archivo adjunto si existe
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

// Permitir mensaje vacío solo si hay archivo
if ($id_ticket <= 0 || (empty($mensaje) && !$archivo_url)) {
    echo "Datos inválidos.";
    exit;
}

// Insertar respuesta en DB (ahora con archivo)
$stmt = $conexion->prepare("INSERT INTO respuestas_ticket (id_ticket, remitente, mensaje, archivo) VALUES (?, 'admin', ?, ?)");
$stmt->bind_param("iss", $id_ticket, $mensaje, $archivo_url);
$stmt->execute();

// Obtener correo del usuario
$stmt = $conexion->prepare("SELECT correo, nombre, numero_ticket FROM tickets WHERE id = ?");
$stmt->bind_param("i", $id_ticket);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

$correo_usuario = $row['correo'] ?? '';
$nombre_usuario = $row['nombre'] ?? '';
$numero_ticket  = $row['numero_ticket'] ?? '(Sin número)';

if (empty($correo_usuario) || empty($nombre_usuario)) {
    echo "No se encontró información del ticket.";
    exit;
}

// Enviar correo
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'jlara@pharpack.cl'; // Tu correo Gmail
    $mail->Password = 'hlbi rhkf obtm yrch'; // Clave de aplicación
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('jlara@pharpack.cl', 'Soporte TI');
    $mail->addAddress($correo_usuario, $nombre_usuario);
    $mail->isHTML(true);
    $mail->Subject = "Respuesta a tu Ticket $numero_ticket";

    // Construir bloque de adjunto si existe
    $bloque_adjunto = "";
    if ($archivo_url) {
        // Cambia 'https://TUDOMINIO/' por la URL pública real de tu servidor
        $link_archivo = "https://TUDOMINIO/$archivo_url";
        $filename = htmlspecialchars(basename($archivo_url));
        $bloque_adjunto = "<p><strong>Archivo adjunto:</strong> <a href='$link_archivo' target='_blank'>📎 $filename</a></p>";
    }

    $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 500px; margin:0 auto;'>
            <p>Hola <strong>$nombre_usuario</strong>,</p>
            <p>Hemos respondido a tu ticket <strong>$numero_ticket</strong>:</p>
            <blockquote style='background: #f4f4f4; border-left: 4px solid #007BFF; padding: 8px 16px; margin: 14px 0; font-size: 16px;'>"
            . (!empty($mensaje) ? $mensaje : '<i>(Sin mensaje de texto)</i>') .
            "</blockquote>
            $bloque_adjunto
            <p>Gracias por tu consulta.<br><b>Soporte TI</b></p>
            <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0 10px 0;'/>
            <div style='font-size:13px;color:#999;text-align:center;'>Este es un mensaje automático del sistema de tickets TI Faret.</div>
        </div>
    ";
    $mail->send();
    echo "✔️ Respuesta enviada correctamente.";
} catch (Exception $e) {
    echo "❌ Error al enviar correo: {$mail->ErrorInfo}";
}
?>
