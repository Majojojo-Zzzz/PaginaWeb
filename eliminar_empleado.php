<?php
// Incluimos la conexión apuntando a la carpeta config
require_once 'config/conexion.php';

// Iniciar sesión si no se ha iniciado antes
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Filtro de seguridad: Si no ha iniciado sesión, redirigir al login
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

// Verificar que la petición sea exclusivamente por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_usuario'])) {
    $id_eliminar = (int)$_POST['id_usuario'];

    // VALIDACIÓN 1: Evitar que el usuario borre su propia cuenta en uso
    if ($id_eliminar === (int)$_SESSION['id_usuario']) {
        echo "<script>
                alert('No puedes eliminar tu propio usuario mientras tienes la sesión activa.');
                window.location.href = 'empleados.php';
              </script>";
        exit;
    }

    try {
        // Intentar la eliminación física del registro
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = :id");
        $stmt->execute(['id' => $id_eliminar]);

        // Redirigir si se eliminó con éxito
        header("Location: empleados.php");
        exit;

    } catch (PDOException $e) {
        // VALIDACIÓN 2: Capturar restricción de Llave Foránea (SQLSTATE 23000 / Error 1451)
        if ($e->getCode() == 23000 || $e->getCode() == 1451) {
            $mensaje_error = "No se puede eliminar este empleado porque tiene historial o registros asociados en otras tablas del sistema.";
        } else {
            $mensaje_error = "Error al intentar eliminar el empleado: " . $e->getMessage();
        }

        echo "<script>
                alert('" . addslashes($mensaje_error) . "');
                window.location.href = 'empleados.php';
              </script>";
        exit;
    }
} else {
    // Si se intenta acceder directamente escribiendo la URL (GET), redirigir
    header("Location: empleados.php");
    exit;
}
?>