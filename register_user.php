<?php
include("conexion.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_completo = $_POST['nombre_completo'];
    $rut = $_POST['rut'];
    $correo_electronico = $_POST['correo_electronico'];
    $telefono = $_POST['telefono'];
    $clave = $_POST['clave'];

    // Verificar si el RUT ya existe
    $check_query = "SELECT * FROM usuarios WHERE rut = ?";
    $stmt = $conexion->prepare($check_query);
    if ($stmt === false) {
        die("Error al preparar la consulta de verificación: " . $conexion->error);
    }

    $stmt->bind_param("s", $rut);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<script>alert('El RUT ya está registrado.'); window.location.href = 'admin.php';</script>";
    } else {
        // Registrar como usuario normal (no admin) con nivel 3
        $query = "INSERT INTO usuarios (nombre_completo, rut, correo_electronico, telefono, clave, es_admin, nivel_admin) 
                  VALUES (?, ?, ?, ?, ?, 0, 3)";
        $stmt = $conexion->prepare($query);

        if ($stmt === false) {
            die("Error al preparar la consulta de inserción: " . $conexion->error);
        }

        $stmt->bind_param("sssss", $nombre_completo, $rut, $correo_electronico, $telefono, $clave);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo "<script>alert('Usuario registrado con éxito.'); window.location.href = 'admin.php';</script>";
        } else {
            echo "<script>alert('Error al registrar el usuario.'); window.location.href = 'admin.php';</script>";
        }
    }
}
?>
