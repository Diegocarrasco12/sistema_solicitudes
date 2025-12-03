<?php
/**
 * get_categorias.php
 * Devuelve (en JSON) las categorías activas para un área dada.
 * Uso: GET /get_categorias.php?area_id=2
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');

require_once 'db.php'; // Debe exponer $mysqli (mysqli conectado)
if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
  http_response_code(500);
  echo json_encode(['error' => 'Conexión a base de datos no disponible']);
  exit;
}

$mysqli->set_charset('utf8mb4');

// Validar parámetro
$area_id = isset($_GET['area_id']) ? (int)$_GET['area_id'] : 0;
if ($area_id <= 0) {
  // Responder lista vacía y 400 si el parámetro no es válido
  http_response_code(400);
  echo json_encode([]);
  exit;
}

$sql = "SELECT id, nombre
        FROM categorias
        WHERE area_id = ? AND activo = 1
        ORDER BY nombre ASC";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['error' => 'No se pudo preparar la consulta']);
  exit;
}

$stmt->bind_param('i', $area_id);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
  // Asegurar tipos consistentes
  $data[] = [
    'id'     => (int)$row['id'],
    'nombre' => $row['nombre'],
  ];
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
