<?php
// reportes/reporte_tabla_empleados.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../index.php");
    exit;
}

// Consultar empleados
try {
    $stmt = $pdo->query("SELECT id_usuario, identificador, nombre_completo, telefono, correo, rol FROM usuarios ORDER BY id_usuario ASC");
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $empleados = [];
}

// Consultar camaristas
try {
    $stmt_cam = $pdo->query("SELECT id_camarista, nombre_completo, telefono, correo, usuario, estatus FROM camaristas ORDER BY id_camarista ASC");
    $camaristas = $stmt_cam->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $camaristas = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Empleados y Camaristas</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color: #000; background: #fff; }
        h2 { margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px; }
        th, td { border: 1px solid #333; padding: 6px 10px; text-align: left; font-size: 14px; }
        th { background-color: #eee; }
        .no-print { margin-bottom: 20px; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print">
        <button onclick="window.print()">Imprimir Reporte</button>
        <a href="../empleados.php"><button type="button">Volver</button></a>
    </div>

    <h2>Lista de Empleados / Usuarios</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Identificador</th>
                <th>Nombre Completo</th>
                <th>Teléfono</th>
                <th>Correo</th>
                <th>Rol</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($empleados)): ?>
                <?php foreach ($empleados as $emp): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($emp['id_usuario']); ?></td>
                        <td><?php echo htmlspecialchars($emp['identificador'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($emp['nombre_completo']); ?></td>
                        <td><?php echo htmlspecialchars($emp['telefono'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($emp['correo'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($emp['rol']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center;">No hay usuarios registrados.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <h2>Lista de Camaristas</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre Completo</th>
                <th>Teléfono</th>
                <th>Correo</th>
                <th>Usuario</th>
                <th>Estatus</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($camaristas)): ?>
                <?php foreach ($camaristas as $cam): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cam['id_camarista']); ?></td>
                        <td><?php echo htmlspecialchars($cam['nombre_completo']); ?></td>
                        <td><?php echo htmlspecialchars($cam['telefono'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($cam['correo'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($cam['usuario']); ?></td>
                        <td><?php echo htmlspecialchars($cam['estatus']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center;">No hay camaristas registrados.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>