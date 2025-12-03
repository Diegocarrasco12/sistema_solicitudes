<?php
session_start();
if (!isset($_SESSION['rut']) || empty($_SESSION['rut'])) {
    http_response_code(401);
    die("Acceso denegado.");
}

include("conexion.php");

// Validar parámetro
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    http_response_code(400);
    die("ID inválido.");
}

// Nivel del usuario actual (quien intenta eliminar)
$rut_actual = $_SESSION['rut'];
$stmt = $conexion->prepare("SELECT nivel_admin FROM usuarios WHERE rut = ?");
$stmt->bind_param("s", $rut_actual);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(403);
    die("Usuario actual no encontrado.");
}
$nivel_actual = (int)$row['nivel_admin'];

// Nivel del usuario objetivo (a eliminar)
$stmt = $conexion->prepare("SELECT nivel_admin FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$objetivo = $res->fetch_assoc();
$stmt->close();

if (!$objetivo) {
    http_response_code(404);
    die("Usuario a eliminar no existe.");
}
$nivel_objetivo = (int)$objetivo['nivel_admin'];

// Reglas: nivel 1 puede eliminar a cualquiera.
// nivel 2 solo puede eliminar a nivel 2 o 3 (nunca a nivel 1).
$permitido = ($nivel_actual === 1) || ($nivel_actual === 2 && $nivel_objetivo >= 2);
if (!$permitido) {
    http_response_code(403);
    die("No permitido.");
}

// Ejecutar DELETE real
$stmt = $conexion->prepare("DELETE FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo "Usuario eliminado correctamente.";
} else {
    http_response_code(500);
    echo "Error al eliminar: " . $stmt->error;
}
$stmt->close();
