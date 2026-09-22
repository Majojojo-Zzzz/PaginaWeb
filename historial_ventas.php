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

// 3. Procesar Liquidación de Pago Pendiente (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'liquidar_pendiente') {
        if (!$id_turno_actual) {
            $_SESSION['mensaje'] = "Error: Debes abrir un turno para liquidar servicios.";
            $_SESSION['tipo_mensaje'] = "error";
        } else {
            $id_pago     = $_POST['id_pago'] ?? null;
            $metodo_pago = $_POST['metodo_pago_liquidar'] ?? 'Efectivo';

            if ($id_pago) {
                try {
                    $sql_upd = "UPDATE pagos_servicios 
                                SET estado_pago = 'Pagado',
                                    metodo_pago = :metodo_pago, 
                                    id_turno = :id_turno, 
                                    id_usuario = :id_usuario, 
                                    fecha_pago = NOW() 
                                WHERE id_pago = :id_pago";
                    $stmt_u = $pdo->prepare($sql_upd);
                    $stmt_u->execute([
                        'metodo_pago' => $metodo_pago,
                        'id_turno'    => $id_turno_actual,
                        'id_usuario'  => $id_usuario_actual,
                        'id_pago'     => $id_pago
                    ]);

                    $_SESSION['mensaje'] = "¡El servicio #" . $id_pago . " fue liquidado correctamente (" . $metodo_pago . ")!";
                    $_SESSION['tipo_mensaje'] = "exito";
                } catch (Exception $e) {
                    $_SESSION['mensaje'] = "Error al actualizar pago: " . $e->getMessage();
                    $_SESSION['tipo_mensaje'] = "error";
                }
            }
        }
        header("Location: historial_ventas.php");
        exit;
    }
}

// 4. Obtener Historial Completo de Servicios
$sql_pagos = "SELECT ps.*, s.nombre_servicio, s.tipo_servicio, u.usuario AS atendido_por,
                     h.numero_habitacion, hu.nombre AS nombre_huesped, hu.apellido_p AS apellido_huesped
              FROM pagos_servicios ps
              INNER JOIN servicios s ON ps.id_servicio = s.id_servicio
              LEFT JOIN estancias e ON ps.id_estancia = e.id_estancia
              LEFT JOIN habitaciones h ON e.id_habitacion = h.id_habitacion
              LEFT JOIN huesped hu ON e.id_huesped = hu.id_huesped
              LEFT JOIN usuarios u ON ps.id_usuario = u.id_usuario
              ORDER BY ps.id_pago DESC";
$stmt_pagos = $pdo->query($sql_pagos);
$historial_pagos = $stmt_pagos->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datasys - Historial de Ventas y Consumos</title>
</head>
<body>

    <h2>Historial de Ventas y Consumos de Servicios</h2>
    <p>
        <a href="servicios.php">Volver a Catálogo de Servicios</a> | 
        <a href="home.php">Menú Principal</a>
    </p>

    <!-- Alerta de Turno -->
    <?php if (!$id_turno_actual): ?>
        <p style="color: red; font-weight: bold; background-color: #fee; padding: 10px; border: 1px solid red;">
            ⚠️ ATENCIÓN: No tienes un turno abierto. Necesitas un turno abierto para liquidar cobros pendientes.
        </p>
    <?php endif; ?>

    <!-- Mensajes de Estado -->
    <?php if (!empty($mensaje)): ?>
        <p style="color: <?php echo ($tipo_mensaje === 'exito') ? 'green' : 'red'; ?>; font-weight: bold;">
            <?php echo htmlspecialchars($mensaje); ?>
        </p>
    <?php endif; ?>

    <table border="1" cellpadding="8" cellspacing="0" style="width: 100%;">
        <thead>
            <tr style="background-color: #eee;">
                <th># Reg.</th>
                <th>Habitación / Huésped</th>
                <th>Servicio</th>
                <th>Total Registrado</th>
                <th>Estatus</th>
                <th>Método Pago</th>
                <th>Turno / Vendedor</th>
                <th>Fecha</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($historial_pagos) > 0): ?>
                <?php foreach ($historial_pagos as $p): ?>
                    <tr>
                        <td>#<?php echo $p['id_pago']; ?></td>
                        <td>
                            <?php if ($p['numero_habitacion']): ?>
                                <strong>Hab. <?php echo $p['numero_habitacion']; ?></strong><br>
                                <small><?php echo htmlspecialchars($p['nombre_huesped'] . ' ' . $p['apellido_huesped']); ?></small>
                            <?php else: ?>
                                <small style="color: gray;">Venta Directa</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($p['nombre_servicio']); ?></td>
                        <td><strong style="color: #2e7d32;"><?php echo formatoPesos($p['monto']); ?></strong></td>
                        <td>
                            <?php if ($p['estado_pago'] === 'Pagado'): ?>
                                <span style="color: green; font-weight: bold;">🟢 PAGADO</span>
                            <?php else: ?>
                                <span style="color: red; font-weight: bold;">🔴 PENDIENTE</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($p['metodo_pago']); ?></td>
                        <td>
                            <small>Turno #<?php echo $p['id_turno']; ?></small><br>
                            <small>(<?php echo htmlspecialchars($p['atendido_por'] ?? 'N/A'); ?>)</small>
                        </td>
                        <td><small><?php echo $p['fecha_pago']; ?></small></td>
                        <td>
                            <?php if ($p['estado_pago'] === 'Pendiente'): ?>
                                <form action="historial_ventas.php" method="POST" onsubmit="return confirm('¿Confirmar cobro de este servicio?');" style="display: flex; gap: 4px;">
                                    <input type="hidden" name="accion" value="liquidar_pendiente">
                                    <input type="hidden" name="id_pago" value="<?php echo $p['id_pago']; ?>">
                                    
                                    <select name="metodo_pago_liquidar" style="font-size: 0.85em;">
                                        <option value="Efectivo">Efectivo</option>
                                        <option value="Trasferencia">Transferencia</option>
                                    </select>

                                    <button type="submit" <?php echo (!$id_turno_actual) ? 'disabled' : ''; ?> style="background-color: orange; color: black; border: 1px solid #d98300; padding: 3px 6px; cursor: pointer;">
                                        💵 Liquidar
                                    </button>
                                </form>
                            <?php else: ?>
                                <small style="color: gray;">✔ Liquidado</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9">No hay registros de ventas.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>