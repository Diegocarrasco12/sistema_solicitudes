<?php
if (session_status() === PHP_SESSION_NONE) {
    // Tiempo según perfil (admin vs usuario normal)
    $lifetime = 60 * 5; // 5 minutos por defecto

    if (isset($_SESSION['rut'])) {
        include_once("conexion.php");
        $rut = $_SESSION['rut'];
        $stmt = $conexion->prepare("SELECT es_admin FROM usuarios WHERE rut = ?");
        $stmt->bind_param("s", $rut);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        if ($row && $row['es_admin'] == 1) {
            $lifetime = 60 * 60 * 24 * 30; // 30 días para admins
        }
    }

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();

    // Refrescar cookie activamente
    setcookie(session_name(), session_id(), [
        'expires' => time() + $lifetime,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
} else {
    session_start(); // por si acaso ya está activa
}
?>
