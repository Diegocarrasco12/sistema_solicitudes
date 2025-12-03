<?php
session_start();
if (!isset($_SESSION['rut']) || empty($_SESSION['rut'])) {
    die("Acceso denegado.");
}

require "conexion.php";

/* ============================================================
   1. VALIDAR QUE EL ADMIN LOGUEADO TENGA PERMISOS
============================================================ */
$rut_admin = $_SESSION['rut'];
$query = "SELECT id, nivel_admin FROM usuarios WHERE rut = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("s", $rut_admin);
$stmt->execute();
$res_admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$res_admin) die("Acceso denegado (admin no encontrado).");

$id_admin = (int)$res_admin['id'];
$nivel_admin = (int)$res_admin['nivel_admin'];

/*
    Nivel 1 = Super Admin (puede editar todo)
    Nivel 2 = Admin intermedio (NO puede editar usuarios nivel 1)
    Nivel 3 = Usuario normal (no puede editar nadie)
*/

if ($nivel_admin > 2) {
    die("No tienes permisos para editar usuarios.");
}

/* ============================================================
   2. VALIDAR QUE SE RECIBAN LOS DATOS NECESARIOS
============================================================ */
if (
    !isset($_POST['id']) ||
    !isset($_POST['nombre_completo']) ||
    !isset($_POST['correo_electronico']) ||
    !isset($_POST['rut'])
) {
    die("Datos incompletos.");
}

$id_usuario = (int)$_POST['id'];
$nombre = trim($_POST['nombre_completo']);
$correo = trim($_POST['correo_electronico']);
$rut = trim($_POST['rut']);
$telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : "";

/* ============================================================
   3. VALIDAR QUE EL USUARIO QUE QUIERES EDITAR EXISTE
============================================================ */
$query = "SELECT nivel_admin FROM usuarios WHERE id = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$res_usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$res_usuario) {
    die("Usuario no encontrado.");
}

$nivel_objetivo = (int)$res_usuario['nivel_admin'];

/* ============================================================
   4. VALIDAR JERARQUÍA
============================================================ */
if ($nivel_admin == 2 && $nivel_objetivo == 1) {
    die("No puedes editar usuarios de nivel superior.");
}

/* ============================================================
   5. ACTUALIZAR USUARIO
============================================================ */
$update = "UPDATE usuarios SET 
            nombre_completo = ?, 
            correo_electronico = ?, 
            rut = ?, 
            telefono = ?
           WHERE id = ?";

$stmt = $conexion->prepare($update);
$stmt->bind_param("ssssi", $nombre, $correo, $rut, $telefono, $id_usuario);

if ($stmt->execute()) {
    echo "Usuario actualizado correctamente.";
} else {
    echo "Error al actualizar: " . $conexion->error;
}

$stmt->close();
$conexion->close();

?>
