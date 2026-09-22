<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

require_once 'config/conexion.php';

$id_incidencia = $_GET['id_incidencia'] ?? null;

if (!$id_incidencia) {
    header("Location: incidencias.php");
    exit;
}

// Procesar actualización al enviar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descripcion = trim($_POST['descripcion'] ?? '');
    $estatus     = $_POST['estatus'] ?? 'Pendiente';
    $id_camarista = $_POST['id_camarista'] ?? null;

    if (!empty($descripcion) && !empty($id_camarista)) {
        try {
            $sql = "UPDATE incidencias SET descripcion = :descripcion, estatus = :estatus, id_camarista = :id_camarista WHERE id_incidencia = :id_incidencia";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'descripcion'   => $descripcion,
                'estatus'       => $estatus,
                'id_camarista'  => $id_camarista,
                'id_incidencia' => $id_incidencia
            ]);

            $_SESSION['mensaje'] = "Incidencia actualizada correctamente.";
            $_SESSION['tipo_mensaje'] = "exito";
            header("Location: incidencias.php");
            exit;
        } catch (Exception $e) {
            $error = "Error al actualizar: " . $e->getMessage();
        }
    } else {
        $error = "Todos los campos obligatorios deben completarse.";
    }
}

// Obtener datos actuales de la incidencia
$stmt = $pdo->prepare("SELECT * FROM incidencias WHERE id_incidencia = :id");
$stmt->execute(['id' => $id_incidencia]);
$incidencia = $stmt->fetch();

if (!$incidencia) {
    header("Location: incidencias.php");
    exit;
}

// Obtener listas para los selects
$stmt_camaristas = $pdo->query("SELECT id_camarista, nombre_completo FROM camaristas ORDER BY nombre_completo ASC");
$lista_camaristas = $stmt_camaristas->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Incidencia</title>
</head>
<body>

    <h2>Editar Incidencia #<?php echo $incidencia['id_incidencia']; ?></h2>
    <p><a href="incidencias.php">Volver al listado</a></p>

    <?php if (isset($error)): ?>
        <p style="color: red; font-weight: bold;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form action="editar_incidencia.php?id_incidencia=<?php echo $id_incidencia; ?>" method="POST">
        <div>
            <label for="id_camarista">Camarista Asignado:</label><br>
            <select id="id_camarista" name="id_camarista" required>
                <?php foreach ($lista_camaristas as $cam): ?>
                    <option value="<?php echo $cam['id_camarista']; ?>" <?php echo ($incidencia['id_camarista'] == $cam['id_camarista']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cam['nombre_completo']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <br>

        <div>
            <label for="estatus">Estatus:</label><br>
            <select id="estatus" name="estatus" required>
                <option value="Pendiente" <?php echo ($incidencia['estatus'] === 'Pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                <option value="en_camino" <?php echo ($incidencia['estatus'] === 'en_camino') ? 'selected' : ''; ?>>En camino</option>
                <option value="En proceso" <?php echo ($incidencia['estatus'] === 'En proceso') ? 'selected' : ''; ?>>En proceso</option>
                <option value="Atendido" <?php echo ($incidencia['estatus'] === 'Atendido') ? 'selected' : ''; ?>>Atendido</option>
            </select>
        </div>
        <br>

        <div>
            <label for="descripcion">Descripción:</label><br>
            <textarea id="descripcion" name="descripcion" rows="4" cols="50" required><?php echo htmlspecialchars($incidencia['descripcion']); ?></textarea>
        </div>
        <br>

        <button type="submit">Guardar Cambios</button>
    </form>

</body>
</html>