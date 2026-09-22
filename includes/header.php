<?php
// Si no hay una sesión activa, la iniciamos de forma segura
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// FILTRO DE SEGURIDAD: Si no existe la sesión del usuario, lo mandamos al Login
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

// Identificamos de manera segura si el usuario actual es un camarista
$es_camarista = (isset($_SESSION['rol']) && strtolower($_SESSION['rol']) === 'camarista') || isset($_SESSION['id_camarista']);

// Configurar zona horaria local
date_default_timezone_set('America/Mexico_City');

// Arrays para traducir los días y meses al español de forma nativa
$dias_semana = [
    'Sunday' => 'Domingo', 'Monday' => 'Lunes', 'Tuesday' => 'Martes', 
    'Wednesday' => 'Miércoles', 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado'
];
$meses_anio = [
    'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril', 
    'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto', 
    'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'
];

$dia_ingles = date('l');
$mes_ingles = date('F');
$fecha_formateada = "Hoy es " . $dias_semana[$dia_ingles] . ", " . date('d') . " de " . $meses_anio[$mes_ingles] . " de " . date('Y');

// --- DETECCIÓN AUTOMÁTICA DE LA SECCIÓN PARA EL TÍTULO ---
$pagina_actual = basename($_SERVER['PHP_SELF']);

$titulos_secciones = [
    'home.php'                  => 'Dashboard',
    'turnos.php'                => 'Control de Turnos',
    'calendario_estancias.php'  => 'Calendario de Estancias',
    'check_in.php'              => 'Check-in',
    'check_out.php'             => 'Check-out',
    'habitaciones.php'          => 'Habitaciones',
    'lista_huespedes.php'       => 'Lista de Huéspedes',
    'historial_huespedes.php'   => 'Historial de Huéspedes',
    'empleados.php'             => 'Empleados',
    'incidencias.php'           => 'Incidencias',
    'servicios.php'             => 'Servicios',
    'camaristas.php'            => 'Turno Camarista',
    'corte_caja.php'            => 'Corte de Caja',
    'configuracion.php'         => 'Configuración',
    'reportes.php'              => 'Reportes',
    'perfil.php'                => 'Perfil de Usuario'
];

$subtitulo_dinamico = isset($titulos_secciones[$pagina_actual]) ? $titulos_secciones[$pagina_actual] : 'Dashboard';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HotelSys - <?php echo $subtitulo_dinamico; ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased">

    <div class="flex h-screen overflow-hidden">

        <!-- SIDEBAR / HUB DE NAVEGACIÓN -->
        <aside class="w-64 bg-white border-r border-gray-200 flex flex-col justify-between hidden md:flex shadow-sm">
            <div>
                <div class="h-20 flex items-center justify-center px-4">
                    <img src="logotipo/original.jpg" alt="HotelSys Logo" class="h-12 w-full object-contain">
                </div>

                <!-- Enlaces del Menú -->
                <nav class="p-4 space-y-1 overflow-y-auto max-h-[calc(100vh-8rem)]">
                    
                    <!-- 1. Inicio -->
                    <a href="home.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg <?php echo ($pagina_actual == 'home.php') ? 'bg-teal-100/70 text-teal-900 font-semibold' : 'text-gray-700 hover:bg-gray-100 font-medium'; ?> transition-colors">
                        <i class="fa-solid fa-house w-5 text-center <?php echo ($pagina_actual == 'home.php') ? 'text-teal-700' : 'text-gray-500'; ?>"></i>
                        <span>Inicio</span>
                    </a>

                    <!-- 2. Control de Turnos -->
                    <?php if (!$es_camarista): ?>
                    <a href="turnos.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg <?php echo ($pagina_actual == 'turnos.php') ? 'bg-teal-100/70 text-teal-900 font-semibold' : 'text-gray-700 hover:bg-gray-100 font-medium'; ?> transition-colors">
                        <i class="fa-solid fa-clock w-5 text-center <?php echo ($pagina_actual == 'turnos.php') ? 'text-teal-700' : 'text-gray-500'; ?>"></i>
                        <span>Control de Turnos</span>
                    </a>
                    <?php endif; ?>

                    <!-- 3. Estancias (Desplegable) -->
                    <div>
                        <button onclick="toggleDropdown('estancias-menu', 'estancias-icon')" class="w-full flex items-center justify-between px-4 py-2.5 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors font-medium">
                            <div class="flex items-center gap-3">
                                <i class="fa-solid fa-bed w-5 text-center text-gray-500"></i>
                                <span>Estancias</span>
                            </div>
                            <i id="estancias-icon" class="fa-solid fa-chevron-down text-xs text-gray-400 transition-transform duration-200"></i>
                        </button>
                        <div id="estancias-menu" class="hidden pl-11 pr-2 py-1 space-y-1">
                            <a href="calendario_estancias.php" class="block py-2 px-3 rounded-md text-sm text-gray-600 hover:text-teal-700 hover:bg-gray-50 font-medium transition-colors">Gestión de Estancias</a>
                            <a href="check_in.php" class="block py-2 px-3 rounded-md text-sm text-gray-600 hover:text-teal-700 hover:bg-gray-50 font-medium transition-colors">Check-in</a>
                            <a href="check_out.php" class="block py-2 px-3 rounded-md text-sm text-gray-600 hover:text-teal-700 hover:bg-gray-50 font-medium transition-colors">Check-out</a>
                        </div>
                    </div>

                    <!-- 4. Habitaciones -->
                    <a href="habitaciones.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg <?php echo ($pagina_actual == 'habitaciones.php') ? 'bg-teal-100/70 text-teal-900 font-semibold' : 'text-gray-700 hover:bg-gray-100 font-medium'; ?> transition-colors">
                        <i class="fa-solid fa-door-open w-5 text-center <?php echo ($pagina_actual == 'habitaciones.php') ? 'text-teal-700' : 'text-gray-500'; ?>"></i>
                        <span>Habitaciones</span>
                    </a>

                    <!-- 5. Huéspedes (Desplegable) -->
                    <div>
                        <button onclick="toggleDropdown('huespedes-menu', 'huespedes-icon')" class="w-full flex items-center justify-between px-4 py-2.5 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors font-medium">
                            <div class="flex items-center gap-3">
                                <i class="fa-solid fa-users w-5 text-center text-gray-500"></i>
                                <span>Huéspedes</span>
                            </div>
                            <i id="huespedes-icon" class="fa-solid fa-chevron-down text-xs text-gray-400 transition-transform duration-200"></i>
                        </button>
                        <div id="huespedes-menu" class="hidden pl-11 pr-2 py-1 space-y-1">
                            <a href="lista_huespedes.php" class="block py-2 px-3 rounded-md text-sm text-gray-600 hover:text-teal-700 hover:bg-gray-50 font-medium transition-colors">Lista de Huéspedes</a>
                            <a href="historial_huespedes.php" class="block py-2 px-3 rounded-md text-sm text-gray-600 hover:text-teal-700 hover:bg-gray-50 font-medium transition-colors">Historial de Huéspedes</a>
                        </div>
                    </div>

                    <!-- 6. Empleados -->
                    <a href="empleados.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg <?php echo ($pagina_actual == 'empleados.php') ? 'bg-teal-100/70 text-teal-900 font-semibold' : 'text-gray-700 hover:bg-gray-100 font-medium'; ?> transition-colors">
                        <i class="fa-solid fa-user-tie w-5 text-center <?php echo ($pagina_actual == 'empleados.php') ? 'text-teal-700' : 'text-gray-500'; ?>"></i>
                        <span>Empleados</span>
                    </a>

                    <!-- 7. Incidencias -->
                    <a href="incidencias.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg <?php echo ($pagina_actual == 'incidencias.php') ? 'bg-teal-100/70 text-teal-900 font-semibold' : 'text-gray-700 hover:bg-gray-100 font-medium'; ?> transition-colors">
                        <i class="fa-solid fa-triangle-exclamation w-5 text-center <?php echo ($pagina_actual == 'incidencias.php') ? 'text-teal-700' : 'text-gray-500'; ?>"></i>
                        <span>Incidencias</span>
                    </a>

                    <!-- 8. Servicios -->
                    <a href="servicios.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg <?php echo ($pagina_actual == 'servicios.php') ? 'bg-teal-100/70 text-teal-900 font-semibold' : 'text-gray-700 hover:bg-gray-100 font-medium'; ?> transition-colors">
                        <i class="fa-solid fa-concierge-bell w-5 text-center <?php echo ($pagina_actual == 'servicios.php') ? 'text-teal-700' : 'text-gray-500'; ?>"></i>
                        <span>Servicios</span>
                    </a>

                    <!-- 9. Turno Camarista -->
                    <?php if ($es_camarista): ?>
                    <a href="camaristas.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg <?php echo ($pagina_actual == 'camaristas.php') ? 'bg-teal-100/70 text-teal-900 font-semibold' : 'text-gray-700 hover:bg-gray-100 font-medium'; ?> transition-colors">
                        <i class="fa-solid fa-broom w-5 text-center <?php echo ($pagina_actual == 'camaristas.php') ? 'text-teal-700' : 'text-gray-500'; ?>"></i>
                        <span>Turno Camarista</span>
                    </a>
                    <?php endif; ?>

                    <!-- 10. Corte de Caja -->
                    <a href="corte_caja.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg <?php echo ($pagina_actual == 'corte_caja.php') ? 'bg-teal-100/70 text-teal-900 font-semibold' : 'text-gray-700 hover:bg-gray-100 font-medium'; ?> transition-colors">
                        <i class="fa-solid fa-cash-register w-5 text-center <?php echo ($pagina_actual == 'corte_caja.php') ? 'text-teal-700' : 'text-gray-500'; ?>"></i>
                        <span>Corte de Caja</span>
                    </a>

                    <!-- 11. Configuración -->
                    <a href="configuracion.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg <?php echo ($pagina_actual == 'configuracion.php') ? 'bg-teal-100/70 text-teal-900 font-semibold' : 'text-gray-700 hover:bg-gray-100 font-medium'; ?> transition-colors">
                        <i class="fa-solid fa-gear w-5 text-center <?php echo ($pagina_actual == 'configuracion.php') ? 'text-teal-700' : 'text-gray-500'; ?>"></i>
                        <span>Configuración</span>
                    </a>

                    <!-- 12. Reportes -->
                    <a href="reportes.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg <?php echo ($pagina_actual == 'reportes.php') ? 'bg-teal-100/70 text-teal-900 font-semibold' : 'text-gray-700 hover:bg-gray-100 font-medium'; ?> transition-colors">
                        <i class="fa-solid fa-chart-line w-5 text-center <?php echo ($pagina_actual == 'reportes.php') ? 'text-teal-700' : 'text-gray-500'; ?>"></i>
                        <span>Reportes</span>
                    </a>
                </nav>
            </div>

            <!-- Cerrar Sesión -->
            <div class="p-4 border-t border-gray-100">
                <a href="logout.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-red-600 hover:bg-red-50 transition-colors font-medium">
                    <i class="fa-solid fa-right-from-bracket w-5 text-center"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </div>
        </aside>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="flex-1 flex flex-col overflow-y-auto">
            <!-- Barra superior con Título Dinámico -->
            <header class="bg-white border-b border-gray-200 flex flex-col md:flex-row items-start md:items-center justify-between px-8 py-3.5 shadow-sm">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-gray-950">
                        Panel de Control / <span class="text-teal-800 font-extrabold"><?php echo $subtitulo_dinamico; ?></span>
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">Resumen general de operaciones, indicadores y acceso rápido al sistema.</p>
                </div>
                
                <div class="flex flex-col items-end w-full md:w-auto mt-2 md:mt-0">
                    <div class="flex items-center gap-2.5">
                        <div class="text-right">
                            <div class="text-sm text-gray-800 font-semibold leading-tight">
                                <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?>
                            </div>
                            <div class="text-xs text-teal-700 font-medium">
                                <?php echo htmlspecialchars($_SESSION['rol']); ?>
                            </div>
                        </div>
                        
                        <?php 
                            $en_turno = false;
                            if (!$es_camarista) {
                                $stmt_check_turno = $pdo->prepare("SELECT id_turno FROM turnos WHERE id_usuario = :id_usuario AND estatus = 'abierto' LIMIT 1");
                                $stmt_check_turno->execute(['id_usuario' => $_SESSION['id_usuario']]);
                                $en_turno = $stmt_check_turno->fetch();
                            } else {
                                $stmt_camarista_estatus = $pdo->prepare("SELECT estatus FROM camaristas WHERE id_camarista = :id_camarista LIMIT 1");
                                $stmt_camarista_estatus->execute(['id_camarista' => $_SESSION['id_camarista']]);
                                $camarista_db = $stmt_camarista_estatus->fetch();
                                
                                if ($camarista_db) {
                                    $estatus_actual = strtolower(trim($camarista_db['estatus']));
                                    $en_turno = ($estatus_actual === 'activo' || $estatus_actual === 'descanso');
                                }
                            }

                            $ruta_imagen = isset($_SESSION['foto']) ? trim($_SESSION['foto']) : '';
                            $icono_default = "icons/usuario.png";
                        ?>

                        <div class="relative">
                            <button onclick="toggleDropdown('profile-dropdown', 'profile-chevron')" class="flex items-center gap-2.5 focus:outline-none py-1 px-1 rounded-lg hover:bg-gray-50 transition-colors">
                                <div class="relative inline-block -mt-1.5">
                                    <?php if (!empty($ruta_imagen) && file_exists($ruta_imagen)): ?>
                                        <img src="<?php echo htmlspecialchars($ruta_imagen); ?>" alt="Foto de Perfil" class="w-10 h-10 object-cover rounded-full border border-gray-300">
                                    <?php else: ?>
                                        <img src="<?php echo htmlspecialchars($icono_default); ?>" alt="Icono Usuario" class="w-9 h-9 object-contain">
                                    <?php endif; ?>

                                    <?php if ($en_turno): ?>
                                        <span class="absolute top-0 right-0 w-3 h-3 bg-green-500 border-2 border-white rounded-full shadow-sm"></span>
                                    <?php else: ?>
                                        <span class="absolute top-0 right-0 w-3 h-3 bg-gray-400 border-2 border-white rounded-full shadow-sm"></span>
                                    <?php endif; ?>
                                </div>
                                <i id="profile-chevron" class="fa-solid fa-chevron-down text-xs text-gray-500 transition-transform duration-200"></i>
                            </button>

                            <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-lg shadow-lg py-1 z-50">
                                <a href="perfil.php" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                    <i class="fa-solid fa-user text-gray-400 w-4"></i>
                                    <span>Perfil</span>
                                </a>
                                <?php if ($es_camarista): ?>
                                    <a href="camaristas.php" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                        <i class="fa-solid fa-broom text-gray-400 w-4"></i>
                                        <span>Gestionar mi Estatus</span>
                                    </a>
                                <?php else: ?>
                                    <a href="turnos.php" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                        <i class="fa-solid fa-clock text-gray-400 w-4"></i>
                                        <span>Iniciar Turno</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="text-xs text-gray-500 font-medium mt-2.5 pr-1">
                        <?php echo $fecha_formateada; ?>
                    </div>
                </div>
            </header>

<!-- SCRIPT NECESARIO PARA ABRIR LOS MENÚS DESPLEGABLES -->
<script>
    function toggleDropdown(menuId, iconId) {
        const menu = document.getElementById(menuId);
        const icon = document.getElementById(iconId);
        
        if (menu) {
            menu.classList.toggle('hidden');
        }
        if (icon) {
            icon.classList.toggle('rotate-180');
        }
    }
</script>