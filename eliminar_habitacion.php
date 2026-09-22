<?php
session_start();
require_once 'config/conexion.php';

if (isset($_GET['id'])) {
    $id_habitacion = $_GET['id'];

    try {
        // Opcional: Validar si la habitación está ocupada antes de borrar
        $stmt_check = $pdo->prepare("SELECT estatus FROM habitaciones WHERE id_habitacion = ?");
        $stmt_check->execute([$id_habitacion]);
        $hab = $stmt_check->fetch();

        if ($hab && strtolower($hab['estatus']) === 'ocupada') {
            // Redirigir con error si está ocupada
            header("Location: habitaciones.php?error=ocupada");
            exit;
        }

        // Ejecutar eliminación
        $stmt = $pdo->prepare("DELETE FROM habitaciones WHERE id_habitacion = ?");
        $stmt->execute([$id_habitacion]);

        header("Location: habitaciones.php?exito=eliminado");
        exit;

    } catch (PDOException $e) {
        // Si hay una restricción de llave foránea (por ejemplo, estancias pasadas)
        header("Location: habitaciones.php?error=relacion");
        exit;
    }
} else {
    header("Location: habitaciones.php");
    exit;
}