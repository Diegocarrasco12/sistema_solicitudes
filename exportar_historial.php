<?php
include 'conexion.php';

header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=historial_tickets.csv");

// UTF-8 BOM para compatibilidad con Excel
echo chr(0xEF) . chr(0xBB) . chr(0xBF);

/************************************
 *   Cargar tramos de todos los tickets
 ************************************/
$tramos_por_ticket = [];

$qTramos = "
    SELECT ticket_id, estado_inicio, estado_fin, fecha_inicio, fecha_fin, minutos_habiles
    FROM ticket_tramos
    ORDER BY id ASC
";
$rTramos = mysqli_query($conexion, $qTramos);

while ($t = mysqli_fetch_assoc($rTramos)) {
    $id = $t['ticket_id'];
    if (!isset($tramos_por_ticket[$id])) {
        $tramos_por_ticket[$id] = [];
    }
    $tramos_por_ticket[$id][] = $t;
}

/************************************/

$ticket_number = $_GET['ticket_number'] ?? '';
$texto        = $_GET['texto'] ?? '';
$tipo         = $_GET['tipo'] ?? '';
$usuario      = $_GET['usuario'] ?? '';
$fecha        = $_GET['fecha'] ?? '';

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

// Encabezados CSV
$campos = [
    'Fecha',
    'Estado',
    'Tipo',
    'Nombre',
    'Correo',
    'Teléfono',
    'Empresa',
    'Asignado',
    'Detalle',
    'Tramos',
    'Total'
];
echo implode(';', $campos) . "\n";

// Datos
while ($row = mysqli_fetch_assoc($resultado)) {

    // Obtener tramos del ticket actual
    $idT = $row['id'];
    $tramos = $tramos_por_ticket[$idT] ?? [];

    $lista_tramos = "";
    $total = 0;

    foreach ($tramos as $t) {

        $min = (int)$t['minutos_habiles'];
        $total += $min;

        $ini = substr($t['fecha_inicio'], 11, 5);
        $fin = $t['fecha_fin'] ? substr($t['fecha_fin'], 11, 5) : '...';

        // Estados claros y limpiados
        $estadoIni = $t['estado_inicio'] ?: '-';
        $estadoFin = $t['estado_fin'] ?: '-';

        // Flecha compatible con Excel
        $lista_tramos .= "$estadoIni > $estadoFin ($ini-$fin / {$min}m) | ";
    }

    // Formato total legible
    $h = floor($total / 60);
    $m = $total % 60;
    $total_legible = "{$h}h {$m}m";

    // Crear línea del CSV
    $linea = [
        $row['fecha_creacion'],
        $row['estado_ticket'],
        $row['tipo'],
        $row['nombre'],
        $row['correo'],
        $row['telefono'],
        $row['empresa'],
        $row['usuario_asignado'] ?: 'Sin asignar',
        str_replace(["\r", "\n", ";"], [' ', ' ', ' '], $row['detalle']),
        trim($lista_tramos),
        $total_legible
    ];

    echo implode(';', array_map('htmlspecialchars', $linea)) . "\n";
}

?>
