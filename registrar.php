<?php
// 1. Iniciar la sesión y verificar autenticación
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

require_once 'config/conexion.php';

$mensaje = "";
$tipo_mensaje = ""; // 'exito' o 'error'

// 2. Procesar el formulario enviado por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario         = trim($_POST['usuario'] ?? '');
    $contrasena      = trim($_POST['contrasena'] ?? '');
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $telefono        = trim($_POST['telefono'] ?? '');
    $correo          = trim($_POST['correo'] ?? '');
    $rol             = trim($_POST['rol'] ?? '');

    if (!empty($usuario) && !empty($contrasena) && !empty($nombre_completo) && !empty($rol)) {
        try {
            // PROCESAR SUBIDA DE IMAGEN DE PERFIL
            $ruta_foto = null;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $carpeta_destino = 'img_empleados/';
                if (!file_exists($carpeta_destino)) {
                    mkdir($carpeta_destino, 0777, true);
                }

                $extension = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (in_array($extension, $extensiones_permitidas)) {
                    // Validar tamaño máximo (2 MB)
                    if ($_FILES['foto']['size'] <= 2 * 1024 * 1024) {
                        $nombre_foto = uniqid('emp_') . '.' . $extension;
                        $ruta_foto = $carpeta_destino . $nombre_foto;
                        move_uploaded_file($_FILES['foto']['tmp_name'], $ruta_foto);
                    } else {
                        throw new Exception("La imagen excede el tamaño máximo permitido (2 MB).");
                    }
                } else {
                    throw new Exception("Formato de imagen no permitido (solo JPG, JPEG, PNG, GIF, WEBP).");
                }
            }

            // GENERACIÓN AUTOMÁTICA DEL IDENTIFICADOR (EMP001, EMP002, ...)
            $stmt_id = $pdo->query("SELECT identificador FROM usuarios WHERE identificador LIKE 'EMP%' ORDER BY id_usuario DESC LIMIT 1");
            $ultimo_identificador = $stmt_id->fetchColumn();

            if ($ultimo_identificador) {
                $numero_actual = (int)substr($ultimo_identificador, 3);
                $nuevo_numero = $numero_actual + 1;
            } else {
                $nuevo_numero = 1;
            }

            $identificador_automatico = "EMP" . str_pad($nuevo_numero, 3, "0", STR_PAD_LEFT);
            $contrasena_encriptada = hash('sha256', $contrasena);

            // GUARDAR REGISTRO EN LA BASE DE DATOS
            $sql = "INSERT INTO usuarios (identificador, usuario, contraseña, nombre_completo, telefono, correo, rol, foto) 
                    VALUES (:identificador, :usuario, :contrasena, :nombre_completo, :telefono, :correo, :rol, :foto)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'identificador'   => $identificador_automatico,
                'usuario'         => $usuario,
                'contrasena'      => $contrasena_encriptada,
                'nombre_completo' => $nombre_completo,
                'telefono'        => $telefono,
                'correo'          => $correo,
                'rol'             => $rol,
                'foto'            => $ruta_foto
            ]);

            $mensaje = "¡Usuario registrado con éxito! Identificador asignado: " . $identificador_automatico;
            $tipo_mensaje = "exito";

        } catch (PDOException $e) {
            $tipo_mensaje = "error";
            if ($e->getCode() == 23000) {
                $mensaje = "Error: El nombre de usuario ya está registrado.";
            } else {
                $mensaje = "Error en el servidor al registrar: " . $e->getMessage();
            }
        } catch (Exception $e) {
            $tipo_mensaje = "error";
            $mensaje = $e->getMessage();
        }
    } else {
        $tipo_mensaje = "error";
        $mensaje = "Por favor, llena todos los campos obligatorios.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datasys - Registrar Usuario</title>
</head>
<body>

    <h2>Registrar Nuevo Usuario</h2>

    <a href="empleados.php">Volver a la lista de empleados</a>
    <br><br>

    <!-- Notificaciones de éxito o error -->
    <?php if (!empty($mensaje)): ?>
        <p style="color: <?php echo ($tipo_mensaje === 'exito') ? 'green' : 'red'; ?>; font-weight: bold;">
            <?php echo htmlspecialchars($mensaje); ?>
        </p>
    <?php endif; ?>

    <!-- Formulario de Registro -->
    <form action="registrar.php" method="POST" enctype="multipart/form-data">
        <div>
            <label for="foto">Foto de Perfil:</label><br>
            <input type="file" id="foto" name="foto" accept="image/*">
        </div>
        <br>
        <div>
            <label for="usuario">Usuario (Obligatorio):</label><br>
            <input type="text" id="usuario" name="usuario" required autocomplete="off">
        </div>
        <br>
        <div>
            <label for="contrasena">Contraseña (Obligatorio):</label><br>
            <input type="password" id="contrasena" name="contrasena" required autocomplete="new-password">
        </div>
        <br>
        <div>
            <label for="nombre_completo">Nombre Completo (Obligatorio):</label><br>
            <input type="text" id="nombre_completo" name="nombre_completo" required>
        </div>
        <br>
        <div>
            <label for="telefono">Teléfono:</label><br>
            <input type="text" id="telefono" name="telefono">
        </div>
        <br>
        <div>
            <label for="correo">Correo Electrónico:</label><br>
            <input type="email" id="correo" name="correo">
        </div>
        <br>
        <div>
            <label for="rol">Rol (Obligatorio):</label><br>
            <select id="rol" name="rol" required>
                <option value="">-- Selecciona un Rol --</option>
                <option value="Administrador">Administrador</option>
                <option value="Recepcionista">Recepcionista</option>
                <option value="Gobernante">Gobernante</option>
                <option value="Camarista">Camarista</option>
                <option value="Contador">Contador</option>
                <option value="Vendedor">Vendedor</option>
            </select>
        </div>
        <br>
        <button type="submit">Guardar Usuario</button>
    </form>

</body>
</html>