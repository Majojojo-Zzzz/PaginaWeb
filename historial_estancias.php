<?php
// Incluimos la conexión
require_once 'config/conexion.php';

// Iniciar sesión si no se ha iniciado antes
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Filtro de seguridad: Redirigir si no hay sesión activa
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

try {
    // Consultar el historial de estancias sin la columna estatus
    $sql = "SELECT 
                e.id_estancia, 
                h.nombre, 
                h.apellido_p, 
                h.apellido_m, 
                hab.numero_habitacion, 
                hab.tipo_habitacion, 
                e.fecha_entrada, 
                e.fecha_salida 
            FROM estancias e
            INNER JOIN huesped h ON e.id_huesped = h.id_huesped
            INNER JOIN habitaciones hab ON e.id_habitacion = hab.id_habitacion
            ORDER BY e.id_estancia DESC";
            
    $stmt = $pdo->query($sql);
    $estancias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_db = "Error al consultar el historial de estancias: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Datasys - Historial de Estancias</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 15px;
        }
        th, td {
            border: 1px solid #aaa;
            padding: 8px 12px;
            text-align: left;
            vertical-align: middle;
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
        }
        .barra-busqueda {
            margin-bottom: 20px;
            margin-top: 10px;
        }
        .barra-busqueda input[type="text"] {
            padding: 8px;
            width: 350px;
            max-width: 100%;
            font-size: 14px;
        }
        .btn-volver {
            display: inline-block;
            padding: 6px 12px;
            background-color: #f0f0f0;
            border: 1px solid #ccc;
            text-decoration: none;
            color: #333;
            border-radius: 4px;
        }
    </style>
</head>
<body>

    <h2>Historial de Estancias</h2>

    <p><a href="home.php" class="btn-volver">Volver al menú principal</a></p>

    <!-- BARRA DE BÚSQUEDA EN TIEMPO REAL -->
    <div class="barra-busqueda">
        <input type="text" id="inputBusqueda" placeholder="Buscar por huésped, habitación, fecha..." onkeyup="filtrarTabla()">
    </div>

    <?php if (isset($error_db)): ?>
        <p class="error"><?php echo htmlspecialchars($error_db); ?></p>
    <?php endif; ?>

    <table id="tablaEstancias">
        <thead>
            <tr>
                <th>ID Estancia</th>
                <th>Huésped</th>
                <th>Habitación</th>
                <th>Tipo de Habitación</th>
                <th>Fecha de Entrada</th>
                <th>Fecha de Salida</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($estancias)): ?>
                <?php foreach ($estancias as $est): ?>
                    <?php 
                        // Determinamos el estado dinámicamente según la fecha de salida
                        $estado = (empty($est['fecha_salida']) || $est['fecha_salida'] == '0000-00-00 00:00:00') ? 'Activa' : 'Finalizada';
                    ?>
                    <tr>
                        <td>#<?php echo htmlspecialchars($est['id_estancia']); ?></td>
                        <td><?php echo htmlspecialchars($est['nombre'] . ' ' . $est['apellido_p'] . ' ' . ($est['apellido_m'] ?? '')); ?></td>
                        <td>Hab. <?php echo htmlspecialchars($est['numero_habitacion']); ?></td>
                        <td><?php echo htmlspecialchars($est['tipo_habitacion']); ?></td>
                        <td><?php echo htmlspecialchars($est['fecha_entrada']); ?></td>
                        <td><?php echo htmlspecialchars($est['fecha_salida'] ?? 'En curso'); ?></td>
                        <td><strong><?php echo $estado; ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center;">No hay registros de estancias en la base de datos.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- SCRIPT DE JAVASCRIPT PARA BÚSQUEDA INSTÁNTANEA -->
    <script>
        function filtrarTabla() {
            let input = document.getElementById("inputBusqueda");
            let filtro = input.value.toLowerCase();
            let tabla = document.getElementById("tablaEstancias");
            let filas = tabla.getElementsByTagName("tr");

            for (let i = 1; i < filas.length; i++) {
                let fila = filas[i];
                let textoFila = fila.textContent || fila.innerText;

                if (textoFila.toLowerCase().indexOf(filtro) > -1) {
                    fila.style.display = "";
                } else {
                    fila.style.display = "none";
                }
            }
        }
    </script>

</body>
</html>