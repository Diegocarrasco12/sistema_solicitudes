<?php
require_once __DIR__ . '/send_wsp.php';

// === DATOS DE PRUEBA ===
$chatId  = "56983249135@c.us";               // tu propio WhatsApp (o el de un técnico)
$mensaje = "Mensaje de prueba desde servidor (DEBUG)";

// === ARMAR URL EXACTA ===
$url = GREEN_API_URL
    . '/waInstance' . GREEN_API_INSTANCE
    . '/sendMessage/' . GREEN_API_TOKEN;

$payload = [
    'chatId'  => $chatId,
    'message' => $mensaje,
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// 🔥 NUEVO: obtener salida detallada de CURL
$response = curl_exec($ch);
$err      = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

// === MOSTRAR INFORME COMPLETO ===
echo "<h2>DEBUG - Test Envío WhatsApp</h2>";

echo "<b>URL usada:</b><br>$url<br><br>";

echo "<b>Payload enviado:</b><br>";
echo "<pre>" . print_r($payload, true) . "</pre><br>";

echo "<b>HTTP CODE:</b> $httpCode<br><br>";

echo "<b>Respuesta RAW de GreenAPI:</b><br>";
echo "<pre>$response</pre><br>";

echo "<b>Error CURL (si existe):</b><br>";
echo "<pre>$err</pre><br>";

if ($err || $httpCode >= 400) {
    echo "<h3 style='color:red'>❌ FALLO EL ENVÍO</h3>";
} else {
    echo "<h3 style='color:green'>✔️ ENVÍO EXITOSO</h3>";
}
