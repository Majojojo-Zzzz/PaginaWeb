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
    // Consultamos los huéspedes con estancia activa, uniendo habitaciones y seleccionando fechas de entrada/salida
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
    $error_db = "Error al consultar la lista de huéspedes con estancia activa: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Datasys - Huéspedes con Estancia Activa</title>
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
        .btn-accion {
            padding: 6px 12px;
            cursor: pointer;
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
    </style>
</head>
<body>

    <h2>Huéspedes con Estancia Activa</h2>

    <p><a href="home.php">Volver al menú principal</a></p>

    <p>
        <a href="reportes/reporte_tabla_huespedes.php" target="_blank">
            <button type="button" class="btn-accion">🖨️ Imprimir Tabla de Huéspedes</button>
        </a>
    </p>

    <!-- BARRA DE BÚSQUEDA EN TIEMPO REAL -->
    <div class="barra-busqueda">
        <input type="text" id="inputBusqueda" placeholder="Escribe para buscar de todo al instante..." onkeyup="filtrarTabla()">
    </div>

    <?php if (isset($error_db)): ?>
        <p class="error"><?php echo htmlspecialchars($error_db); ?></p>
    <?php endif; ?>

    <table id="tablaHuespedes">
        <thead>
            <tr>
                <th>ID</th>
                <th>Habitación</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Identificador</th>
                <th>Nombre</th>
                <th>Apellido Paterno</th>
                <th>Apellido Materno</th>
                <th>Teléfono</th>
                <th>Correo</th>
                <th>Nacionalidad</th>
                <th>Acciones</th>
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
                        <td><?php echo htmlspecialchars($h['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($h['apellido_p']); ?></td>
                        <td><?php echo htmlspecialchars($h['apellido_m'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($h['telefono'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($h['correo'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($h['nacionalidad'] ?? 'N/A'); ?></td>
                        <td>
                            <!-- Botón Editar -->
                            <a href="editar_huesped.php?id=<?php echo $h['id_huesped']; ?>">
                                <button type="button" class="btn-accion">Editar</button>
                            </a>

                            <!-- Botón Borrar -->
                            <form action="eliminar_huesped.php" method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este huésped?');">
                                <input type="hidden" name="id_huesped" value="<?php echo $h['id_huesped']; ?>">
                                <button type="submit" class="btn-accion">Borrar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr id="filaSinResultados">
                    <td colspan="12" style="text-align: center;">No hay huéspedes con estancia activa actualmente.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- SCRIPT DE JAVASCRIPT PARA BÚSQUEDA INSTÁNTANEA -->
    <script>
        function filtrarTabla() {
            let input = document.getElementById("inputBusqueda");
            let filtro = input.value.toLowerCase();
            let tabla = document.getElementById("tablaHuespedes");
            let filas = tabla.getElementsByTagName("tr");

            // Recorremos todas las filas de la tabla (omitiendo la cabecera en i = 1)
            for (let i = 1; i < filas.length; i++) {
                let fila = filas[i];
                let textoFila = fila.textContent || fila.innerText;

                // Si alguna celda de la fila coincide con lo que se escribe, se muestra; si no, se oculta
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