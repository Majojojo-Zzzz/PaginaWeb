<?php
require_once 'config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_huesped = $_POST['id_huesped'] ?? null;

    if ($id_huesped) {
        try {
            $sql = "DELETE FROM huesped WHERE id_huesped = :id_huesped";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id_huesped' => $id_huesped]);
        } catch (PDOException $e) {
            // Opcional: Puedes manejar errores si el huésped está vinculado a una estancia activa
            // $error = "No se puede eliminar porque tiene estancias asociadas.";
        }
    }
}

// Redirigir siempre de vuelta a la lista de huéspedes
header("Location: huespedes.php");
exit;