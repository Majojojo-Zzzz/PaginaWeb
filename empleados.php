<?php
// Incluimos la conexión apuntando a la carpeta config
require_once 'config/conexion.php';

// Iniciar sesión si no se ha iniciado antes
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Filtro de seguridad: Si no ha iniciado sesión, redirigir al login
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

// Consultar todos los empleados/usuarios incluyendo la columna 'foto'
try {
    $stmt = $pdo->query("SELECT id_usuario, identificador, nombre_completo, telefono, correo, rol, foto FROM usuarios ORDER BY id_usuario ASC");
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_db = "Error al consultar la tabla de usuarios: " . $e->getMessage();
}

// Consultar todos los camaristas incluyendo la columna 'foto'
try {
    $stmt_cam = $pdo->query("SELECT id_camarista, foto, nombre_completo, telefono, correo, usuario, estatus FROM camaristas ORDER BY id_camarista ASC");
    $camaristas = $stmt_cam->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_camaristas_db = "Error al consultar la tabla de camaristas: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Datasys - Lista de Empleados</title>
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
        .buscador-container {
            margin: 20px 0;
        }
        .buscador-container input {
            width: 100%;
            max-width: 400px;
            padding: 8px;
            font-size: 14px;
            box-sizing: border-box;
        }
    </style>
    <script>
        function filtrarTablas() {
            let input = document.getElementById('buscadorGlobal');
            let filtro = input.value.toLowerCase();

            // Filtrar Tabla de Empleados
            let tablaEmpleados = document.getElementById('tablaEmpleados');
            let filasEmpleados = tablaEmpleados.getElementsByTagName('tr');

            for (let i = 1; i < filasEmpleados.length; i++) { // Empezamos en 1 para saltar el encabezado
                let fila = filasEmpleados[i];
                // Si es la fila de "No hay usuarios registrados", la ignoramos o manejamos aparte
                if (fila.cells.length <= 1) continue; 

                let textoFila = fila.textContent || fila.innerText;
                if (textoFila.toLowerCase().indexOf(filtro) > -1) {
                    fila.style.display = "";
                } else {
                    fila.style.display = "none";
                }
            }

            // Filtrar Tabla de Camaristas
            let tablaCamaristas = document.getElementById('tablaCamaristas');
            let filasCamaristas = tablaCamaristas.getElementsByTagName('tr');

            for (let i = 1; i < filasCamaristas.length; i++) {
                let fila = filasCamaristas[i];
                if (fila.cells.length <= 1) continue;

                let textoFila = fila.textContent || fila.innerText;
                if (textoFila.toLowerCase().indexOf(filtro) > -1) {
                    fila.style.display = "";
                } else {
                    fila.style.display = "none";
                }
            }
        }
    </script>
</head>
<body>

    <h2>Lista de Empleados / Usuarios</h2>

    <a href="home.php">Volver al menú principal</a>
    <br><br>

    <a href="registrar.php"><button type="button">Agregar Empleado</button></a>
    &nbsp;&nbsp;
    <a href="registrar_camarista.php"><button type="button">Agregar Camarista</button></a>
    &nbsp;&nbsp;
    <a href="reportes/reporte_tabla_empleados.php" target="_blank"><button type="button">🖨️ Imprimir Reporte de Empleados</button></a>
    <br><br>

    <!-- Barra de búsqueda global -->
    <div class="buscador-container">
        <label for="buscadorGlobal"><strong>Buscar en ambas tablas:</strong></label><br>
        <input type="text" id="buscadorGlobal" onkeyup="filtrarTablas()" placeholder="Escribe para buscar por nombre, correo, teléfono, rol, etc...">
    </div>

    <?php if (isset($error_db)): ?>
        <p class="error"><?php echo htmlspecialchars($error_db); ?></p>
    <?php endif; ?>

    <table id="tablaEmpleados">
        <thead>
            <tr>
                <th>ID</th>
                <th>Foto</th>
                <th>Identificador</th>
                <th>Nombre Completo</th>
                <th>Teléfono</th>
                <th>Correo</th>
                <th>Rol</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($empleados)): ?>
                <?php foreach ($empleados as $emp): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($emp['id_usuario']); ?></td>
                        <td>
                            <?php if (!empty($emp['foto']) && file_exists($emp['foto'])): ?>
                                <img src="<?php echo htmlspecialchars($emp['foto']); ?>" alt="Foto" width="50" height="50">
                            <?php else: ?>
                                Sin foto
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($emp['identificador'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($emp['nombre_completo']); ?></td>
                        <td><?php echo htmlspecialchars($emp['telefono'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($emp['correo'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($emp['rol']); ?></td>
                        <td>
                            <a href="editar_empleado.php?id=<?php echo $emp['id_usuario']; ?>">
                                <button type="button">Editar</button>
                            </a>

                            <!-- Eliminación segura mediante POST -->
                            <form action="eliminar_empleado.php" method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este empleado?');">
                                <input type="hidden" name="id_usuario" value="<?php echo $emp['id_usuario']; ?>">
                                <button type="submit">Borrar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align: center;">No hay usuarios registrados.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <br><hr><br>

    <h2>Lista de Camaristas</h2>

    <?php if (isset($error_camaristas_db)): ?>
        <p class="error"><?php echo htmlspecialchars($error_camaristas_db); ?></p>
    <?php endif; ?>

    <table id="tablaCamaristas">
        <thead>
            <tr>
                <th>ID</th>
                <th>Foto</th>
                <th>Nombre Completo</th>
                <th>Teléfono</th>
                <th>Correo</th>
                <th>Usuario</th>
                <th>Estatus</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($camaristas)): ?>
                <?php foreach ($camaristas as $cam): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cam['id_camarista']); ?></td>
                        <td>
                            <?php if (!empty($cam['foto']) && file_exists($cam['foto'])): ?>
                                <img src="<?php echo htmlspecialchars($cam['foto']); ?>" alt="Foto" width="50" height="50">
                            <?php else: ?>
                                Sin foto
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($cam['nombre_completo']); ?></td>
                        <td><?php echo htmlspecialchars($cam['telefono'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($cam['correo'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($cam['usuario']); ?></td>
                        <td><?php echo htmlspecialchars($cam['estatus']); ?></td>
                        <td>
                            <a href="editar_camarista.php?id=<?php echo $cam['id_camarista']; ?>">
                                <button type="button">Editar</button>
                            </a>

                            <form action="eliminar_camarista.php" method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este camarista?');">
                                <input type="hidden" name="id_camarista" value="<?php echo $cam['id_camarista']; ?>">
                                <button type="submit">Borrar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align: center;">No hay camaristas registrados.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>