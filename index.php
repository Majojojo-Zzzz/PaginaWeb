<?php
// 1. Iniciar la sesión (Imprescindible para guardar la sesión del usuario)
session_start();

// 2. Si el usuario ya tiene sesión activa, redirigir directamente a home.php
if (isset($_SESSION['id_usuario'])) {
    header("Location: home.php");
    exit;
}

// 3. Incluir el archivo de conexión PDO
require_once 'config/conexion.php';

$error = "";

// 4. Procesar la información cuando el usuario envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');

    if (!empty($usuario) && !empty($contrasena)) {
        try {
            // Sentencia preparada con PDO: El uso de :usuario protege 100% contra Inyecciones SQL
            $stmt = $pdo->prepare("SELECT id_usuario, identificador, usuario, contraseña, nombre_completo, rol, foto 
                                   FROM usuarios 
                                   WHERE usuario = :usuario 
                                   LIMIT 1");
            $stmt->execute(['usuario' => $usuario]);
            $user_data = $stmt->fetch();

            // Verificación comparando el hash SHA-256
            if ($user_data && hash('sha256', $contrasena) === $user_data['contraseña']) {
                
                // Guardar los datos del usuario en la sesión global
                $_SESSION['id_usuario']      = $user_data['id_usuario'];
                $_SESSION['identificador']   = $user_data['identificador'];
                $_SESSION['usuario']         = $user_data['usuario'];
                $_SESSION['nombre_completo'] = $user_data['nombre_completo'];
                $_SESSION['rol']             = $user_data['rol'];
                $_SESSION['foto']            = $user_data['foto'];

                // Redirección exitosa
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "El usuario o la contraseña son incorrectos.";
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
    <title>Datasys - Login</title>
</head>
<body>

    <h2>Login Datasys</h2>
    
    <!-- Mensaje de error -->
    <?php if (!empty($error)): ?>
        <p style="color: red; font-weight: bold;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <!-- Formulario de ingreso -->
    <form action="index.php" method="POST">
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
        <button type="submit">Iniciar Sesión</button>
    </form>

    <br>
    <p>
        <a href="login_camaristas.php">¿Eres camarista? Haz clic aquí</a>
    </p>

</body>
</html>