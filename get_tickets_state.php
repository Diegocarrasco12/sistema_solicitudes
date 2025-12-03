<?php
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json');

// Trae solo lo necesario para sincronizar UI
// Cambia 'estado_ticket' por 'estado' si tu columna tiene ese nombre.
$sql = "SELECT id, usuario_asignado, categoria, estado_ticket FROM tickets";
$res = $conexion->query($sql);

$tickets = [];
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $id = (int)$row['id'];
    $tickets[$id] = [
      'usuario_asignado' => (string)($row['usuario_asignado'] ?? ''),
      'categoria'        => (string)($row['categoria'] ?? ''),
      'estado_ticket'    => (string)($row['estado_ticket'] ?? ''), // ← usa 'estado' si corresponde
    ];
  }
}

echo json_encode(['ok' => true, 'tickets' => $tickets], JSON_UNESCAPED_UNICODE);
