<?php
/**
 * procesar_ticket_sg.php – SISTEMA SERVICIOS GENERALES
 * Inserta ticket en servicios_generales, genera número correlativo
 * y envía notificaciones usando config_notificaciones_sg.
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

error_reporting(E_ALL);
ini_set('display_errors', '1');

// ▶ Conexión a base principal (solo para sesiones)
require_once 'conexion.php';

// ▶ Conexión exclusiva SG
require_once 'db_sg.php';

// ▶ Helper para SLA
require_once 'helpers_sla.php';

// =========================
// VALIDACIÓN DE POST
// =========================

$area_id      = 2; // fijo SG
$categoria_id = isset($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;

$nombre   = trim($_POST['nombre']   ?? '');
$rut      = trim($_POST['rut']      ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$correo   = trim($_POST['correo']   ?? '');
$tipo     = trim($_POST['tipo']     ?? '');
$empresa  = trim($_POST['empresa']  ?? '');
$detalle  = trim($_POST['detalle']  ?? '');

if ($nombre === '' || $rut === '' || $telefono === '' || $correo === '' ||
    $tipo === '' || $empresa === '' || $detalle === '') {

    die('Error: Todos los campos obligatorios deben completarse.');
}

// =========================
// FECHAS
// =========================

$fecha_creacion    = date('Y-m-d H:i:s');
$fecha_vencimiento = sg_calcular_vencimiento(new DateTime())->format('Y-m-d H:i:s');

// =========================
// ARCHIVO
// =========================

function limpiarNombreArchivoSG(string $original): string {
    $nombre = pathinfo($original, PATHINFO_FILENAME);
    $ext    = pathinfo($original, PATHINFO_EXTENSION);
    $nombre = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nombre);

    return 'uploads/' . time() . '_' . $nombre . '.' . $ext;
}

$archivo1 = null;

if (!empty($_FILES['archivo']['name']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {

    $nombre_archivo  = limpiarNombreArchivoSG($_FILES['archivo']['name']);
    $ruta_fisica     = __DIR__ . '/' . $nombre_archivo;
    $carpeta         = dirname($ruta_fisica);

    if (!file_exists($carpeta)) mkdir($carpeta, 0777, true);

    if (move_uploaded_file($_FILES['archivo']['tmp_name'], $ruta_fisica)) {
        $archivo1 = $nombre_archivo;
    } else {
        die('Error al subir el archivo.');
    }
}

// =========================
// INSERTAR EN SG
// =========================

$sql = "INSERT INTO tickets_servicios (
        nombre, rut, telefono, correo, tipo, empresa, detalle,
        archivo1, archivo2, archivo3, estado_ticket,
        fecha_creacion, area_id, categoria_id, fecha_vencimiento, numero_ticket
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?, NULL)";

$stmt = $mysqli_sg->prepare($sql);

if (!$stmt) die("Error SQL SG: " . $mysqli_sg->error);

$estado_inicial = "Ingresado";

$stmt->bind_param(
    "ssssssssssiis",
    $nombre,
    $rut,
    $telefono,
    $correo,
    $tipo,
    $empresa,
    $detalle,
    $archivo1,
    $estado_inicial,
    $fecha_creacion,
    $area_id,
    $categoria_id,
    $fecha_vencimiento
);

if (!$stmt->execute()) {
    $stmt->close();
    die("Error al insertar ticket SG: " . $stmt->error);
}

$id_ticket = $stmt->insert_id;
$stmt->close();

// =========================
// GENERAR NÚMERO SG
// =========================

$prefijos = [
    'Incidencia' => 'INC',
    'Reclamo'    => 'REC',
    'Solicitud'  => 'SOL'
];

$prefijo = $prefijos[$tipo] ?? 'TCK';

$numero = 0;
$stmtNum = $mysqli_sg->prepare("SELECT id FROM tickets_servicios WHERE tipo = ? ORDER BY id ASC");
$stmtNum->bind_param("s", $tipo);
$stmtNum->execute();
$resNum = $stmtNum->get_result();

while ($row = $resNum->fetch_assoc()) {
    $numero++;
    if ($row['id'] == $id_ticket) break;
}
$stmtNum->close();

$numero_ticket = $prefijo . str_pad((string)$numero, 8, '0', STR_PAD_LEFT);


// Actualizar número
$stmtUp = $mysqli_sg->prepare("UPDATE tickets_servicios SET numero_ticket = ? WHERE id = ?");
$stmtUp->bind_param("si", $numero_ticket, $id_ticket);
$stmtUp->execute();
$stmtUp->close();

// =========================
// CORREOS SG
// =========================

// Correos desde SG, NO desde TI
$emails_csv = "";
$stmtCfg = $mysqli_sg->prepare("SELECT emails_csv FROM config_notificaciones_sg WHERE activo = 1 LIMIT 1");
$stmtCfg->execute();
$resCfg = $stmtCfg->get_result()->fetch_assoc();
$stmtCfg->close();

if ($resCfg && !empty($resCfg['emails_csv'])) {
    $emails_csv = trim($resCfg['emails_csv']);
}

// Recuperar nombre categoría desde SG
$categoria_nombre = "";
if ($categoria_id) {
    $stmtCat = $mysqli_sg->prepare("SELECT nombre FROM categorias_sg WHERE id = ? LIMIT 1");
    $stmtCat->bind_param("i", $categoria_id);
    $stmtCat->execute();
    $rowCat = $stmtCat->get_result()->fetch_assoc();
    $stmtCat->close();

    if ($rowCat) $categoria_nombre = $rowCat['nombre'];
}

// =========================
// EMAIL AL USUARIO
// =========================

$bodyUsuario  = '<h2>Su ticket ha sido registrado correctamente</h2>';
$bodyUsuario .= "<p><strong>Nº Ticket:</strong> $numero_ticket</p>";
$bodyUsuario .= "<p><strong>Nombre:</strong> " . htmlspecialchars($nombre) . "</p>";
$bodyUsuario .= "<p><strong>RUT:</strong> " . htmlspecialchars($rut) . "</p>";
$bodyUsuario .= "<p><strong>Teléfono:</strong> " . htmlspecialchars($telefono) . "</p>";
$bodyUsuario .= "<p><strong>Correo:</strong> " . htmlspecialchars($correo) . "</p>";
$bodyUsuario .= "<p><strong>Tipo:</strong> $tipo</p>";
$bodyUsuario .= "<p><strong>Empresa:</strong> $empresa</p>";
if ($categoria_nombre) {
    $bodyUsuario .= "<p><strong>Categoría:</strong> $categoria_nombre</p>";
}
$bodyUsuario .= "<p><strong>Detalle:</strong><br>" . nl2br(htmlspecialchars($detalle)) . "</p>";

// PHPMailer
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

try {
    $mail = new PHPMailer(true);
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

    $mail->isHTML(true);
    $mail->Subject = "Creación de ticket SG: $numero_ticket";
    $mail->Body    = $bodyUsuario;

    $mail->send();

} catch (Exception $e) {
    error_log("Error correo usuario SG: " . $e->getMessage());
}

// =========================
// EMAIL NOTIFICACIONES SG
// =========================

if ($emails_csv !== "") {

    $emails = array_filter(array_map('trim', explode(',', $emails_csv)));

    if (!empty($emails)) {

        $body = "Se ha creado un ticket para Servicios Generales.\n\n";
        $body .= "Ticket: $numero_ticket\n";
        if ($categoria_nombre) $body .= "Categoría: $categoria_nombre\n";
        $body .= "Tipo: $tipo\n";
        $body .= "Empresa: $empresa\n";
        $body .= "Creado: $fecha_creacion\n";
        $body .= "Vence: $fecha_vencimiento\n\n";
        $body .= "Solicitante: $nombre\n";
        $body .= "Correo: $correo\n";
        $body .= "Teléfono: $telefono\n\n";
        $body .= "Detalle:\n$detalle\n";

        try {
            $mail2 = new PHPMailer(true);
            $mail2->isSMTP();
            $mail2->Host       = 'smtp.gmail.com';
            $mail2->SMTPAuth   = true;
            $mail2->Username   = 'jlara@pharpack.cl';
            $mail2->Password   = 'hlbi rhkf obtm yrch';
            $mail2->SMTPSecure = 'tls';
            $mail2->Port       = 587;
            $mail2->CharSet    = 'UTF-8';

            $mail2->setFrom('jlara@pharpack.cl', 'Servicios Generales');
            foreach ($emails as $e) {
                $mail2->addAddress($e);
            }

            $mail2->isHTML(false);
            $mail2->Subject = "[SG] Nuevo ticket $numero_ticket";
            $mail2->Body    = $body;

            $mail2->send();

        } catch (Exception $ex) {
            error_log("Error correo notificación SG: " . $ex->getMessage());
        }
    }
}

// =========================
// FIN
// =========================

echo "<script>alert('Ticket enviado correctamente.'); window.location.href='formulario2.php';</script>";
exit;
