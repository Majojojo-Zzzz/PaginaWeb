<?php
// 1. Iniciar sesión y validar autenticación
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

require_once 'config/conexion.php';

$mensaje = $_SESSION['mensaje'] ?? "";
$tipo_mensaje = $_SESSION['tipo_mensaje'] ?? "";
unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);

$id_usuario_actual = $_SESSION['id_usuario'];

// 2. Validar Turno Abierto
$stmt_turno = $pdo->prepare("SELECT id_turno FROM turnos WHERE id_usuario = :id_usuario AND estatus = 'abierto' LIMIT 1");
$stmt_turno->execute(['id_usuario' => $id_usuario_actual]);
$turno_activo = $stmt_turno->fetch();

$id_turno_actual = $turno_activo ? $turno_activo['id_turno'] : null;

// 3. Procesar Formulario de Crear Servicio (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$id_turno_actual) {
        $_SESSION['mensaje'] = "Error: Debes abrir un turno para agregar servicios.";
        $_SESSION['tipo_mensaje'] = "error";
        header("Location: agregar_servicio.php");
        exit;
    }

    $tipo_servicio   = trim($_POST['tipo_servicio'] ?? '');
    $nombre_servicio = trim($_POST['nombre_servicio'] ?? '');
    $descripcion     = trim($_POST['descripcion'] ?? '');
    $precio          = (float)($_POST['precio'] ?? 0.00);

    if (!empty($nombre_servicio) && $precio >= 0) {
        try {
            $sql_s = "INSERT INTO servicios (tipo_servicio, nombre_servicio, descripcion, precio, id_usuario, id_turno)
                      VALUES (:tipo_servicio, :nombre_servicio, :descripcion, :precio, :id_usuario, :id_turno)";
            $stmt_s = $pdo->prepare($sql_s);
            $stmt_s->execute([
                'tipo_servicio'   => $tipo_servicio,
                'nombre_servicio' => $nombre_servicio,
                'descripcion'     => $descripcion,
                'precio'          => $precio,
                'id_usuario'      => $id_usuario_actual,
                'id_turno'        => $id_turno_actual
            ]);

            $_SESSION['mensaje'] = "¡Servicio '" . htmlspecialchars($nombre_servicio) . "' guardado con éxito!";
            $_SESSION['tipo_mensaje'] = "exito";

            // Redirigir de regreso al catálogo principal de servicios
            header("Location: servicios.php");
            exit;

        } catch (Exception $e) {
            $_SESSION['mensaje'] = "Error al guardar servicio: " . $e->getMessage();
            $_SESSION['tipo_mensaje'] = "error";
            header("Location: agregar_servicio.php");
            exit;
        }
    } else {
        $_SESSION['mensaje'] = "Por favor completa el nombre del servicio y un precio válido.";
        $_SESSION['tipo_mensaje'] = "error";
        header("Location: agregar_servicio.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datasys - Agregar Nuevo Servicio</title>
</head>
<body>

    <h2>Registrar Nuevo Servicio</h2>
    <p><a href="servicios.php">⬅️ Volver a Servicios</a></p>

    <!-- Alerta de Turno -->
    <?php if (!$id_turno_actual): ?>
        <p style="color: red; font-weight: bold; background-color: #fee; padding: 10px; border: 1px solid red;">
            ⚠️ ATENCIÓN: No tienes un turno abierto. Abre un turno para agregar nuevos servicios al catálogo.
        </p>
    <?php endif; ?>

    <!-- Mensajes de Estado -->
    <?php if (!empty($mensaje)): ?>
        <p style="color: <?php echo ($tipo_mensaje === 'exito') ? 'green' : 'red'; ?>; font-weight: bold;">
            <?php echo htmlspecialchars($mensaje); ?>
        </p>
    <?php endif; ?>

    <!-- FORMULARIO: REGISTRAR NUEVO SERVICIO EN EL CATÁLOGO -->
    <fieldset style="max-width: 500px;">
        <legend><strong>Datos del Servicio</strong></legend>
        <form action="agregar_servicio.php" method="POST">

            <div>
                <label for="tipo_servicio">Categoría / Tipo:</label><br>
                <input type="text" id="tipo_servicio" name="tipo_servicio" placeholder="Ej. Restaurante, Lavandería, Frigobar..." required style="width: 100%;">
            </div>
            <br>
            <div>
                <label for="nombre_servicio">Nombre del Servicio:</label><br>
                <input type="text" id="nombre_servicio" name="nombre_servicio" placeholder="Ej. Botella de Agua, Desayuno..." required style="width: 100%;">
            </div>
            <br>
            <div>
                <label for="descripcion">Descripción:</label><br>
                <textarea id="descripcion" name="descripcion" rows="3" placeholder="Detalles del servicio..." style="width: 100%;"></textarea>
            </div>
            <br>
            <div>
                <label for="precio">Precio Unitario ($):</label><br>
                <input type="number" id="precio" name="precio" step="0.01" min="0" placeholder="0.00" required style="width: 100%;">
            </div>
            <br>
            <button type="submit" <?php echo (!$id_turno_actual) ? 'disabled' : ''; ?> style="background-color: #0275d8; color: white; border: none; padding: 10px 20px; cursor: pointer; font-weight: bold; width: 100%;">
                💾 Guardar Servicio
            </button>
        </form>
    </fieldset>

</body>
</html>