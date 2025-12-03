<?php
require_once 'db_sg.php';   // DB real de Servicios Generales

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=historial_sg.xls");
header("Pragma: no-cache");
header("Expires: 0");

// SOLO tickets cerrados y SOLO de Servicios Generales
$sql = "
  SELECT 
    t.numero_ticket,
    t.tipo,
    c.nombre AS categoria,
    tec.nombre AS tecnico,
    t.estado_ticket,
    t.fecha_creacion,
    t.fecha_cierre,
    t.nombre,
    t.correo,
    t.telefono,
    t.empresa,
    t.detalle
  FROM tickets_servicios t
  LEFT JOIN categorias_sg c ON c.id = t.categoria_id
  LEFT JOIN tecnicos_sg tec ON tec.id = t.tecnico_id
  WHERE t.estado_ticket = 'Cerrado'
  ORDER BY t.id DESC
";

$res = $mysqli_sg->query($sql);

echo "<table border='1'>";
echo "
<tr>
<th>N° Ticket</th>
<th>Tipo</th>
<th>Categoría</th>
<th>Técnico</th>
<th>Estado</th>
<th>Fecha creación</th>
<th>Fecha cierre</th>
<th>Nombre</th>
<th>Correo</th>
<th>Teléfono</th>
<th>Empresa</th>
<th>Detalle</th>
</tr>
";

while ($row = $res->fetch_assoc()) {
    echo "<tr>";
    foreach ($row as $v) {
        echo "<td>".utf8_decode($v)."</td>";
    }
    echo "</tr>";
}

echo "</table>";
