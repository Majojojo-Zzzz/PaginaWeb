<?php
session_start();
require_once 'config/conexion.php';

// Validar si llega el ID
if (!isset($_GET['id'])) {
    header("Location: habitaciones.php");
    exit;
}

$id_habitacion = $_GET['id'];

// Obtener datos actuales de la habitación
$stmt = $pdo->prepare("SELECT * FROM habitaciones WHERE id_habitacion = ?");
$stmt->execute([$id_habitacion]);
$habitacion = $stmt->fetch();

if (!$habitacion) {
    header("Location: habitaciones.php");
    exit;
}

// Aquí puedes procesar el formulario cuando el usuario guarde los cambios (método POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo_habitacion'];
    $precio = $_POST['precio'];
    $capacidad = $_POST['cantidad_personas'];
    $estatus = $_POST['estatus'];

    $update = $pdo->prepare("UPDATE habitaciones SET tipo_habitacion = ?, precio = ?, cantidad_personas = ?, estatus = ? WHERE id_habitacion = ?");
    $update->execute([$tipo, $precio, $capacidad, $estatus, $id_habitacion]);

    header("Location: habitaciones.php");
    exit;
}
?>
<!-- Aquí iría tu HTML con el formulario para editar -->