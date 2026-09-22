<?php
session_start();

if (!isset($_SESSION['id_usuario']) && !isset($_SESSION['user_id']) && !isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit;
}

require_once '../config/conexion.php';

function formatoPesos($monto, $con_decimales = true) {
    if ($monto === null || $monto === '') return '$0.00';
    $decimales = $con_decimales ? 2 : 0;
    return '$' . number_format((float)$monto, $decimales, '.', ',');
}

$id_estancia = $_GET['id_estancia'] ?? null;

if (!$id_estancia) {
    die("Error: No se especificó la estancia para generar el comprobante.");
}

// 1. Obtener datos de la estancia, habitación y huésped
$sql = "SELECT 
            e.id_estancia,
            e.fecha_entrada,
            e.fecha_salida,
            e.estatus_estancia,
            h.numero_habitacion,
            h.tipo_habitacion,
            h.precio AS precio_noche,
            hu.identificador AS id_huesped_codigo,
            hu.nombre,
            hu.apellido_p,
            hu.apellido_m,
            hu.telefono,
            hu.correo
        FROM estancias e
        INNER JOIN habitaciones h ON e.id_habitacion = h.id_habitacion
        INNER JOIN huesped hu ON e.id_huesped = hu.id_huesped
        WHERE e.id_estancia = :id_estancia";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id_estancia' => $id_estancia]);
$estancia = $stmt->fetch();

if (!$estancia) {
    die("Error: Estancia no encontrada.");
}

// 2. Obtener pagos de hospedaje realizados
$sql_pagos_est = "SELECT * FROM pagos_estancias WHERE id_estancia = :id_estancia ORDER BY fecha_pago ASC";
$stmt_pe = $pdo->prepare($sql_pagos_est);
$stmt_pe->execute(['id_estancia' => $id_estancia]);
$pagos_estancia = $stmt_pe->fetchAll();

// 3. Obtener consumos / servicios de la estancia
$sql_servicios = "SELECT 
                    ps.*,
                    s.nombre_servicio,
                    s.tipo_servicio
                  FROM pagos_servicios ps
                  INNER JOIN servicios s ON ps.id_servicio = s.id_servicio
                  WHERE ps.id_estancia = :id_estancia
                  ORDER BY ps.id_pago ASC";
$stmt_s = $pdo->prepare($sql_servicios);
$stmt_s->execute(['id_estancia' => $id_estancia]);
$servicios = $stmt_s->fetchAll();

// Cálculos
$fecha_in  = new DateTime($estancia['fecha_entrada']);
$fecha_out = new DateTime($estancia['fecha_salida']);
$diferencia = $fecha_in->diff($fecha_out);
$noches = $diferencia->days > 0 ? $diferencia->days : 1;

$total_hospedaje = $noches * (float)$estancia['precio_noche'];

$total_pagado_hospedaje = 0;
foreach ($pagos_estancia as $pe) {
    $total_pagado_hospedaje += (float)$pe['monto'];
}
$saldo_hospedaje = $total_hospedaje - $total_pagado_hospedaje;
if ($saldo_hospedaje < 0) $saldo_hospedaje = 0;

$total_servicios = 0;
$total_servicios_pagados = 0;
$total_servicios_pendientes = 0;
foreach ($servicios as $srv) {
    $monto_srv = (float)$srv['monto'];
    $total_servicios += $monto_srv;
    if ($srv['estado_pago'] === 'Pagado') {
        $total_servicios_pagados += $monto_srv;
    } else {
        $total_servicios_pendientes += $monto_srv;
    }
}

$gran_total = $total_hospedaje + $total_servicios;
$gran_total_pagado = $total_pagado_hospedaje + $total_servicios_pagados;
$gran_total_pendiente = $saldo_hospedaje + $total_servicios_pendientes;

// 4. Obtener específicamente el nombre_completo del usuario con la sesión activa
$nombre_usuario_atendio = 'Recepción / Sistema';

if (isset($_SESSION['nombre_completo'])) {
    $nombre_usuario_atendio = $_SESSION['nombre_completo'];
} else {
    $id_usuario_sesion = $_SESSION['id_usuario'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
    if ($id_usuario_sesion) {
        try {
            $stmt_usu = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = :id OR id = :id LIMIT 1");
            $stmt_usu->execute(['id' => $id_usuario_sesion]);
            $datos_usu = $stmt_usu->fetch(PDO::FETCH_ASSOC);
            
            if ($datos_usu) {
                if (!empty($datos_usu['nombre_completo'])) {
                    $nombre_usuario_atendio = $datos_usu['nombre_completo'];
                } elseif (!empty($datos_usu['nombre'])) {
                    $nombre_usuario_atendio = $datos_usu['nombre'];
                }
            }
        } catch (Exception $e) {
            // Silenciar error y mantener valor por defecto
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Check-Out - Estancia #<?php echo $estancia['id_estancia']; ?></title>
    <style>
        @page {
            size: auto;
            margin: 10mm;
        }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; margin: 0; padding: 20px; background-color: #fff; font-size: 14px; }
        .ticket-container { max-width: 800px; margin: 0 auto; border: 1px solid #ddd; padding: 30px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        .header { text-align: center; border-bottom: 2px solid #0275d8; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: #0275d8; font-size: 24px; }
        .header p { margin: 5px 0 0; color: #666; font-size: 13px; }
        .info-section { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .info-box { width: 48%; background-color: #f9f9f9; padding: 12px; border-radius: 5px; box-sizing: border-box; }
        .info-box h3 { margin: 0 0 8px; font-size: 14px; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        .info-box p { margin: 4px 0; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 13px; }
        th { background-color: #f2f2f2; color: #333; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totales-box { background-color: #f1f8ff; border: 1px solid #cce5ff; padding: 15px; border-radius: 5px; margin-top: 20px; }
        .totales-box table { margin: 0; border: none; }
        .totales-box td { border: none; padding: 4px 0; }
        .footer { text-align: center; margin-top: 30px; font-size: 11px; color: #777; border-top: 1px solid #eee; padding-top: 10px; }
        .no-print { margin-top: 20px; text-align: center; }
        .btn { background-color: #0275d8; color: white; border: none; padding: 10px 20px; font-size: 14px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        .btn:hover { background-color: #025aa5; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .ticket-container { border: none; box-shadow: none; padding: 0; }
        }
    </style>
</head>
<body>

<div class="ticket-container">
    <div class="header">
        <h1>Datasys Hotel Management</h1>
        <p>Comprobante Oficial de Estancia y Check-Out</p>
        <p><strong>Folio de Estancia:</strong> #<?php echo $estancia['id_estancia']; ?> | <strong>Estatus:</strong> <?php echo htmlspecialchars($estancia['estatus_estancia']); ?></p>
    </div>

    <div class="info-section">
        <div class="info-box">
            <h3>Datos del Huésped</h3>
            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($estancia['nombre'] . ' ' . $estancia['apellido_p'] . ' ' . $estancia['apellido_m']); ?></p>
            <p><strong>Identificador:</strong> <?php echo htmlspecialchars($estancia['id_huesped_codigo']); ?></p>
            <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($estancia['telefono'] ?: 'No registrado'); ?></p>
            <p><strong>Correo:</strong> <?php echo htmlspecialchars($estancia['correo'] ?: 'No registrado'); ?></p>
        </div>
        <div class="info-box">
            <h3>Detalles de la Habitación y Estancia</h3>
            <p><strong>Habitación:</strong> Núm. <?php echo htmlspecialchars($estancia['numero_habitacion']); ?> (<?php echo htmlspecialchars($estancia['tipo_habitacion']); ?>)</p>
            <p><strong>Entrada:</strong> <?php echo $estancia['fecha_entrada']; ?></p>
            <p><strong>Salida:</strong> <?php echo $estancia['fecha_salida']; ?></p>
            <p><strong>Duración:</strong> <?php echo $noches; ?> noche(s) a <?php echo formatoPesos($estancia['precio_noche']); ?>/noche</p>
        </div>
    </div>

    <h3>1. Desglose de Hospedaje</h3>
    <table>
        <thead>
            <tr>
                <th>Concepto</th>
                <th class="text-center">Noches</th>
                <th class="text-right">Precio Unitario</th>
                <th class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Hospedaje Habitación <?php echo htmlspecialchars($estancia['numero_habitacion']); ?></td>
                <td class="text-center"><?php echo $noches; ?></td>
                <td class="text-right"><?php echo formatoPesos($estancia['precio_noche']); ?></td>
                <td class="text-right"><?php echo formatoPesos($total_hospedaje); ?></td>
            </tr>
        </tbody>
    </table>

    <?php if (count($pagos_estancia) > 0): ?>
        <p style="font-size: 12px; font-weight: bold; margin-bottom: 4px;">Pagos de Hospedaje Registrados:</p>
        <table>
            <thead>
                <tr>
                    <th>ID Pago</th>
                    <th>Método de Pago</th>
                    <th>Fecha de Pago</th>
                    <th class="text-right">Monto Pagado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pagos_estancia as $pe): ?>
                    <tr>
                        <td>#<?php echo $pe['id_pago']; ?></td>
                        <td><?php echo htmlspecialchars($pe['metodo_pago']); ?></td>
                        <td><?php echo $pe['fecha_pago']; ?></td>
                        <td class="text-right" style="color: green;"><?php echo formatoPesos($pe['monto']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h3>2. Consumos y Servicios Adicionales</h3>
    <?php if (count($servicios) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Servicio / Consumo</th>
                    <th>Tipo</th>
                    <th class="text-center">Estado</th>
                    <th>Método / Fecha</th>
                    <th class="text-right">Monto</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($servicios as $srv): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($srv['nombre_servicio']); ?></strong></td>
                        <td><?php echo htmlspecialchars($srv['tipo_servicio']); ?></td>
                        <td class="text-center">
                            <span style="color: <?php echo ($srv['estado_pago'] === 'Pagado') ? 'green' : 'red'; ?>; font-weight: bold;">
                                <?php echo htmlspecialchars($srv['estado_pago']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($srv['estado_pago'] === 'Pagado'): ?>
                                <small><?php echo htmlspecialchars($srv['metodo_pago']); ?> (<?php echo $srv['fecha_pago']; ?>)</small>
                            <?php else: ?>
                                <small style="color: #666;">Pendiente de cobro</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><?php echo formatoPesos($srv['monto']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="color: #666; font-style: italic;">No se registraron consumos ni servicios adicionales en esta estancia.</p>
    <?php endif; ?>

    <div class="totales-box">
        <h3 style="margin-top: 0; color: #0275d8; border-bottom: 1px solid #cce5ff; padding-bottom: 5px;">Resumen Financiero</h3>
        <table>
            <tr>
                <td>Total Hospedaje:</td>
                <td class="text-right"><strong><?php echo formatoPesos($total_hospedaje); ?></strong></td>
            </tr>
            <tr>
                <td>Total Servicios y Consumos:</td>
                <td class="text-right"><strong><?php echo formatoPesos($total_servicios); ?></strong></td>
            </tr>
            <tr style="border-top: 1px dashed #b8daff;">
                <td><strong>Gran Total Consumido:</strong></td>
                <td class="text-right"><strong style="font-size: 1.1em;"><?php echo formatoPesos($gran_total); ?></strong></td>
            </tr>
            <tr>
                <td>Total Pagado Histórico:</td>
                <td class="text-right" style="color: green;"><strong><?php echo formatoPesos($gran_total_pagado); ?></strong></td>
            </tr>
            <tr style="border-top: 1px solid #b8daff;">
                <td><strong>Saldo Pendiente al Finalizar / Liquidado:</strong></td>
                <td class="text-right">
                    <strong style="color: <?php echo ($gran_total_pendiente > 0) ? 'red' : 'green'; ?>; font-size: 1.2em;">
                        <?php echo formatoPesos($gran_total_pendiente); ?>
                    </strong>
                </td>
            </tr>
        </table>
    </div>

    <div style="margin-top: 20px; font-size: 13px;">
        <p><strong>Atendido por (Check-Out):</strong> <?php echo htmlspecialchars($nombre_usuario_atendio); ?></p>
        <p><strong>Fecha de Emisión del Comprobante:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>

    <div class="footer">
        <p>¡Gracias por su estancia! Datasys Hotel Management System.</p>
    </div>

    <div class="no-print">
        <button class="btn" onclick="window.print()">🖨️ Imprimir / Guardar como PDF</button>
        <p style="margin-top: 10px;"><a href="../check_out.php" style="color: #0275d8; text-decoration: none;">← Volver a Recepción</a></p>
    </div>
</div>

<script>
    window.onload = function() {
        window.print();
    };
</script>

</body>
</html>