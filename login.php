<?php
declare(strict_types=1);

/**
 * Inicio de sesión de clientes — Elegancia a Medida
 *
 * Valida email y contraseña contra la tabla clientes.
 * Tras LOGIN_MAX_INTENTOS fallos seguidos, la cuenta se bloquea
 * LOGIN_BLOQUEO_MINUTOS minutos (ver verificar_login() en funciones.php).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$volver = destino_seguro($_GET['volver'] ?? $_POST['volver'] ?? null);

if (cliente_logueado($pdo)) {
    header('Location: ' . $volver);
    exit;
}

$error = '';
$email = '';
$flash = tomar_flash_cliente();

// «¿Olvidaste tu contraseña?»: por ahora la blanquea la modista (WhatsApp o email)
require_once __DIR__ . '/acceso_campos.php';
$sitio_acc = datos_sitio($pdo);
$olvido = enlace_whatsapp($sitio_acc['whatsapp'] ?? '', 'Hola! Olvidé mi contraseña de la web de Elegancia a Medida. Mi email es: ')
       ?? (!empty($sitio_acc['email']) ? 'mailto:' . $sitio_acc['email'] . '?subject=' . rawurlencode('Olvidé mi contraseña') : null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = isset($_POST['email']) && is_string($_POST['email']) ? mb_strtolower(trim($_POST['email'])) : '';
    $clave = isset($_POST['clave']) && is_string($_POST['clave']) ? $_POST['clave'] : '';

    if (!token_cliente_valido()) {
        $error = 'El formulario expiró. Volvé a intentarlo.';
    } elseif ($email === '' || $clave === '') {
        $error = 'Completá el email y la contraseña.';
    } else {
        try {
            $r = verificar_login($pdo, $email, $clave);
            if ($r['ok']) {
                abrir_sesion_cliente($r['id']);
                header('Location: ' . $volver);
                exit;
            }
            $error = $r['error'];
        } catch (PDOException $e) {
            error_log('Error en login de cliente: ' . $e->getMessage());
            $error = 'No se pudo iniciar sesión. Intentá nuevamente más tarde.';
        }
    }
}

$cliente = null;
$pagina_actual = 'login';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión - Elegancia a Medida</title>
    <link rel="stylesheet" href="<?= asset('css/cliente.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/sitio.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/acceso.css') ?>">
</head>
<body>
<?php require __DIR__ . '/encabezado_cliente.php'; ?>
<main class="acceso-pagina">
    <form method="post" action="login.php" class="acceso-card" novalidate>
        <input type="hidden" name="token" value="<?= e(token_cliente()) ?>">
        <input type="hidden" name="volver" value="<?= e($volver) ?>">

        <p class="acceso-marca">Elegancia <em>a Medida</em></p>
        <h1 class="acceso-titulo">Ingresar</h1>
        <p class="acceso-sub">Entrá a tu cuenta para pedir presupuestos y seguir tus trabajos.</p>

        <?php if ($flash): ?>
            <div class="acceso-aviso <?= $flash['tipo'] === 'error' ? 'error' : '' ?>" role="status"><?= e($flash['mensaje']) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="acceso-aviso error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php campo_acceso('arroba', 'email', 'Email', 'email', $email,
            'maxlength="' . EMAIL_MAX . '" required autocomplete="email" autofocus'); ?>
        <?php campo_acceso('candado', 'clave', 'Contraseña', 'password', '',
            'maxlength="' . CLAVE_MAX . '" required autocomplete="current-password"'); ?>

        <div class="acceso-botones">
            <button type="submit" class="acceso-btn principal">Ingresar</button>
            <a class="acceso-btn" href="registro.php?volver=<?= e(urlencode($volver)) ?>">Crear cuenta</a>
        </div>
        <?php if ($olvido): ?>
            <a class="acceso-btn olvido" href="<?= e($olvido) ?>" target="_blank" rel="noopener">¿Olvidaste tu contraseña?</a>
        <?php endif; ?>
    </form>
</main>
<?php require __DIR__ . '/pie_cliente.php'; ?>
<?php script_acceso(); ?>
<script src="<?= asset('js/sitio.js') ?>"></script>
</body>
</html>
