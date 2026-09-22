<?php
require_once 'config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

$mensaje = "";
$id_usuario = $_GET['id'] ?? $_POST['id_usuario'] ?? null;

if (!$id_usuario) {
    header("Location: empleados.php");
    exit;
}

// Consultar datos actuales del empleado
try {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = :id");
    $stmt->execute(['id' => $id_usuario]);
    $empleado = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$empleado) {
        header("Location: empleados.php");
        exit;
    }
} catch (PDOException $e) {
    die("Error al consultar el empleado: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $rol = trim($_POST['rol'] ?? '');

    if (!empty($usuario) && !empty($nombre_completo) && !empty($rol)) {
        try {
            $ruta_foto = $empleado['foto']; // Mantener la foto anterior por defecto

            // Si subió una nueva foto, procesarla y borrar la anterior
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $carpeta_destino = 'img_empleados/';
                if (!file_exists($carpeta_destino)) {
                    mkdir($carpeta_destino, 0777, true);
                }

                $extension = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (in_array($extension, $extensiones_permitidas)) {
                    // Borrar foto anterior si existía físicamente
                    if (!empty($empleado['foto']) && file_exists($empleado['foto'])) {
                        unlink($empleado['foto']);
                    }

                    $nombre_foto = uniqid('emp_') . '.' . $extension;
                    $ruta_foto = $carpeta_destino . $nombre_foto;
                    move_uploaded_file($_FILES['foto']['tmp_name'], $ruta_foto);
                }
            }

            if (!empty($contrasena)) {
                $contrasena_encriptada = hash('sha256', $contrasena);
                $sql = "UPDATE usuarios 
                        SET usuario = :usuario, contraseña = :contrasena, nombre_completo = :nombre_completo, 
                            telefono = :telefono, correo = :correo, rol = :rol, foto = :foto 
                        WHERE id_usuario = :id_usuario";
                $params = [
                    'usuario' => $usuario,
                    'contrasena' => $contrasena_encriptada,
                    'nombre_completo' => $nombre_completo,
                    'telefono' => $telefono,
                    'correo' => $correo,
                    'rol' => $rol,
                    'foto' => $ruta_foto,
                    'id_usuario' => $id_usuario
                ];
            } else {
                $sql = "UPDATE usuarios 
                        SET usuario = :usuario, nombre_completo = :nombre_completo, 
                            telefono = :telefono, correo = :correo, rol = :rol, foto = :foto 
                        WHERE id_usuario = :id_usuario";
                $params = [
                    'usuario' => $usuario,
                    'nombre_completo' => $nombre_completo,
                    'telefono' => $telefono,
                    'correo' => $correo,
                    'rol' => $rol,
                    'foto' => $ruta_foto,
                    'id_usuario' => $id_usuario
                ];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            header("Location: empleados.php");
            exit;

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $mensaje = "Error: El nombre de usuario ya pertenece a otro registro.";
            } else {
                $mensaje = "Error al actualizar: " . $e->getMessage();
            }
        }
    } else {
        $mensaje = "Por favor, llena todos los campos obligatorios.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Datasys - Editar Empleado</title>
</head>
<body>

    <h2>Editar Empleado (<?php echo htmlspecialchars($empleado['identificador'] ?? 'ID: ' . $empleado['id_usuario']); ?>)</h2>

    <a href="empleados.php">Volver a la lista de empleados</a>
    <br><br>

    <?php if (!empty($mensaje)): ?>
        <p><strong><?php echo htmlspecialchars($mensaje); ?></strong></p>
    <?php endif; ?>

    <form action="editar_empleado.php?id=<?php echo $id_usuario; ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id_usuario" value="<?php echo htmlspecialchars($empleado['id_usuario']); ?>">

        <div>
            <label>Foto de Perfil Actual:</label><br>
            <?php if (!empty($empleado['foto']) && file_exists($empleado['foto'])): ?>
                <img src="<?php echo htmlspecialchars($empleado['foto']); ?>" alt="Foto" width="70"><br>
            <?php else: ?>
                <span>Sin foto</span><br>
            <?php endif; ?>
            <label>Cambiar Foto (Opcional):</label>
            <input type="file" name="foto" accept="image/*">
        </div>
        <br>
        <div>
            <label>Identificador (Auto):</label>
            <input type="text" value="<?php echo htmlspecialchars($empleado['identificador'] ?? 'N/A'); ?>" disabled>
        </div>
        <br>
        <div>
            <label>Usuario (Obligatorio):</label>
            <input type="text" name="usuario" value="<?php echo htmlspecialchars($empleado['usuario']); ?>" required>
        </div>
        <br>
        <div>
            <label>Contraseña (Dejar en blanco para conservar la actual):</label>
            <input type="password" name="contrasena" placeholder="Nueva contraseña (Opcional)">
        </div>
        <br>
        <div>
            <label>Nombre Completo (Obligatorio):</label>
            <input type="text" name="nombre_completo" value="<?php echo htmlspecialchars($empleado['nombre_completo']); ?>" required>
        </div>
        <br>
        <div>
            <label>Teléfono:</label>
            <input type="text" name="telefono" value="<?php echo htmlspecialchars($empleado['telefono'] ?? ''); ?>">
        </div>
        <br>
        <div>
            <label>Correo:</label>
            <input type="email" name="correo" value="<?php echo htmlspecialchars($empleado['correo'] ?? ''); ?>">
        </div>
        <br>
        <div>
            <label>Rol (Obligatorio):</label>
            <select name="rol" required>
                <option value="">-- Selecciona un Rol --</option>
                <option value="Administrador" <?php echo ($empleado['rol'] === 'Administrador') ? 'selected' : ''; ?>>Administrador</option>
                <option value="Recepcionista" <?php echo ($empleado['rol'] === 'Recepcionista') ? 'selected' : ''; ?>>Recepcionista</option>
                <option value="Gobernante" <?php echo ($empleado['rol'] === 'Gobernante') ? 'selected' : ''; ?>>Gobernante</option>
                <option value="Camarista" <?php echo ($empleado['rol'] === 'Camarista') ? 'selected' : ''; ?>>Camarista</option>
                <option value="Contador" <?php echo ($empleado['rol'] === 'Contador') ? 'selected' : ''; ?>>Contador</option>
            </select>
        </div>
        <br>
        <button type="submit">Actualizar Empleado</button>
    </form>

</body>
</html>