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
    <link rel="stylesheet" href="css/cliente.css">
    <link rel="stylesheet" href="css/sitio.css">
</head>
<body>
<?php require __DIR__ . '/encabezado_cliente.php'; ?>
<main class="pagina">
    <div class="container angosta">
        <h1>Iniciar sesión</h1>
        <p class="subtitle">Entrá a tu cuenta para pedir presupuestos y ver las respuestas.</p>

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" novalidate>
            <input type="hidden" name="token" value="<?= e(token_cliente()) ?>">
            <input type="hidden" name="volver" value="<?= e($volver) ?>">

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="<?= EMAIL_MAX ?>" required
                       autocomplete="email" value="<?= e($email) ?>" autofocus>
            </div>
            <div class="form-group">
                <label for="clave">Contraseña</label>
                <input type="password" id="clave" name="clave" maxlength="<?= CLAVE_MAX ?>" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn">Iniciar sesión</button>
        </form>

        <p class="pie-form">¿No tenés cuenta? <a href="registro.php?volver=<?= urlencode($volver) ?>">Registrate</a></p>
    </div>
</main>
<?php require __DIR__ . '/pie_cliente.php'; ?>
<script src="js/sitio.js"></script>
</body>
</html>
