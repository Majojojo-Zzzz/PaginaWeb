<?php
// Iniciar la sesión global del sistema
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$db   = 'datasys';
$user = 'root'; // Cambia si usas otro usuario en tu servidor local
$pass = '';     // Cambia si tu servidor local tiene contraseña
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // Desactivar emulación protege contra Inyección SQL
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // En producción es mejor guardar el error en un log, por ahora lo mostramos de forma limpia
     die("Error crítico de conexión: " . $e->getMessage());
}
?>