<?php
// 1. Iniciar sesión, configurar zona horaria y validar autenticación
session_start();
date_default_timezone_set('America/Mexico_City');

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

$rol_usuario = isset($_SESSION['rol']) ? strtolower(trim($_SESSION['rol'])) : '';

require_once 'config/conexion.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Helper para formato en pesos mexicanos
function formatoPesos($monto, $con_decimales = true) {
    if ($monto === null || $monto === '') return '$0.00';
    $decimales = $con_decimales ? 2 : 0;
    return '$' . number_format((float)$monto, $decimales, '.', ',');
}

// RECUPERAR MENSAJE FLASH DE LA SESIÓN (SI EXISTE) Y LIMPIARLO
$mensaje = $_SESSION['mensaje'] ?? '';
$tipo_mensaje = $_SESSION['tipo_mensaje'] ?? '';
unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);

$id_usuario_actual = $_SESSION['id_usuario'];

// 2. Verificar Turno Abierto
$stmt_turno = $pdo->prepare("SELECT id_turno FROM turnos WHERE id_usuario = :id_usuario AND estatus = 'abierto' LIMIT 1");
$stmt_turno->execute(['id_usuario' => $id_usuario_actual]);
$turno_activo = $stmt_turno->fetch();

$id_turno_actual = $turno_activo ? $turno_activo['id_turno'] : null;

// 3. Procesar Acciones (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if (!$id_turno_actual) {
        $_SESSION['mensaje'] = "Atencion: No tienes un turno abierto, por favor inicia uno.";
        $_SESSION['tipo_mensaje'] = "error";
        header("Location: check_out.php");
        exit;
    }

    // --- ACCIÓN A: Liquidar todos los servicios pendientes de una estancia ---
    if ($accion === 'liquidar_todos_servicios') {
        $id_estancia = $_POST['id_estancia'] ?? null;
        $metodo_pago = $_POST['metodo_pago_servicio'] ?? 'Efectivo';

        if ($id_estancia) {
            try {
                $sql_upd = "UPDATE pagos_servicios 
                            SET estado_pago = 'Pagado', 
                                metodo_pago = :metodo_pago, 
                                id_turno = :id_turno, 
                                id_usuario = :id_usuario, 
                                fecha_pago = NOW() 
                            WHERE id_estancia = :id_estancia AND estado_pago = 'Pendiente'";
                $stmt_u = $pdo->prepare($sql_upd);
                $stmt_u->execute([
                    'metodo_pago' => $metodo_pago,
                    'id_turno'    => $id_turno_actual,
                    'id_usuario'  => $id_usuario_actual,
                    'id_estancia' => $id_estancia
                ]);

                $_SESSION['mensaje'] = "Los servicios/consumos fueron liquidado con exito!";
                $_SESSION['tipo_mensaje'] = "success";
            } catch (Exception $e) {
                $_SESSION['mensaje'] = "Atención: Error al cobrar los servicios: " . $e->getMessage();
                $_SESSION['tipo_mensaje'] = "error";
            }
        }
        header("Location: check_out.php");
        exit;
    }

    // --- ACCIÓN B: Renovar Estancia / Agregar más noches (Cobro obligatorio inmediato) ---
    if ($accion === 'renovar_estancia') {
        $id_estancia      = $_POST['id_estancia'] ?? null;
        $noches_a_agregar = (int)($_POST['noches_a_agregar'] ?? 0);
        $metodo_pago_ren  = $_POST['metodo_pago_renovacion'] ?? 'Efectivo';

        if ($id_estancia && $noches_a_agregar > 0) {
            try {
                $pdo->beginTransaction();

                $stmt_est = $pdo->prepare("SELECT e.fecha_salida, h.precio FROM estancias e INNER JOIN habitaciones h ON e.id_habitacion = h.id_habitacion WHERE e.id_estancia = :id_estancia");
                $stmt_est->execute(['id_estancia' => $id_estancia]);
                $datos_estancia = $stmt_est->fetch();

                if (!$datos_estancia) {
                    throw new Exception("No se encontró la estancia.");
                }

                $fecha_salida_actual = new DateTime($datos_estancia['fecha_salida']);
                $fecha_salida_actual->modify("+{$noches_a_agregar} days");
                $nueva_fecha_salida = $fecha_salida_actual->format('Y-m-d H:i:s');

                $precio_noche = (float)$datos_estancia['precio'];
                $monto_extra = $noches_a_agregar * $precio_noche;

                $stmt_upd = $pdo->prepare("UPDATE estancias SET fecha_salida = :nueva_fecha WHERE id_estancia = :id_estancia");
                $stmt_upd->execute([
                    'nueva_fecha' => $nueva_fecha_salida,
                    'id_estancia' => $id_estancia
                ]);

                // Registro obligatorio del pago de extensión
                $sql_ph = "INSERT INTO pagos_estancias (id_estancia, metodo_pago, monto, fecha_pago, id_usuario, id_turno)
                           VALUES (:id_estancia, :metodo_pago, :monto, NOW(), :id_usuario, :id_turno)";
                $stmt_ph = $pdo->prepare($sql_ph);
                $stmt_ph->execute([
                    'id_estancia' => $id_estancia,
                    'metodo_pago' => $metodo_pago_ren,
                    'monto'       => $monto_extra,
                    'id_usuario'  => $id_usuario_actual,
                    'id_turno'    => $id_turno_actual
                ]);

                $pdo->commit();

                $_SESSION['mensaje'] = "¡Estancia renovada con éxito! Se agregaron " . $noches_a_agregar . " noche(s) más.";
                $_SESSION['tipo_mensaje'] = "success";

            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['mensaje'] = "Atención: Error al renovar la estancia: " . $e->getMessage();
                $_SESSION['tipo_mensaje'] = "error";
            }
        } else {
            $_SESSION['mensaje'] = "Atención: Debes ingresar un número válido de noches a agregar.";
            $_SESSION['tipo_mensaje'] = "error";
        }
        header("Location: check_out.php");
        exit;
    }

    // --- ACCIÓN C: Procesar Check-Out (Simple, sin cobro integrado en este botón) ---
    if ($accion === 'procesar_checkout') {
        $id_estancia        = $_POST['id_estancia'] ?? null;
        $id_habitacion      = $_POST['id_habitacion'] ?? null;

        if ($id_estancia && $id_habitacion) {
            try {
                $pdo->beginTransaction();

                // Actualizar estatus de la estancia a Finalizada
                $sql_est = "UPDATE estancias 
                            SET estatus_estancia = 'Finalizada', 
                                fecha_salida = NOW(), 
                                id_usuario_checkout = :id_usuario_checkout 
                            WHERE id_estancia = :id_estancia";
                $stmt_e = $pdo->prepare($sql_est);
                $stmt_e->execute([
                    'id_usuario_checkout' => $id_usuario_actual,
                    'id_estancia'         => $id_estancia
                ]);

                // Liberar la habitación y marcarla en limpieza
                $sql_hab = "UPDATE habitaciones SET estatus = 'limpieza', id_huesped = NULL WHERE id_habitacion = :id_habitacion";
                $stmt_h = $pdo->prepare($sql_hab);
                $stmt_h->execute(['id_habitacion' => $id_habitacion]);

                $pdo->commit();

                $_SESSION['mensaje'] = "¡Check-Out completado con éxito! Habitación marcada en 'Limpieza'.";
                $_SESSION['tipo_mensaje'] = "success";
                
                $_SESSION['pdf_id_estancia'] = $id_estancia;

            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['mensaje'] = "Atención: Error al procesar el Check-Out: " . $e->getMessage();
                $_SESSION['tipo_mensaje'] = "error";
            }
        }
        header("Location: check_out.php");
        exit;
    }
}

// 4. Obtener todas las Estancias Activas
$sql_activas = "SELECT 
                    e.id_estancia,
                    e.fecha_entrada,
                    e.fecha_salida,
                    h.id_habitacion,
                    h.numero_habitacion,
                    h.tipo_habitacion,
                    h.precio AS precio_noche,
                    hu.id_huesped,
                    hu.identificador AS id_huesped_codigo,
                    hu.nombre,
                    hu.apellido_p,
                    hu.apellido_m,
                    IFNULL((SELECT SUM(p.monto) FROM pagos_estancias p WHERE p.id_estancia = e.id_estancia), 0) AS total_pagado_hospedaje
                FROM estancias e
                INNER JOIN habitaciones h ON e.id_habitacion = h.id_habitacion
                INNER JOIN huesped hu ON e.id_huesped = hu.id_huesped
                WHERE e.estatus_estancia = 'Activa'
                ORDER BY h.numero_habitacion ASC";

$stmt_act = $pdo->query($sql_activas);
$estancias_activas = $stmt_act->fetchAll();

// 5. Cargar consumos/servicios pendientes por estancia
$servicios_pendientes_por_estancia = [];

if (count($estancias_activas) > 0) {
    $ids_estancias = array_column($estancias_activas, 'id_estancia');
    $in_clause = implode(',', array_fill(0, count($ids_estancias), '?'));

    $sql_serv_pend = "SELECT 
                        ps.id_pago,
                        ps.id_estancia,
                        ps.monto,
                        ps.fecha_pago,
                        s.nombre_servicio,
                        s.tipo_servicio,
                        s.descripcion
                      FROM pagos_servicios ps
                      INNER JOIN servicios s ON ps.id_servicio = s.id_servicio
                      WHERE ps.id_estancia IN ($in_clause) AND ps.estado_pago = 'Pendiente'
                      ORDER BY ps.id_pago ASC";
    
    $stmt_sp = $pdo->prepare($sql_serv_pend);
    $stmt_sp->execute($ids_estancias);
    $rows_sp = $stmt_sp->fetchAll();

    foreach ($rows_sp as $row) {
        $servicios_pendientes_por_estancia[$row['id_estancia']][] = $row;
    }
}

include 'includes/header.php';
?>

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

    .ventana-modal {
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        backface-visibility: hidden;
        -webkit-backface-visibility: hidden;
        transform: translateZ(0);
        -webkit-transform: translateZ(0);
        border-radius: 1rem;
        overflow: hidden;
    }
    
    @keyframes modalPopIn {
        0% {
            opacity: 0;
            transform: scale(0.85);
        }
        100% {
            opacity: 1;
            transform: scale(1);
        }
    }
    .animate-modal-open {
        animation: modalPopIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .tabla-bloqueada {
        opacity: 0.45;
        filter: grayscale(40%);
        cursor: not-allowed !important;
    }

    .tabla-bloqueada * {
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

    .select-filtro-custom {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23374151' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 1rem;
        padding-right: 2.3rem;
    }
</style>

<!-- CONTENEDOR PARA NOTIFICACIONES FLOTANTES -->
<div id="contenedorNotificaciones" class="fixed top-5 right-5 z-[9999] flex flex-col gap-2 pointer-events-none"></div>

<!-- CUERPO ESPECÍFICO DE CHECK-OUT -->
<div class="px-4 md:px-8 py-3 md:py-4 space-y-4 max-w-7xl mx-auto w-full text-gray-700 relative">

    <!-- ENCABEZADO CON BOTONES DE NAVEGACIÓN ESTÁNDAR -->
    <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-gray-200">
        <div class="flex items-center gap-2">
            <img src="icons/check-out.png" alt="Registro de Check-Out y Salidas" class="w-4 h-4 object-contain">
            <h1 class="text-sm md:text-base font-bold text-gray-800 tracking-wide">Registro de Check-Out y Salidas</h1>
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
            <button onclick="window.location.href='check_in.php'" class="px-3 py-1.5 bg-white text-gray-700 border border-gray-300 text-xs font-semibold rounded-xl hover:bg-gray-50 transition-colors shadow-sm flex items-center gap-2">
                <img src="icons/check-in.png" alt="Check-In" class="w-3.5 h-3.5 object-contain">
                <span>Check-In</span>
            </button>
        </div>
    </div>

    <!-- CONTENEDOR PRINCIPAL -->
    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm space-y-4 text-xs text-gray-600">
        
        <!-- ENCABEZADO DE SECCIÓN, MENÚ DE FILTRO Y BARRA DE BÚSQUEDA -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 border-b border-gray-100 pb-2">
            <h2 class="text-xs md:text-sm font-semibold text-gray-800 flex items-center gap-2">
                <img src="icons/cama.png" alt="Habitación" class="w-4 h-4 object-contain">
                <span>Habitaciones Ocupadas Activas</span>
            </h2>

            <!-- CONTENEDOR DE MENÚ DE FILTRO Y BÚSQUEDA -->
            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                <div class="w-full md:w-auto">
                    <select id="selectFiltroEstado" onchange="filtrarEstadoSelect(this.value)" class="w-full md:w-32 px-3 py-1.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black text-xs bg-white text-gray-700 font-semibold select-filtro-custom shadow-none">
                        <option value="vencida" selected>Vencidas</option>
                        <option value="activa">Activas</option>
                        <option value="todas">Todas</option>
                    </select>
                </div>

                <div class="w-full md:w-64 relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <img src="icons/lupa.png" alt="Buscar" class="w-3.5 h-3.5 object-contain opacity-50">
                    </span>
                    <input type="text" id="buscadorEstancias" placeholder="Buscar..." class="w-full pl-9 pr-3 py-1.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black text-xs bg-white shadow-none">
                </div>
            </div>
        </div>

        <?php if (!$id_turno_actual): ?>
            <div class="p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg flex items-center gap-2 font-medium">
                <img src="icons/alerta.png" alt="Alerta" class="w-4 h-4 object-contain">
                <span>Atencion: No tienes un turno abierto, por favor inicia uno.</span>
            </div>
        <?php endif; ?>

        <?php if (count($estancias_activas) > 0): ?>
            <div id="contenedorTablaCheckOut" class="overflow-x-auto <?php echo !$id_turno_actual ? 'tabla-bloqueada' : ''; ?>">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-100 text-gray-700 border-b border-gray-200 text-[11px]">
                        <tr>
                            <th class="p-3 font-semibold">Habitación</th>
                            <th class="p-3 font-semibold">Tipo</th>
                            <th class="p-3 font-semibold">Huésped</th>
                            <th class="p-3 font-semibold">Salida</th>
                            <th class="p-3 font-semibold">Estado</th>
                        </tr>
                    </thead>
                    <tbody id="tablaEstanciasBody" class="text-[11px] divide-y divide-gray-200">
                        <?php foreach ($estancias_activas as $est): ?>
                            <?php
                                $id_e = $est['id_estancia'];

                                $fecha_in  = new DateTime($est['fecha_entrada']);
                                $fecha_out = new DateTime($est['fecha_salida']);
                                $diferencia = $fecha_in->diff($fecha_out);
                                $noches = $diferencia->days > 0 ? $diferencia->days : 1;

                                $costo_hospedaje = $noches * (float)$est['precio_noche'];
                                $pagado_hospedaje = (float)$est['total_pagado_hospedaje'];
                                $saldo_hospedaje = $costo_hospedaje - $pagado_hospedaje;
                                if ($saldo_hospedaje < 0) $saldo_hospedaje = 0;

                                $lista_serv_pend = $servicios_pendientes_por_estancia[$id_e] ?? [];
                                $total_servicios_pend = 0;
                                foreach ($lista_serv_pend as $sp) {
                                    $total_servicios_pend += (float)$sp['monto'];
                                }

                                $ahora = new DateTime();
                                $esta_vencida = ($ahora > $fecha_out);

                                $nombre_completo = trim($est['nombre'] . ' ' . $est['apellido_p'] . ' ' . $est['apellido_m']);
                                $tipo_habitacion_fmt = ucfirst(strtolower($est['tipo_habitacion']));
                            ?>
                            <tr class="fila-estancia hover:bg-gray-50/80 transition-colors cursor-pointer"
                                data-busqueda="<?php echo strtolower(htmlspecialchars($est['numero_habitacion'] . ' ' . $est['id_huesped_codigo'] . ' ' . $nombre_completo . ' ' . $est['tipo_habitacion'])); ?>"
                                data-estado="<?php echo $esta_vencida ? 'vencida' : 'activa'; ?>"
                                style="<?php echo $esta_vencida ? '' : 'display: none;'; ?>"
                                onclick='abrirModalDetallesEstancia({
                                idEstancia: "<?php echo $est['id_estancia']; ?>",
                                idHabitacion: "<?php echo $est['id_habitacion']; ?>",
                                numeroHabitacion: "<?php echo htmlspecialchars($est['numero_habitacion']); ?>",
                                tipoHabitacion: "<?php echo htmlspecialchars($tipo_habitacion_fmt); ?>",
                                nombreHuesped: "<?php echo htmlspecialchars($nombre_completo); ?>",
                                codigoHuesped: "<?php echo htmlspecialchars($est['id_huesped_codigo']); ?>",
                                fechaEntrada: "<?php echo $est['fecha_entrada']; ?>",
                                fechaSalida: "<?php echo $est['fecha_salida']; ?>",
                                estaVencida: <?php echo $esta_vencida ? 'true' : 'false'; ?>,
                                costoHospedaje: "<?php echo number_format($costo_hospedaje, 2, '.', ''); ?>",
                                pagadoHospedaje: "<?php echo number_format($pagado_hospedaje, 2, '.', ''); ?>",
                                saldoHospedaje: "<?php echo number_format($saldo_hospedaje, 2, '.', ''); ?>",
                                precioNoche: "<?php echo (float)$est['precio_noche']; ?>",
                                servicios: <?php echo json_encode($lista_serv_pend); ?>,
                                totalServicios: "<?php echo number_format($total_servicios_pend, 2, '.', ''); ?>"
                            })'>
                                <td class="p-3 align-middle">
                                    <strong class="text-gray-900 text-xs">Hab. <?php echo htmlspecialchars($est['numero_habitacion']); ?></strong>
                                </td>

                                <td class="p-3 align-middle">
                                    <span class="text-gray-600"><?php echo htmlspecialchars($tipo_habitacion_fmt); ?></span>
                                </td>

                                <td class="p-3 align-middle">
                                    <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($nombre_completo); ?></span><br>
                                    <span class="text-gray-400 font-mono text-[10px]"><?php echo htmlspecialchars($est['id_huesped_codigo']); ?></span>
                                </td>

                                <td class="p-3 align-middle">
                                    <span class="<?php echo $esta_vencida ? 'text-red-800 font-bold' : 'text-gray-700'; ?>">
                                        <?php echo htmlspecialchars($est['fecha_salida']); ?>
                                    </span>
                                </td>

                                <td class="p-3 align-middle">
                                    <?php if ($esta_vencida): ?>
                                        <span class="inline-block px-2 py-0.5 bg-red-100 text-red-800 font-bold rounded text-[10px]">VENCIDO</span>
                                    <?php else: ?>
                                        <span class="inline-block px-2 py-0.5 bg-green-100 text-green-800 font-bold rounded text-[10px]">ACTIVA</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="p-6 text-center text-gray-400 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                No hay estancias activas u ocupadas registradas en este momento.
            </div>
        <?php endif; ?>
    </div>

</div>
</main>
</div>

<!-- CONTENEDOR GLOBAL DE MODALES -->
<div id="contenedorModales" class="fixed inset-0 z-50 pointer-events-none overflow-hidden"></div>

<script>
    let zIndexCounter = 100;
    const tieneTurnoActivo = <?php echo $id_turno_active ?? $id_turno_actual ? 'true' : 'false'; ?>;
    let estadoFiltroActual = 'vencida';

    document.addEventListener('DOMContentLoaded', () => {
        const inputBuscador = document.getElementById('buscadorEstancias');
        if (inputBuscador) {
            inputBuscador.addEventListener('input', ejecutarFiltros);
        }
    });

    function filtrarEstadoSelect(valor) {
        estadoFiltroActual = valor;
        ejecutarFiltros();
    }

    function ejecutarFiltros() {
        const inputBuscador = document.getElementById('buscadorEstancias');
        const query = inputBuscador ? inputBuscador.value.toLowerCase().trim() : '';
        const filas = document.querySelectorAll('.fila-estancia');

        filas.forEach(fila => {
            const datosBusqueda = fila.getAttribute('data-busqueda') || '';
            const estadoFila = fila.getAttribute('data-estado') || '';

            const coincideTexto = datosBusqueda.includes(query);
            
            let coincideEstado = false;
            if (estadoFiltroActual === 'todas') {
                coincideEstado = true;
            } else {
                coincideEstado = (estadoFila === estadoFiltroActual);
            }

            if (coincideTexto && coincideEstado) {
                fila.style.display = '';
            } else {
                fila.style.display = 'none';
            }
        });
    }

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
            mostrarNotificacion("Atencion: No tienes un turno abierto, por favor inicia uno.", "error");
        });
    <?php endif; ?>

    // --- LÓGICA DE ESCALERA: RECICLA POSICIONES LIBRES A PARTIR DE UN MODAL PADRE ---
    function calcularPosicionCascada(ventanaHtml, anchoModal = 500, altoModal = 500, ventanaPadre = null) {
        const contenedorModales = document.getElementById('contenedorModales');
        const modalesAbiertos = contenedorModales ? Array.from(contenedorModales.querySelectorAll('.ventana-modal')) : [];

        let baseX = (window.innerWidth - anchoModal) / 2;
        let baseY = (window.innerHeight - altoModal) / 2;

        if (ventanaPadre) {
            const rectPadre = ventanaPadre.getBoundingClientRect();
            baseX = rectPadre.left;
            baseY = rectPadre.top;
        }

        const posicionesOcupadas = new Set();
        modalesAbiertos.forEach(m => {
            posicionesOcupadas.add(`${Math.round(m.offsetLeft)}_${Math.round(m.offsetTop)}`);
        });

        let nivel = 0;
        let posX = Math.max(20, baseX);
        let posY = Math.max(20, baseY);

        while (posicionesOcupadas.has(`${Math.round(posX)}_${Math.round(posY)}`)) {
            nivel++;
            let offset = nivel * 30;
            posX = Math.max(20, baseX + offset);
            posY = Math.max(20, baseY + offset);

            const maxOffsetX = window.innerWidth - anchoModal - 50;
            const maxOffsetY = window.innerHeight - altoModal - 50;
            if (posX > maxOffsetX || posY > maxOffsetY) {
                posX = Math.max(20, baseX + (nivel % 5) * 30);
                posY = Math.max(20, baseY + (nivel % 5) * 30);
                break;
            }
        }

        ventanaHtml.style.position = 'fixed';
        ventanaHtml.style.left = posX + 'px';
        ventanaHtml.style.top = posY + 'px';
    }

    // --- VENTANA FLOTANTE: VER DETALLES DE LA ESTANCIA ---
    function abrirModalDetallesEstancia(data) {
        if (!tieneTurnoActivo) {
            mostrarNotificacion("Atencion: No tienes un turno abierto, por favor inicia uno.", "error");
            return;
        }

        const idVentana = 'modal-detalles-' + data.idEstancia;
        const ventanaExistente = document.getElementById(idVentana);

        if (ventanaExistente) {
            ventanaExistente.remove();
        }

        const contenedor = document.getElementById('contenedorModales');
        const ventanaHtml = document.createElement('div');
        ventanaHtml.id = idVentana;
        ventanaHtml.className = 'ventana-modal bg-white rounded-2xl shadow-2xl border border-gray-200 max-w-lg w-full pointer-events-auto animate-modal-open text-xs flex flex-col';
        
        zIndexCounter++;
        ventanaHtml.style.zIndex = zIndexCounter;

        calcularPosicionCascada(ventanaHtml, 500, 480);

        let costoHospNum = parseFloat(data.costoHospedaje) || 0;
        let pagadoHospNum = parseFloat(data.pagadoHospedaje) || 0;
        let saldoHospNum = parseFloat(data.saldoHospedaje) || 0;
        let totalServiciosNum = parseFloat(data.totalServicios) || 0;
        let saldoTotalGeneral = saldoHospNum + totalServiciosNum;

        let saldoTotalFmt = '$' + saldoTotalGeneral.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        let consumosHtml = '';
        if (data.servicios && data.servicios.length > 0) {
            consumosHtml = '<div class="space-y-1.5 max-h-[100px] overflow-y-auto pr-1">';
            data.servicios.forEach(sp => {
                let montoNum = parseFloat(sp.monto) || 0;
                let montoFmt = '$' + montoNum.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                consumosHtml += `
                    <div class="flex items-center justify-between text-xs text-gray-800 bg-gray-50 px-2 py-1.5 rounded-lg border border-gray-100">
                        <span class="truncate max-w-[220px]" title="${sp.nombre_servicio}">${sp.nombre_servicio}</span>
                        <span class="font-semibold">${montoFmt}</span>
                    </div>
                `;
            });
            consumosHtml += `
                <button type="button" onclick='abrirModalDetallesServiciosId(${JSON.stringify(data)})' class="text-blue-600 font-semibold hover:text-blue-800 text-[11px] block pt-0.5">
                    Ver pagos pendientes...
                </button>
            </div>`;
        } else {
            consumosHtml = `
                <div class="p-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-500 font-medium text-center">
                    <span>Sin consumos pendientes</span>
                </div>
            `;
        }

        ventanaHtml.innerHTML = `
            <div class="modal-header bg-gray-50 px-5 py-3 border-b border-gray-200 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2">
                    <img src="icons/cama.png" alt="Detalles" class="w-5 h-5 object-contain">
                    <h3 class="text-sm font-bold text-gray-800">
                        Detalles de Estancia
                    </h3>
                </div>
                <button type="button" class="btn-cerrar text-gray-400 hover:text-red-600 font-bold leading-none px-1 transition-colors text-2xl md:text-3xl">&times;</button>
            </div>
            
            <div class="p-5 space-y-4 text-xs text-gray-700 overflow-y-auto max-h-[75vh]">
                
                <div class="grid grid-cols-2 gap-4 pb-3 border-b border-gray-100">
                    <div class="border-l-4 ${data.estaVencida ? 'border-red-600 bg-red-50/20 pl-3' : 'border-green-600 pl-3'}">
                        ${data.estaVencida ? '<span class="inline-block px-1.5 py-0.5 bg-red-100 text-red-800 font-bold rounded text-[10px] mb-1">VENCIDO</span><br>' : '<span class="inline-block px-1.5 py-0.5 bg-green-100 text-green-800 font-bold rounded text-[10px] mb-1">ACTIVA</span><br>'}
                        <strong class="text-gray-900 text-sm">Hab. ${data.numeroHabitacion}</strong><br>
                        <span class="text-gray-500 text-[11px]"><?php echo htmlspecialchars($est['tipo_habitacion'] ?? ''); ?></span>
                    </div>

                    <div class="border-l border-gray-200 pl-3">
                        <span class="font-medium text-gray-500">Huésped:</span><br>
                        <span class="text-gray-900 font-semibold text-sm">${data.nombreHuesped}</span><br>
                        <span class="text-gray-400 font-mono text-[10px]"><?php echo htmlspecialchars($est['id_huesped_codigo'] ?? ''); ?></span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 pb-3 border-b border-gray-100 bg-gray-50/50 p-3 rounded-xl border border-gray-100">
                    <div>
                        <span class="text-gray-500 block mb-0.5">Fecha de Entrada:</span>
                        <span class="text-gray-800 font-medium">${data.fechaEntrada}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 block mb-0.5">Fecha de Salida:</span>
                        <span class="${data.estaVencida ? 'text-red-800 font-bold' : 'text-gray-800 font-medium'}">
                            ${data.fechaSalida}
                        </span>
                    </div>
                </div>

                <div class="space-y-1.5 pb-3 border-b border-gray-100">
                    <span class="font-semibold text-gray-700 block">Pagos pendientes / Servicios:</span>
                    ${consumosHtml}
                </div>

                <div class="flex items-center justify-between text-xs text-gray-700 pt-1">
                    <span class="font-semibold text-gray-700">Saldo total a pagar:</span>
                    <span class="text-sm font-bold text-gray-900">${saldoTotalFmt}</span>
                </div>

                <div class="flex items-center justify-end gap-2 pt-1">
                    <button type="button" onclick='abrirModalRenovarDesdeDetalles(${JSON.stringify(data)})' class="px-3.5 py-1.5 bg-white text-gray-700 border border-gray-300 hover:bg-gray-100 font-semibold rounded-lg shadow-sm transition-colors text-xs flex items-center justify-center">
                        <span>Extender Estancia</span>
                    </button>

                    <button type="button" onclick='intentarCheckOut(${JSON.stringify(data)})' class="px-3.5 py-1.5 bg-black hover:bg-gray-800 text-white font-bold rounded-lg shadow-sm transition-colors text-xs flex items-center justify-center">
                        <span>Hacer Check-Out</span>
                    </button>
                </div>

            </div>
        `;

        contenedor.appendChild(ventanaHtml);
        configurarVentanaFlotante(ventanaHtml);
    }

    // --- VALIDACIÓN DE CHECK-OUT ---
    function intentarCheckOut(data) {
        let totalServiciosNum = parseFloat(data.totalServicios) || 0;

        if (totalServiciosNum > 0) {
            abrirModalDetallesServicios(data);
            mostrarNotificacion("Atención: No se puede hacer Check-Out. Hay pagos pendientes por liquidar.", "error");
            return;
        }

        abrirModalCheckout({
            idEstancia: data.idEstancia,
            idHabitacion: data.idHabitacion,
            numeroHabitacion: data.numeroHabitacion
        });
    }

    function abrirModalDetallesServiciosId(data) {
        abrirModalDetallesServicios({
            idEstancia: data.idEstancia,
            numeroHabitacion: data.numeroHabitacion,
            servicios: data.servicios,
            totalServicios: data.totalServicios
        });
    }

    function abrirModalRenovarDesdeDetalles(data) {
        abrirModalRenovar({
            idEstancia: data.idEstancia,
            numeroHabitacion: data.numeroHabitacion,
            precioNoche: data.precioNoche
        });
    }

    // --- VENTANA FLOTANTE: DETALLES DE SERVICIOS PENDIENTES (SINCRONIZADA CON ESCALERA) ---
    function abrirModalDetallesServicios(data) {
        if (!tieneTurnoActivo) {
            mostrarNotificacion("Atencion: No tienes un turno abierto, por favor inicia uno.", "error");
            return;
        }

        const contenedor = document.getElementById('contenedorModales');
        const idVentana = 'modal-detalles-serv-' + data.idEstancia;
        const ventanaExistente = document.getElementById(idVentana);

        if (ventanaExistente) {
            ventanaExistente.remove();
        }

        const ventanaHtml = document.createElement('div');
        ventanaHtml.id = idVentana;
        ventanaHtml.className = 'ventana-modal bg-white rounded-2xl shadow-2xl border border-gray-200 max-w-lg w-full pointer-events-auto animate-modal-open text-xs flex flex-col';
        
        zIndexCounter++;
        ventanaHtml.style.zIndex = zIndexCounter;

        // Se pasa el modal de detalles actual como referencia para que nazca en cascada desde ahí
        const ventanaPadre = document.getElementById('modal-detalles-' + data.idEstancia);
        calcularPosicionCascada(ventanaHtml, 500, 420, ventanaPadre);

        let listaHtml = '<div class="space-y-3 pr-2 text-xs text-gray-700 max-h-[220px] overflow-y-auto">';

        data.servicios.forEach(sp => {
            let montoNum = parseFloat(sp.monto) || 0;
            let montoFmt = '$' + montoNum.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            let tipoServicioTxt = sp.tipo_servicio ? sp.tipo_servicio : 'General';
            let descripcionTxt = sp.descripcion ? sp.descripcion : 'Sin descripción';
            let fechaPagoTxt = sp.fecha_pago ? sp.fecha_pago : 'Fecha no registrada';

            listaHtml += `
                <div class="border-b border-gray-200 pb-3 space-y-1">
                    <div class="flex items-center justify-between gap-2">
                        <div class="space-x-1">
                            <span class="font-bold text-gray-900 text-sm">${tipoServicioTxt}:</span>
                            <span class="text-gray-800 text-sm font-semibold">${sp.nombre_servicio}</span>
                        </div>
                        <span class="font-bold text-gray-900 text-sm pr-2">${montoFmt}</span>
                    </div>
                    <div class="text-gray-600">
                        <span class="font-semibold text-gray-700">Descripción:</span> ${descripcionTxt}
                    </div>
                    <div class="text-gray-400 font-mono text-[11px]">
                        Fecha y hora: ${fechaPagoTxt}
                    </div>
                </div>
            `;
        });
        listaHtml += '</div>';

        let subtotalNum = parseFloat(data.totalServicios) || 0;
        let subtotalFmt = '$' + subtotalNum.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        ventanaHtml.innerHTML = `
            <div class="modal-header bg-gray-50 px-5 py-3 border-b border-gray-200 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2">
                    <img src="icons/pago_pendiente.png" alt="Pagos pendientes" class="w-5 h-5 object-contain">
                    <h3 class="text-sm font-bold text-gray-800">
                        Pagos pendientes - Hab. ${data.numeroHabitacion}
                    </h3>
                </div>
                <button type="button" class="btn-cerrar text-gray-400 hover:text-red-600 font-bold leading-none px-1 transition-colors text-2xl md:text-3xl">&times;</button>
            </div>
            
            <form action="check_out.php" method="POST" class="p-5 space-y-3 text-xs text-gray-700 flex flex-col">
                ${listaHtml}
                
                <div class="border-t border-gray-200 pt-3 space-y-2 shrink-0">
                    <div class="flex items-center justify-between text-gray-700 text-xs">
                        <span class="font-medium">Tipo de pago:</span>
                        <select name="metodo_pago_servicio" class="px-3 py-1.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black bg-white w-40">
                            <option value="Efectivo">Efectivo</option>
                            <option value="Transferencia">Transferencia</option>
                        </select>
                    </div>

                    <div class="flex items-center justify-between text-gray-700 text-xs">
                        <span class="font-medium">Subtotal:</span>
                        <span class="font-semibold text-gray-900">${subtotalFmt}</span>
                    </div>

                    <div class="flex items-center justify-between text-xs text-gray-700 pt-1">
                        <span class="font-semibold text-gray-700">Total a pagar:</span>
                        <span class="text-sm font-bold text-gray-900">${subtotalFmt}</span>
                    </div>
                </div>

                <div class="border-t border-gray-200 pt-3 flex items-center justify-end shrink-0">
                    <input type="hidden" name="accion" value="liquidar_todos_servicios">
                    <input type="hidden" name="id_estancia" value="${data.idEstancia}">
                    <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 transition-colors">Pagar</button>
                </div>
            </form>
        `;

        contenedor.appendChild(ventanaHtml);
        configurarVentanaFlotante(ventanaHtml);
    }

    // --- VENTANA FLOTANTE: RENOVAR ESTANCIA ---
    function abrirModalRenovar(data) {
        if (!tieneTurnoActivo) {
            mostrarNotificacion("Atencion: No tienes un turno abierto, por favor inicia uno.", "error");
            return;
        }

        const contenedor = document.getElementById('contenedorModales');
        const idVentana = 'modal-renovar-' + data.idEstancia;
        const ventanaExistente = document.getElementById(idVentana);

        if (ventanaExistente) {
            ventanaExistente.remove();
        }

        const precioNoche = parseFloat(data.precioNoche) || 0;

        const ventanaHtml = document.createElement('div');
        ventanaHtml.id = idVentana;
        ventanaHtml.className = 'ventana-modal bg-white rounded-2xl shadow-2xl border border-gray-200 max-w-sm w-full pointer-events-auto animate-modal-open text-xs';
        
        zIndexCounter++;
        ventanaHtml.style.zIndex = zIndexCounter;

        const ventanaPadre = document.getElementById('modal-detalles-' + data.idEstancia);
        calcularPosicionCascada(ventanaHtml, 380, 320, ventanaPadre);

        ventanaHtml.innerHTML = `
            <div class="modal-header bg-gray-50 px-5 py-3 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-sm font-bold text-gray-800">
                    Extender Estancia - Hab. ${data.numeroHabitacion}
                </h3>
                <button type="button" class="btn-cerrar text-gray-400 hover:text-red-600 font-bold leading-none px-1 transition-colors text-2xl md:text-3xl">&times;</button>
            </div>
            
            <form action="check_out.php" method="POST" class="p-5 space-y-3 text-xs text-gray-700">
                <input type="hidden" name="accion" value="renovar_estancia">
                <input type="hidden" name="id_estancia" value="${data.idEstancia}">
                
                <div class="space-y-1">
                    <label class="font-medium text-gray-700">Noches a agregar:</label>
                    <input type="number" id="input_noches_${idVentana}" name="noches_a_agregar" value="1" min="1" max="30" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black">
                </div>

                <div class="space-y-1">
                    <label class="font-medium text-gray-700">Método de pago:</label>
                    <select name="metodo_pago_renovacion" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black bg-white">
                        <option value="Efectivo">Efectivo</option>
                        <option value="Transferencia">Transferencia</option>
                    </select>
                </div>

                <div class="border-t border-gray-200 pt-2 space-y-1">
                    <div class="flex items-center justify-between text-gray-700 text-xs">
                        <span class="font-medium">Subtotal:</span>
                        <span id="subtotal_renovacion_${idVentana}" class="font-semibold text-gray-900">$0.00</span>
                    </div>

                    <div class="flex items-center justify-between text-xs text-gray-700 pt-1">
                        <span class="font-semibold text-gray-700">Total a pagar:</span>
                        <span id="total_pagar_renovacion_${idVentana}" class="text-sm font-bold text-gray-900">$0.00</span>
                    </div>
                </div>

                <div class="border-t border-gray-200 pt-3 flex items-center justify-end">
                    <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 transition-colors">Pagar y Extender</button>
                </div>
            </form>
        `;

        contenedor.appendChild(ventanaHtml);
        configurarVentanaFlotante(ventanaHtml);

        const inputNoches = document.getElementById(`input_noches_${idVentana}`);
        const spanSubtotal = document.getElementById(`subtotal_renovacion_${idVentana}`);
        const spanTotal = document.getElementById(`total_pagar_renovacion_${idVentana}`);

        function actualizarTotalRenovacion() {
            let noches = parseInt(inputNoches.value) || 0;
            if (noches < 1) noches = 1;
            let total = noches * precioNoche;
            let totalFmt = '$' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            spanSubtotal.textContent = totalFmt;
            spanTotal.textContent = totalFmt;
        }

        inputNoches.addEventListener('input', actualizarTotalRenovacion);
        actualizarTotalRenovacion();
    }

    // --- VENTANA FLOTANTE: CHECK-OUT (CENTRADO EN MEDIO) ---
    function abrirModalCheckout(data) {
        if (!tieneTurnoActivo) {
            mostrarNotificacion("Atencion: No tienes un turno abierto, por favor inicia uno.", "error");
            return;
        }

        const contenedor = document.getElementById('contenedorModales');
        const idVentana = 'modal-checkout-' + data.idEstancia;
        const ventanaExistente = document.getElementById(idVentana);

        if (ventanaExistente) {
            ventanaExistente.remove();
        }

        const ventanaHtml = document.createElement('div');
        ventanaHtml.id = idVentana;
        ventanaHtml.className = 'ventana-modal bg-white rounded-2xl shadow-2xl border border-gray-200 max-w-sm w-full pointer-events-auto animate-modal-open text-xs';
        
        zIndexCounter++;
        ventanaHtml.style.zIndex = zIndexCounter;

        // Se deja sin ventana padre para que aparezca centrado en medio de la pantalla
        calcularPosicionCascada(ventanaHtml, 360, 200);

        ventanaHtml.innerHTML = `
            <div class="modal-header bg-gray-50 px-5 py-3 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-sm font-bold text-gray-800">
                    Hacer Check-Out - Hab. ${data.numeroHabitacion}
                </h3>
                <button type="button" class="btn-cerrar text-gray-400 hover:text-red-600 font-bold leading-none px-1 transition-colors text-2xl md:text-3xl">&times;</button>
            </div>
            
            <form action="check_out.php" method="POST" class="p-5 space-y-3 text-xs text-gray-700">
                <input type="hidden" name="accion" value="procesar_checkout">
                <input type="hidden" name="id_estancia" value="${data.idEstancia}">
                <input type="hidden" name="id_habitacion" value="${data.idHabitacion}">

                <p class="font-medium text-gray-700">¿Deseas proceder con el check-out de esta habitación?</p>

                <div class="border-t border-gray-200 pt-3 flex items-center justify-end">
                    <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 transition-colors">Confirmar Check-Out</button>
                </div>
            </form>
        `;

        contenedor.appendChild(ventanaHtml);
        configurarVentanaFlotante(ventanaHtml);
    }

    function configurarVentanaFlotante(ventana) {
        ventana.addEventListener('mousedown', () => {
            zIndexCounter++;
            ventana.style.zIndex = zIndexCounter;
        });

        const botonesCerrar = ventana.querySelectorAll('.btn-cerrar');
        botonesCerrar.forEach(btn => {
            btn.addEventListener('click', () => {
                ventana.remove();
            });
        });

        let isDragging = false;
        let startX = 0, startY = 0;

        ventana.addEventListener('mousedown', (e) => {
            if (!e.target.closest('.modal-header')) {
                return;
            }

            e.preventDefault();
            isDragging = true;
            
            startX = e.clientX;
            startY = e.clientY;

            const rect = ventana.getBoundingClientRect();
            ventana.style.position = 'fixed';
            ventana.style.top = rect.top + 'px';
            ventana.style.left = rect.left + 'px';
            ventana.style.transform = 'none';

            let currentLeft = rect.left;
            let currentTop = rect.top;

            function onMouseMove(moveEvent) {
                if (!isDragging) return;
                const dx = moveEvent.clientX - startX;
                const dy = moveEvent.clientY - startY;

                ventana.style.left = Math.round(currentLeft + dx) + 'px';
                ventana.style.top = Math.round(currentTop + dy) + 'px';
            }

            function onMouseUp() {
                isDragging = false;
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
            }

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });
    }

    window.onload = function() {
        if (!tieneTurnoActivo) {
            const contenedorTabla = document.getElementById('contenedorTablaCheckOut');
            let ultimaAlerta = 0;

            function dispararAlertaUnica(mensaje) {
                constahora = Date.now();
                if (ahora - ultimaAlerta > 400) { 
                    ultimaAlerta = ahora;
                    mostrarNotificacion(mensaje, "error");
                }
            }

            if (contenedorTabla) {
                contenedorTabla.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dispararAlertaUnica("Atencion: No tienes un turno abierto, por favor inicia uno.");
                }, true);

                contenedorTabla.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dispararAlertaUnica("Atencion: No tienes un turno abierto, por favor inicia uno.");
                }, true);

                contenedorTabla.addEventListener('change', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (e.target.tagName === 'SELECT') {
                        e.target.selectedIndex = 0;
                    }
                    dispararAlertaUnica("Atencion: No tienes un turno abierto, por favor inicia uno.");
                }, true);

                contenedorTabla.addEventListener('focusin', (e) => {
                    e.target.blur();
                    dispararAlertaUnica("Atencion: No tienes un turno abierto, por favor inicia uno.");
                }, true);

                contenedorTabla.addEventListener('submit', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dispararAlertaUnica("Atencion: No tienes un turno abierto, por favor inicia uno.");
                    return false;
                }, true);
            }
        }
    };
</script>

<?php if (isset($_SESSION['pdf_id_estancia'])): 
    $pdf_id_estancia = $_SESSION['pdf_id_estancia'];
    unset($_SESSION['pdf_id_estancia']);
?>
<script>
    try {
        window.open('reportes/pdf_check_out.php?id_estancia=<?php echo $pdf_id_estancia; ?>', '_blank');
    } catch (e) {
        console.log("Ventana emergente bloqueada.");
    }
</script>
<?php endif; ?>

</body>
</html>