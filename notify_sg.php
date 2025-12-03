<?php
/**
 * notify_sg.php
 *
 * Envía notificación por correo cuando se crea un ticket en Servicios Generales.
 * Usa exclusivamente la DB servicios_generales para leer la información del ticket
 * y config_notificaciones_sg para obtener los correos de notificación.
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Envía un correo a los destinatarios configurados en Servicios Generales.
 *
 * @param int    $ticket_id   ID del ticket recién creado.
 * @param mysqli $conexion    Conexión a tickets_db (solo sesiones / compatibilidad).
 * @param mysqli $mysqli_sg   Conexión a servicios_generales (tablas reales SG).
 */
function notificar_nuevo_ticket_sg(int $ticket_id, mysqli $conexion, mysqli $mysqli_sg): void
{
    // ============================
    // 1) Obtener correos desde config_notificaciones_sg
    // ============================

    $emails_csv = '';
    $stmtNotif = $mysqli_sg->prepare(
        "SELECT emails_csv 
         FROM config_notificaciones_sg 
         WHERE area_id = 2 
         LIMIT 1"
    );

    if ($stmtNotif) {
        $stmtNotif->execute();
        $row = $stmtNotif->get_result()->fetch_assoc();
        $stmtNotif->close();

        if ($row && !empty($row['emails_csv'])) {
            $emails_csv = trim($row['emails_csv']);
        }
    }

    // Si no hay destinatarios configurados, no se hace nada
    if ($emails_csv === '') {
        return;
    }

    // ============================
    // 2) Obtener datos del ticket desde tickets_servicios
    // ============================

    $stmtT = $mysqli_sg->prepare(
        "SELECT id, numero_ticket, nombre, rut, telefono, correo, tipo, empresa,
                detalle, fecha_creacion, fecha_vencimiento, categoria_id
         FROM tickets_servicios 
         WHERE id = ? LIMIT 1"
    );

    if (!$stmtT) return;

    $stmtT->bind_param("i", $ticket_id);
    $stmtT->execute();
    $ticket = $stmtT->get_result()->fetch_assoc();
    $stmtT->close();

    if (!$ticket) return;

    // ============================
    // 3) Obtener nombre de la categoría desde categorias_sg
    // ============================

    $categoria_nombre = '';

    if (!empty($ticket['categoria_id'])) {
        $cid = (int)$ticket['categoria_id'];

        $stmtCat = $mysqli_sg->prepare(
            "SELECT nombre FROM categorias_sg WHERE id = ? LIMIT 1"
        );

        if ($stmtCat) {
            $stmtCat->bind_param("i", $cid);
            $stmtCat->execute();
            $row = $stmtCat->get_result()->fetch_assoc();
            $stmtCat->close();

            if ($row) {
                $categoria_nombre = $row['nombre'];
            }
        }
    }

    // ============================
    // 4) Construcción del correo
    // ============================

    $numTicket = $ticket['numero_ticket'] ?: ('ID ' . $ticket['id']);
    $tipo      = $ticket['tipo'] ?? '';
    $empresa   = $ticket['empresa'] ?? '';

    $fecha_crea = $ticket['fecha_creacion']
        ? (new DateTime($ticket['fecha_creacion']))->format('d-m-Y H:i')
        : 'N/A';

    $fecha_vence = $ticket['fecha_vencimiento']
        ? (new DateTime($ticket['fecha_vencimiento']))->format('d-m-Y H:i')
        : 'N/A';

    // ASUNTO
    $asunto = "[SG] Nuevo ticket $numTicket";
    if ($empresa !== '') $asunto .= " - $empresa";

    // CUERPO
    $lineas = [];
    $lineas[] = "Se ha creado un ticket para Servicios Generales.";
    $lineas[] = "";
    $lineas[] = "Ticket: $numTicket";

    if ($categoria_nombre !== '') $lineas[] = "Categoría: $categoria_nombre";
    if ($tipo !== '')             $lineas[] = "Tipo: $tipo";
    if ($empresa !== '')          $lineas[] = "Empresa: $empresa";

    $lineas[] = "Creado: $fecha_crea";
    $lineas[] = "Vence: $fecha_vence";
    $lineas[] = "";

    if (!empty($ticket['nombre']))   $lineas[] = "Solicitante: " . $ticket['nombre'];
    if (!empty($ticket['correo']))   $lineas[] = "Correo solicitante: " . $ticket['correo'];
    if (!empty($ticket['telefono'])) $lineas[] = "Teléfono: " . $ticket['telefono'];
    $lineas[] = "";

    if (!empty($ticket['detalle'])) {
        $detalle = $ticket['detalle'];
        // Cortar detalle si es muy largo
        if (mb_strlen($detalle, 'UTF-8') > 600) {
            $detalle = mb_substr($detalle, 0, 600, 'UTF-8') . '…';
        }

        $lineas[] = "Detalle:";
        $lineas[] = $detalle;
        $lineas[] = "";
    }

    $lineas[] = "Ingrese al sistema para gestionarlo.";
    $cuerpo_txt = implode("\n", $lineas);

    // ============================
    // 5) Enviar correo con PHPMailer
    // ============================

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
        $mail->isHTML(false);

        foreach (explode(',', $emails_csv) as $e) {
            $e = trim($e);
            if ($e !== '') $mail->addAddress($e);
        }

        $mail->Subject = $asunto;
        $mail->Body    = $cuerpo_txt;
        $mail->AltBody = $cuerpo_txt;

        $mail->send();

    } catch (Exception $ex) {
        error_log("notify_sg.php: " . $ex->getMessage());
    }
}
