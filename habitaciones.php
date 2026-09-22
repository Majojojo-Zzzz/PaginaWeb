<?php
// 1. Iniciar sesión y validar autenticación
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

// Obtenemos el rol del usuario actual
$rol_usuario = isset($_SESSION['rol']) ? strtolower(trim($_SESSION['rol'])) : '';

// Definimos si el usuario actual tiene permisos de Administrador o Gobernante
$es_admin_o_gobernante = in_array($rol_usuario, ['administrador', 'gobernante', 'admin']);

// Incluimos tu conexión global
require_once 'config/conexion.php';

// Asegurar que PDO muestre excepciones
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --- PROCESAR PETICIONES AJAX / POST (Solo editar y eliminar locales o generales que no sean agregar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');
    
    // Validar acciones protegidas
    if (in_array($_POST['accion'], ['editar', 'eliminar']) && !$es_admin_o_gobernante) {
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para realizar esta acción.']);
        exit;
    }

    if ($_POST['accion'] === 'editar') {
        try {
            $id = $_POST['id_habitacion'];
            $numero = trim($_POST['numero_habitacion']);
            $tipo = $_POST['tipo_habitacion'];
            $precio = $_POST['precio'];
            $cantidad = $_POST['cantidad_personas'];

            // VALIDACIÓN: Verificar si el número de habitación ya existe en OTRA habitación
            $stmt_verificar = $pdo->prepare("SELECT id_habitacion FROM habitaciones WHERE numero_habitacion = ? AND id_habitacion != ?");
            $stmt_verificar->execute([$numero, $id]);
            if ($stmt_verificar->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'El número de habitación ' . $numero . ' ya existe. Por favor, elige otro.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE habitaciones SET numero_habitacion = ?, tipo_habitacion = ?, precio = ?, cantidad_personas = ? WHERE id_habitacion = ?");
            $stmt->execute([$numero, $tipo, $precio, $cantidad, $id]);

            echo json_encode(['success' => true, 'message' => 'La habitación se guardó con éxito']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['accion'] === 'eliminar') {
        try {
            $id = $_POST['id_habitacion'];

            $stmt = $pdo->prepare("DELETE FROM habitaciones WHERE id_habitacion = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'La habitación se eliminó correctamente']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'No se puede eliminar la habitación porque tiene registros relacionados.']);
        }
        exit;
    }
}

// --- FUNCIÓN HELPER PARA FORMATO DE PESOS ---
function formatoPesos($monto) {
    if ($monto === null || $monto === '') return '$0.00';
    return '$' . number_format((float)$monto, 2, '.', ',');
}

// 2. Obtener habitaciones
$stmt_hab = $pdo->query("
    SELECT h.*, 
           hu.nombre AS nombre_huesped, 
           hu.apellido_p AS apellido_p_huesped, 
           hu.apellido_m AS apellido_m_huesped,
           e.fecha_entrada,
           e.fecha_salida,
           e.estatus_estancia,
           DATEDIFF(e.fecha_salida, NOW()) AS noches_restantes
    FROM habitaciones h 
    LEFT JOIN huesped hu ON h.id_huesped = hu.id_huesped 
    LEFT JOIN estancias e ON h.id_habitacion = e.id_habitacion AND e.estatus_estancia != 'Finalizada'
    ORDER BY h.numero_habitacion ASC
");
$habitaciones = $stmt_hab->fetchAll();

include 'includes/header.php';
?>

<!-- Estilos optimizados -->
<style>
    /* Desactivar selección de texto exclusivamente en el contenido principal, liberando header y menú lateral */
    body > div > main, 
    body > div > main * {
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }

    /* Permitir selección y clics libres en inputs, formularios, header, aside y nav */
    input, textarea, select, header, aside, nav {
        -webkit-user-select: text;
        -moz-user-select: text;
        -ms-user-select: text;
        user-select: text;
    }

    .ventana-modal {
        will-change: transform, opacity, scale;
    }
    
    @keyframes modalPopIn {
        0% {
            opacity: 0;
            transform: translate3d(var(--pos-x), var(--pos-y), 0) scale(0.85);
        }
        100% {
            opacity: 1;
            transform: translate3d(var(--pos-x), var(--pos-y), 0) scale(1);
        }
    }
    .animate-modal-open {
        animation: modalPopIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
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

            <!-- CUERPO ESPECÍFICO DE LA PÁGINA DE HABITACIONES -->
            <div class="px-4 md:px-8 py-3 md:py-4 space-y-4 max-w-7xl mx-auto w-full text-gray-700">

                <!-- ENCABEZADO CON ESTILOS EXACTOS -->
                <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-gray-200">
                    <div class="flex items-center gap-2">
                        <?php if ($es_admin_o_gobernante): ?>
                            <button onclick="abrirModalAgregar()" class="px-3 py-1.5 bg-white text-gray-700 border border-gray-300 text-xs font-semibold rounded-xl hover:bg-gray-50 transition-colors shadow-sm flex items-center gap-2">
                                <img src="icons/agregar.png" alt="Agregar" class="w-3.5 h-3.5 object-contain">
                                <span>Agregar habitación</span>
                            </button>
                        <?php endif; ?>
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

                <!-- CONTENEDORES DE FILTROS LADO A LADO -->
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                    
                    <!-- Filtro por estatus -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-2">
                            <img src="icons/filtrar.png" alt="Filtrar" class="w-4 h-4 object-contain">
                            <h3 class="text-xs md:text-sm font-semibold text-gray-800">Filtrar habitaciones por estatus</h3>
                        </div>
                        <div class="flex flex-wrap items-center gap-1">
                            <button onclick="filtrarEstatus('todos')" id="filtro-estatus-todos" class="px-3 py-1 rounded-lg text-xs font-semibold bg-gray-900 text-white shadow-sm transition-all">
                                Todos
                            </button>
                            <button onclick="filtrarEstatus('disponible')" id="filtro-estatus-disponible" class="px-3 py-1 rounded-lg text-xs font-semibold bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 transition-all">
                                Disponible
                            </button>
                            <button onclick="filtrarEstatus('ocupada')" id="filtro-estatus-ocupada" class="px-3 py-1 rounded-lg text-xs font-semibold bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 transition-all">
                                Ocupado
                            </button>
                            <button onclick="filtrarEstatus('mantenimiento')" id="filtro-estatus-mantenimiento" class="px-3 py-1 rounded-lg text-xs font-semibold bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 transition-all">
                                En mantenimiento
                            </button>
                            <button onclick="filtrarEstatus('limpieza')" id="filtro-estatus-limpieza" class="px-3 py-1 rounded-lg text-xs font-semibold bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 transition-all">
                                Limpieza
                            </button>
                        </div>
                    </div>

                    <!-- Filtro por tipo de habitación -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-2">
                            <img src="icons/filtrar.png" alt="Filtrar" class="w-4 h-4 object-contain">
                            <h3 class="text-xs md:text-sm font-semibold text-gray-800">Filtrar habitaciones por tipo</h3>
                        </div>
                        <div class="flex flex-wrap items-center gap-1">
                            <button onclick="filtrarTipo('todos')" id="filtro-tipo-todos" class="px-3 py-1 rounded-lg text-xs font-semibold bg-gray-900 text-white shadow-sm transition-all">
                                Todos
                            </button>
                            <button onclick="filtrarTipo('individual')" id="filtro-tipo-individual" class="px-3 py-1 rounded-lg text-xs font-semibold bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 transition-all">
                                Individual
                            </button>
                            <button onclick="filtrarTipo('doble')" id="filtro-tipo-doble" class="px-3 py-1 rounded-lg text-xs font-semibold bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 transition-all">
                                Doble
                            </button>
                            <button onclick="filtrarTipo('triple')" id="filtro-tipo-triple" class="px-3 py-1 rounded-lg text-xs font-semibold bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 transition-all">
                                Triple
                            </button>
                            <button onclick="filtrarTipo('familiar')" id="filtro-tipo-familiar" class="px-3 py-1 rounded-lg text-xs font-semibold bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 transition-all">
                                Familiar
                            </button>
                            <button onclick="filtrarTipo('estandar')" id="filtro-tipo-estandar" class="px-3 py-1 rounded-lg text-xs font-semibold bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 transition-all">
                                Estandar
                            </button>
                            <button onclick="filtrarTipo('suite')" id="filtro-tipo-suite" class="px-3 py-1 rounded-lg text-xs font-semibold bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 transition-all">
                                Suite
                            </button>
                        </div>
                    </div>

                </div>

                <!-- CONTENEDOR DE CUADRITOS -->
                <div class="flex flex-wrap gap-3" id="contenedorHabitaciones">
                    <?php if (count($habitaciones) > 0): ?>
                        <?php 
                        $contador = 0;
                        foreach ($habitaciones as $hab): 
                            $contador++;
                            $es_oculta = ($contador > 30) ? 'hidden habitacion-oculta' : '';

                            $estatus = strtolower(trim($hab['estatus']));
                            $tipo_raw = strtolower(trim($hab['tipo_habitacion']));
                            
                            switch ($estatus) {
                                case 'disponible':
                                    $card_bg = 'bg-white';
                                    $card_opacity = '';
                                    $badge_bg = 'bg-green-50 text-green-700 border-green-200';
                                    $card_border = 'border-green-200 hover:border-green-300';
                                    break;
                                case 'ocupada':
                                    $card_bg = 'bg-gray-200/80';
                                    $card_opacity = 'opacity-70';
                                    $badge_bg = 'bg-red-50 text-red-700 border-red-200';
                                    $card_border = 'border-gray-300 hover:border-gray-400';
                                    break;
                                case 'limpieza':
                                    $card_bg = 'bg-gray-200/80';
                                    $card_opacity = 'opacity-70';
                                    $badge_bg = 'bg-blue-50 text-blue-700 border-blue-200';
                                    $card_border = 'border-gray-300 hover:border-gray-400';
                                    break;
                                case 'mantenimiento':
                                    $card_bg = 'bg-gray-200/80';
                                    $card_opacity = 'opacity-70';
                                    $badge_bg = 'bg-orange-50 text-orange-700 border-orange-200';
                                    $card_border = 'border-gray-300 hover:border-gray-400';
                                    break;
                                default:
                                    $card_bg = 'bg-white';
                                    $card_opacity = '';
                                    $badge_bg = 'bg-gray-50 text-gray-700 border-gray-200';
                                    $card_border = 'border-gray-200 hover:border-gray-300';
                                    break;
                            }
                            
                            $nombre_completo_huesped = '';
                            if (!empty($hab['id_huesped']) && !empty($hab['nombre_huesped'])) {
                                $nombre_completo_huesped = trim($hab['nombre_huesped'] . ' ' . $hab['apellido_p_huesped'] . ' ' . $hab['apellido_m_huesped']);
                            } else {
                                $nombre_completo_huesped = 'Ninguno (Disponible)';
                            }

                            $texto_noches = 'N/A';
                            $clase_noches = 'font-bold text-gray-900'; 
                            $incluir_alerta = false;

                            if (isset($hab['noches_restantes']) && $hab['noches_restantes'] !== null) {
                                $noches = intval($hab['noches_restantes']);
                                if ($noches < 0) {
                                    $texto_noches = abs($noches) . " día(s) vencida";
                                    $clase_noches = 'font-bold text-red-600';
                                } elseif ($noches === 0) {
                                    $texto_noches = "Sale hoy";
                                    $clase_noches = 'font-bold text-red-600';
                                    $incluir_alerta = true;
                                } else {
                                    $texto_noches = $noches . " noche(s)";
                                }
                            }

                            if ($incluir_alerta) {
                                $html_noches = '<span class="' . $clase_noches . ' inline-flex items-center gap-1"><img src="icons/alerta.png" alt="Alerta" class="w-3.5 h-3.5 object-contain"> ' . $texto_noches . '</span>';
                            } else {
                                $html_noches = '<span class="' . $clase_noches . '">' . $texto_noches . '</span>';
                            }

                            $precio_texto = formatoPesos($hab['precio']) . ' por Noche';
                            $numero_habitacion_raw = htmlspecialchars($hab['numero_habitacion']);
                            $tipo_formateado = ucwords(strtolower(trim($hab['tipo_habitacion'])));
                            $capacidad_formateada = htmlspecialchars($hab['cantidad_personas']) . ' personas';
                        ?>
                            <!-- Tarjeta de habitación -->
                            <div data-estatus="<?php echo $estatus; ?>" data-tipo="<?php echo $tipo_raw; ?>" class="<?php echo $card_bg; ?> border <?php echo $card_border; ?> rounded-xl p-3.5 shadow-sm flex flex-col justify-between transition-all <?php echo $card_opacity; ?> w-[calc(50%-0.375rem)] md:w-[calc(33.333%-0.5rem)] xl:w-[calc(16.666%-0.625rem)] <?php echo $es_oculta; ?>">
                                <div>
                                    <div class="flex items-center justify-between mb-2.5">
                                        <div class="flex items-center gap-1.5">
                                            <img src="icons/cama.png" alt="Cama" class="w-6 h-6 object-contain">
                                            <span class="text-base font-bold text-gray-900"><?php echo $numero_habitacion_raw; ?></span>
                                        </div>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium border <?php echo $badge_bg; ?>">
                                            <?php echo ucfirst($hab['estatus']); ?>
                                        </span>
                                    </div>

                                    <div class="space-y-1 text-xs text-gray-600 mb-3">
                                        <p><?php echo $tipo_formateado; ?></p>
                                        <p><?php echo $capacidad_formateada; ?></p>
                                        <p><span class="font-semibold text-gray-950"><?php echo $precio_texto; ?></span></p>
                                    </div>
                                </div>

                                <div class="pt-2.5 border-t border-gray-300/60 flex items-center justify-between">
                                    <span class="text-[10px] text-gray-500">ID: <?php echo $hab['id_habitacion']; ?></span>
                                    <div class="flex items-center gap-2">
                                        <?php if ($es_admin_o_gobernante): ?>
                                            <!-- Botón Eliminar -->
                                            <button onclick='abrirModalEliminar({
                                                id: "<?php echo $hab['id_habitacion']; ?>",
                                                numero: "<?php echo $numero_habitacion_raw; ?>"
                                            })' title="Eliminar habitacion" class="transform hover:scale-125 transition-transform duration-200 ease-in-out">
                                                <img src="icons/eliminar.png" alt="Eliminar" class="w-4 h-4 object-contain">
                                            </button>
                                            <!-- Botón Editar -->
                                            <button onclick='abrirModalEditar({
                                                id: "<?php echo $hab['id_habitacion']; ?>",
                                                numero: "<?php echo $numero_habitacion_raw; ?>",
                                                tipo: "<?php echo strtolower(trim($hab['tipo_habitacion'])); ?>",
                                                precio: "<?php echo $hab['precio']; ?>",
                                                cantidad: "<?php echo $hab['cantidad_personas']; ?>"
                                            })' title="Editar habitacion" class="transform hover:scale-125 transition-transform duration-200 ease-in-out">
                                                <img src="icons/editar.png" alt="Editar" class="w-4 h-4 object-contain">
                                            </button>
                                        <?php endif; ?>

                                        <!-- Botón Ver detalles -->
                                        <button onclick='abrirModalDetalles({
                                            id: "<?php echo $hab['id_habitacion']; ?>",
                                            numero: "<?php echo $numero_habitacion_raw; ?>",
                                            estatus: "<?php echo ucfirst(htmlspecialchars($hab['estatus'])); ?>",
                                            badgeClass: "<?php echo $badge_bg; ?>",
                                            tipo: "<?php echo $tipo_formateado; ?>",
                                            capacidad: "<?php echo $capacidad_formateada; ?>",
                                            precio: "<?php echo $precio_texto; ?>",
                                            huesped: "<?php echo htmlspecialchars($nombre_completo_huesped, ENT_QUOTES); ?>",
                                            entrada: "<?php echo !empty($hab['fecha_entrada']) ? htmlspecialchars($hab['fecha_entrada']) : "No asignada"; ?>",
                                            salida: "<?php echo !empty($hab['fecha_salida']) ? htmlspecialchars($hab['fecha_salida']) : "No asignada"; ?>",
                                            nochesHtml: <?php echo json_encode($html_noches); ?>
                                        })' class="text-xs font-medium text-blue-600 hover:text-blue-800 hover:underline">
                                            Ver detalles
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="w-full bg-white border border-gray-200 rounded-xl p-8 text-center text-gray-400">
                            No hay habitaciones registradas en el sistema.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Botón Ver más -->
                <?php if (count($habitaciones) > 30): ?>
                    <div class="flex justify-center mt-4" id="contenedorBtnVerMas">
                        <button id="btnVerMas" onclick="mostrarMasHabitaciones()" class="px-6 py-2 bg-black text-white text-xs font-semibold rounded-xl hover:bg-gray-800 transition-colors shadow-sm">
                            Ver más...
                        </button>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <!-- CONTENEDOR GLOBAL DE MODALES -->
    <div id="contenedorModales" class="fixed inset-0 z-50 pointer-events-none overflow-hidden"></div>

    <!-- Script general -->
    <script>
        let zIndexCounter = 100;
        let filtroEstatusActual = 'todos';
        let filtroTipoActual = 'todos';
        let mostrarTodasCompletas = false;

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

        function filtrarEstatus(estatus) {
            filtroEstatusActual = estatus;
            const botones = ['todos', 'disponible', 'ocupada', 'mantenimiento', 'limpieza'];
            botones.forEach(b => {
                const btn = document.getElementById('filtro-estatus-' + b);
                if (btn) {
                    if (b === estatus) {
                        btn.className = 'px-3 py-1 rounded-lg text-xs font-semibold bg-gray-900 text-white shadow-sm transition-all';
                    } else {
                        btn.className = 'px-3 py-1 rounded-lg text-xs font-semibold bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 transition-all';
                    }
                }
            });
            aplicarFiltrosYPaginacion();
        }

        function filtrarTipo(tipo) {
            filtroTipoActual = tipo;
            const botones = ['todos', 'individual', 'doble', 'triple', 'familiar', 'estandar', 'suite'];
            botones.forEach(b => {
                const btn = document.getElementById('filtro-tipo-' + b);
                if (btn) {
                    if (b === tipo) {
                        btn.className = 'px-3 py-1 rounded-lg text-xs font-semibold bg-gray-900 text-white shadow-sm transition-all';
                    } else {
                        btn.className = 'px-3 py-1 rounded-lg text-xs font-semibold bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 transition-all';
                    }
                }
            });
            aplicarFiltrosYPaginacion();
        }

        function mostrarMasHabitaciones() {
            mostrarTodasCompletas = true;
            const btn = document.getElementById('contenedorBtnVerMas');
            if (btn) btn.style.display = 'none';
            aplicarFiltrosYPaginacion();
        }

        function aplicarFiltrosYPaginacion() {
            const tarjetas = document.querySelectorAll('#contenedorHabitaciones > div[data-estatus]');
            let contadorVisibles = 0;

            tarjetas.forEach(tarjeta => {
                const estatusTarjeta = tarjeta.getAttribute('data-estatus');
                const tipoTarjeta = tarjeta.getAttribute('data-tipo');

                const coincideEstatus = (filtroEstatusActual === 'todos' || estatusTarjeta === filtroEstatusActual);
                const coincideTipo = (filtroTipoActual === 'todos' || tipoTarjeta === filtroTipoActual);

                if (coincideEstatus && coincideTipo) {
                    contadorVisibles++;
                    if (mostrarTodasCompletas || contadorVisibles <= 30) {
                        tarjeta.classList.remove('hidden');
                    } else {
                        tarjeta.classList.add('hidden');
                    }
                } else {
                    tarjeta.classList.add('hidden');
                }
            });

            const contenedorBtn = document.getElementById('contenedorBtnVerMas');
            if (contenedorBtn) {
                if (!mostrarTodasCompletas && filtroEstatusActual === 'todos' && filtroTipoActual === 'todos' && tarjetas.length > 30) {
                    contenedorBtn.style.display = 'flex';
                } else {
                    contenedorBtn.style.display = 'none';
                }
            }
        }

        // --- VENTANA FLOTANTE AGREGAR HABITACIÓN ---
        function abrirModalAgregar() {
            const contenedor = document.getElementById('contenedorModales');
            const idVentana = 'modal-agregar-habitacion';

            if (document.getElementById(idVentana)) {
                document.getElementById(idVentana).remove();
            }

            const ventanaHtml = document.createElement('div');
            ventanaHtml.id = idVentana;
            ventanaHtml.className = 'ventana-modal absolute bg-white rounded-2xl shadow-2xl border border-gray-200 max-w-sm w-full overflow-hidden pointer-events-auto animate-modal-open';
            
            posicionarVentanaFlotante(ventanaHtml, contenedor, 384, 380);

            ventanaHtml.innerHTML = `
                <div class="modal-header bg-gray-50 px-6 py-3 border-b border-gray-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <img src="icons/cama.png" alt="Agregar" class="w-4 h-4 object-contain">
                        <h3 class="text-sm font-bold text-gray-900">Agregar Nueva Habitación</h3>
                    </div>
                    <button type="button" class="btn-cerrar text-gray-400 hover:text-red-600 text-3xl font-bold leading-none px-1 transition-colors">&times;</button>
                </div>
                
                <form id="formAgregarHabitacion" class="p-6 space-y-4 text-xs text-gray-600">
                    <input type="hidden" name="accion" value="agregar">
                    
                    <div class="space-y-1">
                        <label class="font-medium text-gray-700">Número de Habitación:</label>
                        <input type="text" id="inputNumeroHabitacion" name="numero_habitacion" required placeholder="Cargando..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black">
                    </div>

                    <div class="space-y-1">
                        <label class="font-medium text-gray-700">Tipo de Habitación:</label>
                        <select name="tipo_habitacion" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black bg-white">
                            <option value="individual">Individual</option>
                            <option value="doble">Doble</option>
                            <option value="triple">Triple</option>
                            <option value="familiar">Familiar</option>
                            <option value="estandar">Estandar</option>
                            <option value="suite">Suite</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="font-medium text-gray-700">Precio por Noche ($):</label>
                        <input type="number" step="0.01" name="precio" required placeholder="0.00" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black">
                    </div>

                    <div class="space-y-1">
                        <label class="font-medium text-gray-700">Cantidad de Personas:</label>
                        <input type="number" name="cantidad_personas" required placeholder="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black">
                    </div>

                    <div class="pt-2 flex justify-end gap-2">
                        <button type="button" class="btn-cerrar px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-300 transition-colors">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 transition-colors">Guardar Habitación</button>
                    </div>
                </form>
            `;

            contenedor.appendChild(ventanaHtml);
            configurarVentanaFlotante(ventanaHtml);

            // PETICIÓN AJAX A agregar_habitacion.php PARA OBTENER EL SIGUIENTE NÚMERO
            const formDataSugerido = new FormData();
            formDataSugerido.append('accion', 'siguiente_numero');
            fetch('agregar_habitacion.php', {
                method: 'POST',
                body: formDataSugerido
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const inputNum = ventanaHtml.querySelector('#inputNumeroHabitacion');
                    if (inputNum) {
                        inputNum.value = data.siguiente;
                    }
                }
            })
            .catch(err => console.error(err));

            // PETICIÓN AJAX A agregar_habitacion.php PARA GUARDAR LA HABITACIÓN
            ventanaHtml.querySelector('#formAgregarHabitacion').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);

                fetch('agregar_habitacion.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        ventanaHtml.remove();
                        mostrarNotificacion(result.message, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        mostrarNotificacion(result.message, 'error');
                    }
                })
                .catch(err => console.error(err));
            });
        }

        // --- VENTANA FLOTANTE EDITAR ---
        function abrirModalEditar(data) {
            const contenedor = document.getElementById('contenedorModales');
            const idVentana = 'modal-editar-' + data.id;

            if (document.getElementById(idVentana)) {
                document.getElementById(idVentana).remove();
            }

            const ventanaHtml = document.createElement('div');
            ventanaHtml.id = idVentana;
            ventanaHtml.className = 'ventana-modal absolute bg-white rounded-2xl shadow-2xl border border-gray-200 max-w-sm w-full overflow-hidden pointer-events-auto animate-modal-open';
            
            posicionarVentanaFlotante(ventanaHtml, contenedor, 384, 380);

            const tiposDisponibles = ['individual', 'doble', 'triple', 'familiar', 'estandar', 'suite'];
            let opcionesTipoHtml = '';
            tiposDisponibles.forEach(t => {
                const selected = (data.tipo.toLowerCase() === t) ? 'selected' : '';
                opcionesTipoHtml += `<option value="${t}" ${selected}>${t.charAt(0).toUpperCase() + t.slice(1)}</option>`;
            });

            ventanaHtml.innerHTML = `
                <div class="modal-header bg-gray-50 px-6 py-3 border-b border-gray-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <img src="icons/editar.png" alt="Editar" class="w-4 h-4 object-contain">
                        <h3 class="text-sm font-bold text-gray-900">Editar Habitación ${data.numero}</h3>
                    </div>
                    <button type="button" class="btn-cerrar text-gray-400 hover:text-red-600 text-3xl font-bold leading-none px-1 transition-colors">&times;</button>
                </div>
                
                <form id="formEditarHabitacion" class="p-6 space-y-4 text-xs text-gray-600">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id_habitacion" value="${data.id}">
                    
                    <div class="space-y-1">
                        <label class="font-medium text-gray-700">Número de Habitación:</label>
                        <input type="text" name="numero_habitacion" value="${data.numero}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black">
                    </div>

                    <div class="space-y-1">
                        <label class="font-medium text-gray-700">Tipo de Habitación:</label>
                        <select name="tipo_habitacion" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black bg-white">
                            ${opcionesTipoHtml}
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="font-medium text-gray-700">Precio por Noche ($):</label>
                        <input type="number" step="0.01" name="precio" value="${data.precio}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black">
                    </div>

                    <div class="space-y-1">
                        <label class="font-medium text-gray-700">Cantidad de Personas:</label>
                        <input type="number" name="cantidad_personas" value="${data.cantidad}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-black">
                    </div>

                    <div class="pt-2 flex justify-end gap-2">
                        <button type="button" class="btn-cerrar px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-300 transition-colors">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg text-xs font-medium hover:bg-gray-800 transition-colors">Guardar Cambios</button>
                    </div>
                </form>
            `;

            contenedor.appendChild(ventanaHtml);
            configurarVentanaFlotante(ventanaHtml);

            ventanaHtml.querySelector('#formEditarHabitacion').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        ventanaHtml.remove();
                        mostrarNotificacion(result.message, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        mostrarNotificacion(result.message, 'error');
                    }
                })
                .catch(err => console.error(err));
            });
        }

        // --- VENTANA FLOTANTE ELIMINAR ---
        function abrirModalEliminar(data) {
            const contenedor = document.getElementById('contenedorModales');
            const idVentana = 'modal-eliminar-' + data.id;

            if (document.getElementById(idVentana)) {
                document.getElementById(idVentana).remove();
            }

            const ventanaHtml = document.createElement('div');
            ventanaHtml.id = idVentana;
            ventanaHtml.className = 'ventana-modal absolute bg-white rounded-2xl shadow-2xl border border-gray-200 max-w-sm w-full overflow-hidden pointer-events-auto animate-modal-open';
            
            posicionarVentanaFlotante(ventanaHtml, contenedor, 384, 200);

            ventanaHtml.innerHTML = `
                <div class="modal-header bg-gray-50 px-6 py-3 border-b border-gray-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <img src="icons/eliminar.png" alt="Eliminar" class="w-4 h-4 object-contain">
                        <h3 class="text-sm font-bold text-red-600">Eliminar Habitación</h3>
                    </div>
                    <button type="button" class="btn-cerrar text-gray-400 hover:text-red-600 text-3xl font-bold leading-none px-1 transition-colors">&times;</button>
                </div>
                
                <div class="p-6 space-y-4 text-xs text-gray-600">
                    <p>¿Estás seguro de que deseas eliminar la habitación <strong>${data.numero}</strong>? Esta acción no se puede deshacer.</p>
                    
                    <div class="pt-2 flex justify-end gap-2">
                        <button type="button" class="btn-cerrar px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-300 transition-colors">Cancelar</button>
                        <button type="button" id="btnConfirmarEliminar" class="px-4 py-2 bg-red-600 text-white rounded-lg text-xs font-medium hover:bg-red-700 transition-colors">Sí, eliminar</button>
                    </div>
                </div>
            `;

            contenedor.appendChild(ventanaHtml);
            configurarVentanaFlotante(ventanaHtml);

            ventanaHtml.querySelector('#btnConfirmarEliminar').addEventListener('click', function() {
                const formData = new FormData();
                formData.append('accion', 'eliminar');
                formData.append('id_habitacion', data.id);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        ventanaHtml.remove();
                        mostrarNotificacion(result.message, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        mostrarNotificacion(result.message, 'error');
                    }
                })
                .catch(err => console.error(err));
            });
        }

        // --- VENTANA FLOTANTE VER DETALLES ---
        function abrirModalDetalles(data) {
            const contenedor = document.getElementById('contenedorModales');
            const idVentana = 'modal-hab-' + data.id;

            if (document.getElementById(idVentana)) {
                document.getElementById(idVentana).remove();
            }

            const ventanaHtml = document.createElement('div');
            ventanaHtml.id = idVentana;
            ventanaHtml.className = 'ventana-modal absolute bg-white rounded-2xl shadow-2xl border border-gray-200 max-w-md w-full overflow-hidden pointer-events-auto animate-modal-open';
            
            posicionarVentanaFlotante(ventanaHtml, contenedor, 448, 420);

            ventanaHtml.innerHTML = `
                <div class="modal-header bg-gray-50 px-6 py-3 border-b border-gray-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <img src="icons/cama.png" alt="Cama" class="w-5 h-5 object-contain">
                        <h3 class="text-base font-bold text-gray-900">Habitación ${data.numero}</h3>
                    </div>
                    <button type="button" class="btn-cerrar text-gray-400 hover:text-red-600 text-3xl font-bold leading-none px-1 transition-colors">&times;</button>
                </div>
                
                <div class="p-6 space-y-3 text-xs text-gray-600">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                        <span class="font-medium text-gray-700">Estatus:</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium border ${data.badgeClass}">${data.estatus}</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-100 pb-2">
                        <span class="font-medium text-gray-700">Tipo de habitación:</span>
                        <span class="text-gray-900">${data.tipo}</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-100 pb-2">
                        <span class="font-medium text-gray-700">Capacidad:</span>
                        <span class="text-gray-900">${data.capacidad}</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-100 pb-2">
                        <span class="font-medium text-gray-700">Tarifa:</span>
                        <span class="font-semibold text-gray-900">${data.precio}</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-100 pb-2">
                        <span class="font-medium text-gray-700">Huésped asignado:</span>
                        <span class="text-gray-900 font-medium">${data.huesped}</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-100 pb-2">
                        <span class="font-medium text-gray-700">Fecha de entrada:</span>
                        <span class="text-gray-900">${data.entrada}</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-100 pb-2">
                        <span class="font-medium text-gray-700">Fecha de salida:</span>
                        <span class="text-gray-900">${data.salida}</span>
                    </div>
                    <div class="flex justify-between pt-1">
                        <span class="font-medium text-gray-700">Noches restantes:</span>
                        <div>${data.nochesHtml}</div>
                    </div>
                </div>

                <div class="bg-gray-50 px-6 py-3 border-t border-gray-200 flex justify-end">
                    <button type="button" class="btn-cerrar px-4 py-2 bg-gray-800 text-white rounded-lg text-xs font-medium hover:bg-gray-900 transition-colors">
                        Cerrar
                    </button>
                </div>
            `;

            contenedor.appendChild(ventanaHtml);
            configurarVentanaFlotante(ventanaHtml);
        }

        function posicionarVentanaFlotante(ventanaHtml, contenedor, anchoEstimado = 400, altoEstimado = 300) {
            const ventanasAbiertas = contenedor.querySelectorAll('.ventana-modal').length;
            
            const centroBaseX = (window.innerWidth / 2) - (anchoEstimado / 2);
            const centroBaseY = (window.innerHeight / 2) - (altoEstimado / 2);
            
            let startXPos, startYPos;
            if (ventanasAbiertas === 0) {
                startXPos = centroBaseX;
                startYPos = centroBaseY;
            } else {
                const cascadeOffset = (ventanasAbiertas % 12) * 35; 
                startXPos = centroBaseX + cascadeOffset;
                startYPos = centroBaseY + cascadeOffset;
            }
            
            zIndexCounter++;
            
            ventanaHtml.style.setProperty('--pos-x', startXPos + 'px');
            ventanaHtml.style.setProperty('--pos-y', startYPos + 'px');
            ventanaHtml.style.zIndex = zIndexCounter;
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
            
            let posX = parseFloat(ventana.style.getPropertyValue('--pos-x')) || 0;
            let posY = parseFloat(ventana.style.getPropertyValue('--pos-y')) || 0;

            ventana.addEventListener('mousedown', (e) => {
                if (e.target.tagName === 'BUTTON' || e.target.closest('button') || e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') {
                    return;
                }

                e.preventDefault();
                isDragging = true;
                
                if (ventana.classList.contains('animate-modal-open')) {
                    ventana.classList.remove('animate-modal-open');
                    ventana.style.transform = `translate3d(${posX}px, ${posY}px, 0px)`;
                }

                startX = e.clientX - posX;
                startY = e.clientY - posY;

                function onMouseMove(moveEvent) {
                    if (!isDragging) return;
                    posX = moveEvent.clientX - startX;
                    posY = moveEvent.clientY - startY;
                    ventana.style.transform = `translate3d(${posX}px, ${posY}px, 0px)`;
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
    </script>
</body>
</html>