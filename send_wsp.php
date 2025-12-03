<?php
/**
 * send_wsp.php – GreenAPI WhatsApp Sender (VERSIÓN FINAL)
 */

// Usa SIEMPRE el apiUrl exacto entregado por GreenAPI
const GREEN_API_URL       = 'https://7105.api.green-api.com';
const GREEN_API_INSTANCE  = '7105392614';
const GREEN_API_TOKEN     = 'c247d332a5564b639d341d3533e78ed6b1a4c57cf3b4437b87';

/**
 * Mapea nombre técnico → chatId de WhatsApp
 */
function obtenerChatIdTecnico(string $nombreTecnico): ?string
{
    $mapa = [
        'Diego Carrasco' => '56983249135@c.us',
        'Josman Lara'    => '56968487555@c.us',
        'Juan Rangel'    => '56935764031@c.us',
    ];

    return $mapa[$nombreTecnico] ?? null;
}

/**
 * Envía mensaje vía GreenAPI
 */
function enviarWhatsApp(string $chatId, string $mensaje): bool
{
    $url = GREEN_API_URL
        . '/waInstance' . GREEN_API_INSTANCE
        . '/sendMessage/' . GREEN_API_TOKEN;

    $payload = [
        'chatId'  => $chatId,
        'message' => $mensaje,
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // FIX SSL
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($err || $httpCode >= 400) {
        error_log("ERROR WhatsApp ($httpCode): $err | RESP: $response");
        return false;
    }

    return true;
}
