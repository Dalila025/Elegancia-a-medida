<?php
declare(strict_types=1);

/**
 * Registro de clientes — Elegancia a Medida
 *
 * Crea la cuenta (tabla clientes) y deja la sesión iniciada.
 * Solo los clientes con cuenta pueden pedir presupuestos.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/acceso_campos.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$volver = destino_seguro($_GET['volver'] ?? $_POST['volver'] ?? null, 'solicitud.php');

// Si ya tiene sesión, no tiene sentido registrarse de nuevo.
if (cliente_logueado($pdo)) {
    header('Location: ' . $volver);
    exit;
}

$errores = [];
$datos   = ['nombre' => '', 'telefono' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $campo = fn(string $n): string => isset($_POST[$n]) && is_string($_POST[$n]) ? trim($_POST[$n]) : '';

    $datos = [
        'nombre'   => $campo('nombre'),
        'telefono' => $campo('telefono'),
        'email'    => mb_strtolower($campo('email')),
    ];
    // La clave no se recorta: los espacios forman parte de ella.
    $clave  = isset($_POST['clave'])  && is_string($_POST['clave'])  ? $_POST['clave']  : '';
    $clave2 = isset($_POST['clave2']) && is_string($_POST['clave2']) ? $_POST['clave2'] : '';

    if (!token_cliente_valido()) {
        $errores[] = 'El formulario expiró. Volvé a intentarlo.';
    } else {
        $errores = array_merge(validar_nombre($datos['nombre']), validar_telefono($datos['telefono']));

        if ($datos['email'] === '') {
            $errores[] = 'El email es obligatorio.';
        } elseif (mb_strlen($datos['email']) > EMAIL_MAX || !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El email no tiene un formato válido.';
        }

        if (strlen($clave) < CLAVE_MIN) {
            $errores[] = 'La contraseña debe tener al menos ' . CLAVE_MIN . ' caracteres.';
        } elseif (strlen($clave) > CLAVE_MAX) {
            $errores[] = 'La contraseña no puede superar los ' . CLAVE_MAX . ' caracteres.';
        } elseif (!preg_match('/[A-Za-z]/', $clave) || !preg_match('/\d/', $clave)) {
            $errores[] = 'La contraseña debe tener al menos una letra y un número.';
        } elseif ($clave !== $clave2) {
            $errores[] = 'Las contraseñas no coinciden.';
        }
    }

    if (!$errores) {
        try {
            $stmt = $pdo->prepare('SELECT 1 FROM clientes WHERE email = :email');
            $stmt->execute([':email' => $datos['email']]);

            if ($stmt->fetchColumn()) {
                $errores[] = 'Ya existe una cuenta con ese email. Iniciá sesión.';
            } else {
                $pdo->prepare(
                    'INSERT INTO clientes (nombre, telefono, email, clave_hash, ultimo_acceso)
                     VALUES (:nombre, :telefono, :email, :hash, NOW())'
                )->execute([
                    ':nombre'   => $datos['nombre'],
                    ':telefono' => $datos['telefono'],
                    ':email'    => $datos['email'],
                    ':hash'     => password_hash($clave, PASSWORD_DEFAULT),
                ]);

                abrir_sesion_cliente((int) $pdo->lastInsertId());
                flash_cliente('ok', '¡Cuenta creada! Ya podés pedir tu presupuesto.');
                header('Location: ' . $volver);
                exit;
            }
        } catch (PDOException $e) {
            // 1062: dos registros simultáneos con el mismo email.
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                $errores[] = 'Ya existe una cuenta con ese email. Iniciá sesión.';
            } else {
                error_log('Error al registrar cliente: ' . $e->getMessage());
                $errores[] = 'No se pudo crear la cuenta. Intentá nuevamente más tarde.';
            }
        }
    }
}

$cliente = null;
$pagina_actual = 'registro';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrarse - Elegancia a Medida</title>
    <link rel="stylesheet" href="<?= asset('css/cliente.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/sitio.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/acceso.css') ?>">
</head>
<body>
<?php require __DIR__ . '/encabezado_cliente.php'; ?>
<main class="acceso-pagina">
    <form method="post" action="registro.php" class="acceso-card ancha" novalidate>
        <input type="hidden" name="token" value="<?= e(token_cliente()) ?>">
        <input type="hidden" name="volver" value="<?= e($volver) ?>">

        <p class="acceso-marca">Elegancia <em>a Medida</em></p>
        <h1 class="acceso-titulo">Crear cuenta</h1>
        <p class="acceso-sub">Con tu cuenta pedís presupuestos, elegís turnos y seguís tus trabajos.</p>

        <?php if ($errores): ?>
            <div class="acceso-aviso error" role="alert">
                <?php if (count($errores) === 1): ?>
                    <?= e($errores[0]) ?>
                <?php else: ?>
                    <strong>Revisá estos datos:</strong>
                    <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php campo_acceso('persona', 'nombre', 'Nombre completo', 'text', $datos['nombre'],
            'maxlength="' . NOMBRE_MAX . '" required autocomplete="name"'); ?>
        <?php campo_acceso('telefono', 'telefono', 'Teléfono (la modista te contacta acá)', 'tel', $datos['telefono'],
            'maxlength="' . TELEFONO_MAX . '" required autocomplete="tel"'); ?>
        <?php campo_acceso('arroba', 'email', 'Email', 'email', $datos['email'],
            'maxlength="' . EMAIL_MAX . '" required autocomplete="email"'); ?>
        <?php campo_acceso('candado', 'clave', 'Contraseña', 'password', '',
            'minlength="' . CLAVE_MIN . '" maxlength="' . CLAVE_MAX . '" required autocomplete="new-password" aria-describedby="ayuda-clave"'); ?>
        <p class="acceso-ayuda" id="ayuda-clave">Mínimo <?= CLAVE_MIN ?> caracteres, con al menos una letra y un número.</p>
        <?php campo_acceso('candado', 'clave2', 'Repetir contraseña', 'password', '',
            'maxlength="' . CLAVE_MAX . '" required autocomplete="new-password"'); ?>

        <div class="acceso-botones">
            <button type="submit" class="acceso-btn principal">Crear cuenta</button>
            <a class="acceso-btn" href="login.php?volver=<?= e(urlencode($volver)) ?>">Ya tengo cuenta</a>
        </div>
    </form>
</main>
<?php require __DIR__ . '/pie_cliente.php'; ?>
<?php script_acceso(); ?>
<script src="<?= asset('js/sitio.js') ?>"></script>
</body>
</html>
