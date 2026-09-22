<?php
// 1. Iniciar sesión y validar autenticación
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

require_once 'config/conexion.php';

// RECUPERAR MENSAJE FLASH DE LA SESIÓN (SI EXISTE) Y LIMPIARLO
$mensaje = $_SESSION['mensaje'] ?? '';
$tipo_mensaje = $_SESSION['tipo_mensaje'] ?? '';
unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);

// 2. Procesar formulario si se envía una nueva incidencia o cambio de estatus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'crear';

    if ($accion === 'crear') {
        $id_habitacion = $_POST['id_habitacion'] ?? null;
        $id_camarista  = $_POST['id_camarista'] ?? null;
        $descripcion   = trim($_POST['descripcion'] ?? '');

        if (!empty($id_habitacion) && !empty($descripcion) && !empty($id_camarista)) {
            try {
                $pdo->beginTransaction();

                // VALIDACIÓN DE SEGURIDAD: Verificar estatus y número de tareas activas del camarista (máximo 3)
                $stmt_check_c = $pdo->prepare("SELECT estatus, (SELECT COUNT(*) FROM incidencias i WHERE i.id_camarista = camaristas.id_camarista AND i.estatus != 'Atendido') as tareas_activas FROM camaristas WHERE id_camarista = :id_camarista");
                $stmt_check_c->execute(['id_camarista' => $id_camarista]);
                $datos_camarista = $stmt_check_c->fetch();

                if (!$datos_camarista) {
                    throw new Exception("El camarista seleccionado no existe.");
                }

                $tareas_activas_actuales = intval($datos_camarista['tareas_activas'] ?? 0);

                if ($tareas_activas_actuales >= 3) {
                    throw new Exception("El camarista seleccionado ya ha alcanzado el límite máximo de 3 tareas activas.");
                }

                if (strtolower($datos_camarista['estatus']) === 'no_disponible') {
                    throw new Exception("El camarista seleccionado se encuentra no disponible (fuera de turno).");
                }

                // A. Registrar la incidencia como Pendiente por defecto
                $sql = "INSERT INTO incidencias (id_habitacion, id_camarista, descripcion, estatus, fecha_reporte) 
                        VALUES (:id_habitacion, :id_camarista, :descripcion, 'Pendiente', NOW())";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'id_habitacion' => $id_habitacion,
                    'id_camarista'  => $id_camarista,
                    'descripcion'   => $descripcion
                ]);

                // B. Marcar al camarista como activo (ocupado) si con esta asignación alcanza o supera las 3 tareas
                $nuevo_total_activas = $tareas_activas_actuales + 1;
                if ($nuevo_total_activas >= 3) {
                    $sql_camarista = "UPDATE camaristas SET estatus = 'activo' WHERE id_camarista = :id_camarista";
                    $stmt_camarista = $pdo->prepare($sql_camarista);
                    $stmt_camarista->execute(['id_camarista' => $id_camarista]);
                }

                $pdo->commit();

                $_SESSION['mensaje'] = "Incidencia reportada y camarista asignado correctamente. (Tareas activas: $nuevo_total_activas/3)";
                $_SESSION['tipo_mensaje'] = "exito";

            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['mensaje'] = "Error al registrar la incidencia: " . $e->getMessage();
                $_SESSION['tipo_mensaje'] = "error";
            }
        } else {
            $_SESSION['mensaje'] = "Por favor, selecciona una habitación, completa la descripción y elige un camarista.";
            $_SESSION['tipo_mensaje'] = "error";
        }
    } elseif ($accion === 'actualizar_estatus') {
        $id_incidencia = $_POST['id_incidencia'] ?? null;
        $nuevo_estatus = $_POST['nuevo_estatus'] ?? 'Pendiente';

        if ($id_incidencia) {
            try {
                $pdo->beginTransaction();

                // A. Obtener el id_camarista asignado a esta incidencia
                $stmt_get = $pdo->prepare("SELECT id_camarista FROM incidencias WHERE id_incidencia = :id_incidencia");
                $stmt_get->execute(['id_incidencia' => $id_incidencia]);
                $incidencia_actual = $stmt_get->fetch();
                $id_camarista = $incidencia_actual ? $incidencia_actual['id_camarista'] : null;

                // B. Actualizar el estatus de la incidencia
                $sql_up = "UPDATE incidencias SET estatus = :estatus WHERE id_incidencia = :id_incidencia";
                $stmt_up = $pdo->prepare($sql_up);
                $stmt_up->execute([
                    'estatus'       => $nuevo_estatus,
                    'id_incidencia' => $id_incidencia
                ]);

                // C. Gestionar el estatus del camarista según las tareas activas restantes
                if ($id_camarista) {
                    if ($nuevo_estatus === 'Atendido') {
                        // Contar cuántas tareas activas le quedan al camarista
                        $stmt_act = $pdo->prepare("SELECT COUNT(*) as activas FROM incidencias WHERE id_camarista = :id_camarista AND estatus != 'Atendido'");
                        $stmt_act->execute(['id_camarista' => $id_camarista]);
                        $res_act = $stmt_act->fetch();
                        $activas_restantes = $res_act ? intval($res_act['activas']) : 0;

                        // Si baja de 3 tareas activas, vuelve a estar disponible (descanso)
                        if ($activas_restantes < 3) {
                            $sql_liberar = "UPDATE camaristas SET estatus = 'descanso' WHERE id_camarista = :id_camarista";
                            $stmt_liberar = $pdo->prepare($sql_liberar);
                            $stmt_liberar->execute(['id_camarista' => $id_camarista]);
                        }
                    } else {
                        // Verificar si tiene 3 o más tareas activas para mantenerlo como ocupado (activo)
                        $stmt_act = $pdo->prepare("SELECT COUNT(*) as activas FROM incidencias WHERE id_camarista = :id_camarista AND estatus != 'Atendido'");
                        $stmt_act->execute(['id_camarista' => $id_camarista]);
                        $res_act = $stmt_act->fetch();
                        $activas_total = $res_act ? intval($res_act['activas']) : 0;

                        if ($activas_total >= 3) {
                            $sql_estatus_c = "UPDATE camaristas SET estatus = 'activo' WHERE id_camarista = :id_camarista";
                            $stmt_ec = $pdo->prepare($sql_estatus_c);
                            $stmt_ec->execute(['id_camarista' => $id_camarista]);
                        }
                    }
                }

                $pdo->commit();

                $_SESSION['mensaje'] = "Estatus de la incidencia actualizado correctamente a: " . $nuevo_estatus;
                $_SESSION['tipo_mensaje'] = "exito";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['mensaje'] = "Error al actualizar estatus: " . $e->getMessage();
                $_SESSION['tipo_mensaje'] = "error";
            }
        }
    } elseif ($accion === 'eliminar_lote') {
        $cantidad = intval($_POST['cantidad'] ?? 50);
        if ($cantidad === 50 || $cantidad === 100) {
            try {
                $sql_del = "DELETE FROM incidencias ORDER BY fecha_reporte DESC LIMIT " . $cantidad;
                $pdo->exec($sql_del);

                $_SESSION['mensaje'] = "Se han eliminado los últimos " . $cantidad . " registros correctamente.";
                $_SESSION['tipo_mensaje'] = "exito";
            } catch (Exception $e) {
                $_SESSION['mensaje'] = "Error al eliminar los registros: " . $e->getMessage();
                $_SESSION['tipo_mensaje'] = "error";
            }
        }
    } elseif ($accion === 'eliminar_todo') {
        try {
            $pdo->exec("DELETE FROM incidencias");
            $_SESSION['mensaje'] = "Se ha vaciado toda la tabla de incidencias correctamente.";
            $_SESSION['tipo_mensaje'] = "exito";
        } catch (Exception $e) {
            $_SESSION['mensaje'] = "Error al vaciar la tabla: " . $e->getMessage();
            $_SESSION['tipo_mensaje'] = "error";
        }
    }

    // PRG: Redirección para limpiar el POST
    header("Location: incidencias.php");
    exit;
}

// 3. Obtener únicamente las habitaciones directas de la tabla habitaciones
$stmt_hab = $pdo->query("SELECT id_habitacion, numero_habitacion, tipo_habitacion 
                         FROM habitaciones 
                         ORDER BY numero_habitacion ASC");
$habitaciones = $stmt_hab->fetchAll();

// Obtener camaristas junto con el conteo de sus tareas activas (no atendidas)
$stmt_camaristas = $pdo->query("SELECT c.id_camarista, c.nombre_completo, c.estatus,
                                       (SELECT COUNT(*) FROM incidencias i WHERE i.id_camarista = c.id_camarista AND i.estatus != 'Atendido') as tareas_activas
                                FROM camaristas c 
                                ORDER BY c.nombre_completo ASC");
$lista_camaristas = $stmt_camaristas->fetchAll();

// 4. Listado de incidencias
$stmt_incidencias = $pdo->query("SELECT i.*, 
                               h.numero_habitacion, 
                               c.nombre_completo as camarista_asignado,
                               c.estatus as estatus_camarista
                               FROM incidencias i
                               LEFT JOIN habitaciones h ON i.id_habitacion = h.id_habitacion
                               LEFT JOIN camaristas c ON i.id_camarista = c.id_camarista
                               ORDER BY i.fecha_reporte DESC LIMIT 50");
$lista_incidencias = $stmt_incidencias->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datasys - Control de Incidencias y Camaristas</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f9f9f9;
            color: #333;
        }
        h2 {
            color: #2c3e50;
        }
        .tabla-selector-container {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ccc;
            background: #fff;
            margin-top: 5px;
        }
        .fila-habitacion:hover, .fila-camarista:hover {
            background-color: #f1f1f1;
            cursor: pointer;
        }
        .fila-ocupada {
            background-color: #f8d7da !important;
            color: #842029;
            cursor: not-allowed !important;
        }
        .seleccionada {
            background-color: #d1e7dd !important;
        }
        fieldset {
            background: #fff;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        button {
            padding: 8px 15px;
            cursor: pointer;
        }
    </style>
</head>
<body>

    <!-- CABECERA CON BOTÓN EN LA ESQUINADA DERECHA SIN COLORES NI ICONOS -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
        <div>
            <h2 style="margin: 0 0 5px 0;">Gestión de Incidencias y Tareas de Camaristas</h2>
            <a href="home.php">Volver al Menú Principal</a>
        </div>
        <div>
            <a href="noti_habitacion.php">Notificaciones</a>
        </div>
    </div>

    <?php if (!empty($mensaje)): ?>
        <p style="color: <?php echo ($tipo_mensaje === 'exito') ? 'green' : 'red'; ?>; font-weight: bold;">
            <?php echo htmlspecialchars($mensaje); ?>
        </p>
    <?php endif; ?>

    <!-- FORMULARIO PARA NUEVA INCIDENCIA -->
    <fieldset>
        <legend>Reportar Nueva Incidencia / Asignar a Camarista (Máximo 3 tareas por camarista)</legend>
        <form action="incidencias.php" method="POST">
            <input type="hidden" name="accion" value="crear">

            <!-- BUSCADOR Y TABLA DE SELECCIÓN DE HABITACIÓN -->
            <div>
                <label><strong>Habitación Afectada:</strong></label><br>
                <input type="hidden" name="id_habitacion" id="id_habitacion_input" required>
                <div id="texto_habitacion_seleccionada" style="margin: 5px 0; font-weight: bold; color: #0f5132;">
                    ⚠️ Ninguna habitación seleccionada (Busca por número o tipo)
                </div>
                
                <input type="text" id="buscador_hab" placeholder="🔍 Escribe número de habitación o tipo..." onkeyup="filtrarHabitaciones()" style="width: 100%; padding: 8px; box-sizing: border-box; margin-bottom: 5px;">
                
                <div class="tabla-selector-container">
                    <table id="tabla_habitaciones" border="0" cellpadding="6" cellspacing="0" style="width: 100%; border-collapse: collapse;">
                        <tbody>
                            <?php foreach ($habitaciones as $hab): ?>
                                <tr class="fila-habitacion" onclick="seleccionarHabitacion(<?php echo $hab['id_habitacion']; ?>, 'Hab. <?php echo htmlspecialchars($hab['numero_habitacion']); ?> (<?php echo htmlspecialchars($hab['tipo_habitacion']); ?>)', this)">
                                    <td style="border-bottom: 1px solid #eee; width: 40%;"><strong>Hab. <?php echo htmlspecialchars($hab['numero_habitacion']); ?></strong></td>
                                    <td style="border-bottom: 1px solid #eee; width: 50%;"><?php echo htmlspecialchars($hab['tipo_habitacion']); ?></td>
                                    <td style="border-bottom: 1px solid #eee; width: 10%; text-align: right;"><button type="button" style="padding: 2px 6px;">Seleccionar</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <br>

            <!-- BUSCADOR Y TABLA DE SELECCIÓN DE CAMARISTA -->
            <div>
                <label><strong>Asignar a Camarista:</strong></label><br>
                <input type="hidden" name="id_camarista" id="id_camarista_input" required>
                <div id="texto_camarista_seleccionado" style="margin: 5px 0; font-weight: bold; color: #0f5132;">
                    ⚠️ Ningún camarista seleccionado (Permite hasta 3 tareas activas)
                </div>
                
                <input type="text" id="buscador_cam" placeholder="🔍 Escribe el nombre del camarista..." onkeyup="filtrarCamaristas()" style="width: 100%; padding: 8px; box-sizing: border-box; margin-bottom: 5px;">
                
                <div class="tabla-selector-container">
                    <table id="tabla_camaristas" border="0" cellpadding="6" cellspacing="0" style="width: 100%; border-collapse: collapse;">
                        <tbody>
                            <?php foreach ($lista_camaristas as $cam): ?>
                                <?php 
                                    $tareas_activas = intval($cam['tareas_activas'] ?? 0);
                                    $estatus_lower = strtolower($cam['estatus']);
                                    
                                    // Se considera bloqueado/ocupado si ya tiene 3 o más tareas, o si está fuera de turno (no_disponible)
                                    $no_disponible_turno = ($estatus_lower === 'no_disponible');
                                    $limite_alcanzado = ($tareas_activas >= 3);
                                    $esta_bloqueado = ($limite_alcanzado || $no_disponible_turno);
                                    
                                    if ($limite_alcanzado) {
                                        $texto_estatus_visual = 'Ocupado (3/3 tareas)';
                                    } elseif ($tareas_activas > 0) {
                                        $texto_estatus_visual = 'Disponible (' . $tareas_activas . '/3 tareas)';
                                    } else {
                                        $texto_estatus_visual = $no_disponible_turno ? 'No disponible' : 'Disponible (0/3 tareas)';
                                    }
                                    
                                    $clase_fila = $esta_bloqueado ? 'fila-ocupada' : 'fila-camarista';
                                    
                                    if ($no_disponible_turno) {
                                        $mensaje_alerta = "Este camarista se encuentra no disponible (fuera de turno).";
                                    } elseif ($limite_alcanzado) {
                                        $mensaje_alerta = "Este camarista ya ha alcanzado el límite máximo de 3 tareas activas.";
                                    } else {
                                        $mensaje_alerta = "";
                                    }
                                    
                                    $onclick_evento = $esta_bloqueado 
                                        ? "alert('" . $mensaje_alerta . "');" 
                                        : "seleccionarCamarista(" . $cam['id_camarista'] . ", '" . htmlspecialchars($cam['nombre_completo']) . " (" . $tareas_activas . "/3 tareas)', this)";
                                ?>
                                <tr class="<?php echo $clase_fila; ?>" onclick="<?php echo $onclick_evento; ?>">
                                    <td style="border-bottom: 1px solid #eee; width: 50%;"><strong><?php echo htmlspecialchars($cam['nombre_completo']); ?></strong></td>
                                    <td style="border-bottom: 1px solid #eee; width: 40%;">
                                        <small>Estado: <strong><?php echo $texto_estatus_visual; ?></strong></small>
                                    </td>
                                    <td style="border-bottom: 1px solid #eee; width: 10%; text-align: right;">
                                        <?php if (!$esta_bloqueado): ?>
                                            <button type="button" style="padding: 2px 6px;">Seleccionar</button>
                                        <?php else: ?>
                                            <span style="font-size: 11px; color: red;"><?php echo $limite_alcanzado ? 'Límite (3)' : 'No disponible'; ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <br>

            <div>
                <label for="descripcion">Descripción del Reporte:</label><br>
                <textarea id="descripcion" name="descripcion" rows="3" cols="50" required placeholder="Detalla el problema..."></textarea>
            </div>
            <br>

            <button type="submit" style="background-color: #007bff; color: white; border: none; border-radius: 4px; font-weight: bold;">Registrar y Asignar Tarea</button>
        </form>
    </fieldset>

    <!-- LISTADO DE INCIDENCIAS -->
    <h3>Historial y Estado de Incidencias</h3>
    <table border="1" cellpadding="8" cellspacing="0" style="width: 100%; background: #fff; border-collapse: collapse;">
        <thead>
            <tr style="background-color: #f2f2f2;">
                <th>ID</th>
                <th>Habitación</th>
                <th>Camarista Asignado</th>
                <th>Descripción</th>
                <th>Estatus Incidencia</th>
                <th>Fecha Reporte</th>
                <th>Cambiar Estatus</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($lista_incidencias)): ?>
                <tr>
                    <td colspan="8" style="text-align: center;">No hay incidencias registradas.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($lista_incidencias as $inc): ?>
                    <?php 
                        $estatus_c_lower = strtolower($inc['estatus_camarista'] ?? '');
                        $estatus_c_visual = ($estatus_c_lower === 'descanso') ? 'Disponible' : 'Ocupado';
                    ?>
                    <tr>
                        <td>#<?php echo $inc['id_incidencia']; ?></td>
                        <td>Hab. <?php echo htmlspecialchars($inc['numero_habitacion']); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($inc['camarista_asignado']); ?></strong>
                            <br><small>Estado: <?php echo $estatus_c_visual; ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($inc['descripcion']); ?></td>
                        <td>
                            <?php 
                                $color_estatus = 'chocolate';
                                if ($inc['estatus'] === 'Atendido' || $inc['estatus'] === 'atendido') {
                                    $color_estatus = 'green';
                                } elseif ($inc['estatus'] === 'en_camino') {
                                    $color_estatus = 'purple';
                                } elseif ($inc['estatus'] === 'En proceso') {
                                    $color_estatus = 'blue';
                                }
                            ?>
                            <span style="color: <?php echo $color_estatus; ?>; font-weight: bold;">
                                <?php echo htmlspecialchars($inc['estatus']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($inc['fecha_reporte']); ?></td>
                        <td>
                            <form action="incidencias.php" method="POST">
                                <input type="hidden" name="accion" value="actualizar_estatus">
                                <input type="hidden" name="id_incidencia" value="<?php echo $inc['id_incidencia']; ?>">
                                <select name="nuevo_estatus" onchange="this.form.submit()">
                                    <option value="" disabled>Cambiar estatus...</option>
                                    <option value="Pendiente" <?php echo ($inc['estatus'] === 'Pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                                    <option value="en_camino" <?php echo ($inc['estatus'] === 'en_camino') ? 'selected' : ''; ?>>En camino</option>
                                    <option value="En proceso" <?php echo ($inc['estatus'] === 'En proceso') ? 'selected' : ''; ?>>En proceso</option>
                                    <option value="Atendido" <?php echo ($inc['estatus'] === 'Atendido') ? 'selected' : ''; ?>>Atendido</option>
                                </select>
                            </form>
                        </td>
                        <td>
                            <a href="editar_incidencia.php?id_incidencia=<?php echo $inc['id_incidencia']; ?>">Editar</a> | 
                            <a href="eliminar_incidencia.php?id_incidencia=<?php echo $inc['id_incidencia']; ?>" onclick="return confirm('¿Estás seguro de eliminar esta incidencia?');">Borrar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- ZONA DE ELIMINACIÓN MASIVA / MANTENIMIENTO -->
    <fieldset style="margin-top: 20px;">
        <legend>Zona de Mantenimiento / Eliminación Masiva</legend>
        <p>Opciones para eliminar registros de la base de datos:</p>
        
        <div>
            <form action="incidencias.php" method="POST" onsubmit="return confirm('¿Estás seguro de eliminar los últimos 50 registros?');" style="display:inline;">
                <input type="hidden" name="accion" value="eliminar_lote">
                <input type="hidden" name="cantidad" value="50">
                <button type="submit" style="background-color: #ffc107; border: none; border-radius: 4px; font-weight: bold;">Eliminar Últimos 50</button>
            </form>

            <form action="incidencias.php" method="POST" onsubmit="return confirm('¿Estás seguro de eliminar los últimos 100 registros?');" style="display:inline;">
                <input type="hidden" name="accion" value="eliminar_lote">
                <input type="hidden" name="cantidad" value="100">
                <button type="submit" style="background-color: #fd7e14; color: white; border: none; border-radius: 4px; font-weight: bold;">Eliminar Últimos 100</button>
            </form>

            <form action="incidencias.php" method="POST" onsubmit="return confirm('¿Estás completamente seguro de vaciar toda la tabla de incidencias?');" style="display:inline;">
                <input type="hidden" name="accion" value="eliminar_todo">
                <button type="submit" style="background-color: #dc3545; color: white; border: none; border-radius: 4px; font-weight: bold;">Vaciar Toda la Tabla</button>
            </form>
        </div>
    </fieldset>

    <script>
        function filtrarHabitaciones() {
            let input = document.getElementById('buscador_hab').value.toLowerCase();
            let filas = document.getElementsByClassName('fila-habitacion');
            
            for (let i = 0; i < filas.length; i++) {
                let textoFila = filas[i].textContent || filas[i].innerText;
                if (textoFila.toLowerCase().indexOf(input) > -1) {
                    filas[i].style.display = "";
                } else {
                    filas[i].style.display = "none";
                }
            }
        }

        function seleccionarHabitacion(id, texto, elementoRow) {
            document.getElementById('id_habitacion_input').value = id;
            document.getElementById('texto_habitacion_seleccionada').innerText = "✅ Habitación seleccionada: " + texto;
            
            let filas = document.getElementsByClassName('fila-habitacion');
            let f;
            for (f of filas) {
                f.classList.remove('seleccionada');
            }
            elementoRow.classList.add('seleccionada');
        }

        function filtrarCamaristas() {
            let input = document.getElementById('buscador_cam').value.toLowerCase();
            let filas = document.getElementsByClassName('fila-camarista');
            
            for (let i = 0; i < filas.length; i++) {
                let textoFiltro = filas[i].textContent || filas[i].innerText;
                if (textoFiltro.toLowerCase().indexOf(input) > -1) {
                    filas[i].style.display = "";
                } else {
                    filas[i].style.display = "none";
                }
            }
        }

        function seleccionarCamarista(id, texto, elementoRow) {
            document.getElementById('id_camarista_input').value = id;
            document.getElementById('texto_camarista_seleccionado').innerText = "✅ Camarista seleccionado: " + texto;
            
            let filas = document.getElementsByClassName('fila-camarista');
            let f;
            for (f of filas) {
                f.classList.remove('seleccionada');
            }
            elementoRow.classList.add('seleccionada');
        }
    </script>

</body>
</html>