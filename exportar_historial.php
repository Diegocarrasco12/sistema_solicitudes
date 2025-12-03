<?php
include 'conexion.php';

header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=historial_tickets.csv");

// UTF-8 BOM para compatibilidad con Excel
echo chr(0xEF) . chr(0xBB) . chr(0xBF);

$ticket_number = $_GET['ticket_number'] ?? '';
$texto = $_GET['texto'] ?? '';
$tipo = $_GET['tipo'] ?? '';
$usuario = $_GET['usuario'] ?? '';
$fecha = $_GET['fecha'] ?? '';

$condiciones = ["estado_ticket = 'Gestionado'"];
if ($ticket_number !== '') {
    $condiciones[] = "numero_ticket LIKE '%" . mysqli_real_escape_string($conexion, $ticket_number) . "%'";
}
if ($texto !== '') {
    $condiciones[] = "detalle LIKE '%" . mysqli_real_escape_string($conexion, $texto) . "%'";
}
if ($tipo !== '') {
    $condiciones[] = "tipo = '" . mysqli_real_escape_string($conexion, $tipo) . "'";
}
if ($usuario !== '') {
    $condiciones[] = "usuario_asignado = '" . mysqli_real_escape_string($conexion, $usuario) . "'";
}
if ($fecha !== '') {
    $condiciones[] = "DATE(fecha_creacion) = '" . mysqli_real_escape_string($conexion, $fecha) . "'";
}

$cond_sql = " WHERE " . implode(' AND ', $condiciones);
$query = "SELECT * FROM tickets $cond_sql ORDER BY fecha_creacion DESC";
$resultado = mysqli_query($conexion, $query);

// Encabezados
$campos = ['Fecha', 'Estado', 'Tipo', 'Nombre', 'Correo', 'Teléfono', 'Empresa', 'Asignado', 'Detalle'];
echo implode(';', $campos) . "\n";

// Datos
while ($row = mysqli_fetch_assoc($resultado)) {
    $linea = [
        $row['fecha_creacion'],
        $row['estado_ticket'],
        $row['tipo'],
        $row['nombre'],
        $row['correo'],
        $row['telefono'],
        $row['empresa'],
        $row['usuario_asignado'] ?? 'Sin asignar',
        str_replace(['\r', '\n', ';'], [' ', ' ', ' '], $row['detalle'])
    ];
    echo implode(';', array_map('htmlspecialchars', $linea)) . "\n";
}
?>