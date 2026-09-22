<?php
// 1. Iniciar sesión y validar autenticación
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

require_once 'config/conexion.php';

// Helper para formato en pesos mexicanos
function formatoPesos($monto, $con_decimales = true) {
    if ($monto === null || $monto === '') return '$0.00';
    $decimales = $con_decimales ? 2 : 0;
    return '$' . number_format((float)$monto, $decimales, '.', ',');
}

// RECUPERAR MENSAJES DE LA SESIÓN
$mensaje = $_SESSION['mensaje'] ?? "";
$tipo_mensaje = $_SESSION['tipo_mensaje'] ?? "";

unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);

$id_usuario_actual = $_SESSION['id_usuario'];

// 2. Validar Turno Abierto
$stmt_turno = $pdo->prepare("SELECT id_turno FROM turnos WHERE id_usuario = :id_usuario AND estatus = 'abierto' LIMIT 1");
$stmt_turno->execute(['id_usuario' => $id_usuario_actual]);
$turno_activo = $stmt_turno->fetch();

$id_turno_actual = $turno_activo ? $turno_activo['id_turno'] : null;

// 3. Procesar Registro de Venta (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if (!$id_turno_actual) {
        $_SESSION['mensaje'] = "Error: Debes abrir un turno para realizar o cobrar servicios.";
        $_SESSION['tipo_mensaje'] = "error";
        header("Location: servicios.php");
        exit;
    } else {

        if ($accion === 'registrar_venta_servicio') {
            $id_servicio     = $_POST['id_servicio'] ?? null;
            $nombre_servicio = $_POST['nombre_servicio_hidden'] ?? 'Servicio';
            $id_estancia     = !empty($_POST['id_estancia']) ? $_POST['id_estancia'] : null;
            $precio_unitario = (float)($_POST['precio_unitario'] ?? 0.00);
            $cantidad        = (int)($_POST['cantidad'] ?? 1);
            $estado_pago     = $_POST['estado_pago'] ?? 'Pagado';
            $metodo_pago     = $_POST['metodo_pago'] ?? 'Efectivo';

            if ($cantidad < 1) $cantidad = 1;

            // TOTAL A PAGAR
            $monto_total = $precio_unitario * $cantidad;

            if ($id_servicio && $monto_total >= 0) {
                try {
                    $sql_p = "INSERT INTO pagos_servicios (id_servicio, id_estancia, estado_pago, metodo_pago, monto, fecha_pago, id_usuario, id_turno)
                              VALUES (:id_servicio, :id_estancia, :estado_pago, :metodo_pago, :monto, NOW(), :id_usuario, :id_turno)";
                    $stmt_p = $pdo->prepare($sql_p);
                    $stmt_p->execute([
                        'id_servicio' => $id_servicio,
                        'id_estancia' => $id_estancia,
                        'estado_pago' => $estado_pago,
                        'metodo_pago' => $metodo_pago,
                        'monto'       => $monto_total,
                        'id_usuario'  => $id_usuario_actual,
                        'id_turno'    => $id_turno_actual
                    ]);

                    $_SESSION['mensaje'] = "¡Venta de " . htmlspecialchars($nombre_servicio) . " registrada con éxito (" . formatoPesos($monto_total) . ")!";
                    $_SESSION['tipo_mensaje'] = "exito";
                } catch (Exception $e) {
                    $_SESSION['mensaje'] = "Error al registrar la venta: " . $e->getMessage();
                    $_SESSION['tipo_mensaje'] = "error";
                }
            }
            header("Location: servicios.php");
            exit;
        }
    }
}

// 4. Obtener catálogo de servicios
$stmt_servicios = $pdo->query("SELECT * FROM servicios ORDER BY id_servicio DESC");
$servicios = $stmt_servicios->fetchAll();

// 5. Obtener Estancias Activas (Habitaciones Ocupadas)
$sql_estancias = "SELECT e.id_estancia, h.numero_habitacion, hu.nombre, hu.apellido_p 
                  FROM estancias e
                  INNER JOIN habitaciones h ON e.id_habitacion = h.id_habitacion
                  INNER JOIN huesped hu ON e.id_huesped = hu.id_huesped
                  WHERE e.estatus_estancia = 'Activa'
                  ORDER BY h.numero_habitacion ASC";
$stmt_est = $pdo->query($sql_estancias);
$estancias_activas = $stmt_est->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datasys - Venta y Asignación de Servicios</title>

    <style>
        .btn-filtro-cat {
            background-color: #f0f0f0;
            color: #333;
            border: 1px solid #999;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: bold;
        }
        .btn-filtro-cat.activo {
            background-color: #0275d8;
            color: white;
            border-color: #0275d8;
        }
    </style>

    <script>
        let precioServicioSeleccionado = 0;
        let categoriaSeleccionada = '';

        // Abrir ventana emergente al hacer clic en 'Seleccionar'
        function abrirModalVenta(idServicio, nombreServicio, precioUnitario) {
            document.getElementById('modal_id_servicio').value = idServicio;
            document.getElementById('modal_nombre_servicio_hidden').value = nombreServicio;
            document.getElementById('modal_precio_unitario').value = precioUnitario;
            document.getElementById('modal_titulo').textContent = 'Vender: ' + nombreServicio;
            
            document.getElementById('modal_cantidad').value = 1;
            
            precioServicioSeleccionado = parseFloat(precioUnitario);
            recalcularTotalModal();

            document.getElementById('modalVenta').showModal();
        }

        // Cerrar ventana emergente
        function cerrarModalVenta() {
            document.getElementById('modalVenta').close();
        }

        // Recalcular total en tiempo real dentro del modal
        function recalcularTotalModal() {
            let cantidadInput = document.getElementById('modal_cantidad');
            let cant = parseInt(cantidadInput.value);

            if (isNaN(cant) || cant < 1) {
                cant = 1;
            }

            let total = cant * precioServicioSeleccionado;
            let totalFormateado = '$' + total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');

            document.getElementById('modal_total_display').textContent = totalFormateado;
        }

        // SELECCIONAR CATEGORÍA DESDE LOS BOTONES
        function seleccionarCategoria(categoria, btnElement) {
            categoriaSeleccionada = categoria.toLowerCase();

            let botones = document.querySelectorAll('.btn-filtro-cat');
            botones.forEach(btn => btn.classList.remove('activo'));
            btnElement.classList.add('activo');

            filtrarServicios();
        }

        // FILTRAR TABLA (COMBINA TEXTO DE BÚSQUEDA Y BOTÓN DE CATEGORÍA)
        function filtrarServicios() {
            let input = document.getElementById("buscarServicio");
            let filter = input.value.toLowerCase();
            let table = document.getElementById("tablaCatServicios");
            let trs = table.getElementsByTagName("tbody")[0].getElementsByTagName("tr");

            for (let i = 0; i < trs.length; i++) {
                let row = trs[i];
                let colCategoria = row.getElementsByTagName("td")[0];

                if (!colCategoria) continue;

                let textoFila = row.textContent || row.innerText;
                let textoCategoria = colCategoria.textContent || colCategoria.innerText;

                let coincideTexto = textoFila.toLowerCase().indexOf(filter) > -1;
                let coincideCategoria = (categoriaSeleccionada === '' || categoriaSeleccionada === 'todos') 
                                        ? true 
                                        : textoCategoria.toLowerCase().includes(categoriaSeleccionada);

                if (coincideTexto && coincideCategoria) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            }
        }
    </script>
</head>
<body>

    <h2>Gestión y Venta de Servicios</h2>
    <p><a href="home.php">Volver al Menú Principal</a></p>

    <!-- Alerta de Turno -->
    <?php if (!$id_turno_actual): ?>
        <p style="color: red; font-weight: bold; background-color: #fee; padding: 10px; border: 1px solid red;">
            ⚠️ ATENCIÓN: No tienes un turno abierto. Abre un turno para procesar cobros o registrar atenciones.
        </p>
    <?php endif; ?>

    <!-- Mensajes de Estado -->
    <?php if (!empty($mensaje)): ?>
        <p style="color: <?php echo ($tipo_mensaje === 'exito') ? 'green' : 'red'; ?>; font-weight: bold;">
            <?php echo htmlspecialchars($mensaje); ?>
        </p>
    <?php endif; ?>

    <!-- BOTONES DE NAVEGACIÓN Y ACCIÓN -->
    <p style="display: flex; gap: 10px;">
        <a href="agregar_servicio.php" style="background-color: #0275d8; color: white; padding: 8px 14px; text-decoration: none; font-weight: bold; border: 1px solid #025aa5;">
            ➕ Nuevo Servicio
        </a>
        <a href="historial_ventas.php" style="background-color: #5bc0de; color: black; padding: 8px 14px; text-decoration: none; font-weight: bold; border: 1px solid #46b8da;">
            📋 Ver Historial de Ventas
        </a>
    </p>

    <br>

    <!-- CATÁLOGO DE SERVICIOS -->
    <h3>Catálogo de Servicios</h3>

    <!-- BOTONES DE FILTRO Y BARRA DE BÚSQUEDA -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
        
        <!-- 5 BOTONES DE FILTRO -->
        <div style="display: flex; gap: 4px; flex-wrap: wrap;">
            <button type="button" class="btn-filtro-cat activo" onclick="seleccionarCategoria('todos', this)">Todos</button>
            <button type="button" class="btn-filtro-cat" onclick="seleccionarCategoria('restaurante', this)">Restaurante</button>
            <button type="button" class="btn-filtro-cat" onclick="seleccionarCategoria('bar', this)">Bar</button>
            <button type="button" class="btn-filtro-cat" onclick="seleccionarCategoria('lavanderia', this)">Lavandería</button>
            <button type="button" class="btn-filtro-cat" onclick="seleccionarCategoria('spa', this)">Spa</button>
        </div>

        <!-- BARRA DE BÚSQUEDA -->
        <div>
            <input type="text" 
                   id="buscarServicio" 
                   onkeyup="filtrarServicios()" 
                   placeholder="🔍 Buscar servicio..." 
                   style="padding: 6px 10px; width: 240px; border: 1px solid #ccc;">
        </div>
    </div>

    <table id="tablaCatServicios" border="1" cellpadding="8" cellspacing="0" style="width: 100%;">
        <thead>
            <tr style="background-color: #eee;">
                <th>Categoría</th>
                <th>Servicio</th>
                <th>Precio Unit.</th>
                <th style="width: 130px; text-align: center;">Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($servicios) > 0): ?>
                <?php foreach ($servicios as $s): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($s['tipo_servicio']); ?></strong></td>
                        <td><?php echo htmlspecialchars($s['nombre_servicio']); ?></td>
                        <td><strong><?php echo formatoPesos($s['precio']); ?></strong></td>
                        <td style="text-align: center;">
                            <button type="button" 
                                    onclick="abrirModalVenta(<?php echo $s['id_servicio']; ?>, '<?php echo addslashes(htmlspecialchars($s['nombre_servicio'])); ?>', <?php echo $s['precio']; ?>)"
                                    <?php echo (!$id_turno_actual) ? 'disabled' : ''; ?> 
                                    style="background-color: #0275d8; color: white; border: 1px solid #025aa5; padding: 6px 12px; cursor: pointer; font-weight: bold;">
                                Seleccionar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">No hay servicios registrados en el catálogo.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- VENTANA EMERGENTE (MODAL) PARA DATOS DE LA VENTA -->
    <dialog id="modalVenta" style="border: 1px solid #333; padding: 20px; width: 380px;">
        <form action="servicios.php" method="POST">
            <input type="hidden" name="accion" value="registrar_venta_servicio">
            <input type="hidden" name="id_servicio" id="modal_id_servicio">
            <input type="hidden" name="nombre_servicio_hidden" id="modal_nombre_servicio_hidden">
            <input type="hidden" name="precio_unitario" id="modal_precio_unitario">

            <h3 id="modal_titulo" style="margin-top: 0; color: #333;">Vender Servicio</h3>
            <hr>

            <!-- Habitación -->
            <div style="margin-bottom: 12px;">
                <label for="id_estancia"><strong>Habitación:</strong></label><br>
                <select name="id_estancia" required style="width: 100%; padding: 6px; margin-top: 4px;">
                    <option value="">-- Seleccionar Habitación --</option>
                    <?php foreach ($estancias_activas as $est): ?>
                        <option value="<?php echo $est['id_estancia']; ?>">
                            Hab. <?php echo $est['numero_habitacion']; ?> (<?php echo htmlspecialchars($est['nombre'] . ' ' . $est['apellido_p']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Cantidad -->
            <div style="margin-bottom: 12px;">
                <label for="modal_cantidad"><strong>Cantidad:</strong></label><br>
                <input type="number" 
                       id="modal_cantidad" 
                       name="cantidad" 
                       value="1" 
                       min="1" 
                       required 
                       style="width: 95%; padding: 6px; margin-top: 4px;"
                       oninput="recalcularTotalModal()"
                       onchange="recalcularTotalModal()">
            </div>

            <!-- Total Calculado en Vivo -->
            <div style="background-color: #e8f5e9; border: 1px solid #c8e6c9; padding: 10px; text-align: center; margin-bottom: 12px;">
                <small style="color: #2e7d32; font-weight: bold;">TOTAL A PAGAR</small><br>
                <strong id="modal_total_display" style="color: #2e7d32; font-size: 1.6em;">$0.00</strong>
            </div>

            <!-- Estatus de Pago -->
            <div style="margin-bottom: 12px;">
                <label for="estado_pago"><strong>Estatus de Pago:</strong></label><br>
                <select name="estado_pago" style="width: 100%; padding: 6px; margin-top: 4px;">
                    <option value="Pagado">Pagado</option>
                    <option value="Pendiente">Pendiente (A la cuenta)</option>
                </select>
            </div>

            <!-- Método de Pago -->
            <div style="margin-bottom: 20px;">
                <label for="metodo_pago"><strong>Método de Pago:</strong></label><br>
                <select name="metodo_pago" style="width: 100%; padding: 6px; margin-top: 4px;">
                    <option value="Efectivo">Efectivo</option>
                    <option value="Trasferencia">Transferencia</option>
                </select>
            </div>

            <!-- Botones de Acción -->
            <div style="display: flex; justify-content: space-between;">
                <button type="button" onclick="cerrarModalVenta()" style="background-color: #777; color: white; border: 1px solid #555; padding: 8px 16px; cursor: pointer;">
                    Cancelar
                </button>
                <button type="submit" style="background-color: #5cb85c; color: white; border: 1px solid #4cae4c; padding: 8px 16px; cursor: pointer; font-weight: bold;">
                    ✔ Confirmar Venta
                </button>
            </div>
        </form>
    </dialog>

</body>
</html>