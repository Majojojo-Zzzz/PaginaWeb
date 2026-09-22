<?php
// 1. Iniciar sesión y validar autenticación
session_start();

if (!isset($_SESSION['id_camarista']) && !isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

require_once 'config/conexion.php';

// RECUPERAR MENSAJE FLASH
$mensaje = $_SESSION['mensaje'] ?? '';
unset($_SESSION['mensaje']);

// Datos del camarista actual en sesión
$id_camarista_actual = $_SESSION['id_camarista'] ?? $_SESSION['id_usuario'];

// Consultar el estado más fresco del camarista desde la base de datos
$stmt_cam = $pdo->prepare("SELECT nombre_completo, estatus FROM camaristas WHERE id_camarista = :id");
$stmt_cam->execute(['id' => $id_camarista_actual]);
$datos_camarista = $stmt_cam->fetch();

$nombre_camarista = $datos_camarista['nombre_completo'] ?? ($_SESSION['nombre_completo'] ?? 'Camarista');
$estatus_actual   = trim($datos_camarista['estatus'] ?? 'no_disponible');
if ($estatus_actual === '') { $estatus_actual = 'no_disponible'; }

// 2. Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // ACCIÓN: Botón único para alternar turno (Iniciar / Finalizar)
    if ($accion === 'cambiar_turno') {
        $nuevo_estatus_turno = ($estatus_actual === 'no_disponible') ? 'descanso' : 'no_disponible';
        
        try {
            // Validar que no pueda finalizar turno si tiene tareas pendientes
            if ($nuevo_estatus_turno === 'no_disponible') {
                $stmt_check = $pdo->prepare("SELECT COUNT(*) as pendientes FROM incidencias WHERE id_camarista = :id_camarista AND estatus != 'Atendido'");
                $stmt_check->execute(['id_camarista' => $id_camarista_actual]);
                $res_pendientes = $stmt_check->fetch();
                $total_pendientes = $res_pendientes ? intval($res_pendientes['pendientes']) : 0;

                if ($total_pendientes > 0) {
                    throw new Exception("No puedes finalizar tu turno mientras tengas tareas pendientes por atender.");
                }
            }

            $stmt_upd = $pdo->prepare("UPDATE camaristas SET estatus = :estatus WHERE id_camarista = :id");
            $stmt_upd->execute([
                'estatus' => $nuevo_estatus_turno,
                'id'      => $id_camarista_actual
            ]);
            $_SESSION['mensaje'] = "Estatus de turno actualizado correctamente.";
        } catch (Exception $e) {
            $_SESSION['mensaje'] = "Error al actualizar el turno: " . $e->getMessage();
        }
        header("Location: camaristas.php");
        exit;
    }

    // ACCIÓN: Vaciar el historial de tareas terminadas de este camarista (eliminar de la base de datos)
    if ($accion === 'vaciar_historial') {
        try {
            $stmt_del = $pdo->prepare("DELETE FROM incidencias WHERE id_camarista = :id_camarista AND estatus = 'Atendido'");
            $stmt_del->execute(['id_camarista' => $id_camarista_actual]);
            $_SESSION['mensaje'] = "Historial de tareas terminadas vaciado correctamente.";
        } catch (Exception $e) {
            $_SESSION['mensaje'] = "Error al vaciar el historial: " . $e->getMessage();
        }
        header("Location: camaristas.php");
        exit;
    }

    // ACCIÓN: Actualizar tarea
    if ($accion === 'actualizar_tarea') {
        $id_incidencia   = $_POST['id_incidencia'] ?? null;
        $nuevo_estatus   = $_POST['nuevo_estatus'] ?? null;
        $nueva_desc      = trim($_POST['descripcion'] ?? '');
        $cambiar_a_mant  = isset($_POST['cambiar_mantenimiento']) ? true : false;

        if ($id_incidencia && $nuevo_estatus) {
            try {
                $pdo->beginTransaction();

                $stmt_get = $pdo->prepare("SELECT i.id_camarista, i.id_habitacion, h.estatus as hab_estatus 
                                           FROM incidencias i 
                                           INNER JOIN habitaciones h ON i.id_habitacion = h.id_habitacion 
                                           WHERE i.id_incidencia = :id_incidencia");
                $stmt_get->execute(['id_incidencia' => $id_incidencia]);
                $incidencia_data = $stmt_get->fetch();
                
                $id_cam      = $incidencia_data ? $incidencia_data['id_camarista'] : null;
                $id_hab      = $incidencia_data ? $incidencia_data['id_habitacion'] : null;
                $hab_estatus = $incidencia_data ? strtolower($incidencia_data['hab_estatus']) : '';

                if ($nueva_desc !== '') {
                    $stmt_desc = $pdo->prepare("UPDATE incidencias SET descripcion = :descripcion WHERE id_incidencia = :id_incidencia");
                    $stmt_desc->execute([
                        'descripcion'   => $nueva_desc,
                        'id_incidencia' => $id_incidencia
                    ]);
                }

                if ($nuevo_estatus === 'en_camino') {
                    $pdo->prepare("UPDATE incidencias SET estatus = 'en_camino' WHERE id_incidencia = :id_incidencia")
                        ->execute(['id_incidencia' => $id_incidencia]);

                    if ($id_cam) {
                        $pdo->prepare("UPDATE camaristas SET estatus = 'activo' WHERE id_camarista = :id_camarista")
                            ->execute(['id_camarista' => $id_cam]);
                    }

                } elseif ($nuevo_estatus === 'Atendido') {
                    $pdo->prepare("UPDATE incidencias SET estatus = 'Atendido' WHERE id_incidencia = :id_incidencia")
                        ->execute(['id_incidencia' => $id_incidencia]);

                    if ($id_cam) {
                        $pdo->prepare("UPDATE camaristas SET estatus = 'descanso' WHERE id_camarista = :id_camarista")
                            ->execute(['id_camarista' => $id_cam]);
                    }

                    if ($cambiar_a_mant) {
                        if ($hab_estatus === 'limpieza') {
                            $pdo->prepare("UPDATE habitaciones SET estatus = 'mantenimiento' WHERE id_habitacion = :id_habitacion")
                                ->execute(['id_habitacion' => $id_hab]);
                        } else {
                            throw new Exception("Solo se puede cambiar a mantenimiento si la habitación se encuentra en estado de limpieza.");
                        }
                    } else {
                        if ($id_hab) {
                            $pdo->prepare("UPDATE habitaciones SET estatus = 'disponible' WHERE id_habitacion = :id_habitacion")
                                ->execute(['id_habitacion' => $id_hab]);
                        }
                    }
                }

                $pdo->commit();
                $_SESSION['mensaje'] = "Tarea actualizada exitosamente.";

            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['mensaje'] = "Error: " . $e->getMessage();
            }
        }

        header("Location: camaristas.php");
        exit;
    }
}

// 3. Consultar tareas pendientes del camarista
$sql_tareas = "SELECT i.*, h.numero_habitacion, h.tipo_habitacion, h.estatus as habitacion_estatus, c.nombre_completo as camarista_nombre
               FROM incidencias i
               INNER JOIN habitaciones h ON i.id_habitacion = h.id_habitacion
               INNER JOIN camaristas c ON i.id_camarista = c.id_camarista
               WHERE i.id_camarista = :id_camarista AND i.estatus != 'Atendido'
               ORDER BY i.fecha_reporte DESC";
$stmt = $pdo->prepare($sql_tareas);
$stmt->execute(['id_camarista' => $id_camarista_actual]);
$tareas_pendientes = $stmt->fetchAll();

// 4. Historial de tareas terminadas unicamente de este camarista
$sql_terminadas = "SELECT i.*, h.numero_habitacion, h.tipo_habitacion, c.nombre_completo as camarista_nombre
                   FROM incidencias i
                   INNER JOIN habitaciones h ON i.id_habitacion = h.id_habitacion
                   INNER JOIN camaristas c ON i.id_camarista = c.id_camarista
                   WHERE i.id_camarista = :id_camarista AND i.estatus = 'Atendido'
                   ORDER BY i.fecha_reporte DESC LIMIT 30";
$stmt_term = $pdo->prepare($sql_terminadas);
$stmt_term->execute(['id_camarista' => $id_camarista_actual]);
$tareas_terminadas = $stmt_term->fetchAll();

// Mapeo visual de textos y estados
$texto_estatus_map = [
    'activo'        => 'Ocupado',
    'descanso'      => 'Disponible',
    'no_disponible' => 'No disponible'
];
$estatus_visible = $texto_estatus_map[$estatus_actual] ?? 'No disponible';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Camaristas</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #fff; color: #000; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; background: #f9f9f9; padding: 10px; border: 1px solid #ccc; }
        .user-info { font-weight: bold; }
        .btn-logout { background-color: #d9534f; color: white; padding: 4px 10px; text-decoration: none; border-radius: 3px; font-size: 13px; font-weight: bold; }
        .btn-turno { padding: 6px 12px; border: none; cursor: pointer; font-weight: bold; color: white; }
        .btn-vaciar { background-color: #f0ad4e; color: white; padding: 5px 10px; border: none; cursor: pointer; font-weight: bold; font-size: 13px; border-radius: 3px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; margin-bottom: 10px; }
        .badge-estatus { padding: 3px 8px; font-size: 12px; font-weight: bold; border-radius: 3px; display: inline-block; }
        .badge-no_disponible { background: #d9534f; color: #fff; }
        .badge-descanso { background: #5cb85c; color: #fff; }
        .badge-activo { background: #0275d8; color: #fff; }
        .tarjeta-tarea { border: 1px solid #999; padding: 15px; margin-bottom: 15px; background: #fff; }
        .info-label { font-weight: bold; }
        .btn-accion { padding: 6px 12px; margin-right: 5px; cursor: pointer; }
        textarea { width: 100%; box-sizing: border-box; padding: 5px; border: 1px solid #999; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #999; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>

    <h2>Panel de Tareas y Asignaciones - Módulo Camaristas</h2>

    <div class="top-bar">
        <div>
            <a href="index.php">Volver al Menú Principal</a>
        </div>
        <div>
            <span style="margin-right: 15px;">
                Estatus Turno: 
                <span class="badge-estatus badge-<?php echo htmlspecialchars($estatus_actual); ?>">
                    <?php echo htmlspecialchars($estatus_visible); ?>
                </span>
            </span>

            <!-- Botón Único Inteligente para Iniciar / Finalizar Turno -->
            <form action="camaristas.php" method="POST" style="display: inline-block; margin-right: 15px;">
                <input type="hidden" name="accion" value="cambiar_turno">
                <?php if ($estatus_actual === 'no_disponible'): ?>
                    <button type="submit" class="btn-turno" style="background-color: #5cb85c;">🟢 Iniciar Turno</button>
                <?php else: ?>
                    <button type="submit" class="btn-turno" style="background-color: #d9534f;">🔴 Finalizar Turno</button>
                <?php endif; ?>
            </form>

            <span class="user-info">👤 <?php echo htmlspecialchars($nombre_camarista); ?></span> 
            &nbsp;|&nbsp; 
            <a href="logout_camarista.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </div>

    <?php if (!empty($mensaje)): ?>
        <p style="font-weight: bold; border: 1px solid #999; padding: 8px; background: #eee;">
            <?php echo htmlspecialchars($mensaje); ?>
        </p>
    <?php endif; ?>

    <h3>Tareas Asignadas Activas</h3>

    <?php if (empty($tareas_pendientes)): ?>
        <p>No hay tareas pendientes en este momento.</p>
    <?php else: ?>
        <?php foreach ($tareas_pendientes as $t): ?>
            <div class="tarjeta-tarea">
                <form action="camaristas.php" method="POST">
                    <input type="hidden" name="accion" value="actualizar_tarea">
                    <input type="hidden" name="id_incidencia" value="<?php echo $t['id_incidencia']; ?>">

                    <p>
                        <span class="info-label">Habitación:</span> Hab. <?php echo htmlspecialchars($t['numero_habitacion']); ?> 
                        (<?php echo htmlspecialchars($t['tipo_habitacion']); ?>) — <span class="info-label">Estado Hab.:</span> <?php echo htmlspecialchars($t['habitacion_estatus']); ?>
                    </p>
                    <p>
                        <span class="info-label">Fecha de Reporte:</span> <?php echo htmlspecialchars($t['fecha_reporte']); ?><br>
                        <span class="info-label">Estado de Tarea:</span> <?php echo htmlspecialchars($t['estatus']); ?>
                    </p>

                    <p>
                        <label for="descripcion_<?php echo $t['id_incidencia']; ?>" class="info-label">Descripción / Detalles:</label>
                        <textarea id="descripcion_<?php echo $t['id_incidencia']; ?>" name="descripcion" rows="2"><?php echo htmlspecialchars($t['descripcion']); ?></textarea>
                    </p>

                    <?php if (strtolower($t['habitacion_estatus']) === 'limpieza'): ?>
                        <p style="margin: 8px 0;">
                            <label>
                                <input type="checkbox" name="cambiar_mantenimiento" value="1"> 
                                Marcar esta habitación para <strong>Mantenimiento</strong>
                            </label>
                        </p>
                    <?php endif; ?>

                    <div style="margin-top: 10px;">
                        <button type="submit" name="nuevo_estatus" value="en_camino" class="btn-accion">Voy en camino</button>
                        <button type="submit" name="nuevo_estatus" value="Atendido" class="btn-accion" style="font-weight: bold;">Terminado</button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <hr style="margin: 30px 0; border: 0; border-top: 1px solid #999;">

    <!-- Encabezado del Historial con Botón para Vaciar tabla (solo de lo que él hizo) -->
    <div class="section-header">
        <h3 style="margin: 0;">Historial de Tareas Terminadas</h3>
        <?php if (!empty($tareas_terminadas)): ?>
            <form action="camaristas.php" method="POST" onsubmit="return confirm('¿Estás seguro de vaciar tu historial de tareas terminadas?');">
                <input type="hidden" name="accion" value="vaciar_historial">
                <button type="submit" class="btn-vaciar">🗑️ Vaciar Mi Historial</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (empty($tareas_terminadas)): ?>
        <p>Aún no hay registros de tareas terminadas.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Habitación</th>
                    <th>Camarista</th>
                    <th>Descripción</th>
                    <th>Fecha de Reporte</th>
                    <th>Estatus</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tareas_terminadas as $term): ?>
                    <tr>
                        <td>#<?php echo $term['id_incidencia']; ?></td>
                        <td>Hab. <?php echo htmlspecialchars($term['numero_habitacion']); ?></td>
                        <td><?php echo htmlspecialchars($term['camarista_nombre']); ?></td>
                        <td><?php echo htmlspecialchars($term['descripcion']); ?></td>
                        <td><?php echo htmlspecialchars($term['fecha_reporte']); ?></td>
                        <td><strong><?php echo htmlspecialchars($term['estatus']); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</body>
</html>