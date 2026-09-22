<?php
// 1. Iniciar sesión y validar autenticación
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

require_once 'config/conexion.php';

// Asegurar que PDO muestre excepciones de SQL para depurar rápido si algo falla
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --- FUNCIÓN HELPER PARA FORMATO DE PESOS MEXICANOS ---
function formatoPesos($monto, $con_decimales = true) {
    if ($monto === null || $monto === '') return '-';
    $decimales = $con_decimales ? 2 : 0;
    return '$' . number_format((float)$monto, $decimales, '.', ',');
}

$id_usuario_actual = $_SESSION['id_usuario'];

// Obtener el rol del usuario actual (Recepcionista, Vendedor, Administrador, etc.)
$stmt_rol_actual = $pdo->prepare("SELECT rol FROM usuarios WHERE id_usuario = :id_usuario LIMIT 1");
$stmt_rol_actual->execute(['id_usuario' => $id_usuario_actual]);
$usuario_actual_info = $stmt_rol_actual->fetch();
$rol_usuario_actual = $usuario_actual_info ? trim($usuario_actual_info['rol']) : '';

// 2. Verificar si el usuario tiene un turno actualmente ABIERTO
$stmt_turno = $pdo->prepare("SELECT * FROM turnos WHERE id_usuario = :id_usuario AND estatus = 'abierto' LIMIT 1");
$stmt_turno->execute(['id_usuario' => $id_usuario_actual]);
$turno_activo = $stmt_turno->fetch();

// Mensajes flash temporales para éxito al abrir/cerrar
$mensaje = $_SESSION['mensaje_turno'] ?? "";
$tipo_mensaje = $_SESSION['tipo_mensaje_turno'] ?? "";
unset($_SESSION['mensaje_turno'], $_SESSION['tipo_mensaje_turno']);

// Variable auxiliar para activar la apertura automática del PDF tras cerrar el turno
$id_turno_a_imprimir = null;

// 3. Procesar Acciones (Abrir o Cerrar Turno)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // --- ACCIÓN ABRIR TURNO ---
    if ($accion === 'abrir') {
        if ($turno_activo) {
            header("Location: turnos.php");
            exit;
        } else {
            $monto_inicial_limpio = isset($_POST['monto_inicial']) ? str_replace(',', '', $_POST['monto_inicial']) : 0;
            $monto_inicial = ($monto_inicial_limpio !== '') ? floatval($monto_inicial_limpio) : 0.00;
            $numero_caja = $_POST['numero_caja'] ?? 'caja_1'; 

            try {
                $sql_abrir = "INSERT INTO turnos (id_usuario, numero_caja, fecha_inicio, monto_inicial, monto_final, estatus) 
                              VALUES (:id_usuario, :numero_caja, NOW(), :monto_inicial, 0.00, 'abierto')";
                $stmt = $pdo->prepare($sql_abrir);
                $stmt->execute([
                    'id_usuario'    => $id_usuario_actual,
                    'numero_caja'   => $numero_caja,
                    'monto_inicial' => $monto_inicial
                ]);

                $_SESSION['mensaje_turno'] = "¡Turno abierto con éxito en la " . str_replace('_', ' ', $numero_caja) . "!";
                $_SESSION['tipo_mensaje_turno'] = "exito";

                header("Location: turnos.php");
                exit;

            } catch (Exception $e) {
                $_SESSION['mensaje_turno'] = "Error al abrir turno: " . $e->getMessage();
                $_SESSION['tipo_mensaje_turno'] = "error";
                header("Location: turnos.php");
                exit;
            }
        }
    }

    // --- ACCIÓN CERRAR TURNO ---
    if ($accion === 'cerrar') {
        if (!$turno_activo) {
            $_SESSION['mensaje_turno'] = "No tienes ningún turno abierto para cerrar.";
            $_SESSION['tipo_mensaje_turno'] = "error";
            header("Location: turnos.php");
            exit;
        } else {
            try {
                $id_turno_actual = $turno_activo['id_turno'];

                // Calcular efectivo en vivo para el cierre seguro
                $stmt_calc = $pdo->prepare("SELECT monto_inicial FROM turnos WHERE id_turno = :id_turno");
                $stmt_calc->execute(['id_turno' => $id_turno_actual]);
                $t_data = $stmt_calc->fetch();
                
                $s_efec = $pdo->prepare("SELECT SUM(monto) AS total FROM pagos_estancias WHERE id_turno = :id_turno AND id_usuario = :id_usuario AND metodo_pago = 'Efectivo'");
                $s_efec->execute(['id_turno' => $id_turno_actual, 'id_usuario' => $id_usuario_actual]);
                $efec1 = $s_efec->fetch()['total'] ?? 0;

                $s_efec2 = $pdo->prepare("SELECT SUM(monto) AS total FROM pagos_servicios WHERE id_turno = :id_turno AND id_usuario = :id_usuario AND metodo_pago = 'Efectivo' AND (estado_pago = 'Pagado' OR estado_pago IS NULL)");
                $s_efec2->execute(['id_turno' => $id_turno_actual, 'id_usuario' => $id_usuario_actual]);
                $efec2 = $s_efec2->fetch()['total'] ?? 0;

                $monto_final_calculado = floatval($t_data['monto_inicial']) + floatval($efec1) + floatval($efec2);

                $sql_cerrar = "UPDATE turnos 
                               SET fecha_fin = NOW(), 
                                   monto_final = :monto_final, 
                                   estatus = 'cerrado' 
                               WHERE id_turno = :id_turno";
                $stmt = $pdo->prepare($sql_cerrar);
                $stmt->execute([
                    'monto_final' => $monto_final_calculado,
                    'id_turno'    => $id_turno_actual
                ]);

                // Guardamos el ID del turno cerrado para abrir su reporte PDF automáticamente mediante JS abajo
                $id_turno_a_imprimir = $id_turno_actual;

                // Actualizamos $turno_activo para que la vista refleje inmediatamente que ya no hay turno abierto
                $turno_activo = null; 

            } catch (Exception $e) {
                $_SESSION['mensaje_turno'] = "Error al cerrar turno: " . $e->getMessage();
                $_SESSION['tipo_mensaje_turno'] = "error";
                header("Location: turnos.php");
                exit;
            }
        }
    }
}

// 4. Capturar la caja seleccionada vía GET o usar 'caja_1' por defecto
$caja_seleccionada = $_GET['numero_caja'] ?? 'caja_1';

$dinero_anterior = 0.00;
$ultimo_turno_cerrado = null;

if (!empty($rol_usuario_actual) && $rol_usuario_actual !== 'Camarista') {
    $stmt_ultimo = $pdo->prepare("
        SELECT t.monto_final, t.id_usuario, t.fecha_fin 
        FROM turnos t
        JOIN usuarios u ON t.id_usuario = u.id_usuario
        WHERE t.estatus = 'cerrado' 
          AND t.numero_caja = :numero_caja 
          AND u.rol = :rol_usuario
        ORDER BY t.fecha_fin DESC 
        LIMIT 1
    ");
    $stmt_ultimo->execute([
        'numero_caja'  => $caja_seleccionada,
        'rol_usuario'  => $rol_usuario_actual
    ]);
    $ultimo_turno_cerrado = $stmt_ultimo->fetch();
    $dinero_anterior = $ultimo_turno_cerrado ? floatval($ultimo_turno_cerrado['monto_final']) : 0.00;
}

// 5. Si el turno está ABIERTO, calcular resumen de cobros y obtener la lista exclusiva
$total_efectivo = 0.00;
$total_transferencia = 0.00;
$total_cobrado = 0.00;
$lista_cobros = [];

if ($turno_activo) {
    $id_turno_actual = $turno_activo['id_turno'];
    $caja_seleccionada = $turno_activo['numero_caja']; 

    $sql_est = $pdo->prepare("SELECT metodo_pago, SUM(monto) AS total FROM pagos_estancias WHERE id_turno = :id_turno AND id_usuario = :id_usuario GROUP BY metodo_pago");
    $sql_est->execute([
        'id_turno'   => $id_turno_actual,
        'id_usuario' => $id_usuario_actual
    ]);
    foreach ($sql_est->fetchAll() as $c) {
        if ($c['metodo_pago'] === 'Efectivo') $total_efectivo += $c['total'];
        if ($c['metodo_pago'] === 'Trasferencia' || $c['metodo_pago'] === 'Transferencia') $total_transferencia += $c['total'];
    }

    $sql_ser = $pdo->prepare("SELECT metodo_pago, SUM(monto) AS total FROM pagos_servicios WHERE id_turno = :id_turno AND id_usuario = :id_usuario AND (estado_pago = 'Pagado' OR estado_pago IS NULL) GROUP BY metodo_pago");
    $sql_ser->execute([
        'id_turno'   => $id_turno_actual,
        'id_usuario' => $id_usuario_actual
    ]);
    foreach ($sql_ser->fetchAll() as $c) {
        if ($c['metodo_pago'] === 'Efectivo') $total_efectivo += $c['total'];
        if ($c['metodo_pago'] === 'Trasferencia' || $c['metodo_pago'] === 'Transferencia') $total_transferencia += $c['total'];
    }

    $total_cobrado = $total_efectivo + $total_transferencia;

    $sql_historial_cobros = "
        SELECT 
            'Estancia' AS tipo_cobro,
            p.id_pago,
            p.id_estancia,
            NULL AS id_servicio,
            NULL AS tipo_servicio,
            NULL AS nombre_servicio,
            p.metodo_pago,
            p.monto,
            p.fecha_pago,
            p.id_turno,
            u.nombre_completo AS usuario_cobro,
            u.rol,
            NULL AS estado_pago
        FROM pagos_estancias p
        LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
        WHERE p.id_usuario = :id_usu1 AND p.id_turno = :id_turno1 AND u.rol != 'Camarista'

        UNION ALL

        SELECT 
            'Servicio' AS tipo_cobro,
            p.id_pago,
            p.id_estancia,
            p.id_servicio,
            s.tipo_servicio,
            s.nombre_servicio,
            p.metodo_pago,
            p.monto,
            p.fecha_pago,
            p.id_turno,
            u.nombre_completo AS usuario_cobro,
            u.rol,
            p.estado_pago
        FROM pagos_servicios p
        LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
        LEFT JOIN servicios s ON p.id_servicio = s.id_servicio
        WHERE p.id_usuario = :id_usu2 AND p.id_turno = :id_turno2 AND u.rol != 'Camarista'

        ORDER BY fecha_pago DESC";

    $stmt_hc = $pdo->prepare($sql_historial_cobros);
    $stmt_hc->execute([
        'id_usu1'   => $id_usuario_actual,
        'id_turno1' => $id_turno_actual,
        'id_usu2'   => $id_usuario_actual,
        'id_turno2' => $id_turno_actual
    ]);
    $lista_cobros = $stmt_hc->fetchAll();
}

if ($turno_activo) {
    $mensaje = "Ya tienes un turno abierto en la " . str_replace('_', ' ', $turno_activo['numero_caja']) . ". Debes cerrarlo antes de iniciar otro.";
    $tipo_mensaje = "error";
} elseif (!$turno_activo && empty($mensaje)) {
    $mensaje = "Pulsa Abrir turno ahora para iniciar tu turno";
    $tipo_mensaje = "aviso";
}

include 'includes/header.php';
?>

<!-- Estilo global para prohibir que el usuario seleccione texto con el cursor en todo el panel -->
<style>
    body, html, * {
        -webkit-user-select: none !important;
        -moz-user-select: none !important;
        -ms-user-select: none !important;
        user-select: none !important;
    }
    /* Permite escribir y seleccionar dentro de los inputs o textareas necesarios */
    input, textarea, select {
        -webkit-user-select: text !important;
        -moz-user-select: text !important;
        -ms-user-select: text !important;
        user-select: text !important;
    }
</style>

            <!-- CONTENIDO PRINCIPAL DE TURNOS -->
            <div class="p-4 md:p-8 space-y-6 max-w-7xl mx-auto w-full text-gray-700">

                <!-- SECCIÓN PRINCIPAL: DISTRIBUCIÓN EN 2 COLUMNAS -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
                    
                    <!-- COLUMNA IZQUIERDA: ACCIÓN Y AVISO -->
                    <div class="lg:col-span-5 flex flex-col justify-between space-y-4">
                        
                        <!-- Tarjeta principal -->
                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            
                            <?php if (!$turno_activo): ?>
                                <!-- Formulario de Apertura -->
                                <div class="flex items-center gap-3 mb-4 pb-3 border-b border-gray-100">
                                    <div class="w-8 h-8 rounded bg-gray-100 flex items-center justify-center text-gray-600 font-semibold text-sm">
                                        <i class="fa-solid fa-cash-register"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-base font-semibold text-gray-900">Iniciar nuevo turno</h2>
                                        <p class="text-xs text-gray-500">Rol detectado: <strong><?php echo htmlspecialchars($rol_usuario_actual); ?></strong></p>
                                    </div>
                                </div>

                                <form action="turnos.php" method="POST" class="space-y-4">
                                    <input type="hidden" name="accion" value="abrir">

                                    <div>
                                        <label for="numero_caja" class="block text-xs font-medium text-gray-600 mb-1">Seleccionar Caja:</label>
                                        <select id="numero_caja" name="numero_caja" required onchange="cambiarCaja(this.value)"
                                            class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-gray-900 text-sm focus:ring-1 focus:ring-gray-400 focus:outline-none">
                                            <option value="caja_1" <?php echo ($caja_seleccionada === 'caja_1') ? 'selected' : ''; ?>>Caja 1</option>
                                            <option value="caja_2" <?php echo ($caja_seleccionada === 'caja_2') ? 'selected' : ''; ?>>Caja 2</option>
                                            <option value="caja_3" <?php echo ($caja_seleccionada === 'caja_3') ? 'selected' : ''; ?>>Caja 3</option>
                                            <option value="caja_4" <?php echo ($caja_seleccionada === 'caja_4') ? 'selected' : ''; ?>>Caja 4</option>
                                            <option value="caja_5" <?php echo ($caja_seleccionada === 'caja_5') ? 'selected' : ''; ?>>Caja 5</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label for="monto_inicial" class="block text-xs font-medium text-gray-600 mb-1">Fondo inicial de caja ($):</label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 text-sm">$</span>
                                            <input type="text" id="monto_inicial" name="monto_inicial" value="<?php echo number_format($dinero_anterior, 2, '.', ','); ?>" 
                                                onfocus="clearCurrencyInput(this)" 
                                                onblur="formatCurrencyInput(this)" required
                                                class="w-full pl-7 pr-3 py-2 bg-white border border-gray-300 rounded-lg text-gray-900 text-sm focus:ring-1 focus:ring-gray-400 focus:outline-none">
                                        </div>
                                        <p class="text-xs text-gray-400 mt-1">Último monto dejado por otro/a <strong><?php echo htmlspecialchars($rol_usuario_actual); ?></strong> en esta caja.</p>
                                    </div>

                                    <button type="submit" class="w-full bg-gray-900 hover:bg-gray-800 text-white text-sm font-medium py-2 px-4 rounded-lg shadow-sm transition-colors">
                                        Abrir turno ahora
                                    </button>
                                </form>

                            <?php else: ?>
                                <!-- Formulario de Cierre -->
                                <div class="flex items-center gap-3 mb-4 pb-3 border-b border-gray-100">
                                    <div class="w-8 h-8 rounded bg-gray-100 flex items-center justify-center text-gray-600 font-semibold text-sm">
                                        <i class="fa-solid fa-lock"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-base font-semibold text-gray-900">Finalizar turno actual</h2>
                                        <p class="text-xs text-gray-500">
                                            <strong class="font-semibold text-gray-800"><?php echo ucwords(str_replace('_', ' ', $turno_activo['numero_caja'])); ?></strong> - <?php echo htmlspecialchars($rol_usuario_actual); ?>
                                        </p>
                                    </div>
                                </div>

                                <form action="turnos.php" method="POST" onsubmit="return confirm('¿Estás seguro de que deseas cerrar tu turno?');" class="space-y-4">
                                    <input type="hidden" name="accion" value="cerrar">

                                    <div>
                                        <label for="monto_final" class="block text-xs font-medium text-gray-600 mb-1">Monto final contado en caja ($):</label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 text-sm">$</span>
                                            <input type="text" id="monto_final" name="monto_final" value="<?php echo number_format($turno_activo['monto_inicial'] + $total_efectivo, 2, '.', ','); ?>" 
                                                readonly
                                                class="w-full pl-7 pr-3 py-2 bg-gray-100 border border-gray-300 rounded-lg text-gray-700 text-sm font-medium cursor-not-allowed focus:outline-none">
                                        </div>
                                        <p class="text-xs text-gray-400 mt-1">Este monto se calcula automáticamente y no se puede modificar.</p>
                                    </div>

                                    <button type="submit" style="background-color: #F01D1D;" class="w-full hover:opacity-90 text-white text-sm font-medium py-2 px-4 rounded-lg shadow-sm transition-opacity">
                                        Finalizar Turno
                                    </button>
                                </form>
                            <?php endif; ?>

                        </div>

                        <!-- Contenedor de aviso independiente -->
                        <?php if (!empty($mensaje)): ?>
                            <div class="p-3.5 rounded-xl border text-xs bg-white shadow-sm flex items-center <?php echo ($tipo_mensaje === 'exito') ? 'border-green-300 text-green-900' : (($tipo_mensaje === 'error') ? 'border-red-300 text-red-900' : 'border-amber-300 text-amber-900 bg-amber-50/50'); ?>">
                                <div>
                                    <span class="font-semibold"><?php echo ($tipo_mensaje === 'exito') ? 'Éxito: ' : (($tipo_mensaje === 'error') ? 'Aviso: ' : 'Aviso: '); ?></span>
                                    <?php echo htmlspecialchars($mensaje); ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="flex-1"></div>
                        <?php endif; ?>
                    </div>

                    <!-- COLUMNA DERECHA: ESTADO / ARQUEO EN TIEMPO REAL -->
                    <div class="lg:col-span-7 flex flex-col">
                        <?php if (!$turno_activo): ?>
                            <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm flex flex-col justify-between h-full">
                                <div>
                                    <span class="text-xs font-medium text-gray-500 border border-gray-200 px-2.5 py-1 rounded">Sin turno activo</span>
                                    <h3 class="text-base font-semibold text-gray-900 mt-3">Comienza tu jornada seleccionando tu caja</h3>
                                    <p class="text-xs text-gray-500 mt-1">El sistema busca de forma aislada el cierre del último <strong><?php echo htmlspecialchars($rol_usuario_actual); ?></strong> que operó en la caja seleccionada.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm space-y-3 h-full flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between pb-2.5 border-b border-gray-100">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 bg-green-600 rounded-full"></span>
                                            <h3 class="text-sm font-semibold text-gray-900">
                                                Arqueo en vivo - <?php echo ucwords(str_replace('_', ' ', $turno_activo['numero_caja'])); ?> - Turno #<?php echo $turno_activo['id_turno']; ?>
                                            </h3>
                                        </div>
                                        <span class="text-xs text-gray-500">Iniciado: <?php echo date('h:i A', strtotime($turno_activo['fecha_inicio'])); ?></span>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3 mt-3">
                                        <div class="bg-gray-50 p-2.5 rounded-lg border border-gray-200">
                                            <span class="text-[11px] text-gray-500 block">Cobros en efectivo</span>
                                            <span class="text-sm font-semibold text-gray-900 mt-0.5 block"><?php echo formatoPesos($total_efectivo); ?></span>
                                        </div>
                                        <div class="bg-gray-50 p-2.5 rounded-lg border border-gray-200">
                                            <span class="text-[11px] text-gray-500 block">Por transferencia</span>
                                            <span class="text-sm font-semibold text-gray-900 mt-0.5 block"><?php echo formatoPesos($total_transferencia); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-2.5 pt-1">
                                    <div class="bg-gray-50 border border-gray-200 p-2.5 rounded-lg flex items-center justify-between">
                                        <div>
                                            <span class="text-xs font-semibold text-gray-900 block">Dinero neto generado</span>
                                            <span class="text-[11px] text-gray-500">Suma total de cobros del turno</span>
                                        </div>
                                        <div class="text-sm font-bold text-gray-900">
                                            <?php echo formatoPesos($total_cobrado); ?>
                                        </div>
                                    </div>

                                    <div class="bg-gray-50 border border-gray-200 p-2.5 rounded-lg flex items-center justify-between">
                                        <div>
                                            <span class="text-xs font-semibold text-gray-900 block">Efectivo esperado en caja</span>
                                            <span class="text-[11px] text-gray-500">
                                                Fondo inicial (<?php echo formatoPesos($turno_activo['monto_inicial']); ?>) + Efectivo cobrado (<?php echo formatoPesos($total_efectivo); ?>)
                                            </span>
                                        </div>
                                        <div class="text-sm font-bold text-gray-900 whitespace-nowrap">
                                            <?php echo formatoPesos($turno_activo['monto_inicial'] + $total_efectivo); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- SECCIÓN INFERIOR: HISTORIAL DE COBROS -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="px-4 md:px-6 py-4 border-b border-gray-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Actividad y Cobros de Tu Turno Actual</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Listado detallado de estancias y servicios cobrados en este turno.</p>
                        </div>
                        
                        <?php if ($turno_activo): ?>
                            <a href="reportes/generar_reporte_turno.php?id_turno=<?php echo $turno_activo['id_turno']; ?>" target="_blank" 
                               class="inline-flex items-center gap-1.5 bg-white hover:bg-gray-50 text-gray-700 text-xs font-medium py-2 px-3.5 rounded-lg border border-gray-300 shadow-sm transition-colors whitespace-nowrap">
                                <img src="icons/pdf.png" alt="PDF" class="w-4 h-4 object-contain">
                                Generar reporte
                            </a>
                        <?php else: ?>
                            <button type="button" disabled 
                               class="inline-flex items-center gap-1.5 bg-gray-50 text-gray-400 text-xs font-medium py-2 px-3.5 rounded-lg border border-gray-200 cursor-not-allowed opacity-60 whitespace-nowrap">
                                <img src="icons/pdf.png" alt="PDF" class="w-4 h-4 object-contain opacity-50">
                                Generar reporte
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="w-full overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[700px]">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200 text-xs font-medium text-gray-600">
                                    <th class="py-3 px-4 md:px-6"># Pago</th>
                                    <th class="py-3 px-4 md:px-6">Ref. Estancia / Servicio</th>
                                    <th class="py-3 px-4 md:px-6">Tipo</th>
                                    <th class="py-3 px-4 md:px-6">Fecha de Pago</th>
                                    <th class="py-3 px-4 md:px-6"># Turno</th>
                                    <th class="py-3 px-4 md:px-6">Método de Pago</th>
                                    <th class="py-3 px-4 md:px-6">Monto</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 text-xs">
                                <?php if ($turno_activo && count($lista_cobros) > 0): ?>
                                    <?php foreach ($lista_cobros as $cobro): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="py-3 px-4 md:px-6 font-semibold text-gray-900 whitespace-nowrap">#<?php echo $cobro['id_pago']; ?></td>
                                            <td class="py-3 px-4 md:px-6 text-gray-600">
                                                <?php if ($cobro['tipo_cobro'] === 'Estancia'): ?>
                                                    Estancia: Check-in<br>
                                                    <span class="text-gray-400 text-[11px]">(Estancia #<?php echo $cobro['id_estancia']; ?>)</span>
                                                <?php else: ?>
                                                    <strong class="text-gray-900"><?php echo htmlspecialchars($cobro['tipo_servicio'] ?? 'Servicio'); ?>:</strong> 
                                                    <?php echo htmlspecialchars($cobro['nombre_servicio'] ?? ''); ?><br>
                                                    <span class="text-gray-400 text-[11px]">(Estancia #<?php echo $cobro['id_estancia']; ?>)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-4 md:px-6 whitespace-nowrap">
                                                <?php if ($cobro['tipo_cobro'] === 'Estancia'): ?>
                                                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium bg-green-50 text-green-700 border border-green-200">Estancia</span>
                                                <?php else: ?>
                                                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium bg-cyan-50 text-cyan-700 border border-cyan-200">Servicio</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-4 md:px-6 text-gray-600 whitespace-nowrap"><?php echo $cobro['fecha_pago']; ?></td>
                                            <td class="py-3 px-4 md:px-6 text-gray-600 whitespace-nowrap">#<?php echo $cobro['id_turno']; ?></td>
                                            <td class="py-3 px-4 md:px-6 text-gray-600 whitespace-nowrap"><?php echo htmlspecialchars($cobro['metodo_pago']); ?></td>
                                            <td class="py-3 px-4 md:px-6 font-semibold text-green-700 whitespace-nowrap"><?php echo formatoPesos($cobro['monto']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="py-6 text-center text-gray-400">
                                            <?php echo (!$turno_activo) ? "Abre un turno para ver la actividad registrada." : "No hay cobros registrados en este turno todavía."; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Script para limpiar al enfocar, formatear montos y cambiar de caja dinámicamente -->
    <script>
        // Si PHP detecta que se acaba de cerrar un turno exitosamente, abre el reporte en automático
        <?php if (!empty($id_turno_a_imprimir)): ?>
            window.open('reportes/generar_reporte_turno.php?id_turno=<?php echo $id_turno_a_imprimir; ?>', '_blank');
        <?php endif; ?>

        function cambiarCaja(caja) {
            window.location.href = "turnos.php?numero_caja=" + caja;
        }

        function clearCurrencyInput(input) {
            let valSinComas = input.value.replace(/,/g, '');
            if (parseFloat(valSinComas) === 0 || input.value === '0.00') {
                input.value = '';
            }
        }

        function formatCurrencyInput(input) {
            let value = input.value.replace(/,/g, '');
            let num = parseFloat(value);
            if (isNaN(num) || input.value.trim() === '') {
                input.value = '0.00';
            } else {
                input.value = num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }

        function toggleDropdown(menuId, iconId) {
            const menu = document.getElementById(menuId);
            const icon = document.getElementById(iconId ? iconId : null);
            
            menu.classList.toggle('hidden');
            if (icon) {
                icon.classList.toggle('rotate-180');
            }
        }

        window.addEventListener('click', function(e) {
            const dropdown = document.getElementById('profile-dropdown');
            const button = dropdown ? dropdown.previousElementSibling : null;
            if (button && dropdown && !button.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.add('hidden');
                const icon = document.getElementById('profile-chevron');
                if (icon) icon.classList.remove('rotate-180');
            }
        });
    </script>
</body>
</html>