<?php
/*
 * PLANTILLA DE CONEXIÓN - NO contiene credenciales reales.
 *
 * Instrucciones:
 *   1. Copiar este archivo como "conexion.php" (en la misma carpeta).
 *   2. Completar usuario y clave de su MySQL local.
 *   3. NO subir conexion.php al repositorio (está en .gitignore).
 */

$host    = 'localhost';
$base    = 'elegancia_a_medida';
$usuario = 'root';   // XAMPP por defecto
$clave   = '';       // XAMPP por defecto: vacía
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$base;charset=$charset";

$opciones = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $usuario, $clave, $opciones);
} catch (PDOException $e) {
    // El detalle va al log del servidor, no a la pantalla del usuario.
    error_log('Error de conexión a la base de datos: ' . $e->getMessage());
    die('No se pudo conectar con la base de datos.');
}
