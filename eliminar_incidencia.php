<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

require_once 'config/conexion.php';

$id_incidencia = $_GET['id_incidencia'] ?? null;

if ($id_incidencia) {
    try {
        $stmt = $pdo->prepare("DELETE FROM incidencias WHERE id_incidencia = :id");
        $stmt->execute(['id' => $id_incidencia]);

        $_SESSION['mensaje'] = "Incidencia eliminada correctamente.";
        $_SESSION['tipo_mensaje'] = "exito";
    } catch (Exception $e) {
        $_SESSION['mensaje'] = "Error al eliminar la incidencia: " . $e->getMessage();
        $_SESSION['tipo_mensaje'] = "error";
    }
}

header("Location: incidencias.php");
exit;