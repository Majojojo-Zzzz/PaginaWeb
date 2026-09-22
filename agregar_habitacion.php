<?php
session_start();

// Validar autenticación
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}

$rol_usuario = isset($_SESSION['rol']) ? strtolower(trim($_SESSION['rol'])) : '';
$es_admin_o_gobernante = in_array($rol_usuario, ['administrador', 'gobernante', 'admin']);

require_once 'config/conexion.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    
    // Validar permisos para agregar
    if ($_POST['accion'] === 'agregar' && !$es_admin_o_gobernante) {
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para realizar esta acción.']);
        exit;
    }

    // Acción para sugerir el siguiente número de habitación
    if ($_POST['accion'] === 'siguiente_numero') {
        try {
            $stmt = $pdo->query("SELECT numero_habitacion FROM habitaciones");
            $habitaciones_db = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $siguiente = 101; 
            if (!empty($habitaciones_db)) {
                $numeros_enteros = [];
                foreach ($habitaciones_db as $num) {
                    if (is_numeric($num)) {
                        $numeros_enteros[] = (int)$num;
                    }
                }
                if (!empty($numeros_enteros)) {
                    $siguiente = max($numeros_enteros) + 1;
                }
            }
            echo json_encode(['success' => true, 'siguiente' => $siguiente]);
        } catch (Exception $e) {
            echo json_encode(['success' => true, 'siguiente' => '101']);
        }
        exit;
    }

    // Acción para guardar la nueva habitación
    if ($_POST['accion'] === 'agregar') {
        try {
            $numero = trim($_POST['numero_habitacion']);
            $tipo = $_POST['tipo_habitacion'];
            $precio = $_POST['precio'];
            $cantidad = $_POST['cantidad_personas'];

            // Verificar si ya existe
            $stmt_verificar = $pdo->prepare("SELECT id_habitacion FROM habitaciones WHERE numero_habitacion = ?");
            $stmt_verificar->execute([$numero]);
            if ($stmt_verificar->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'El número de habitación ' . $numero . ' ya existe. Por favor, elige otro.']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO habitaciones (numero_habitacion, tipo_habitacion, precio, cantidad_personas, estatus) VALUES (?, ?, ?, ?, 'Disponible')");
            $stmt->execute([$numero, $tipo, $precio, $cantidad]);

            echo json_encode(['success' => true, 'message' => 'La habitación se agregó con éxito']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Petición inválida.']);