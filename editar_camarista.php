<?php
session_start();
require_once 'config/conexion.php';

$mensaje = '';
$error = '';

$id_camarista = $_GET['id'] ?? null;

if (!$id_camarista) {
    header("Location: empleados.php");
    exit;
}

// Obtener datos actuales del camarista
try {
    $stmt = $pdo->prepare("SELECT * FROM camaristas WHERE id_camarista = :id");
    $stmt->execute(['id' => $id_camarista]);
    $camarista = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$camarista) {
        header("Location: empleados.php");
        exit;
    }
} catch (Exception $e) {
    $error = "Error al obtener los datos: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $telefono        = trim($_POST['telefono'] ?? '');
    $correo          = trim($_POST['correo'] ?? '');
    $usuario         = trim($_POST['usuario'] ?? '');
    $password        = $_POST['password'] ?? '';

    if ($nombre_completo === '' || $usuario === '') {
        $error = "El nombre completo y el usuario son obligatorios.";
    } else {
        try {
            // Verificar si el usuario ya existe en otro registro
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM camaristas WHERE usuario = :usuario AND id_camarista != :id");
            $stmt_check->execute(['usuario' => $usuario, 'id' => $id_camarista]);
            if ($stmt_check->fetchColumn() > 0) {
                throw new Exception("El nombre de usuario ya está en uso por otro camarista.");
            }

            $ruta_foto_db = $camarista['foto'];

            // Procesar nueva foto si se adjuntó una
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath   = $_FILES['foto']['tmp_name'];
                $fileName      = $_FILES['foto']['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($fileExtension, $allowedExtensions)) {
                    $uploadFileDir = 'img_camaristas/';
                    if (!is_dir($uploadFileDir)) {
                        mkdir($uploadFileDir, 0755, true);
                    }

                    $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                    $dest_path = $uploadFileDir . $newFileName;

                    if (move_uploaded_file($fileTmpPath, $dest_path)) {
                        // Eliminar foto anterior si existe
                        if (!empty($camarista['foto']) && file_exists($camarista['foto'])) {
                            unlink($camarista['foto']);
                        }
                        $ruta_foto_db = $dest_path;
                    } else {
                        throw new Exception("Error al mover la nueva imagen cargada.");
                    }
                } else {
                    throw new Exception("Formato de imagen no permitido.");
                }
            }

            // Actualizar con o sin contraseña nueva
            if (!empty($password)) {
                $stmt_update = $pdo->prepare("UPDATE camaristas SET nombre_completo = :nombre, telefono = :telefono, correo = :correo, usuario = :usuario, contraseña = :password, foto = :foto WHERE id_camarista = :id");
                $stmt_update->execute([
                    'nombre'   => $nombre_completo,
                    'telefono' => $telefono,
                    'correo'   => $correo,
                    'usuario'  => $usuario,
                    'password' => $password,
                    'foto'     => $ruta_foto_db,
                    'id'       => $id_camarista
                ]);
            } else {
                $stmt_update = $pdo->prepare("UPDATE camaristas SET nombre_completo = :nombre, telefono = :telefono, correo = :correo, usuario = :usuario, foto = :foto WHERE id_camarista = :id");
                $stmt_update->execute([
                    'nombre'   => $nombre_completo,
                    'telefono' => $telefono,
                    'correo'   => $correo,
                    'usuario'  => $usuario,
                    'foto'     => $ruta_foto_db,
                    'id'       => $id_camarista
                ]);
            }

            $mensaje = "Camarista actualizado exitosamente.";
            
            // Recargar datos actualizados
            $stmt = $pdo->prepare("SELECT * FROM camaristas WHERE id_camarista = :id");
            $stmt->execute(['id' => $id_camarista]);
            $camarista = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Camarista</title>
</head>
<body>

    <h2>Editar Camarista</h2>

    <?php if (!empty($mensaje)): ?>
        <p style="color: green;"><?php echo htmlspecialchars($mensaje); ?></p>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form action="editar_camarista.php?id=<?php echo $id_camarista; ?>" method="POST" enctype="multipart/form-data">
        <div>
            <label>Nombre Completo:</label><br>
            <input type="text" name="nombre_completo" value="<?php echo htmlspecialchars($camarista['nombre_completo']); ?>" required>
        </div>
        <br>
        <div>
            <label>Teléfono:</label><br>
            <input type="text" name="telefono" value="<?php echo htmlspecialchars($camarista['telefono'] ?? ''); ?>">
        </div>
        <br>
        <div>
            <label>Correo:</label><br>
            <input type="email" name="correo" value="<?php echo htmlspecialchars($camarista['correo'] ?? ''); ?>">
        </div>
        <br>
        <div>
            <label>Usuario:</label><br>
            <input type="text" name="usuario" value="<?php echo htmlspecialchars($camarista['usuario']); ?>" required>
        </div>
        <br>
        <div>
            <label>Contraseña (dejar en blanco para mantener la actual):</label><br>
            <input type="text" name="password">
        </div>
        <br>
        <div>
            <label>Fotografía Actual:</label><br>
            <?php if (!empty($camarista['foto']) && file_exists($camarista['foto'])): ?>
                <img src="<?php echo htmlspecialchars($camarista['foto']); ?>" alt="Foto" width="60" height="60"><br>
            <?php else: ?>
                <p>Sin foto</p>
            <?php endif; ?>
            <label>Cambiar Fotografía:</label><br>
            <input type="file" name="foto" accept="image/png, image/jpeg, image/webp">
        </div>
        <br>
        <button type="submit">Actualizar Camarista</button>
    </form>

    <br>
    <a href="empleados.php">Volver a la lista</a>

</body>
</html>