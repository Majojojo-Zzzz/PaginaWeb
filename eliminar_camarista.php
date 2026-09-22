<?php
session_start();
require_once 'config/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_camarista = $_POST['id_camarista'] ?? null;

    if ($id_camarista) {
        try {
            // Obtener la foto para eliminarla del servidor si existe
            $stmt_foto = $pdo->prepare("SELECT foto FROM camaristas WHERE id_camarista = :id");
            $stmt_foto->execute(['id' => $id_camarista]);
            $camarista = $stmt_foto->fetch(PDO::FETCH_ASSOC);

            if ($camarista && !empty($camarista['foto']) && file_exists($camarista['foto'])) {
                unlink($camarista['foto']);
            }

            // Eliminar registros dependientes en incidencias o poner NULL si aplica, o proceder a borrar
            // Si hay restricciones de FK, asegúrate de borrarlas o manejarlas. Aquí eliminamos el registro directamente:
            $stmt_del = $pdo->prepare("DELETE FROM camaristas WHERE id_camarista = :id");
            $stmt_del->execute(['id' => $id_camarista]);

        } catch (Exception $e) {
            // Manejar error si hay dependencias activas (ej. incidencias asociadas)
        }
    }
}

header("Location: empleados.php");
exit;