<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
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

// 1. Obtener datos de la estancia, habitación, huésped y el nombre completo del usuario que hizo el check-in usando JOIN
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
            hu.correo,
            hu.nacionalidad,
            u.nombre_completo AS recepcionista_nombre
        FROM estancias e
        INNER JOIN habitaciones h ON e.id_habitacion = h.id_habitacion
        INNER JOIN huesped hu ON e.id_huesped = hu.id_huesped
        LEFT JOIN usuarios u ON e.id_usuario_checkin = u.id_usuario
        WHERE e.id_estancia = :id_estancia";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id_estancia' => $id_estancia]);
$estancia = $stmt->fetch();

if (!$estancia) {
    die("Error: Estancia no encontrada.");
}

// 2. Obtener pagos de la estancia
$sql_pagos = "SELECT * FROM pagos_estancias WHERE id_estancia = :id_estancia ORDER BY fecha_pago ASC";
$stmt_p = $pdo->prepare($sql_pagos);
$stmt_p->execute(['id_estancia' => $id_estancia]);
$pagos = $stmt_p->fetchAll();

// Cálculos
$fecha_in  = new DateTime($estancia['fecha_entrada']);
$fecha_out = new DateTime($estancia['fecha_salida']);
$diferencia = $fecha_in->diff($fecha_out);
$noches = $diferencia->days > 0 ? $diferencia->days : 1;

$total_hospedaje = $noches * (float)$estancia['precio_noche'];

$total_pagado = 0;
foreach ($pagos as $p) {
    $total_pagado += (float)$p['monto'];
}
$saldo_pendiente = $total_hospedaje - $total_pagado;
if ($saldo_pendiente < 0) $saldo_pendiente = 0;

// Nombre del recepcionista (Si está vacío, respaldar con la sesión)
$nombre_recepcionista = !empty($estancia['recepcionista_nombre']) ? $estancia['recepcionista_nombre'] : ($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? 'Sistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Check-In #<?php echo $estancia['id_estancia']; ?></title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 20px; font-size: 13px; background-color: #fff; }
        .no-print { margin-bottom: 20px; text-align: center; }
        .btn { padding: 8px 15px; background-color: #28a745; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold; border: none; cursor: pointer; display: inline-block; margin: 0 5px; }
        .btn-secondary { background-color: #6c757d; }
        .container { max-width: 750px; margin: 0 auto; padding: 15px; border: 1px solid #ddd; border-radius: 6px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        .header { text-align: center; border-bottom: 2px solid #28a745; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { margin: 0; color: #28a745; font-size: 20px; }
        .header p { margin: 4px 0 0; color: #666; font-size: 12px; }
        .info-section { width: 100%; margin-bottom: 15px; display: table; }
        .info-box { width: 48%; background-color: #f9f9f9; padding: 10px; border-radius: 4px; display: table-cell; vertical-align: top; box-sizing: border-box; }
        .info-box-right { padding-left: 15px; }
        .info-box h3 { margin: 0 0 6px; font-size: 13px; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 3px; }
        .info-box p { margin: 3px 0; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 15px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; font-size: 12px; }
        th { background-color: #f2f2f2; color: #333; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totales-box { background-color: #e8f5e9; border: 1px solid #c8e6c9; padding: 10px; border-radius: 4px; margin-top: 15px; }
        .totales-box table { margin: 0; border: none; }
        .totales-box td { border: none; padding: 3px 0; }
        .footer { text-align: center; margin-top: 25px; font-size: 10px; color: #777; border-top: 1px solid #eee; padding-top: 8px; }

        @media print {
            .no-print { display: none; }
            body { padding: 0; background-color: #fff; }
            .container { border: none; box-shadow: none; padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" class="btn">🖨️ Guardar como PDF / Imprimir</button>
    <a href="../check_in.php" class="btn btn-secondary">⬅️ Volver a Check-In</a>
</div>

<div class="container">
    <div class="header">
        <h1>Datasys Hotel Management</h1>
        <p>Comprobante Oficial de Registro de Check-In</p>
        <p><strong>Folio de Estancia:</strong> #<?php echo $estancia['id_estancia']; ?> | <strong>Estatus:</strong> <?php echo htmlspecialchars($estancia['estatus_estancia']); ?></p>
    </div>

    <div class="info-section">
        <div class="info-box">
            <h3>Datos del Huésped</h3>
            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($estancia['nombre'] . ' ' . $estancia['apellido_p'] . ' ' . $estancia['apellido_m']); ?></p>
            <p><strong>Identificador:</strong> <?php echo htmlspecialchars($estancia['id_huesped_codigo']); ?></p>
            <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($estancia['telefono'] ?: 'No registrado'); ?></p>
            <p><strong>Correo:</strong> <?php echo htmlspecialchars($estancia['correo'] ?: 'No registrado'); ?></p>
            <p><strong>Nacionalidad:</strong> <?php echo htmlspecialchars($estancia['nacionalidad'] ?: 'No registrada'); ?></p>
        </div>
        <div class="info-box info-box-right">
            <h3>Detalles de la Habitación y Fechas</h3>
            <p><strong>Habitación:</strong> Núm. <?php echo htmlspecialchars($estancia['numero_habitacion']); ?> (<?php echo htmlspecialchars($estancia['tipo_habitacion']); ?>)</p>
            <p><strong>Entrada:</strong> <?php echo $estancia['fecha_entrada']; ?></p>
            <p><strong>Salida:</strong> <?php echo $estancia['fecha_salida']; ?></p>
            <p><strong>Duración:</strong> <?php echo $noches; ?> noche(s)</p>
            <p><strong>Precio por Noche:</strong> <?php echo formatoPesos($estancia['precio_noche']); ?></p>
        </div>
    </div>

    <h3>Desglose de Hospedaje</h3>
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
                <td>Estancia en Habitación <?php echo htmlspecialchars($estancia['numero_habitacion']); ?></td>
                <td class="text-center"><?php echo $noches; ?></td>
                <td class="text-right"><?php echo formatoPesos($estancia['precio_noche']); ?></td>
                <td class="text-right"><?php echo formatoPesos($total_hospedaje); ?></td>
            </tr>
        </tbody>
    </table>

    <?php if (count($pagos) > 0): ?>
    <p style="font-size: 11px; font-weight: bold; margin-bottom: 2px;">Pagos / Anticipos Registrados:</p>
    <table>
        <thead>
            <tr>
                <th>ID Pago</th>
                <th>Método de Pago</th>
                <th>Fecha de Pago</th>
                <th class="text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pagos as $p): ?>
            <tr>
                <td>#<?php echo $p['id_pago']; ?></td>
                <td><?php echo htmlspecialchars($p['metodo_pago']); ?></td>
                <td><?php echo $p['fecha_pago']; ?></td>
                <td class="text-right" style="color: green;"><?php echo formatoPesos($p['monto']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php $color_saldo = ($saldo_pendiente > 0) ? 'red' : 'green'; ?>

    <div class="totales-box">
        <h3 style="margin-top: 0; color: #2e7d32; border-bottom: 1px solid #c8e6c9; padding-bottom: 4px; font-size: 13px;">Resumen Financiero del Check-In</h3>
        <table>
            <tr>
                <td>Costo Total de Estancia:</td>
                <td class="text-right"><strong><?php echo formatoPesos($total_hospedaje); ?></strong></td>
            </tr>
            <tr>
                <td>Total Pagado / Anticipo:</td>
                <td class="text-right" style="color: green;"><strong><?php echo formatoPesos($total_pagado); ?></strong></td>
            </tr>
            <tr>
                <td><strong>Saldo Pendiente:</strong></td>
                <td class="text-right">
                    <strong style="color: <?php echo $color_saldo; ?>; font-size: 1.1em;"><?php echo formatoPesos($saldo_pendiente); ?></strong>
                </td>
            </tr>
        </table>
    </div>

    <div style="margin-top: 15px; font-size: 12px;">
        <p><strong>Atendido por (Recepcionista):</strong> <?php echo htmlspecialchars($nombre_recepcionista); ?></p>
        <p><strong>Fecha de Emisión del Comprobante:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>

    <div class="footer">
        <p>¡Bienvenido! Gracias por elegirnos. Datasys Hotel Management System.</p>
    </div>
</div>

<script>
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 500);
    };
</script>

</body>
</html>