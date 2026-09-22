<?php
require_once 'config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

$mensaje = '';
$tipo_mensaje = '';

// Obtener el ID del huésped por GET o POST
$id_huesped = $_GET['id'] ?? $_POST['id_huesped'] ?? null;

if (!$id_huesped) {
    header("Location: huespedes.php");
    exit;
}

// Procesar el formulario cuando se envía la actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // El identificador se mantiene el de la base de datos o se ignora lo que venga por POST por seguridad
    $nombre        = trim($_POST['nombre'] ?? '');
    $apellido_p    = trim($_POST['apellido_p'] ?? '');
    $apellido_m    = trim($_POST['apellido_m'] ?? '');
    $telefono      = trim($_POST['telefono'] ?? '');
    $correo        = trim($_POST['correo'] ?? '');
    $nacionalidad  = trim($_POST['nacionalidad'] ?? '');

    if (!empty($nombre) && !empty($apellido_p)) {
        try {
            // Nota: No actualizamos 'identificador' porque no se debe poder editar
            $sql = "UPDATE huesped SET 
                        nombre = :nombre, 
                        apellido_p = :apellido_p, 
                        apellido_m = :apellido_m, 
                        telefono = :telefono, 
                        correo = :correo, 
                        nacionalidad = :nacionalidad 
                    WHERE id_huesped = :id_huesped";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'nombre'        => $nombre,
                'apellido_p'    => $apellido_p,
                'apellido_m'    => $apellido_m,
                'telefono'      => $telefono,
                'correo'        => $correo,
                'nacionalidad'  => $nacionalidad,
                'id_huesped'    => $id_huesped
            ]);

            header("Location: huespedes.php");
            exit;

        } catch (PDOException $e) {
            $mensaje = "Error al actualizar el huésped: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "El nombre y el apellido paterno son obligatorios.";
        $tipo_mensaje = "error";
    }
}

// Consultar los datos actuales del huésped para rellenar el formulario
try {
    $stmt = $pdo->prepare("SELECT * FROM huesped WHERE id_huesped = :id_huesped");
    $stmt->execute(['id_huesped' => $id_huesped]);
    $huesped = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$huesped) {
        header("Location: huespedes.php");
        exit;
    }
} catch (PDOException $e) {
    die("Error al obtener los datos del huésped: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Datasys - Editar Huésped</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"], input[type="email"] { width: 100%; max-width: 400px; padding: 8px; box-sizing: border-box; }
        input[readonly] { background-color: #e9ecef; color: #6c757d; cursor: not-allowed; }
        .error { color: red; font-weight: bold; }
    </style>
</head>
<body>

    <h2>Editar Huésped</h2>
    <p><a href="huespedes.php">Volver a la lista de huéspedes</a></p>

    <?php if (!empty($mensaje)): ?>
        <p class="<?php echo $tipo_mensaje; ?>"><?php echo htmlspecialchars($mensaje); ?></p>
    <?php endif; ?>

    <form action="editar_huesped.php?id=<?php echo $id_huesped; ?>" method="POST">
        <input type="hidden" name="id_huesped" value="<?php echo $id_huesped; ?>">

        <div class="form-group">
            <label for="identificador">Identificador:</label>
            <input type="text" id="identificador" name="identificador" value="<?php echo htmlspecialchars($huesped['identificador'] ?? ''); ?>" readonly>
        </div>

        <div class="form-group">
            <label for="nombre">Nombre:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($huesped['nombre']); ?>" required>
        </div>

        <div class="form-group">
            <label for="apellido_p">Apellido Paterno:</label>
            <input type="text" id="apellido_p" name="apellido_p" value="<?php echo htmlspecialchars($huesped['apellido_p']); ?>" required>
        </div>

        <div class="form-group">
            <label for="apellido_m">Apellido Materno:</label>
            <input type="text" id="apellido_m" name="apellido_m" value="<?php echo htmlspecialchars($huesped['apellido_m'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="telefono">Teléfono:</label>
            <input type="text" id="telefono" name="telefono" value="<?php echo htmlspecialchars($huesped['telefono'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="correo">Correo Electrónico:</label>
            <input type="email" id="correo" name="correo" value="<?php echo htmlspecialchars($huesped['correo'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="nacionalidad">Nacionalidad:</label>
            <input type="text" id="nacionalidad" name="nacionalidad" value="<?php echo htmlspecialchars($huesped['nacionalidad'] ?? ''); ?>">
        </div>

        <button type="submit">Guardar Cambios</button>
    </form>

</body>
</html>