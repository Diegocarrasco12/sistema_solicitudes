<?php
/**
 * db_sg.php
 *
 * Conexión dedicada a la base de datos "servicios_generales". Este archivo
 * crea una instancia de mysqli almacenada en la variable $mysqli_sg para
 * interactuar con las tablas relacionadas con los tickets de Servicios
 * Generales. Separar esta conexión de la existente en conexion.php evita
 * interferir con la ticketera de TI, que continúa utilizando "tickets_db".
 *
 * Si en algún momento cambian las credenciales o el nombre de la base de
 * datos de Servicios Generales, actualiza las variables de configuración
 * correspondientes.
 */

/* =======================================================
   🔵 CONEXIÓN REAL — PRODUCCIÓN (COMENTADA)
   =======================================================*/

//$servidor    = '127.0.0.1';
//$usuario_db  = 'tickera';
//$clave_db    = 'admin123';
//$base_datos  = 'servicios_generales';

/*======================================================= */


/* =======================================================
   🟢 CONEXIÓN LOCAL — XAMPP (ACTIVA)
   ======================================================= */

$servidor    = 'localhost';
$usuario_db  = 'root';   // XAMPP: root sin contraseña
$clave_db    = '';
$base_datos  = 'servicios_generales';

/* ======================================================= */

// Crear conexión usando mysqli orientado a objetos
$mysqli_sg = new mysqli($servidor, $usuario_db, $clave_db, $base_datos);

// Comprobar error de conexión
if ($mysqli_sg->connect_error) {
    die('Error de conexión (SG): ' . $mysqli_sg->connect_error);
}

// Forzar codificación UTF-8 para soportar acentos y caracteres especiales
$mysqli_sg->set_charset('utf8mb4');

?>
