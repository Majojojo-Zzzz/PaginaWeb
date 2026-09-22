<?php
// 1. Iniciar sesión y validar autenticación
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

require_once 'config/conexion.php';

// RECUPERAR MENSAJE FLASH
$mensaje = $_SESSION['mensaje'] ?? '';
$tipo_mensaje = $_SESSION['tipo_mensaje'] ?? '';
unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);

// 2. Procesar la asignación del camarista para limpiar o dar mantenimiento a la habitación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_habitacion = $_POST['id_habitacion'] ?? null;
    $id_camarista  = $_POST['id_camarista'] ?? null;
    $tipo_tarea    = $_POST['tipo_tarea'] ?? 'limpieza';
    $descripcion_usr = trim($_POST['descripcion'] ?? '');

    if (!empty($id_habitacion) && !empty($id_camarista)) {
        try {
            $pdo->beginTransaction();

            // A. Verificar que el camarista esté en descanso (disponible)
            $stmt_c = $pdo->prepare("SELECT estatus FROM camaristas WHERE id_camarista = :id_camarista");
            $stmt_c->execute(['id_camarista' => $id_camarista]);
            $camarista = $stmt_c->fetch();

            if (!$camarista || strtolower($camarista['estatus']) !== 'descanso') {
                throw new Exception("El camarista seleccionado no se encuentra disponible.");
            }

            // B. Definir la descripción y estatus base según la tarea seleccionada o la ingresada por el usuario
            if ($tipo_tarea === 'mantenimiento') {
                $descripcion_base = 'Atención y revisión por reporte de Mantenimiento';
                $nuevo_estatus_hab = 'mantenimiento';
            } else {
                $descripcion_base = 'Limpieza por liberación de habitación (Check-out)';
                $nuevo_estatus_hab = 'limpieza';
            }

            // Si el usuario escribió una descripción propia, la usamos o la combinamos con la base
            $descripcion_final = !empty($descripcion_usr) ? $descripcion_usr : $descripcion_base;

            // C. Registrar esto como una incidencia/tarea automática
            $sql_inc = "INSERT INTO incidencias (id_habitacion, id_camarista, descripcion, estatus, fecha_reporte) 
                        VALUES (:id_habitacion, :id_camarista, :descripcion, 'Pendiente', NOW())";
            $stmt_inc = $pdo->prepare($sql_inc);
            $stmt_inc->execute([
                'id_habitacion' => $id_habitacion,
                'id_camarista'  => $id_camarista,
                'descripcion'   => $descripcion_final
            ]);

            // D. Cambiar el estatus del camarista a 'activo' (ocupado con esta tarea)
            $stmt_up_c = $pdo->prepare("UPDATE camaristas SET estatus = 'activo' WHERE id_camarista = :id_camarista");
            $stmt_up_c->execute(['id_camarista' => $id_camarista]);

            // E. Actualizar el estatus de la habitación
            $stmt_up_h = $pdo->prepare("UPDATE habitaciones SET estatus = :estatus WHERE id_habitacion = :id_habitacion");
            $stmt_up_h->execute([
                'estatus'       => $nuevo_estatus_hab,
                'id_habitacion' => $id_habitacion
            ]);

            $pdo->commit();

            $_SESSION['mensaje'] = "Camarista asignado exitosamente para atender la habitación.";
            $_SESSION['tipo_mensaje'] = "exito";

        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['mensaje'] = "Error al asignar camarista: " . $e->getMessage();
            $_SESSION['tipo_mensaje'] = "error";
        }
    } else {
        $_SESSION['mensaje'] = "Debe seleccionar una habitación y un camarista.";
        $_SESSION['tipo_mensaje'] = "error";
    }

    header("Location: noti_habitacion.php");
    exit;
}

// 3. Consultar habitaciones pendientes de limpieza
$stmt_limpieza = $pdo->query("SELECT id_habitacion, numero_habitacion, tipo_habitacion, estatus 
                              FROM habitaciones 
                              WHERE estatus = 'limpieza' OR estatus = 'sucia' 
                              ORDER BY numero_habitacion ASC");
$habitaciones_limpieza = $stmt_limpieza->fetchAll();

// 4. Consultar habitaciones pendientes de mantenimiento
$stmt_mantenimiento = $pdo->query("SELECT id_habitacion, numero_habitacion, tipo_habitacion, estatus 
                                     FROM habitaciones 
                                     WHERE estatus = 'mantenimiento' 
                                     ORDER BY numero_habitacion ASC");
$habitaciones_mantenimiento = $stmt_mantenimiento->fetchAll();

// 5. Consultar camaristas (ordenados para que los disponibles salgan primero)
$stmt_camaristas = $pdo->query("SELECT id_camarista, nombre_completo, estatus 
                                  FROM camaristas 
                                  ORDER BY FIELD(estatus, 'descanso', 'activo') ASC, nombre_completo ASC");
$lista_camaristas = $stmt_camaristas->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones - Habitaciones por Limpiar y Mantenimiento</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #fff;
            color: #000;
        }
        h2 {
            color: #000;
        }
        .tabla-selector-container {
            max-height: 180px;
            overflow-y: auto;
            border: 1px solid #999;
            background: #fff;
            margin-top: 5px;
            margin-bottom: 15px;
        }
        .fila-habitacion:hover, .fila-camarista:hover {
            background-color: #e5e5e5;
            cursor: pointer;
        }
        .fila-ocupada {
            background-color: #f2f2f2 !important;
            color: #666;
            cursor: not-allowed !important;
        }
        .seleccionada {
            background-color: #dcdcdc !important;
            font-weight: bold;
        }
        fieldset {
            background: #fff;
            border: 1px solid #999;
            padding: 15px;
            margin-bottom: 20px;
        }
        button {
            padding: 5px 10px;
            cursor: pointer;
        }
        input[type="text"], textarea {
            padding: 5px;
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #999;
        }
    </style>
</head>
<body>

    <h2>Notificaciones: Habitaciones Pendientes (Limpieza y Mantenimiento)</h2>
    <p>
        <a href="incidencias.php">Volver a Incidencias</a> | 
        <a href="home.php">Menú Principal</a>
    </p>

    <?php if (!empty($mensaje)): ?>
        <p style="font-weight: bold; border: 1px solid #999; padding: 8px;">
            <?php echo htmlspecialchars($mensaje); ?>
        </p>
    <?php endif; ?>

    <fieldset>
        <legend>Asignar Camarista a Habitación</legend>
        <form action="noti_habitacion.php" method="POST">
            
            <input type="hidden" name="id_habitacion" id="id_habitacion_input" required>
            <input type="hidden" name="tipo_tarea" id="tipo_tarea_input" value="limpieza">

            <!-- SECCIÓN 1: HABITACIONES PENDIENTES DE LIMPIEZA -->
            <div>
                <label><strong>Habitaciones pendientes de limpieza:</strong></label><br>
                <input type="text" id="buscador_limpieza" placeholder="Filtrar limpieza..." onkeyup="filtrarTabla('buscador_limpieza', 'tabla_limpieza')" style="margin-bottom: 5px;">
                <div class="tabla-selector-container">
                    <table id="tabla_limpieza" border="0" cellpadding="6" cellspacing="0" style="width: 100%; border-collapse: collapse;">
                        <tbody>
                            <?php if (empty($habitaciones_limpieza)): ?>
                                <tr>
                                    <td style="padding: 10px; text-align: center; color: #666;">No hay habitaciones pendientes de limpieza.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($habitaciones_limpieza as $hab): ?>
                                    <tr class="fila-habitacion" onclick="seleccionarHabitacion(<?php echo $hab['id_habitacion']; ?>, 'Hab. <?php echo htmlspecialchars($hab['numero_habitacion']); ?> (Limpieza)', 'limpieza', this)">
                                        <td style="border-bottom: 1px solid #ccc; width: 40%;"><strong>Hab. <?php echo htmlspecialchars($hab['numero_habitacion']); ?></strong></td>
                                        <td style="border-bottom: 1px solid #ccc; width: 40%;"><?php echo htmlspecialchars($hab['tipo_habitacion']); ?></td>
                                        <td style="border-bottom: 1px solid #ccc; width: 20%; text-align: right;"><button type="button">Seleccionar</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SECCIÓN 2: HABITACIONES PENDIENTES DE MANTENIMIENTO -->
            <div>
                <label><strong>Habitaciones pendientes de mantenimiento:</strong></label><br>
                <input type="text" id="buscador_mantenimiento" placeholder="Filtrar mantenimiento..." onkeyup="filtrarTabla('buscador_mantenimiento', 'tabla_mantenimiento')" style="margin-bottom: 5px;">
                <div class="tabla-selector-container">
                    <table id="tabla_mantenimiento" border="0" cellpadding="6" cellspacing="0" style="width: 100%; border-collapse: collapse;">
                        <tbody>
                            <?php if (empty($habitaciones_mantenimiento)): ?>
                                <tr>
                                    <td style="padding: 10px; text-align: center; color: #666;">No hay habitaciones pendientes de mantenimiento.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($habitaciones_mantenimiento as $hab): ?>
                                    <tr class="fila-habitacion" onclick="seleccionarHabitacion(<?php echo $hab['id_habitacion']; ?>, 'Hab. <?php echo htmlspecialchars($hab['numero_habitacion']); ?> (Mantenimiento)', 'mantenimiento', this)">
                                        <td style="border-bottom: 1px solid #ccc; width: 40%;"><strong>Hab. <?php echo htmlspecialchars($hab['numero_habitacion']); ?></strong></td>
                                        <td style="border-bottom: 1px solid #ccc; width: 40%;"><?php echo htmlspecialchars($hab['tipo_habitacion']); ?></td>
                                        <td style="border-bottom: 1px solid #ccc; width: 20%; text-align: right;"><button type="button">Seleccionar</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- INDICADOR DE SELECCIÓN DE HABITACIÓN -->
            <div id="texto_habitacion_seleccionada" style="margin: 10px 0; font-weight: bold; border: 1px solid #999; padding: 6px; background: #f0f0f0;">
                Habitación seleccionada: Ninguna
            </div>
            <br>

            <!-- SECCIÓN 3: CAMARISTAS DISPONIBLES -->
            <div>
                <label><strong>Camaristas disponibles:</strong></label><br>
                <input type="text" id="buscador_camaristas" placeholder="Filtrar camaristas..." onkeyup="filtrarTabla('buscador_camaristas', 'tabla_camaristas')" style="margin-bottom: 5px;">
                <input type="hidden" name="id_camarista" id="id_camarista_input" required>
                
                <div class="tabla-selector-container">
                    <table id="tabla_camaristas" border="0" cellpadding="6" cellspacing="0" style="width: 100%; border-collapse: collapse;">
                        <tbody>
                            <?php foreach ($lista_camaristas as $cam): ?>
                                <?php 
                                    $ocupado = (strtolower($cam['estatus']) !== 'descanso');
                                    $clase = $ocupado ? 'fila-ocupada' : 'fila-camarista';
                                    $onclick = $ocupado ? "alert('Este camarista está ocupado actualmente.');" : "seleccionarCamarista(" . $cam['id_camarista'] . ", '" . htmlspecialchars($cam['nombre_completo']) . "', this)";
                                ?>
                                <tr class="<?php echo $clase; ?>" onclick="<?php echo $onclick; ?>">
                                    <td style="border-bottom: 1px solid #ccc; width: 60%;"><strong><?php echo htmlspecialchars($cam['nombre_completo']); ?></strong></td>
                                    <td style="border-bottom: 1px solid #ccc; width: 40%;">Estado: <strong><?php echo $ocupado ? 'Ocupado' : 'Disponible'; ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- INDICADOR DE SELECCIÓN DE CAMARISTA -->
            <div id="texto_camarista_seleccionado" style="margin: 10px 0; font-weight: bold; border: 1px solid #999; padding: 6px; background: #f0f0f0;">
                Camarista seleccionado: Ninguno
            </div>
            <br>

            <!-- SECCIÓN 4: DESCRIPCIÓN DE LA TAREA -->
            <div>
                <label for="descripcion"><strong>Descripción de la tarea / detalles (Opcional):</strong></label><br>
                <textarea id="descripcion" name="descripcion" rows="3" placeholder="Detalles adicionales sobre la limpieza o mantenimiento..."></textarea>
            </div>
            <br>

            <button type="submit" style="padding: 8px 15px; font-weight: bold;">Asignar Tarea a Camarista</button>
        </form>
    </fieldset>

    <script>
        function filtrarTabla(idInput, idTabla) {
            let input = document.getElementById(idInput).value.toLowerCase();
            let tabla = document.getElementById(idTabla);
            let filas = tabla.getElementsByTagName('tr');
            
            for (let i = 0; i < filas.length; i++) {
                let textoFila = filas[i].textContent || filas[i].innerText;
                if (textoFila.toLowerCase().indexOf(input) > -1) {
                    filas[i].style.display = "";
                } else {
                    filas[i].style.display = "none";
                }
            }
        }

        function seleccionarHabitacion(id, texto, tipoTarea, elemento) {
            document.getElementById('id_habitacion_input').value = id;
            document.getElementById('tipo_tarea_input').value = tipoTarea;
            document.getElementById('texto_habitacion_seleccionada').innerText = "Habitación seleccionada: " + texto;
            
            let tablas = ['tabla_limpieza', 'tabla_mantenimiento'];
            tablas.forEach(idT => {
                let filas = document.getElementById(idT).getElementsByClassName('fila-habitacion');
                for (let f of filas) f.classList.remove('seleccionada');
            });
            
            elemento.classList.add('seleccionada');
        }

        function seleccionarCamarista(id, texto, elemento) {
            document.getElementById('id_camarista_input').value = id;
            document.getElementById('texto_camarista_seleccionado').innerText = "Camarista seleccionado: " + texto;
            
            let filas = document.getElementsByClassName('fila-camarista');
            for (let f of filas) f.classList.remove('seleccionada');
            
            elemento.classList.add('seleccionada');
        }
    </script>

</body>
</html>