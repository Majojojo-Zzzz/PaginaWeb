<?php
// 1. Iniciar sesión, configurar zona horaria de México y validar autenticación
session_start();
date_default_timezone_set('America/Mexico_City');

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

$rol_usuario = isset($_SESSION['rol']) ? strtolower(trim($_SESSION['rol'])) : '';

require_once 'config/conexion.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// RECUPERAR MENSAJE FLASH DE LA SESIÓN (SI EXISTE) Y LIMPIARLO
$mensaje = $_SESSION['mensaje'] ?? '';
$tipo_mensaje = $_SESSION['tipo_mensaje'] ?? '';
unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);

// 2. Verificar si el usuario actual tiene un TURNO ABIERTO
$stmt_turno = $pdo->prepare("SELECT id_turno FROM turnos WHERE id_usuario = :id_usuario AND estatus = 'abierto' LIMIT 1");
$stmt_turno->execute(['id_usuario' => $_SESSION['id_usuario']]);
$turno_activo = $stmt_turno->fetch();

$id_turno_actual = $turno_activo ? $turno_activo['id_turno'] : null;

// 3. Obtener habitaciones DISPONIBLES y tipos únicos para el filtro
$stmt_hab = $pdo->query("SELECT id_habitacion, numero_habitacion, tipo_habitacion, precio, cantidad_personas 
                         FROM habitaciones 
                         WHERE estatus = 'disponible' 
                         ORDER BY numero_habitacion ASC");
$habitaciones_disponibles = $stmt_hab->fetchAll();

// Extraer tipos de habitación únicos para el select del filtro
$tipos_habitacion = array_unique(array_column($habitaciones_disponibles, 'tipo_habitacion'));

// 4. Obtener la lista de huéspedes para selección en tabla
$stmt_huespedes = $pdo->query("SELECT id_huesped, identificador, nombre, apellido_p, apellido_m, telefono, correo 
                              FROM huesped 
                              ORDER BY id_huesped DESC");
$lista_huespedes = $stmt_huespedes->fetchAll();

// 5. Procesar el Formulario de Check-In Múltiple
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$id_turno_actual) {
        $_SESSION['mensaje'] = "Atención: Debes tener un turno abierto para registrar estancias.";
        $_SESSION['tipo_mensaje'] = "error";
        header("Location: check_in.php");
        exit;
    } else {
        $tipo_huesped     = $_POST['tipo_huesped'] ?? 'nuevo';
        $id_huesped       = $_POST['id_huesped_existente'] ?? null;
        
        $nombre_h         = trim($_POST['nombre_huesped'] ?? '');
        $apellido_p_h     = trim($_POST['apellido_p_huesped'] ?? '');
        $apellido_m_h     = trim($_POST['apellido_m_huesped'] ?? '');
        $telefono_h       = trim($_POST['telefono_huesped'] ?? '');
        $correo_h         = trim($_POST['correo_huesped'] ?? '');
        $nacionalidad_h   = trim($_POST['nacionalidad_huesped'] ?? 'México');

        // Array de habitaciones seleccionadas
        $ids_habitaciones = $_POST['ids_habitaciones'] ?? [];
        
        $fecha_entrada_solo = $_POST['fecha_entrada'] ?? date('Y-m-d');
        $fecha_salida_solo  = $_POST['fecha_salida'] ?? '';
        
        $hora_actual_mexico = date('H:i:s');
        $fecha_entrada      = $fecha_entrada_solo . ' ' . $hora_actual_mexico;
        $fecha_salida       = $fecha_salida_solo . ' ' . $hora_actual_mexico;

        $monto_pago       = $_POST['monto_pago'] ?? 0.00;
        $metodo_pago      = $_POST['metodo_pago'] ?? 'Efectivo';

        if (!empty($ids_habitaciones) && !empty($fecha_salida_solo)) {
            try {
                $pdo->beginTransaction();

                if ($tipo_huesped === 'nuevo') {
                    if (empty($nombre_h) || empty($apellido_p_h) || empty($apellido_m_h)) {
                        throw new Exception("Debes completar el Nombre, Apellido Paterno y Apellido Materno del huésped.");
                    }

                    $stmt_id_h = $pdo->query("SELECT identificador FROM huesped WHERE identificador LIKE 'HDP%' ORDER BY id_huesped DESC LIMIT 1");
                    $ultimo_identificador_h = $stmt_id_h->fetchColumn();

                    if ($ultimo_identificador_h) {
                        $numero_actual_h = (int)substr($ultimo_identificador_h, 3);
                        $nuevo_numero_h = $numero_actual_h + 1;
                    } else {
                        $nuevo_numero_h = 1;
                    }

                    $identificador_huesped_auto = "HDP" . str_pad($nuevo_numero_h, 3, "0", STR_PAD_LEFT);

                    $sql_huesped = "INSERT INTO huesped (identificador, nombre, apellido_p, apellido_m, telefono, correo, nacionalidad) 
                                     VALUES (:identificador, :nombre, :apellido_p, :apellido_m, :telefono, :correo, :nacionalidad)";
                    $stmt_h = $pdo->prepare($sql_huesped);
                    $stmt_h->execute([
                        'identificador' => $identificador_huesped_auto,
                        'nombre'        => $nombre_h,
                        'apellido_p'    => $apellido_p_h,
                        'apellido_m'    => $apellido_m_h,
                        'telefono'      => $telefono_h,
                        'correo'        => $correo_h,
                        'nacionalidad'  => $nacionalidad_h
                    ]);
                    $id_huesped = $pdo->lastInsertId();
                }

                if (!$id_huesped) {
                    throw new Exception("No se ha seleccionado ni registrado un huésped válido.");
                }

                $primer_id_estancia = null;
                $monto_por_habitacion = count($ids_habitaciones) > 0 ? ($monto_pago / count($ids_habitaciones)) : 0;

                foreach ($ids_habitaciones as $id_habitacion) {
                    $sql_estancia = "INSERT INTO estancias (id_huesped, id_habitacion, fecha_entrada, fecha_salida, estatus_estancia, id_usuario_checkin, id_turno) 
                                     VALUES (:id_huesped, :id_habitacion, :fecha_entrada, :fecha_salida, 'Activa', :id_usuario, :id_turno)";
                    $stmt_estancia = $pdo->prepare($sql_estancia);
                    $stmt_estancia->execute([
                        'id_huesped'    => $id_huesped,
                        'id_habitacion' => $id_habitacion,
                        'fecha_entrada' => $fecha_entrada,
                        'fecha_salida'  => $fecha_salida,
                        'id_usuario'    => $_SESSION['id_usuario'],
                        'id_turno'      => $id_turno_actual
                    ]);
                    $id_estancia = $pdo->lastInsertId();

                    if (!$primer_id_estancia) {
                        $primer_id_estancia = $id_estancia;
                    }

                    $sql_hab_update = "UPDATE habitaciones SET estatus = 'ocupada', id_huesped = :id_huesped WHERE id_habitacion = :id_habitacion";
                    $stmt_hab_up = $pdo->prepare($sql_hab_update);
                    $stmt_hab_up->execute([
                        'id_huesped'    => $id_huesped,
                        'id_habitacion' => $id_habitacion
                    ]);

                    if ($monto_pago > 0) {
                        $sql_pago = "INSERT INTO pagos_estancias (id_estancia, metodo_pago, monto, fecha_pago, id_usuario, id_turno) 
                                     VALUES (:id_estancia, :metodo_pago, :monto, NOW(), :id_usuario, :id_turno)";
                        $stmt_pago = $pdo->prepare($sql_pago);
                        $stmt_pago->execute([
                            'id_estancia' => $id_estancia,
                            'metodo_pago' => $metodo_pago,
                            'monto'       => $monto_por_habitacion,
                            'id_usuario'  => $_SESSION['id_usuario'],
                            'id_turno'    => $id_turno_actual
                        ]);
                    }
                }

                $pdo->commit();
                
                $_SESSION['mensaje'] = "El Check-In se guardó con éxito";
                $_SESSION['tipo_mensaje'] = "success";
                
                header("Location: reportes/pdf_check_in.php?id_estancia=" . $primer_id_estancia);
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['mensaje'] = "Atención: Error al procesar el Check-In: " . $e->getMessage();
                $_SESSION['tipo_mensaje'] = "error";
                header("Location: check_in.php");
                exit;
            }
        } else {
            $_SESSION['mensaje'] = "Atención: Por favor, selecciona al menos una habitación y completa los datos requeridos.";
            $_SESSION['tipo_mensaje'] = "error";
            header("Location: check_in.php");
            exit;
        }
    }
}

include 'includes/header.php';
?>

<!-- Estilos optimizados y regla estricta de cursor -->
<style>
    body > div > main, 
    body > div > main * {
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }

    input, textarea, select, header, aside, nav {
        -webkit-user-select: text;
        -moz-user-select: text;
        -ms-user-select: text;
        user-select: text;
    }

    /* Regla absoluta para forzar el cursor de prohibido en todo el formulario si no hay turno */
    .sin-turno-activo, .sin-turno-activo * {
        cursor: not-allowed !important;
    }

    @keyframes fadeInOut {
        0% { opacity: 0; transform: translateY(-20px); }
        8% { opacity: 1; transform: translateY(0); }
        92% { opacity: 1; transform: translateY(0); }
        100% { opacity: 0; transform: translateY(-20px); }
    }
    .animate-toast-5s {
        animation: fadeInOut 5s ease-in-out forwards;
    }
</style>

<!-- CONTENEDOR PARA NOTIFICACIONES FLOTANTES (TOASTS) -->
<div id="contenedorNotificaciones" class="fixed top-5 right-5 z-[9999] flex flex-col gap-2 pointer-events-none"></div>

<!-- CUERPO ESPECÍFICO DE CHECK-IN -->
<div class="px-4 md:px-8 py-3 md:py-4 space-y-4 max-w-7xl mx-auto w-full text-gray-700 relative">

    <!-- ENCABEZADO CON BOTONES DE NAVEGACIÓN ESTÁNDAR -->
    <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-gray-200">
        <div class="flex items-center gap-2">
            <img src="icons/calendario.png" alt="Calendario" class="w-4 h-4 object-contain">
            <h1 class="text-sm md:text-base font-bold text-gray-800 tracking-wide">Registro de llegada del huesped</h1>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button onclick="window.location.href='habitaciones.php'" class="px-3 py-1.5 bg-white text-gray-700 border border-gray-300 text-xs font-semibold rounded-xl hover:bg-gray-50 transition-colors shadow-sm flex items-center gap-2">
                <img src="icons/cama.png" alt="Habitaciones" class="w-3.5 h-3.5 object-contain">
                <span>Habitaciones</span>
            </button>
            <button onclick="window.location.href='huespedes.php'" class="px-3 py-1.5 bg-white text-gray-700 border border-gray-300 text-xs font-semibold rounded-xl hover:bg-gray-50 transition-colors shadow-sm flex items-center gap-2">
                <img src="icons/huesped.png" alt="Huéspedes" class="w-3.5 h-3.5 object-contain">
                <span>Huéspedes</span>
            </button>
            <button onclick="window.location.href='check_out.php'" class="px-3 py-1.5 bg-white text-gray-700 border border-gray-300 text-xs font-semibold rounded-xl hover:bg-gray-50 transition-colors shadow-sm flex items-center gap-2">
                <img src="icons/check-out.png" alt="Check-Out" class="w-3.5 h-3.5 object-contain">
                <span>Check-Out</span>
            </button>
        </div>
    </div>

    <!-- FORMULARIO PRINCIPAL DE CHECK-IN -->
    <div class="relative">
        <form id="formCheckIn" action="check_in.php" method="POST" class="space-y-4 <?php echo (!$id_turno_actual) ? 'sin-turno-activo' : ''; ?>">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                
                <!-- COLUMNA IZQUIERDA Y CENTRO -->
                <div class="lg:col-span-2 space-y-4">
                    
                    <!-- SECCIÓN 1: DATOS DEL HUÉSPED -->
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm space-y-4 text-xs text-gray-600">
                        <h2 class="text-xs md:text-sm font-semibold text-gray-800 border-b border-gray-100 pb-2 flex items-center gap-2">
                            <img src="icons/huesped.png" alt="Huésped" class="w-4 h-4 object-contain">
                            <span>1. Información personal del huésped</span>
                        </h2>
                        
                        <div class="flex items-center gap-6">
                            <label id="label_nuevo_huesped" class="flex items-center gap-2 cursor-pointer font-medium text-gray-700">
                                <input type="radio" id="radio_nuevo" name="tipo_huesped" value="nuevo" checked onclick="toggleHuesped('nuevo')" class="text-black focus:ring-black"> Nuevo Huésped
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer font-medium text-gray-700">
                                <input type="radio" id="radio_existente" name="tipo_huesped" value="existente" onclick="toggleHuesped('existente')" class="text-black focus:ring-black"> Huésped Existente
                            </label>
                        </div>

                        <!-- Selección de huésped existente mediante tabla y barra de búsqueda -->
                        <div id="sec_existente" class="hidden space-y-3">
                            <input type="hidden" id="id_huesped_existente" name="id_huesped_existente" value="">
                            
                            <div id="contenedor_buscador_huesped" class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <label class="font-medium text-gray-700">Seleccionar Huésped (Haz clic en la fila):</label>
                                <div class="relative flex items-center w-full sm:w-64">
                                    <img src="icons/lupa.png" alt="Buscar" class="w-3.5 h-3.5 absolute left-2.5 object-contain pointer-events-none opacity-60">
                                    <input type="text" id="filtro_huespedes" placeholder="Buscar..." onkeyup="filtrarTablaHuespedes()" class="w-full pl-7 pr-3 py-1.5 bg-gray-50 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black text-[11px]">
                                </div>
                            </div>

                            <div id="info_huesped_seleccionado" class="hidden p-2.5 bg-blue-50 border border-blue-200 rounded-lg flex items-center justify-between text-xs text-blue-900">
                                <div>
                                    <span class="font-bold">Huésped seleccionado:</span> <span id="nombre_huesped_seleccionado" class="font-medium"></span>
                                </div>
                                <button type="button" onclick="limpiarHuespedSeleccionado()" class="text-red-600 hover:text-red-800 font-semibold text-[11px] underline">Quitar</button>
                            </div>

                            <div id="contenedor_tabla_huespedes" class="border border-gray-200 rounded-lg overflow-hidden max-h-[220px] overflow-y-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead class="bg-gray-50 text-gray-700 sticky top-0 border-b border-gray-200 text-[11px]">
                                        <tr>
                                            <th class="p-2 font-semibold">ID</th>
                                            <th class="p-2 font-semibold">Nombre Completo</th>
                                            <th class="p-2 font-semibold">Teléfono</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tabla_huespedes_cuerpo" class="divide-y divide-gray-100">
                                        <?php if (empty($lista_huespedes)): ?>
                                            <tr>
                                                <td colspan="3" class="p-4 text-center text-gray-400">No hay huéspedes registrados.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($lista_huespedes as $h): ?>
                                                <tr class="fila-huesped cursor-pointer hover:bg-blue-50/60 transition-colors" 
                                                    data-busqueda="<?php echo htmlspecialchars(strtolower($h['identificador'] . ' ' . $h['nombre'] . ' ' . $h['apellido_p'] . ' ' . $h['apellido_m'])); ?>"
                                                    onclick="seleccionarHuesped(<?php echo $h['id_huesped']; ?>, '<?php echo htmlspecialchars($h['identificador'] . ' - ' . $h['nombre'] . ' ' . $h['apellido_p'] . ' ' . $h['apellido_m'], ENT_QUOTES); ?>', this)">
                                                    <td class="p-2 font-mono text-[11px] text-gray-600"><?php echo htmlspecialchars($h['identificador']); ?></td>
                                                    <td class="p-2 font-medium text-gray-900"><?php echo htmlspecialchars($h['nombre'] . ' ' . $h['apellido_p'] . ' ' . $h['apellido_m']); ?></td>
                                                    <td class="p-2 text-gray-600"><?php echo htmlspecialchars($h['telefono'] ?: 'N/D'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Formulario para nuevo huésped -->
                        <div id="sec_nuevo" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="space-y-1">
                                <label class="font-medium text-gray-700">Nombre(s) <span class="text-red-500 text-sm font-bold">*</span></label>
                                <input type="text" name="nombre_huesped" id="input_nombre_huesped" placeholder="Ej. Juan Carlos" class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black">
                            </div>
                            <div class="space-y-1">
                                <label class="font-medium text-gray-700">Apellido Paterno <span class="text-red-500 text-sm font-bold">*</span></label>
                                <input type="text" name="apellido_p_huesped" id="input_apellido_p_huesped" placeholder="Ej. Pérez" class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black">
                            </div>
                            <div class="space-y-1">
                                <label class="font-medium text-gray-700">Apellido Materno <span class="text-red-500 text-sm font-bold">*</span></label>
                                <input type="text" name="apellido_m_huesped" id="input_apellido_m_huesped" placeholder="Ej. Gómez" class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black">
                            </div>
                            <div class="space-y-1">
                                <label class="font-medium text-gray-700">Teléfono</label>
                                <input type="text" name="telefono_huesped" id="input_telefono_huesped" placeholder="Ej. 5512345678" class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black">
                            </div>
                            <div class="space-y-1">
                                <label class="font-medium text-gray-700">Correo Electrónico</label>
                                <input type="email" name="correo_huesped" id="input_correo_huesped" placeholder="Ej. correo@ejemplo.com" class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black">
                            </div>
                            <div class="space-y-1">
                                <label class="font-medium text-gray-700">País</label>
                                <select id="nacionalidad_huesped" name="nacionalidad_huesped" class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black">
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: SELECCIÓN MÚLTIPLE DE HABITACIONES -->
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm space-y-4 text-xs text-gray-600">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-gray-100 pb-2 gap-3">
                            <h2 class="text-xs md:text-sm font-semibold text-gray-800 flex items-center gap-2">
                                <img src="icons/cama.png" alt="Habitación" class="w-4 h-4 object-contain">
                                <span>2. Selección de habitaciones <span class="text-red-500 text-sm font-bold">*</span></span>
                            </h2>
                            
                            <?php if ($id_turno_actual): ?>
                                <div class="flex items-center gap-2">
                                    <div class="relative flex items-center">
                                        <img src="icons/lupa.png" alt="Buscar" class="w-3.5 h-3.5 absolute left-2.5 object-contain pointer-events-none opacity-60">
                                        <input type="text" id="filtro_numero" placeholder="Buscar..." onkeyup="filtrarHabitaciones()" class="pl-7 pr-2 py-1.5 bg-gray-50 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black text-[11px] w-36">
                                    </div>
                                    <select id="filtro_tipo" onchange="filtrarHabitaciones()" class="px-2 py-1.5 bg-gray-50 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black text-[11px]">
                                        <option value="todos">Todos los tipos</option>
                                        <?php foreach ($tipos_habitacion as $tipo): ?>
                                            <option value="<?php echo htmlspecialchars(strtolower($tipo)); ?>"><?php echo htmlspecialchars(ucfirst($tipo)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!$id_turno_actual): ?>
                            <div class="p-4 text-center text-black font-medium text-xs flex items-center justify-center gap-2">
                                <img src="icons/alerta.png" alt="Alerta" class="w-4 h-4 object-contain">
                                <span>Atención: No tienes ningún turno abierto, por favor inicia uno</span>
                            </div>
                        <?php elseif (empty($habitaciones_disponibles)): ?>
                            <div class="p-4 text-center text-gray-400 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                                No hay habitaciones disponibles en este momento.
                            </div>
                        <?php else: ?>
                            <div id="contenedor_habitaciones" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2.5 max-h-[290px] overflow-y-auto pr-1">
                                <?php foreach ($habitaciones_disponibles as $hab): ?>
                                    <div onclick="toggleHabitacion(this, '<?php echo $hab['id_habitacion']; ?>', '<?php echo $hab['precio']; ?>')"
                                         class="card-habitacion cursor-pointer border border-[#bbf7d0] rounded-lg p-2.5 text-left transition-all hover:shadow-sm bg-white hover:border-[#86efac] flex flex-col justify-between gap-0.5 relative"
                                         data-numero="<?php echo htmlspecialchars($hab['numero_habitacion']); ?>"
                                         data-tipo="<?php echo htmlspecialchars(strtolower($hab['tipo_habitacion'])); ?>">
                                        
                                        <input type="checkbox" name="ids_habitaciones[]" value="<?php echo $hab['id_habitacion']; ?>" data-precio="<?php echo $hab['precio']; ?>" class="hidden checkbox-hab">

                                        <div class="font-bold text-gray-900 text-xs flex items-center gap-1.5">
                                            <img src="icons/cama.png" alt="Cama" class="w-3.5 h-3.5 object-contain">
                                            <span><?php echo htmlspecialchars($hab['numero_habitacion']); ?></span>
                                        </div>
                                        <div class="text-[10px] text-gray-500 truncate">
                                            <?php echo htmlspecialchars(ucfirst($hab['tipo_habitacion'])); ?>
                                        </div>
                                        <div class="text-[10px] text-gray-600">
                                            <?php echo (int)$hab['cantidad_personas']; ?> Personas
                                        </div>
                                        <div class="text-[11px] font-semibold text-black mt-0.5">
                                            $ <?php echo number_format($hab['precio'], 2); ?> por noche
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- COLUMNA DERECHA: RESUMEN Y TOTAL A PAGAR -->
                <div class="space-y-4">
                    
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm space-y-3 text-xs text-gray-600">
                        <h2 class="text-xs md:text-sm font-semibold text-gray-800 border-b border-gray-100 pb-2 flex items-center gap-2">
                            <img src="icons/pago.png" alt="Pago" class="w-4 h-4 object-contain">
                            <span>Resumen y Total a pagar</span>
                        </h2>

                        <div class="space-y-1">
                            <label class="font-medium text-gray-700">Fecha de Entrada <span class="text-red-500 text-sm font-bold">*</span></label>
                            <input type="date" id="fecha_entrada" name="fecha_entrada" required onchange="calcularTotalPorFechas()" class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black">
                        </div>

                        <div class="space-y-1">
                            <label class="font-medium text-gray-700">Fecha de Salida <span class="text-red-500 text-sm font-bold">*</span></label>
                            <input type="date" id="fecha_salida" name="fecha_salida" required onchange="calcularTotalPorFechas()" class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black">
                        </div>

                        <div class="space-y-1 pt-1">
                            <label class="font-medium text-gray-700">Número de Noches</label>
                            <input type="number" id="noches_calculadas" min="1" value="1" oninput="calcularTotalByNoches()" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-800 font-bold text-center focus:outline-none focus:ring-1 focus:ring-black">
                        </div>

                        <div class="space-y-1">
                            <label class="font-medium text-gray-700">Total a Pagar</label>
                            <input type="text" id="total_estancia" readonly value="$0.00" class="w-full px-3 py-2.5 border border-green-500 rounded-lg bg-gray-50 text-gray-900 font-extrabold text-center text-sm tracking-wide">
                        </div>

                        <div class="space-y-1">
                            <label class="font-medium text-gray-700">Método de Pago</label>
                            <select id="metodo_pago" name="metodo_pago" onchange="verificarMetodoPago()" class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black">
                                <option value="Efectivo">Efectivo</option>
                                <option value="Transferencia">Transferencia</option>
                            </select>
                        </div>

                        <!-- Fila compartida para Pago con y Cambio -->
                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-1">
                                <label id="label_pago_con" class="font-medium text-gray-700">Pago con ($)</label>
                                <input type="number" id="pago_con" step="0.01" min="0" value="" placeholder="0.00" oninput="calcularCambio()" class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black font-bold text-xs">
                            </div>
                            <div class="space-y-1">
                                <label id="label_cambio" class="font-medium text-gray-700">Cambio ($)</label>
                                <input type="text" id="cambio" readonly value="0.00" class="w-full px-3 py-2 border border-blue-200 rounded-lg bg-blue-50 text-blue-700 font-extrabold text-center text-xs">
                            </div>
                        </div>

                        <!-- Campo oculto que envía exactamente el Total a Pagar a la base de datos -->
                        <input type="hidden" id="monto_pago" name="monto_pago" value="0.00">

                        <div class="pt-2">
                            <button type="submit" id="btnSubmit" class="w-full py-2.5 bg-black hover:bg-gray-800 text-white font-semibold rounded-xl transition-colors shadow-sm flex items-center justify-center gap-2">
                                <span>Check-In</span>
                            </button>
                        </div>
                    </div>

                </div>

            </div>

        </form>
    </div>

</div>
</main>
</div>

<script>
    const tieneTurnoActivo = <?php echo $id_turno_actual ? 'true' : 'false'; ?>;

    function mostrarNotificacion(mensaje, tipo = 'success') {
        const contenedor = document.getElementById('contenedorNotificaciones');
        const toast = document.createElement('div');
        
        let bgColor = '';
        if (tipo === 'success') {
            bgColor = 'bg-emerald-600 text-white border-emerald-700 shadow-emerald-950/20';
        } else if (tipo === 'error' || tipo === 'danger') {
            bgColor = 'bg-red-600 text-white border-red-700 shadow-red-950/20';
        } else {
            bgColor = 'bg-gray-900 text-white border-gray-800';
        }
        
        toast.className = `pointer-events-auto px-4 py-3 rounded-xl shadow-xl text-xs font-semibold border flex items-center gap-2 animate-toast-5s ${bgColor}`;
        toast.innerHTML = `<span>${mensaje}</span>`;
        
        contenedor.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 5000);
    }

    <?php if (!empty($mensaje)): ?>
        window.addEventListener('DOMContentLoaded', () => {
            mostrarNotificacion(<?php echo json_encode($mensaje); ?>, <?php echo json_encode($tipo_mensaje === 'success' ? 'success' : 'error'); ?>);
        });
    <?php elseif (!$id_turno_actual): ?>
        window.addEventListener('DOMContentLoaded', () => {
            mostrarNotificacion("Atención: No tienes un turno abierto, por favor inicia uno.", "error");
        });
    <?php endif; ?>

    const listaPaises = [
        "Afganistán", "Alemania", "Andorra", "Angola", "Antigua y Barbuda", "Arabia Saudita", "Argelia", "Argentina", "Armenia", "Australia", "Austria", "Azerbaiyán",
        "Bahamas", "Bangladés", "Barbados", "Baréin", "Bélgica", "Belice", "Benín", "Bielorrusia", "Birmania", "Bolivia", "Bosnia y Herzegovina", "Botsuana", "Brasil", "Brunéi", "Bulgaria", "Burkina Faso", "Burundi", "Bután",
        "Cabo Verde", "Camboya", "Camerún", "Canadá", "Catar", "Chad", "Chile", "China", "Chipre", "Colombia", "Comoras", "Corea del Norte", "Corea del Sur", "Costa de Marfil", "Costa Rica", "Croacia", "Cuba",
        "Dinamarca", "Dominica",
        "Ecuador", "Egipto", "El Salvador", "Emiratos Árabes Unidos", "Eritrea", "Eslovaquia", "Eslovenia", "España", "Estados Unidos", "Estonia", "Etiopía",
        "Filipinas", "Finlandia", "Fiyi", "Francia",
        "Gabón", "Gambia", "Georgia", "Ghana", "Granada", "Grecia", "Guatemala", "Guinea", "Guinea Ecuatorial", "Guinea-Bisáu", "Guyana",
        "Haití", "Honduras", "Hungría",
        "India", "Indonesia", "Irak", "Irán", "Irlanda", "Islandia", "Islas Marshall", "Islas Salomón", "Israel", "Italia",
        "Jamaica", "Japón", "Jordania",
        "Kazajistán", "Kenia", "Kirguistán", "Kiribati", "Kuwait",
        "Laos", "Lesoto", "Letonia", "Líbano", "Liberia", "Libia", "Liechtenstein", "Lituania", "Luxemburgo",
        "Macedonia del Norte", "Madagascar", "Malaui", "Malasia", "Maldivas", "Malí", "Malta", "Marruecos", "Mauricio", "Mauritania", "México", "Micronesia", "Moldavia", "Mónaco", "Mongolia", "Montenegro", "Mozambique",
        "Namibia", "Nauru", "Nepal", "Nicaragua", "Níger", "Nigeria", "Noruega", "Nueva Zelanda",
        "Omán",
        "Países Bajos", "Pakistán", "Palaos", "Panamá", "Papúa Nueva Guinea", "Paraguay", "Perú", "Polonia", "Portugal",
        "Reino Unido", "República Centroafricana", "República Checa", "República del Congo", "República Democrática del Congo", "República Dominicana", "Ruanda", "Rumanía", "Rusia",
        "Samoa", "San Marino", "San Vicente y las Granadinas", "Santa Lucía", "Santo Tomé y Príncipe", "Senegal", "Serbia", "Seychelles", "Sierra Leona", "Singapur", "Siria", "Somalia", "Sri Lanka", "Suazilandia", "Sudáfrica", "Sudán", "Sudán del Sur", "Suecia", "Suiza", "Surinam",
        "Tailandia", "Tanzania", "Tayikistán", "Timor Oriental", "Togo", "Tonga", "Trinidad y Tobago", "Túnez", "Turkmenistán", "Turquía", "Tuvalu",
        "Ucrania", "Uganda", "Uruguay", "Uzbekistán",
        "Vanuatu", "Venezuela", "Vietnam",
        "Yemen", "Yibuti",
        "Zambia", "Zimbabue"
    ];

    function cargarPaises() {
        const select = document.getElementById('nacionalidad_huesped');
        select.innerHTML = '';
        listaPaises.forEach(pais => {
            const option = document.createElement('option');
            option.value = pais;
            option.textContent = pais;
            if (pais === 'México') option.selected = true;
            select.appendChild(option);
        });
    }

    function toggleHuesped(tipo) {
        if (!tieneTurnoActivo) return;
        if (tipo === 'existente') {
            document.getElementById('sec_existente').classList.remove('hidden');
            document.getElementById('sec_nuevo').classList.add('hidden');
        } else {
            document.getElementById('sec_existente').classList.add('hidden');
            document.getElementById('sec_nuevo').classList.remove('hidden');
        }
    }

    function filtrarTablaHuespedes() {
        if (!tieneTurnoActivo) return;
        const texto = document.getElementById('filtro_huespedes').value.toLowerCase();
        document.querySelectorAll('.fila-huesped').forEach(fila => {
            fila.style.display = fila.getAttribute('data-busqueda').includes(texto) ? '' : 'none';
        });
    }

    function seleccionarHuesped(idHuesped, nombreCompleto, filaElemento) {
        if (!tieneTurnoActivo) return;
        document.getElementById('id_huesped_existente').value = idHuesped;
        document.getElementById('nombre_huesped_seleccionado').textContent = nombreCompleto;
        document.getElementById('info_huesped_seleccionado').classList.remove('hidden');
        document.getElementById('contenedor_tabla_huespedes').classList.add('hidden');
        document.getElementById('contenedor_buscador_huesped').classList.add('hidden');

        const radioNuevo = document.getElementById('radio_nuevo');
        const labelNuevo = document.getElementById('label_nuevo_huesped');
        radioNuevo.disabled = true;
        labelNuevo.classList.add('opacity-50', 'cursor-not-allowed');

        const inputsNuevo = document.querySelectorAll('#sec_nuevo input, #sec_nuevo select');
        inputsNuevo.forEach(input => {
            input.value = '';
            input.disabled = true;
            input.classList.add('bg-gray-100', 'cursor-not-allowed');
        });

        document.querySelectorAll('.fila-huesped').forEach(f => f.classList.remove('bg-blue-100', 'font-semibold'));
        filaElemento.classList.add('bg-blue-100', 'font-semibold');
    }

    function limpiarHuespedSeleccionado() {
        if (!tieneTurnoActivo) return;
        document.getElementById('id_huesped_existente').value = '';
        document.getElementById('info_huesped_seleccionado').classList.add('hidden');
        document.getElementById('contenedor_tabla_huespedes').classList.remove('hidden');
        document.getElementById('contenedor_buscador_huesped').classList.remove('hidden');
        document.getElementById('filtro_huespedes').value = '';
        filtrarTablaHuespedes();

        const radioNuevo = document.getElementById('radio_nuevo');
        const labelNuevo = document.getElementById('label_nuevo_huesped');
        radioNuevo.disabled = false;
        labelNuevo.classList.remove('opacity-50', 'cursor-not-allowed');

        const inputsNuevo = document.querySelectorAll('#sec_nuevo input, #sec_nuevo select');
        inputsNuevo.forEach(input => {
            input.disabled = false;
            input.classList.remove('bg-gray-100', 'cursor-not-allowed');
        });
        document.getElementById('nacionalidad_huesped').value = 'México';
    }

    function filtrarHabitaciones() {
        if (!tieneTurnoActivo) return;
        const textoFiltro = document.getElementById('filtro_numero').value.toLowerCase();
        const tipoFiltro = document.getElementById('filtro_tipo').value;
        document.querySelectorAll('.card-habitacion').forEach(tarjeta => {
            const numero = tarjeta.getAttribute('data-numero').toLowerCase();
            const tipo = tarjeta.getAttribute('data-tipo');
            const coincideTexto = numero.includes(textoFiltro) || tipo.includes(textoFiltro);
            const coincideSelect = (tipoFiltro === 'todos' || tipo === tipoFiltro);
            tarjeta.style.display = (coincideTexto && coincideSelect) ? 'flex' : 'none';
        });
    }

    function toggleHabitacion(elemento, idHabitacion, precio) {
        if (!tieneTurnoActivo) return;
        const checkbox = elemento.querySelector('.checkbox-hab');
        checkbox.checked = !checkbox.checked;

        if (checkbox.checked) {
            elemento.classList.remove('border-[#bbf7d0]', 'bg-white', 'hover:border-[#86efac]');
            elemento.classList.add('border-blue-400', 'bg-blue-50', 'border-[1.5px]');
        } else {
            elemento.classList.remove('border-blue-400', 'bg-blue-50', 'border-[1.5px]');
            elemento.classList.add('border-[#bbf7d0]', 'bg-white', 'hover:border-[#86efac]');
        }
        calcularTotalPorFechas();
    }

    function inicializarFechasActuales() {
        const ahora = new Date();
        const anio = ahora.getFullYear();
        const mes = String(ahora.getMonth() + 1).padStart(2, '0');
        const dia = String(ahora.getDate()).padStart(2, '0');
        document.getElementById('fecha_entrada').value = `${anio}-${mes}-${dia}`;

        const maniana = new Date(ahora);
        maniana.setDate(ahora.getDate() + 1);
        const mAnio = maniana.getFullYear();
        const MMes = String(maniana.getMonth() + 1).padStart(2, '0');
        const MDia = String(maniana.getDate()).padStart(2, '0');
        document.getElementById('fecha_salida').value = `${mAnio}-${MMes}-${MDia}`;

        calcularTotalPorFechas();
    }

    function verificarMetodoPago() {
        if (!tieneTurnoActivo) return;
        const metodo = document.getElementById('metodo_pago').value;
        const inputPagoCon = document.getElementById('pago_con');
        const inputCambio = document.getElementById('cambio');
        const labelPagoCon = document.getElementById('label_pago_con');
        const labelCambio = document.getElementById('label_cambio');

        if (metodo === 'Efectivo') {
            inputPagoCon.disabled = false;
            inputPagoCon.classList.remove('bg-gray-100', 'text-gray-400', 'cursor-not-allowed');
            inputPagoCon.classList.add('bg-gray-50', 'text-gray-900');
            labelPagoCon.classList.remove('opacity-50');

            inputCambio.disabled = false;
            inputCambio.classList.remove('opacity-50');
            labelCambio.classList.remove('opacity-50');

            calcularCambio();
        } else {
            inputPagoCon.disabled = true;
            inputPagoCon.classList.add('bg-gray-100', 'text-gray-400', 'cursor-not-allowed');
            inputPagoCon.classList.remove('bg-gray-50', 'text-gray-900');
            labelPagoCon.classList.add('opacity-50');

            inputCambio.disabled = true;
            inputCambio.classList.add('opacity-50');
            labelCambio.classList.add('opacity-50');

            inputPagoCon.value = "";
            inputCambio.value = "0.00";
            inputCambio.classList.remove('text-red-600', 'bg-red-50', 'border-red-200');
            inputCambio.classList.add('text-blue-700', 'bg-blue-50', 'border-blue-200');
        }
    }

    function calcularTotalPorFechas() {
        if (!tieneTurnoActivo) return;
        const entradaVal = document.getElementById('fecha_entrada').value;
        const salidaVal = document.getElementById('fecha_salida').value;

        let sumaPreciosPorNoche = 0;
        document.querySelectorAll('.checkbox-hab:checked').forEach(cb => {
            sumaPreciosPorNoche += parseFloat(cb.getAttribute('data-precio') || 0);
        });

        if (entradaVal && salidaVal && sumaPreciosPorNoche > 0) {
            const entrada = new Date(entradaVal + 'T00:00:00');
            const salida = new Date(salidaVal + 'T00:00:00');
            const diferencia_tiempo = salida - entrada;

            if (diferencia_tiempo > 0) {
                let noches = Math.round(diferencia_tiempo / (1000 * 60 * 60 * 24));
                if (noches < 1) noches = 1;

                document.getElementById('noches_calculadas').value = noches;
                const total = noches * sumaPreciosPorNoche;
                document.getElementById('total_estancia').value = `$${total.toFixed(2)}`;
                document.getElementById('monto_pago').value = total.toFixed(2);
                calcularCambio();
            } else {
                document.getElementById('noches_calculadas').value = "1";
                document.getElementById('total_estancia').value = "$0.00";
                document.getElementById('monto_pago').value = "0.00";
                document.getElementById('cambio').value = "0.00";
            }
        } else {
            document.getElementById('noches_calculadas').value = "1";
            document.getElementById('total_estancia').value = "$0.00";
            document.getElementById('monto_pago').value = "0.00";
            document.getElementById('cambio').value = "0.00";
        }
        verificarMetodoPago();
    }

    function calcularCambio() {
        if (!tieneTurnoActivo) return;
        const metodo = document.getElementById('metodo_pago').value;
        
        const totalTexto = document.getElementById('total_estancia').value;
        const totalEstancia = parseFloat(totalTexto.replace(/[^0-9.]/g, '')) || 0;
        
        document.getElementById('monto_pago').value = totalEstancia.toFixed(2);

        if (metodo !== 'Efectivo') return;

        const pagoConVal = document.getElementById('pago_con').value;
        if (pagoConVal === "" || isNaN(pagoConVal)) {
            document.getElementById('cambio').value = "0.00";
            document.getElementById('cambio').classList.remove('text-red-600', 'bg-red-50', 'border-red-200');
            document.getElementById('cambio').classList.add('text-blue-700', 'bg-blue-50', 'border-blue-200');
            return;
        }

        const pagoCon = parseFloat(pagoConVal) || 0;
        const cambio = pagoCon - totalEstancia;

        if (cambio >= 0) {
            document.getElementById('cambio').value = cambio.toFixed(2);
            document.getElementById('cambio').classList.remove('text-red-600', 'bg-red-50', 'border-red-200');
            document.getElementById('cambio').classList.add('text-blue-700', 'bg-blue-50', 'border-blue-200');
        } else {
            document.getElementById('cambio').value = cambio.toFixed(2);
            document.getElementById('cambio').classList.remove('text-blue-700', 'bg-blue-50', 'border-red-200');
            document.getElementById('cambio').classList.add('text-red-600', 'bg-red-50', 'border-red-200');
        }
    }

    window.onload = function() {
        inicializarFechasActuales();
        cargarPaises();

        if (!tieneTurnoActivo) {
            const form = document.getElementById('formCheckIn');
            let ultimaAlerta = 0;

            function dispararAlertaUnica(mensaje) {
                const ahora = Date.now();
                if (ahora - ultimaAlerta > 400) { 
                    ultimaAlerta = ahora;
                    mostrarNotificacion(mensaje, "error");
                }
            }

            form.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                dispararAlertaUnica("Atención: Debes tener un turno abierto para interactuar.");
            }, true);

            form.addEventListener('mousedown', (e) => {
                e.preventDefault();
                e.stopPropagation();
                dispararAlertaUnica("Atención: Debes tener un turno abierto para interactuar.");
            }, true);

            form.addEventListener('change', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (e.target.tagName === 'SELECT') {
                    e.target.selectedIndex = 0;
                }
                dispararAlertaUnica("Atención: Debes tener un turno abierto para interactuar.");
            }, true);

            form.addEventListener('focusin', (e) => {
                e.target.blur();
                dispararAlertaUnica("Atención: Debes tener un turno abierto para interactuar.");
            }, true);

            form.addEventListener('submit', (e) => {
                e.preventDefault();
                e.stopPropagation();
                dispararAlertaUnica("Atención: No cuentas con un turno abierto.");
                return false;
            }, true);
        }
    };
</script>
</body>
</html>