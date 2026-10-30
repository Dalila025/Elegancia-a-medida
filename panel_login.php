<?php
declare(strict_types=1);

/**
 * Ingreso al panel de la modista — Elegancia a Medida
 *
 * Valida email y contraseña contra la tabla usuarios.
 * Mismo bloqueo por intentos fallidos que los clientes.
 * Si la clave es provisoria, después del ingreso pide cambiarla.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/acceso_campos.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$volver = destino_seguro($_GET['volver'] ?? $_POST['volver'] ?? null, 'modista.php');

if (modista_logueada($pdo)) {
    header('Location: ' . $volver);
    exit;
}

$error = '';
$email = '';
$flash = tomar_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = isset($_POST['email']) && is_string($_POST['email']) ? mb_strtolower(trim($_POST['email'])) : '';
    $clave = isset($_POST['clave']) && is_string($_POST['clave']) ? $_POST['clave'] : '';

    if (!token_modista_valido()) {
        $error = 'El formulario expiró. Volvé a intentarlo.';
    } elseif ($email === '' || $clave === '') {
        $error = 'Completá el email y la contraseña.';
    } else {
        try {
            $r = verificar_credenciales($pdo, 'usuarios', $email, $clave);
            if ($r['ok']) {
                abrir_sesion_modista($r['id']);
                header('Location: ' . $volver);   // requerir_modista() pide cambiar la clave si es provisoria
                exit;
            }
            $error = $r['error'];
        } catch (PDOException $e) {
            error_log('Error en login del panel: ' . $e->getMessage());
            $error = 'No se pudo iniciar sesión. Intente nuevamente.';
        }
    }
}

$token = token_modista();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Ingreso al panel · Elegancia a Medida</title>
    <link rel="stylesheet" href="<?= asset('css/modista.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/panel.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/acceso.css') ?>">
</head>
<body class="pantalla-login acceso-fondo">
    <main class="acceso-pagina">
        <form method="post" action="panel_login.php" class="acceso-card" novalidate>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="volver" value="<?= e($volver) ?>">

            <p class="acceso-marca">Elegancia <em>a Medida</em></p>
            <h1 class="acceso-titulo">Panel de la modista</h1>
            <p class="acceso-sub">Solicitudes, taller, turnos, cobros y gastos.</p>

            <?php if ($flash): ?>
                <div class="acceso-aviso <?= $flash['tipo'] === 'ok' ? '' : 'error' ?>" role="status"><?= e($flash['mensaje']) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="acceso-aviso error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <?php campo_acceso('arroba', 'email', 'Email', 'text', $email,
                'inputmode="email" maxlength="' . EMAIL_MAX . '" required autocomplete="username" autofocus'); ?>
            <?php campo_acceso('candado', 'clave', 'Contraseña', 'password', '',
                'maxlength="' . CLAVE_MAX . '" required autocomplete="current-password"'); ?>

            <div class="acceso-botones">
                <button type="submit" class="acceso-btn principal">Ingresar</button>
                <a class="acceso-btn" href="index.php">Volver al sitio</a>
            </div>
        </form>
    </main>
    <?php script_acceso(); ?>
</body>
</html>
