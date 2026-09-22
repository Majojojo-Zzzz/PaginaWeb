<?php
// 1. Iniciar sesión
session_start();

require_once 'config/conexion.php';

$error = "";

// 2. Procesar el formulario de acceso de camaristas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');

    if (!empty($usuario) && !empty($contrasena)) {
        try {
            // Sentencia protegida 100% contra Inyección SQL usando PDO, buscando por el campo 'usuario'
            $stmt = $pdo->prepare("SELECT id_camarista, nombre_completo, foto, estatus 
                                   FROM camaristas 
                                   WHERE usuario = :usuario AND contraseña = :contrasena 
                                   LIMIT 1");
            $stmt->execute([
                'usuario'    => $usuario,
                'contrasena' => $contrasena
            ]);
            $camarista_data = $stmt->fetch();

            if ($camarista_data) {
                // Definir las variables de sesión para el camarista
                $_SESSION['id_usuario']      = $camarista_data['id_camarista'];
                $_SESSION['id_camarista']    = $camarista_data['id_camarista'];
                $_SESSION['nombre_completo'] = $camarista_data['nombre_completo'];
                $_SESSION['foto']            = $camarista_data['foto'];
                $_SESSION['rol']             = 'camarista';

                // Redireccionar al panel exclusivo de camaristas
                header("Location: home.php");
                exit;
            } else {
                $error = "Usuario o contraseña de camarista incorrectos.";
            }
        } catch (PDOException $e) {
            $error = "Hubo un problema en el servidor.";
        }
    } else {
        $error = "Por favor, llena todos los campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datasys - Login Camaristas</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background-color: #f4f4f4; position: relative; }
        
        /* Nombre del camarista en la esquina superior derecha */
        .esquina-usuario {
            position: absolute;
            top: 10px;
            right: 20px;
            background: #fff;
            padding: 8px 12px;
            border: 1px solid #ccc;
            font-size: 14px;
        }

        .login-container { max-width: 400px; background: #fff; padding: 20px; border: 1px solid #ccc; margin: 40px auto 0 auto; }
        input[type="text"], input[type="password"] { width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box; border: 1px solid #ccc; }
        button { padding: 8px 15px; margin-top: 10px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>

    <!-- Si ya hay sesión iniciada, muestra el nombre en la esquina -->
    <?php if (isset($_SESSION['nombre_completo'])): ?>
        <div class="esquina-usuario">
            👤 <strong><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></strong> 
            | <a href="camaristas.php">Ir a mi Panel</a> 
            | <a href="logout.php" style="color: red;">Cerrar Sesión</a>
        </div>
    <?php endif; ?>

    <div class="login-container">
        <h2>Acceso Camaristas</h2>
        
        <?php if (!empty($error)): ?>
            <p style="color: red; font-weight: bold;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form action="login_camaristas.php" method="POST">
            <div>
                <label for="usuario">Usuario:</label><br>
                <input type="text" id="usuario" name="usuario" required autocomplete="username">
            </div>
            <br>
            <div>
                <label for="contrasena">Contraseña:</label><br>
                <input type="password" id="contrasena" name="contrasena" required autocomplete="current-password">
            </div>
            <br>
            <button type="submit">Ingresar como Camarista</button>
        </form>

        <p style="margin-top: 15px;">
            <a href="index.php">← Volver al login general</a>
        </p>
    </div>

</body>
</html>