<?php
/**
 * ------------------------------------------------------------
 *  CONEXIÓN LOCAL REAL PARA TU XAMPP Y TU DB "tickets_db"
 * ------------------------------------------------------------
 *  phpMyAdmin está usando root sin contraseña
 *  y tu servidor real es localhost
 * ------------------------------------------------------------
 */

$servidor   = "localhost";   // ← ESTE HOST FUNCA EN XAMPP
$usuario    = "root";        // ← usuario por defecto real
$clave      = "";            // ← sin contraseña
$base_datos = "tickets_db";  // ← tu base exacta

$conexion = new mysqli($servidor, $usuario, $clave, $base_datos);

if ($conexion->connect_error) {
    die("❌ Error de conexión: " . $conexion->connect_error);
}
?>
