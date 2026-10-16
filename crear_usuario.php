<?php
declare(strict_types=1);

/**
 * Alta de una usuaria del panel desde la terminal — Elegancia a Medida
 *
 * NO se abre desde el navegador: solo funciona por línea de comandos.
 *
 * Uso (desde la carpeta del proyecto, en Git Bash o CMD):
 *     C:/xampp/php/php.exe crear_usuario.php "Nombre Apellido" email@ejemplo.com
 *
 * Pide la contraseña sin mostrarla en pantalla (si la terminal lo permite),
 * la guarda con password_hash() y, si el email ya existe, le cambia la clave.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexion.php';

[$script, $nombre, $email] = array_pad($argv, 3, '');
$nombre = trim($nombre);
$email  = trim($email);

if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php crear_usuario.php \"Nombre Apellido\" email@ejemplo.com\n");
    exit(1);
}

/** Lee una línea sin eco cuando se puede (Linux/Mac/Git Bash). */
function leer_clave(string $mensaje): string
{
    echo $mensaje;
    $sinEco = DIRECTORY_SEPARATOR === '/' && @shell_exec('stty -echo 2>/dev/null') !== null;
    $clave = rtrim((string) fgets(STDIN), "\r\n");
    if ($sinEco) {
        shell_exec('stty echo 2>/dev/null');
        echo PHP_EOL;
    }
    return $clave;
}

$clave  = leer_clave('Contraseña (mínimo 8 caracteres): ');
$repite = leer_clave('Repetir contraseña: ');

if (mb_strlen($clave) < 8) {
    fwrite(STDERR, "La contraseña debe tener al menos 8 caracteres.\n");
    exit(1);
}
if ($clave !== $repite) {
    fwrite(STDERR, "Las contraseñas no coinciden.\n");
    exit(1);
}

$hash = password_hash($clave, PASSWORD_DEFAULT);

$existe = $pdo->prepare('SELECT id_usuarios FROM usuarios WHERE email = :email');
$existe->execute([':email' => $email]);
$id = $existe->fetchColumn();

if ($id) {
    $pdo->prepare('UPDATE usuarios SET nombre = :n, clave_hash = :h WHERE id_usuarios = :id')
        ->execute([':n' => $nombre, ':h' => $hash, ':id' => $id]);
    echo "Usuaria #$id actualizada: $email\n";
} else {
    $pdo->prepare("INSERT INTO usuarios (nombre, email, clave_hash, rol) VALUES (:n, :e, :h, 'modista')")
        ->execute([':n' => $nombre, ':e' => $email, ':h' => $hash]);
    echo 'Usuaria creada (#' . $pdo->lastInsertId() . "): $email\n";
}
