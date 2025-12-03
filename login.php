<?php
session_start();
include("conexion.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rut = $_POST['username'];      // RUT ingresado
    $clave = $_POST['password'];    // Clave ingresada
    $area = $_POST['area'];         // Área seleccionada

    // Verificar conexión a la BD
    if ($conexion->connect_error) {
        die("Error de conexión: " . $conexion->connect_error);
    }

    // Buscar usuario solo por RUT
    $query = "SELECT * FROM usuarios WHERE rut = ?";
    if ($stmt = $conexion->prepare($query)) {
        $stmt->bind_param("s", $rut);
        $stmt->execute();
        $result = $stmt->get_result();

        // Si existe el usuario
        if ($result->num_rows > 0) {
            $usuario = $result->fetch_assoc();

            // Comparar clave directamente (sin hash)
            if ($usuario['clave'] === $clave) {

// Regenerar el ID de sesión por seguridad
session_regenerate_id(true);

// Guardar datos en sesión
// Guardar datos en sesión
$_SESSION['user']        = $rut;
$_SESSION['rut']         = $usuario['rut'];
$_SESSION['correo']      = $usuario['correo_electronico'];
$_SESSION['telefono']    = $usuario['telefono'];
$_SESSION['nombre']      = $usuario['nombre_completo'];
$_SESSION['area']        = $area;
$_SESSION['es_admin']    = $usuario['es_admin'];        // NUEVO: acceso general de administrador
$_SESSION['nivel_admin'] = $usuario['nivel_admin'] ?? 3; // NUEVO: nivel 1, 2 o 3; por defecto 3


                // Redirigir si debe cambiar clave
                if ($usuario['cambio_clave'] == 0) {
                    header('Location: cambiar_clave.php');
                    exit();
                }

                // Redirigir según el área
                if ($area === 'Soporte Informático') {
                    header('Location: formulario.php');
                } elseif ($area === 'Servicios Generales') {
                    header('Location: formulario2.php');
                } else {
                    header('Location: index.php');
                }
                exit();

            } else {
                // Clave incorrecta
                echo '<script>alert("Contraseña incorrecta."); window.location.href = "index.php";</script>';
                exit();
            }

        } else {
            // Usuario no encontrado
            echo '<script>alert("Usuario no encontrado."); window.location.href = "index.php";</script>';
            exit();
        }
    } else {
        echo "Error al preparar la consulta: " . $conexion->error;
    }
}
?>
