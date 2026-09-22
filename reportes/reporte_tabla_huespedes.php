<?php
// Incluimos la conexión (subiendo un nivel desde la carpeta 'reportes')
require_once '../config/conexion.php';

// Iniciar sesión si no se ha iniciado antes
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Filtro de seguridad: Redirigir si no hay sesión activa
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../index.php");
    exit;
}

try {
    // Consultamos los mismos huéspedes con estancia activa para el reporte
    $sql = "SELECT 
                h.id_huesped, 
                h.identificador, 
                h.nombre, 
                h.apellido_p, 
                h.apellido_m, 
                h.telefono, 
                h.correo, 
                h.nacionalidad,
                r.numero_habitacion,
                e.fecha_entrada,
                e.fecha_salida
            FROM huesped h
            JOIN estancias e ON h.id_huesped = e.id_huesped
            JOIN habitaciones r ON e.id_habitacion = r.id_habitacion
            WHERE LOWER(e.estatus_estancia) = 'activa'
            ORDER BY h.id_huesped DESC";
            
    $stmt = $pdo->query($sql);
    $huespedes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_db = "Error al generar el reporte de huéspedes: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Huéspedes con Estancia Activa</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        h2 {
            text-align: center;
            margin-bottom: 5px;
        }
        .fecha-reporte {
            text-align: center;
            font-size: 13px;
            color: #666;
            margin-bottom: 20px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 15px;
        }
        th, td {
            border: 1px solid #999;
            padding: 8px 10px;
            text-align: left;
            font-size: 13px;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .error {
            color: red;
            font-weight: bold;
            text-align: center;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                margin: 0;
            }
        }
    </style>
</head>
<body onload="window.print()">

    <h2>Reporte de Huéspedes con Estancia Activa</h2>
    <div class="fecha-reporte">Fecha de emisión: <?php echo date('d/m/Y H:i:s'); ?></div>

    <?php if (isset($error_db)): ?>
        <p class="error"><?php echo htmlspecialchars($error_db); ?></p>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Habitación</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Identificador</th>
                <th>Nombre Completo</th>
                <th>Teléfono</th>
                <th>Correo</th>
                <th>Nacionalidad</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($huespedes)): ?>
                <?php foreach ($huespedes as $h): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($h['id_huesped']); ?></td>
                        <td><strong><?php echo htmlspecialchars($h['numero_habitacion']); ?></strong></td>
                        <td><?php echo htmlspecialchars($h['fecha_entrada']); ?></td>
                        <td><?php echo htmlspecialchars($h['fecha_salida']); ?></td>
                        <td><?php echo htmlspecialchars($h['identificador'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($h['nombre'] . ' ' . $h['apellido_p'] . ' ' . ($h['apellido_m'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars($h['telefono'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($h['correo'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($h['nacionalidad'] ?? 'N/A'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" style="text-align: center;">No hay huéspedes con estancia activa actualmente.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>