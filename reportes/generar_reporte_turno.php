<?php
// reportes/generar_reporte_turno.php
session_start();

// 1. Validar autenticación
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../index.php");
    exit;
}

require_once '../config/conexion.php';

// Validar que venga el ID del turno por GET
if (!isset($_GET['id_turno']) || empty($_GET['id_turno'])) {
    die("ID de turno no especificado.");
}

$id_turno = intval($_GET['id_turno']);

// 2. Obtener la información general del turno y del usuario
$stmt_turno = $pdo->prepare("
    SELECT t.*, u.nombre_completo, u.rol 
    FROM turnos t
    JOIN usuarios u ON t.id_usuario = u.id_usuario
    WHERE t.id_turno = :id_turno
    LIMIT 1
");
$stmt_turno->execute(['id_turno' => $id_turno]);
$turno = $stmt_turno->fetch();

if (!$turno) {
    die("El turno especificado no existe.");
}

// Determinar el texto del rol según las reglas solicitadas
$rol_original = trim($turno['rol']);
$texto_rol = 'Caja'; // Valor por defecto si hubiera otro
if ($rol_original === 'Administrador') {
    $texto_rol = 'Administrador';
} elseif ($rol_original === 'Recepcionista') {
    $texto_rol = 'Recepción';
} elseif ($rol_original === 'Vendedor') {
    $texto_rol = 'Servicio';
}

// 3. Calcular los totales de cobros de este turno específico
$total_efectivo = 0.00;
$total_transferencia = 0.00;

$sql_est = $pdo->prepare("SELECT metodo_pago, SUM(monto) AS total FROM pagos_estancias WHERE id_turno = :id_turno GROUP BY metodo_pago");
$sql_est->execute(['id_turno' => $id_turno]);
foreach ($sql_est->fetchAll() as $c) {
    if ($c['metodo_pago'] === 'Efectivo') $total_efectivo += $c['total'];
    if ($c['metodo_pago'] === 'Trasferencia' || $c['metodo_pago'] === 'Transferencia') $total_transferencia += $c['total'];
}

$sql_ser = $pdo->prepare("SELECT metodo_pago, SUM(monto) AS total FROM pagos_servicios WHERE id_turno = :id_turno AND (estado_pago = 'Pagado' OR estado_pago IS NULL) GROUP BY metodo_pago");
$sql_ser->execute(['id_turno' => $id_turno]);
foreach ($sql_ser->fetchAll() as $c) {
    if ($c['metodo_pago'] === 'Efectivo') $total_efectivo += $c['total'];
    if ($c['metodo_pago'] === 'Trasferencia' || $c['metodo_pago'] === 'Transferencia') $total_transferencia += $c['total'];
}

$total_cobrado = $total_efectivo + $total_transferencia;
$efectivo_esperado = floatval($turno['monto_inicial']) + $total_efectivo;
$monto_final_caja = ($turno['estatus'] === 'cerrado') ? floatval($turno['monto_final']) : $efectivo_esperado;

// 4. Obtener la lista detallada de los cobros realizados en el turno
$sql_historial = "
    SELECT 
        'Estancia' AS tipo_cobro,
        p.id_pago,
        p.id_estancia,
        NULL AS tipo_servicio,
        NULL AS nombre_servicio,
        p.metodo_pago,
        p.monto,
        p.fecha_pago
    FROM pagos_estancias p
    WHERE p.id_turno = :id_turno1

    UNION ALL

    SELECT 
        'Servicio' AS tipo_cobro,
        p.id_pago,
        p.id_estancia,
        s.tipo_servicio,
        s.nombre_servicio,
        p.metodo_pago,
        p.monto,
        p.fecha_pago
    FROM pagos_servicios p
    LEFT JOIN servicios s ON p.id_servicio = s.id_servicio
    WHERE p.id_turno = :id_turno2 AND (p.estado_pago = 'Pagado' OR p.estado_pago IS NULL)

    ORDER BY fecha_pago DESC
";
$stmt_h = $pdo->prepare($sql_historial);
$stmt_h->execute(['id_turno1' => $id_turno, 'id_turno2' => $id_turno]);
$lista_cobros = $stmt_h->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Turno #<?php echo $turno['id_turno']; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 13px;
            color: #333;
            margin: 0;
            padding: 20px;
            background: #fff;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            text-transform: uppercase;
        }
        .header p {
            margin: 4px 0 0;
            color: #666;
            font-size: 12px;
        }
        .section-title {
            background-color: #f4f4f4;
            padding: 6px 10px;
            font-size: 13px;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #333;
        }
        .grid-info {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
        }
        .grid-info td {
            padding: 4px 6px;
            vertical-align: top;
        }
        .grid-info td.label {
            font-weight: bold;
            width: 30%;
            color: #555;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #ddd;
            padding: 7px 10px;
            text-align: left;
        }
        table.data-table th {
            background-color: #333;
            color: #fff;
            font-size: 12px;
        }
        table.data-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .no-print {
            text-align: center;
            margin: 20px 0;
        }
        .btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 14px;
            cursor: pointer;
            border-radius: 5px;
            font-weight: bold;
        }
        .btn:hover {
            background: #1d4ed8;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Botón de acción para imprimir / guardar como PDF -->
    <div class="no-print">
        <button class="btn" onclick="window.print()">🖨️ Imprimir / Guardar como PDF</button>
    </div>

    <div class="header">
        <h1>Reporte de Turno de Caja - <?php echo htmlspecialchars($texto_rol); ?></h1>
        <p>Sistema de Control de Estancias y Servicios</p>
    </div>

    <div class="section-title">Información General</div>
    <table class="grid-info">
        <tr>
            <td class="label">No. Turno:</td>
            <td>#<?php echo $turno['id_turno']; ?></td>
            <td class="label">Estatus:</td>
            <td><strong><?php echo ucfirst($turno['estatus']); ?></strong></td>
        </tr>
        <tr>
            <td class="label">Caja Operada:</td>
            <td><?php echo ucwords(str_replace('_', ' ', $turno['numero_caja'])); ?></td>
            <td class="label">Fecha Inicio:</td>
            <td><?php echo $turno['fecha_inicio']; ?></td>
        </tr>
        <tr>
            <td class="label">Nombre:</td>
            <td><?php echo htmlspecialchars($turno['nombre_completo']); ?></td>
            <td class="label">Fecha Cierre:</td>
            <td><?php echo ($turno['fecha_fin'] ? $turno['fecha_fin'] : 'En curso'); ?></td>
        </tr>
    </table>

    <div class="section-title">Arqueo y Resumen Financiero</div>
    <table class="grid-info">
        <tr>
            <td class="label">Fondo Inicial:</td>
            <td class="text-right">$<?php echo number_format($turno['monto_inicial'], 2, '.', ','); ?></td>
        </tr>
        <tr>
            <td class="label">Cobros en Efectivo:</td>
            <td class="text-right">$<?php echo number_format($total_efectivo, 2, '.', ','); ?></td>
        </tr>
        <tr>
            <td class="label">Cobros por Transferencia:</td>
            <td class="text-right">$<?php echo number_format($total_transferencia, 2, '.', ','); ?></td>
        </tr>
        <tr>
            <td class="label">Dinero Neto Generado (Cobros Totales):</td>
            <td class="text-right"><strong>$<?php echo number_format($total_cobrado, 2, '.', ','); ?></strong></td>
        </tr>
        <tr>
            <td class="label">Efectivo Esperado en Caja:</td>
            <td class="text-right"><strong>$<?php echo number_format($efectivo_esperado, 2, '.', ','); ?></strong></td>
        </tr>
        <tr>
            <td class="label">Monto Final Registrado / Cierre:</td>
            <td class="text-right"><strong>$<?php echo number_format($monto_final_caja, 2, '.', ','); ?></strong></td>
        </tr>
    </table>

    <div class="section-title">Detalle de Actividad y Cobros del Turno</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 10%;"># Pago</th>
                <th style="width: 35%;">Referencia</th>
                <th class="text-center" style="width: 15%;">Tipo</th>
                <th class="text-center" style="width: 20%;">Fecha / Hora</th>
                <th class="text-center" style="width: 20%;">Método de Pago</th>
                <th class="text-right" style="width: 15%;">Monto</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($lista_cobros) > 0): ?>
                <?php foreach ($lista_cobros as $cobro): ?>
                    <?php 
                        $ref = ($cobro['tipo_cobro'] === 'Estancia') 
                            ? 'Estancia #' . $cobro['id_estancia'] 
                            : $cobro['tipo_servicio'] . ': ' . $cobro['nombre_servicio'];
                    ?>
                    <tr>
                        <td class="text-center">#<?php echo $cobro['id_pago']; ?></td>
                        <td><?php echo htmlspecialchars($ref); ?></td>
                        <td class="text-center"><?php echo $cobro['tipo_cobro']; ?></td>
                        <td class="text-center"><?php echo $cobro['fecha_pago']; ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($cobro['metodo_pago']); ?></td>
                        <td class="text-right">$<?php echo number_format($cobro['monto'], 2, '.', ','); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center" style="padding: 20px; color: #777;">No se registraron cobros en este turno.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>