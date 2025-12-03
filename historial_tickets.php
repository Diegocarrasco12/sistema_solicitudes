<?php
session_start();
include("conexion.php");

if (!isset($_SESSION['user'])) {
    header('Location: login.html'); 
    exit();
}

$username = $_SESSION['user'];

$query = "SELECT * FROM tickets WHERE usuario = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

echo "<h1>Historial de Tickets</h1>";
while ($row = $result->fetch_assoc()) {
    echo "<div>";
    echo "<h3>" . $row['titulo'] . "</h3>";
    echo "<p>" . $row['detalle'] . "</p>";
    echo "<p><strong>Fecha:</strong> " . $row['fecha'] . "</p>";
    echo "</div><hr>";
}
?>
