<?php
include("conexion.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = $_POST["id"] ?? null;
    $nivel = $_POST["nivel"] ?? null;

    if (!$id || !$nivel) {
        echo "Parámetros incompletos.";
        exit;
    }

    $stmt = $conexion->prepare("UPDATE usuarios SET nivel_admin = ? WHERE id = ?");
    if ($stmt === false) {
        echo "Error en la preparación: " . $conexion->error;
        exit;
    }

    $stmt->bind_param("ii", $nivel, $id);

    if ($stmt->execute()) {
        echo "✔ Nivel de administrador actualizado.";
    } else {
        echo "Error al actualizar: " . $stmt->error;
    }

    $stmt->close();
} else {
    echo "Método no permitido.";
}
?>
