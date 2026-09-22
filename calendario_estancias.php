<?php
// 1. Iniciar sesión y validar autenticación
session_start();

// Configurar la zona horaria de México para evitar desfasajes
date_default_timezone_set('America/Mexico_City');

if (!isset($_SESSION['id_usuario'])) {
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        http_response_code(403);
        exit(json_encode(['error' => 'No autorizado']));
    }
    header("Location: index.php");
    exit;
}

$rol_usuario = isset($_SESSION['rol']) ? strtolower(trim($_SESSION['rol'])) : '';

require_once 'config/conexion.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Control de Mes y Año base
$mes_actual = isset($_GET['mes']) ? intval($_GET['mes']) : intval(date('m'));
$anio_actual = isset($_GET['anio']) ? intval($_GET['anio']) : intval(date('Y'));

if ($mes_actual < 1) { $mes_actual = 12; $anio_actual--; }
if ($mes_actual > 12) { $mes_actual = 1; $anio_actual++; }

// Carga inicial (mes anterior, actual y siguiente)
$mes_anterior_ini = $mes_actual - 1;
$anio_anterior_ini = $anio_actual;
if ($mes_anterior_ini < 1) { $mes_anterior_ini = 12; $anio_anterior_ini--; }

$mes_siguiente_ini = $mes_actual + 1;
$anio_siguiente_ini = $anio_actual;
if ($mes_siguiente_ini > 12) { $mes_siguiente_ini = 1; $anio_siguiente_ini++; }

function obtenerDatosMes($pdo, $mes, $anio) {
    $primer_dia = sprintf('%04d-%02d-01', $anio, $mes);
    $total_dias = intval(date('t', strtotime($primer_dia)));
    $ultimo_dia = sprintf('%04d-%02d-%02d', $anio, $mes, $total_dias);

    $stmt_est = $pdo->prepare("
        SELECT e.*, h.nombre, h.apellido_p 
        FROM estancias e
        LEFT JOIN huesped h ON e.id_huesped = h.id_huesped
        WHERE (e.fecha_entrada <= :ultimo_dia AND e.fecha_salida >= :primer_dia)
    ");
    $stmt_est->execute([
        'ultimo_dia'  => $ultimo_dia . ' 23:59:59',
        'primer_dia'  => $primer_dia . ' 00:00:00'
    ]);
    $estancias = $stmt_est->fetchAll();

    $mapa = [];
    foreach ($estancias as $est) {
        $mapa[$est['id_habitacion']][] = $est;
    }
    return ['total_dias' => $total_dias, 'estancias' => $mapa, 'primer_str' => $primer_dia, 'ultimo_str' => $ultimo_dia];
}

$info_ant = obtenerDatosMes($pdo, $mes_anterior_ini, $anio_anterior_ini);
$info_act = obtenerDatosMes($pdo, $mes_actual, $anio_actual);
$info_sig = obtenerDatosMes($pdo, $mes_siguiente_ini, $anio_siguiente_ini);

// ---------------------------------------------------------
// PETICIÓN AJAX
// ---------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $target_mes = $mes_actual;
    $target_anio = $anio_actual;
    $info_ajax = obtenerDatosMes($pdo, $target_mes, $target_anio);
    $total_dias_mes = $info_ajax['total_dias'];
    $estancias_por_habitacion = $info_ajax['estancias'];

    $html_encabezado = '';
    for ($d = 1; $d <= $total_dias_mes; $d++) {
        $fecha_iter = sprintf('%04d-%02d-%02d', $target_anio, $target_mes, $d);
        $num_dia_semana = date('N', strtotime($fecha_iter));
        $dias_cortos = ['','L','M','Mi','J','V','S','D'];
        $letra_dia = $dias_cortos[$num_dia_semana];
        
        $html_encabezado .= "<th class=\"p-0 text-center font-medium col-dia\" style=\"width: 40px; min-width: 40px; max-width: 40px; position: sticky; top: 0; z-index: 30;\" data-dia-col=\"{$d}\" data-mes=\"{$target_mes}\" data-anio=\"{$target_anio}\" data-fecha=\"{$target_anio}-".sprintf('%02d', $target_mes)."-".sprintf('%02d', $d)."\">
            <div class=\"py-3 px-1 bg-gray-200 text-gray-900 font-bold border-r border-gray-300 h-full flex flex-col justify-center\">
                <span class=\"block text-[10px] text-gray-500\">{$letra_dia}</span>
                {$d}
            </div>
        </th>";
    }

    $stmt_hab = $pdo->query("SELECT id_habitacion FROM habitaciones ORDER BY LPAD(numero_habitacion, 10, '0') ASC");
    $habitaciones = $stmt_hab->fetchAll();
    $html_habitaciones = [];

    foreach ($habitaciones as $hab) {
        $id_hab = $hab['id_habitacion'];
        $estancias_hab = $estancias_por_habitacion[$id_hab] ?? [];

        $carril_arriba = []; $carril_medio = []; $carril_abajo = [];

        foreach ($estancias_hab as $est) {
            $f_entrada_str = substr($est['fecha_entrada'], 0, 10);
            $f_salida_str  = substr($est['fecha_salida'], 0, 10);
            
            $primer_mes_str = $info_ajax['primer_str'];
            $ultimo_mes_str = $info_ajax['ultimo_str'];

            if ($f_salida_str < $primer_mes_str || $f_entrada_str > $ultimo_mes_str) {
                continue;
            }

            $dt_entrada = new DateTime($f_entrada_str);
            $dt_salida  = new DateTime($f_salida_str);

            $dia_inicio = ($f_entrada_str < $primer_mes_str) ? 1 : intval($dt_entrada->format('j'));
            $dia_fin = ($f_salida_str > $ultimo_mes_str) ? $total_dias_mes : intval($dt_salida->format('j'));

            $duracion = ($dia_fin - $dia_inicio) + 1;
            $duracion = max(1, $duracion);

            if ($dia_fin >= $dia_inicio) {
                $estatus_lower = strtolower(trim($est['estatus_estancia'] ?? ''));
                if ($estatus_lower === 'finalizada') {
                    $carril_abajo[$dia_inicio] = ['estancia' => $est, 'duracion' => $duracion];
                } elseif ($estatus_lower === 'reservada') {
                    $carril_medio[$dia_inicio] = ['estancia' => $est, 'duracion' => $duracion];
                } else {
                    $carril_arriba[$dia_inicio] = ['estancia' => $est, 'duracion' => $duracion];
                }
            }
        }

        $html_tds = '';
        for ($d = 1; $d <= $total_dias_mes; $d++) {
            $html_tds .= '<td class="p-0 text-center relative h-20 align-middle col-dia-contenido" style="width: 40px; min-width: 40px; max-width: 40px;" data-mes="'.$target_mes.'" data-anio="'.$target_anio.'" data-dia="'.$d.'" data-fecha="'.$target_anio.'-'.sprintf('%02d', $target_mes).'-'.sprintf('%02d', $d).'">';
            $html_tds .= '<div class="flex flex-col justify-around h-full py-1.5 px-0.5 border-r border-b border-gray-200"></div>';
            
            if (isset($carril_arriba[$d])) {
                $item = $carril_arriba[$d]; $est = $item['estancia']; $span = $item['duracion'];
                if (($d + $span - 1) > $total_dias_mes) $span = ($total_dias_mes - $d) + 1;
                $width_px = ($span * 40) - 2;
                $html_tds .= '<div class="absolute rounded-md bg-red-600 text-white flex items-center justify-center text-[9px] font-medium shadow-2xs overflow-hidden cursor-pointer z-20" style="left: 1px; width: '.($width_px - 1).'px; top: 8px; height: 18px;" title="[Activo] #'.$est['id_estancia'].' - '.htmlspecialchars($est['nombre'] ?? '').'"><span class="truncate px-1.5">#'.$est['id_estancia'].' - '.htmlspecialchars($est['nombre'] ?? 'Huésped').'</span></div>';
            }
            if (isset($carril_medio[$d])) {
                $item = $carril_medio[$d]; $est = $item['estancia']; $span = $item['duracion'];
                if (($d + $span - 1) > $total_dias_mes) $span = ($total_dias_mes - $d) + 1;
                $width_px = ($span * 40) - 2;
                $html_tds .= '<div class="absolute rounded-md bg-blue-600 text-white flex items-center justify-center text-[9px] font-medium shadow-2xs overflow-hidden cursor-pointer z-20" style="left: 1px; width: '.($width_px - 1).'px; top: 31px; height: 18px;" title="[Reservado] #'.$est['id_estancia'].' - '.htmlspecialchars($est['nombre'] ?? '').'"><span class="truncate px-1.5">#'.$est['id_estancia'].' - '.htmlspecialchars($est['nombre'] ?? 'Huésped').'</span></div>';
            }
            if (isset($carril_abajo[$d])) {
                $item = $carril_abajo[$d]; $est = $item['estancia']; $span = $item['duracion'];
                if (($d + $span - 1) > $total_dias_mes) $span = ($total_dias_mes - $d) + 1;
                $width_px = ($span * 40) - 2;
                $html_tds .= '<div class="absolute rounded-md bg-gray-400 text-white flex items-center justify-center text-[9px] font-medium shadow-2xs overflow-hidden cursor-pointer z-20" style="left: 1px; width: '.($width_px - 1).'px; top: 54px; height: 18px;" title="[Finalizado] #'.$est['id_estancia'].' - '.htmlspecialchars($est['nombre'] ?? '').'"><span class="truncate px-1.5">#'.$est['id_estancia'].' - '.htmlspecialchars($est['nombre'] ?? 'Huésped').'</span></div>';
            }

            $html_tds .= '</td>';
        }

        $html_habitaciones[] = ['id_habitacion' => $id_hab, 'html_tds' => $html_tds];
    }

    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'total_dias' => $total_dias_mes,
        'html_encabezado' => $html_encabezado,
        'html_habitaciones' => $html_habitaciones,
        'mes_nombre' => ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'][$target_mes]
    ]);
    exit;
}

$stmt_hab = $pdo->query("SELECT * FROM habitaciones ORDER BY LPAD(numero_habitacion, 10, '0') ASC");
$habitaciones = $stmt_hab->fetchAll();

$fecha_hoy_str = date('Y-m-d');

include 'includes/header.php';
?>

<style>
    body, html, * {
        -webkit-user-select: none !important;
        -moz-user-select: none !important;
        -ms-user-select: none !important;
        user-select: none !important;
    }
    input, textarea, select {
        -webkit-user-select: text !important;
        -moz-user-select: text !important;
        -ms-user-select: text !important;
        user-select: text !important;
    }

    .columna-fija-habitacion {
        position: sticky;
        left: 0;
        z-index: 25;
        width: 110px !important;
        min-width: 110px !important;
        max-width: 110px !important;
        background-color: #e5e7eb !important; /* Mismo color bg-gray-200 */
    }
    thead th.columna-fija-habitacion {
        background-color: #e5e7eb !important;
        z-index: 45 !important; 
    }

    #contenedor-scroll-calendario {
        overflow-x: auto;
        overflow-y: scroll;
        scrollbar-width: auto;
        scrollbar-color: #c1c1c1 #f1f1f1;
    }
    #contenedor-scroll-calendario::-webkit-scrollbar {
        width: 18px !important;  
        height: 0px !important;  
    }
    #contenedor-scroll-calendario::-webkit-scrollbar-track {
        background: #f1f1f1 !important;
    }
    #contenedor-scroll-calendario::-webkit-scrollbar-thumb {
        background-color: #c1c1c1 !important;
        border-radius: 8px !important;
        border: 3px solid #f1f1f1 !important;
    }
    #contenedor-scroll-calendario::-webkit-scrollbar-thumb:hover {
        background-color: #a8a8a8 !important;
    }
</style>

            <!-- CONTENIDO PRINCIPAL -->
            <div class="py-4 px-6 md:px-8 space-y-3 max-w-full mx-auto w-full text-gray-700">

                <!-- ENCABEZADO CON LOS BOTONES DE ACCIÓN -->
                <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-gray-200">
                    <div class="flex items-center gap-2">
                        <button onclick="window.location.href='habitaciones.php'" class="px-3 py-1.5 bg-white text-gray-700 border border-gray-300 text-xs font-semibold rounded-xl hover:bg-gray-50 transition-colors shadow-sm flex items-center gap-2">
                            <img src="icons/agregar.png" alt="Habitaciones" class="w-3.5 h-3.5 object-contain">
                            <span>Habitaciones</span>
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button onclick="window.location.href='huespedes.php'" class="px-3 py-1.5 bg-white text-gray-700 border border-gray-300 text-xs font-semibold rounded-xl hover:bg-gray-50 transition-colors shadow-sm flex items-center gap-2">
                            <img src="icons/huesped.png" alt="Huéspedes" class="w-3.5 h-3.5 object-contain">
                            <span>Huéspedes</span>
                        </button>
                        <button onclick="window.location.href='check_in.php'" class="px-3 py-1.5 bg-white text-gray-700 border border-gray-300 text-xs font-semibold rounded-xl hover:bg-gray-50 transition-colors shadow-sm flex items-center gap-2">
                            <img src="icons/check-in.png" alt="Check-In" class="w-3.5 h-3.5 object-contain">
                            <span>Check-In</span>
                        </button>
                        <button onclick="window.location.href='check_out.php'" class="px-3 py-1.5 bg-white text-gray-700 border border-gray-300 text-xs font-semibold rounded-xl hover:bg-gray-50 transition-colors shadow-sm flex items-center gap-2">
                            <img src="icons/check-out.png" alt="Check-Out" class="w-3.5 h-3.5 object-contain">
                            <span>Check-Out</span>
                        </button>
                    </div>
                </div>

                <!-- ESTADO DE LA HABITACIÓN Y CONTROLES DE MES -->
                <div class="bg-white border border-gray-200 rounded-xl px-4 py-3 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4 text-xs">
                    <div class="flex items-center gap-6">
                        <span class="font-semibold text-gray-700">Estado de la Habitacion:</span>
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 bg-red-600 rounded-sm"></span>
                            <span class="text-gray-600">Ocupado / Activo</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 bg-blue-600 rounded-sm"></span>
                            <span class="text-gray-600">Reservado</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 bg-gray-400 rounded-sm"></span>
                            <span class="text-gray-600">Finalizado</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <?php 
                            $meses_es = [1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'];
                            $mes_actual_num = intval(date('n'));
                            $anio_actual_num = intval(date('Y'));
                        ?>

                        <!-- Botón "Ir a hoy" -->
                        <a href="calendario_estancias.php?mes=<?php echo $mes_actual_num; ?>&anio=<?php echo $anio_actual_num; ?>" 
                           class="px-3 py-1.5 bg-[#014d4e] hover:bg-[#013b3c] text-white text-xs font-medium rounded-lg shadow-sm transition-colors flex items-center gap-2">
                            <img src="icons/calendario.png" alt="Calendario" class="w-3.5 h-3.5 object-contain filter invert">
                            <span>Ir a hoy</span>
                        </a>

                        <form method="GET" action="calendario_estancias.php" class="flex items-center gap-1.5">
                            <select name="mes" onchange="this.form.submit()" class="px-2 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs font-medium text-gray-700 focus:outline-none focus:ring-1 focus:ring-gray-400">
                                <?php foreach ($meses_es as $num_m => $nombre_m): ?>
                                    <option value="<?php echo $num_m; ?>" <?php echo ($num_m == $mes_actual) ? 'selected' : ''; ?>>
                                        <?php echo $nombre_m; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <select name="anio" onchange="this.form.submit()" class="px-2 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs font-medium text-gray-700 focus:outline-none focus:ring-1 focus:ring-gray-400">
                                <?php for ($a = 2025; $a <= 2035; $a++): ?>
                                    <option value="<?php echo $a; ?>" <?php echo ($a == $anio_actual) ? 'selected' : ''; ?>>
                                        <?php echo $a; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </form>
                    </div>
                </div>

                <!-- MATRIZ DEL CALENDARIO -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="bg-gray-100 border-b border-gray-200 py-2.5 px-4 text-center sticky top-0 z-30">
                        <span id="label-mes-actual" class="text-sm font-bold text-gray-800"
                              data-mes-actual="<?php echo $mes_actual; ?>" data-anio-actual="<?php echo $anio_actual; ?>"
                              data-mes-min="<?php echo $mes_anterior_ini; ?>" data-anio-min="<?php echo $anio_anterior_ini; ?>" 
                              data-mes-max="<?php echo $mes_siguiente_ini; ?>" data-anio-max="<?php echo $anio_siguiente_ini; ?>">
                            <?php echo $meses_es[$mes_actual] . ' ' . $anio_actual; ?>
                        </span>
                    </div>

                    <div id="contenedor-scroll-calendario" class="overflow-x-auto overflow-y-scroll w-full relative cursor-grab active:cursor-grabbing select-none" style="max-height: 70vh;">
                        <table id="tabla-calendario" class="w-full border-collapse text-left text-xs" style="table-layout: fixed; min-width: 2700px;">
                            <thead>
                                <tr id="fila-encabezado-dias" class="bg-gray-50 text-gray-700">
                                    <th class="py-3 px-3 font-semibold border-r border-b border-gray-300 columna-fija-habitacion" style="position: sticky; top: 0; z-index: 40; background-color: #e5e7eb !important;">Habitación</th>
                                    
                                    <?php 
                                    $bloques_iniciales = [
                                        [$mes_anterior_ini, $anio_anterior_ini, $info_ant],
                                        [$mes_actual, $anio_actual, $info_act],
                                        [$mes_siguiente_ini, $anio_siguiente_ini, $info_sig]
                                    ];
                                    foreach ($bloques_iniciales as [$m_iter, $a_iter, $inf]):
                                        for ($d = 1; $d <= $inf['total_dias']; $d++):
                                            $fecha_iter = sprintf('%04d-%02d-%02d', $a_iter, $m_iter, $d);
                                            $num_dia_semana = date('N', strtotime($fecha_iter));
                                    ?>
                                        <th class="p-0 text-center font-medium col-dia" style="width: 40px; min-width: 40px; max-width: 40px; position: sticky; top: 0; z-index: 30;" data-dia-col="<?php echo $d; ?>" data-mes="<?php echo $m_iter; ?>" data-anio="<?php echo $a_iter; ?>" data-fecha="<?php echo $fecha_iter; ?>">
                                            <div class="py-3 px-1 bg-gray-200 text-gray-900 font-bold border-r border-gray-300 h-full flex flex-col justify-center">
                                                <span class="block text-[10px] text-gray-500">
                                                    <?php 
                                                        $dias_cortos = ['','L','M','Mi','J','V','S','D'];
                                                        echo $dias_cortos[$num_dia_semana];
                                                    ?>
                                                </span>
                                                <?php echo $d; ?>
                                            </div>
                                        </th>
                                    <?php 
                                        endfor;
                                    endforeach; 
                                    ?>
                                </tr>
                            </thead>
                            <tbody id="cuerpo-tabla-calendario">
                                <?php if (count($habitaciones) > 0): ?>
                                    <?php foreach ($habitaciones as $hab): 
                                        $id_hab = $hab['id_habitacion'];
                                    ?>
                                        <tr class="hover:bg-gray-50/50 transition-colors h-20 fila-habitacion" data-id-habitacion="<?php echo $id_hab; ?>">
                                            <td class="py-3 px-3 font-semibold text-gray-900 border-r border-b border-gray-300 whitespace-nowrap align-middle columna-fija-habitacion" style="background-color: #e5e7eb !important;">
                                                <div>
                                                    <span class="text-sm">#<?php echo htmlspecialchars($hab['numero_habitacion']); ?></span>
                                                    <span class="block text-[11px] font-normal text-gray-500 capitalize"><?php echo htmlspecialchars($hab['tipo_habitacion']); ?></span>
                                                </div>
                                            </td>

                                            <?php 
                                            foreach ($bloques_iniciales as [$m_iter, $a_iter, $inf]):
                                                $estancias_hab = $inf['estancias'][$id_hab] ?? [];
                                                $primer_mes_str = $inf['primer_str'];
                                                $ultimo_mes_str = $inf['ultimo_str'];
                                                $total_dias_mes = $inf['total_dias'];

                                                $carril_arriba = []; $carril_medio = []; $carril_abajo = [];

                                                foreach ($estancias_hab as $est) {
                                                    $f_entrada_str = substr($est['fecha_entrada'], 0, 10);
                                                    $f_salida_str  = substr($est['fecha_salida'], 0, 10);
                                                    
                                                    if ($f_salida_str < $primer_mes_str || $f_entrada_str > $ultimo_mes_str) {
                                                        continue;
                                                    }

                                                    $dt_entrada = new DateTime($f_entrada_str);
                                                    $dt_salida  = new DateTime($f_salida_str);

                                                    $dia_inicio = ($f_entrada_str < $primer_mes_str) ? 1 : intval($dt_entrada->format('j'));
                                                    $dia_fin = ($f_salida_str > $ultimo_mes_str) ? $total_dias_mes : intval($dt_salida->format('j'));

                                                    $duracion = ($dia_fin - $dia_inicio) + 1;
                                                    $duracion = max(1, $duracion);

                                                    if ($dia_fin >= $dia_inicio) {
                                                        $estatus_lower = strtolower(trim($est['estatus_estancia'] ?? ''));

                                                        if ($estatus_lower === 'finalizada') {
                                                            $carril_abajo[$dia_inicio] = ['estancia' => $est, 'duracion' => $duracion];
                                                        } elseif ($estatus_lower === 'reservada') {
                                                            $carril_medio[$dia_inicio] = ['estancia' => $est, 'duracion' => $duracion];
                                                        } else {
                                                            $carril_arriba[$dia_inicio] = ['estancia' => $est, 'duracion' => $duracion];
                                                        }
                                                    }
                                                }

                                                for ($d = 1; $d <= $total_dias_mes; $d++):
                                                    $fecha_iter = sprintf('%04d-%02d-%02d', $a_iter, $m_iter, $d);
                                            ?>
                                                <td class="p-0 text-center relative h-20 align-middle col-dia-contenido" style="width: 40px; min-width: 40px; max-width: 40px;" data-mes="<?php echo $m_iter; ?>" data-anio="<?php echo $a_iter; ?>" data-dia="<?php echo $d; ?>" data-fecha="<?php echo $fecha_iter; ?>">
                                                    <div class="flex flex-col justify-around h-full py-1.5 px-0.5 border-r border-b border-gray-200"></div>

                                                    <?php 
                                                    if (isset($carril_arriba[$d])) {
                                                        $item = $carril_arriba[$d]; $est = $item['estancia']; $span = $item['duracion'];
                                                        if (($d + $span - 1) > $total_dias_mes) $span = ($total_dias_mes - $d) + 1;
                                                        $width_px = ($span * 40) - 2;
                                                    ?>
                                                        <div class="absolute rounded-md bg-red-600 text-white flex items-center justify-center text-[9px] font-medium shadow-2xs overflow-hidden cursor-pointer z-20"
                                                             style="left: 1px; width: <?php echo ($width_px - 1); ?>px; top: 8px; height: 18px;"
                                                             title="[Activo] #<?php echo $est['id_estancia']; ?> - <?php echo htmlspecialchars($est['nombre'] ?? ''); ?>">
                                                            <span class="truncate px-1.5">#<?php echo $est['id_estancia']; ?> - <?php echo htmlspecialchars($est['nombre'] ?? 'Huésped'); ?></span>
                                                        </div>
                                                    <?php } ?>

                                                    <?php 
                                                    if (isset($carril_medio[$d])) {
                                                        $item = $carril_medio[$d]; $est = $item['estancia']; $span = $item['duracion'];
                                                        if (($d + $span - 1) > $total_dias_mes) $span = ($total_dias_mes - $d) + 1;
                                                        $width_px = ($span * 40) - 2;
                                                    ?>
                                                        <div class="absolute rounded-md bg-blue-600 text-white flex items-center justify-center text-[9px] font-medium shadow-2xs overflow-hidden cursor-pointer z-20"
                                                             style="left: 1px; width: <?php echo ($width_px - 1); ?>px; top: 31px; height: 18px;"
                                                             title="[Reservado] #<?php echo $est['id_estancia']; ?> - <?php echo htmlspecialchars($est['nombre'] ?? ''); ?>">
                                                            <span class="truncate px-1.5">#<?php echo $est['id_estancia']; ?> - <?php echo htmlspecialchars($est['nombre'] ?? 'Huésped'); ?></span>
                                                        </div>
                                                    <?php } ?>

                                                    <?php 
                                                    if (isset($carril_abajo[$d])) {
                                                        $item = $carril_abajo[$d]; $est = $item['estancia']; $span = $item['duracion'];
                                                        if (($d + $span - 1) > $total_dias_mes) $span = ($total_dias_mes - $d) + 1;
                                                        $width_px = ($span * 40) - 2;
                                                    ?>
                                                        <div class="absolute rounded-md bg-gray-400 text-white flex items-center justify-center text-[9px] font-medium shadow-2xs overflow-hidden cursor-pointer z-20"
                                                             style="left: 1px; width: <?php echo ($width_px - 1); ?>px; top: 54px; height: 18px;"
                                                             title="[Finalizado] #<?php echo $est['id_estancia']; ?> - <?php echo htmlspecialchars($est['nombre'] ?? ''); ?>">
                                                            <span class="truncate px-1.5">#<?php echo $est['id_estancia']; ?> - <?php echo htmlspecialchars($est['nombre'] ?? 'Huésped'); ?></span>
                                                        </div>
                                                    <?php } ?>

                                                </td>
                                            <?php 
                                                endfor;
                                            endforeach; 
                                            ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="100" class="py-6 text-center text-gray-400">
                                            No hay habitaciones registradas en el sistema.
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

    <script>
        const contenedorScroll = document.getElementById('contenedor-scroll-calendario');
        const labelMes = document.getElementById('label-mes-actual');
        const nombresMeses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        let cargandoMes = false;
        let timeoutScroll = null;

        let estaArrastrando = false;
        let inicioX = 0;
        let scrollIzquierdaInicial = 0;

        contenedorScroll.addEventListener('mousedown', (e) => {
            estaArrastrando = true;
            inicioX = e.pageX - contenedorScroll.offsetLeft;
            scrollIzquierdaInicial = contenedorScroll.scrollLeft;
        });

        contenedorScroll.addEventListener('mouseleave', () => {
            estaArrastrando = false;
        });

        contenedorScroll.addEventListener('mouseup', () => {
            estaArrastrando = false;
        });

        contenedorScroll.addEventListener('mousemove', (e) => {
            if (!estaArrastrando) return;
            e.preventDefault();
            const x = e.pageX - contenedorScroll.offsetLeft;
            const desplazamientoX = (x - inicioX) * 1.5;
            contenedorScroll.scrollLeft = scrollIzquierdaInicial - desplazamientoX;
        });

        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                const fechaHoy = "<?php echo $fecha_hoy_str; ?>";
                const thHoy = document.querySelector(`#fila-encabezado-dias th.col-dia[data-fecha="${fechaHoy}"]`);
                
                if (thHoy) {
                    contenedorScroll.scrollLeft = thHoy.offsetLeft - 110; 
                } else {
                    contenedorScroll.scrollLeft = contenedorScroll.scrollWidth / 3;
                }
                actualizarMesVisible();
            }, 50);
        });

        contenedorScroll.addEventListener('scroll', function() {
            clearTimeout(timeoutScroll);
            
            actualizarMesVisible();

            if (cargandoMes) return;

            timeoutScroll = setTimeout(() => {
                const scrollLeft = contenedorScroll.scrollLeft;
                const scrollWidth = contenedorScroll.scrollWidth;
                const clientWidth = contenedorScroll.clientWidth;

                if (scrollWidth - (scrollLeft + clientWidth) <= 300) {
                    cargandoMes = true;
                    cargarMesScroll('adelante');
                }

                if (scrollLeft <= 300) {
                    cargandoMes = true;
                    cargarMesScroll('atras');
                }
            }, 100);
        });

        function actualizarMesVisible() {
            const rectContenedor = contenedorScroll.getBoundingClientRect();
            const puntoReferencia = rectContenedor.left + 110 + 20;

            const columnasTh = document.querySelectorAll('#fila-encabezado-dias th.col-dia');
            let mesDetectado = null;
            let anioDetectado = null;

            for (let th of columnasTh) {
                const rectTh = th.getBoundingClientRect();
                if (rectTh.left <= puntoReferencia && rectTh.right > puntoReferencia) {
                    mesDetectado = th.getAttribute('data-mes');
                    anioDetectado = th.getAttribute('data-anio');
                    break;
                }
            }

            if (!mesDetectado && columnasTh.length > 0) {
                for (let th of columnasTh) {
                    const rectTh = th.getBoundingClientRect();
                    if (rectTh.left >= rectContenedor.left + 110) {
                        mesDetectado = th.getAttribute('data-mes');
                        anioDetectado = th.getAttribute('data-anio');
                        break;
                    }
                }
            }

            if (mesDetectado && anioDetectado) {
                labelMes.textContent = `${nombresMeses[parseInt(mesDetectado)]} ${anioDetectado}`;
            }
        }

        function cargarMesScroll(direccion) {
            let mesMin = parseInt(labelMes.getAttribute('data-mes-min') || '<?php echo $mes_anterior_ini; ?>');
            let anioMin = parseInt(labelMes.getAttribute('data-anio-min') || '<?php echo $anio_anterior_ini; ?>');
            let mesMax = parseInt(labelMes.getAttribute('data-mes-max') || '<?php echo $mes_siguiente_ini; ?>');
            let anioMax = parseInt(labelMes.getAttribute('data-anio-max') || '<?php echo $mes_siguiente_ini; ?>');

            const ths = document.querySelectorAll('#fila-encabezado-dias th.col-dia');
            if (ths.length > 0) {
                const primerTh = ths[0];
                const ultimoTh = ths[ths.length - 1];
                mesMin = parseInt(primerTh.getAttribute('data-mes'));
                anioMin = parseInt(primerTh.getAttribute('data-anio'));
                mesMax = parseInt(ultimoTh.getAttribute('data-mes'));
                anioMax = parseInt(ultimoTh.getAttribute('data-anio'));
            }

            let targetMes, targetAnio;

            if (direccion === 'adelante') {
                targetMes = mesMax + 1;
                targetAnio = anioMax;
                if (targetMes > 12) { targetMes = 1; targetAnio++; }
            } else {
                targetMes = mesMin - 1;
                targetAnio = anioMin;
                if (targetMes < 1) { targetMes = 12; targetAnio--; }
            }

            fetch(`calendario_estancias.php?ajax=1&mes=${targetMes}&anio=${targetAnio}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const filaEncabezado = document.getElementById('fila-encabezado-dias');

                        if (direccion === 'adelante') {
                            filaEncabezado.insertAdjacentHTML('beforeend', data.html_encabezado);
                            data.html_habitaciones.forEach(itemHab => {
                                const filaHab = document.querySelector(`.fila-habitacion[data-id-habitacion="${itemHab.id_habitacion}"]`);
                                if (filaHab) filaHab.insertAdjacentHTML('beforeend', itemHab.html_tds);
                            });

                            labelMes.setAttribute('data-mes-max', targetMes);
                            labelMes.setAttribute('data-anio-max', targetAnio);
                        } else {
                            const oldScrollWidth = contenedorScroll.scrollWidth;
                            const oldScrollLeft = contenedorScroll.scrollLeft;

                            const primerThDespuesHabitacion = filaEncabezado.querySelector('th:nth-child(2)');
                            if (primerThDespuesHabitacion) {
                                primerThDespuesHabitacion.insertAdjacentHTML('beforebegin', data.html_encabezado);
                            }

                            data.html_habitaciones.forEach(itemHab => {
                                const filaHab = document.querySelector(`.fila-habitacion[data-id-habitacion="${itemHab.id_habitacion}"]`);
                                if (filaHab) {
                                    const primerTdDespuesHabitacion = filaHab.querySelector('td:nth-child(2)');
                                    if (primerTdDespuesHabitacion) {
                                        primerTdDespuesHabitacion.insertAdjacentHTML('beforebegin', itemHab.html_tds);
                                    }
                                }
                            });

                            labelMes.setAttribute('data-mes-min', targetMes);
                            labelMes.setAttribute('data-anio-min', targetAnio);

                            setTimeout(() => {
                                const newScrollWidth = contenedorScroll.scrollWidth;
                                contenedorScroll.scrollLeft = oldScrollLeft + (newScrollWidth - oldScrollWidth);
                            }, 10);
                        }
                    }
                    cargandoMes = false;
                })
                .catch(error => {
                    console.error('Error en scroll infinito bidireccional:', error);
                    cargandoMes = false;
                });
        }
    </script>
</body>
</html>