<?php
session_start();

require_once 'config/conexion.php';

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $telefono        = trim($_POST['telefono'] ?? '');
    $correo          = trim($_POST['correo'] ?? '');
    $usuario         = trim($_POST['usuario'] ?? '');
    $password        = $_POST['password'] ?? '';
    $estatus         = 'no_disponible'; // Asignado automáticamente

    if ($nombre_completo === '' || $usuario === '' || $password === '') {
        $error = "Por favor, completa los campos obligatorios.";
    } else {
        try {
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM camaristas WHERE usuario = :usuario");
            $stmt_check->execute(['usuario' => $usuario]);
            if ($stmt_check->fetchColumn() > 0) {
                throw new Exception("El nombre de usuario ya está registrado.");
            }

            $ruta_foto_db = null;

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
                        $ruta_foto_db = $dest_path;
                    } else {
                        throw new Exception("Error al mover la imagen cargada.");
                    }
                } else {
                    throw new Exception("Formato de imagen no permitido.");
                }
            }

            $stmt_insert = $pdo->prepare("INSERT INTO camaristas (nombre_completo, telefono, correo, usuario, contraseña, estatus, foto) VALUES (:nombre_completo, :telefono, :correo, :usuario, :contrasena, :estatus, :foto)");
            $stmt_insert->execute([
                'nombre_completo' => $nombre_completo,
                'telefono'        => $telefono,
                'correo'          => $correo,
                'usuario'         => $usuario,
                'contrasena'      => $password,
                'estatus'         => $estatus,
                'foto'            => $ruta_foto_db
            ]);

            $mensaje = "Camarista registrado exitosamente.";
            $nombre_completo = $telefono = $correo = $usuario = '';

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
    <title>Registrar Camarista</title>
</head>
<body>

    <h2>Registrar Nuevo Camarista</h2>

    <?php if (!empty($mensaje)): ?>
        <p style="color: green;"><?php echo htmlspecialchars($mensaje); ?></p>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form action="registrar_camarista.php" method="POST" enctype="multipart/form-data">
        <div>
            <label>Nombre Completo:</label><br>
            <input type="text" name="nombre_completo" value="<?php echo htmlspecialchars($nombre_completo ?? ''); ?>" required>
        </div>
        <br>
        <div>
            <label>Teléfono:</label><br>
            <input type="text" name="telefono" value="<?php echo htmlspecialchars($telefono ?? ''); ?>">
        </div>
        <br>
        <div>
            <label>Correo:</label><br>
            <input type="email" name="correo" value="<?php echo htmlspecialchars($correo ?? ''); ?>">
        </div>
        <br>
        <div>
            <label>Usuario:</label><br>
            <input type="text" name="usuario" value="<?php echo htmlspecialchars($usuario ?? ''); ?>" required>
        </div>
        <br>
        <div>
            <label>Contraseña:</label><br>
            <input type="text" name="password" required>
        </div>
        <br>
        <div>
            <label>Fotografía:</label><br>
            <input type="file" name="foto" accept="image/png, image/jpeg, image/webp">
        </div>
        <br>
        <button type="submit">Guardar</button>
    </form>

    <br>
    <a href="camaristas.php">Volver al Panel</a>

</body>
</html>