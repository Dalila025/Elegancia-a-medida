<?php
declare(strict_types=1);

/**
 * Ingreso de la modista — Elegancia a Medida
 *
 * GET : muestra el formulario.
 * POST: valida token, email y contraseña contra la tabla usuarios
 *       (password_verify) y abre la sesión.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';

session_start();

require_once __DIR__ . '/auth.php';

// Si ya está adentro, va directo al panel.
if (usuario_actual()) {
    header('Location: modista.php');
    exit;
}

$volver = isset($_REQUEST['volver']) && is_string($_REQUEST['volver']) ? destino_seguro($_REQUEST['volver']) : 'modista.php';
$email  = '';
$error  = '';
$aviso  = $_SESSION['aviso_login'] ?? '';
unset($_SESSION['aviso_login']);

if (empty($_SESSION['token_login'])) {
    $_SESSION['token_login'] = bin2hex(random_bytes(32));
}


// ---------- PROCESAR EL INGRESO ----------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';
    $clave_ingresada = isset($_POST['clave']) && is_string($_POST['clave']) ? $_POST['clave'] : '';
    $token = isset($_POST['token']) && is_string($_POST['token']) ? $_POST['token'] : '';

    $bloqueo = (int) ($_SESSION['login_bloqueo_hasta'] ?? 0);

    if ($token === '' || !hash_equals($_SESSION['token_login'], $token)) {

        $error = 'El formulario venció. Intentá de nuevo.';

    } elseif ($bloqueo > time()) {

        $error = 'Demasiados intentos. Esperá ' . ($bloqueo - time()) . ' segundos y volvé a intentar.';

    } elseif ($email === '' || $clave_ingresada === '') {

        $error = 'Completá el email y la contraseña.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'El email no tiene un formato válido.';

    } else {

        require_once __DIR__ . '/conexion.php';

        // conexion.php define $usuario y $clave (credenciales de MySQL): por eso la
        // contraseña ingresada se guarda en $clave_ingresada y no se pisa.
        try {
            $usuario = verificar_credenciales($pdo, $email, $clave_ingresada);
        } catch (PDOException $e) {
            error_log('Error en login: ' . $e->getMessage());
            $usuario = null;
            $error = 'No se pudo verificar el ingreso. Intentá más tarde.';
        }

        if ($usuario) {
            unset($_SESSION['token_login']);
            iniciar_sesion_usuario($usuario);
            flash('ok', '¡Hola, ' . $usuario['nombre'] . '! Ingresaste al panel.');
            header('Location: ' . $volver);
            exit;
        }

        if ($error === '') {
            // Mensaje único: no se revela si el email existe.
            $_SESSION['login_intentos'] = ($_SESSION['login_intentos'] ?? 0) + 1;
            if ($_SESSION['login_intentos'] >= LOGIN_MAX_INTENTOS) {
                $_SESSION['login_bloqueo_hasta'] = time() + LOGIN_ESPERA_SEG;
                $_SESSION['login_intentos'] = 0;
                $error = 'Demasiados intentos. Esperá ' . LOGIN_ESPERA_SEG . ' segundos y volvé a intentar.';
            } else {
                $error = 'Email o contraseña incorrectos.';
            }
            usleep(400000); // frena intentos automáticos
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Ingresar · Panel de la modista · Elegancia a Medida</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <main class="login">
        <section class="login-marca" aria-hidden="true">
            <div class="marca-contenido">
                <span class="marca-logo">E</span>
                <p class="marca-nombre">Elegancia a Medida</p>
                <p class="marca-frase">Cada prenda, a la medida de quien la lleva.</p>
            </div>
            <svg class="marca-hilo" viewBox="0 0 400 120" preserveAspectRatio="none">
                <path d="M0 80 C 60 20, 120 120, 200 60 S 340 10, 400 50"/>
            </svg>
        </section>

        <section class="login-panel">
            <form method="post" action="login.php" class="login-form" novalidate>
                <p class="sobretitulo">Panel de la modista</p>
                <h1>Ingresar</h1>
                <p class="bajada">Usá tu email y contraseña para ver y gestionar las solicitudes.</p>

                <?php if ($aviso !== ''): ?>
                    <div class="mensaje mensaje-aviso" role="status"><?= e($aviso) ?></div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                    <div class="mensaje mensaje-error" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <input type="hidden" name="token" value="<?= e($_SESSION['token_login']) ?>">
                <input type="hidden" name="volver" value="<?= e($volver) ?>">

                <div class="campo">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= e($email) ?>"
                           autocomplete="username" required <?= $email === '' ? 'autofocus' : '' ?>
                           placeholder="modista@ejemplo.com">
                </div>

                <div class="campo">
                    <label for="clave">Contraseña</label>
                    <div class="campo-clave">
                        <input type="password" id="clave" name="clave"
                               autocomplete="current-password" required <?= $email !== '' ? 'autofocus' : '' ?>>
                        <button type="button" class="ver-clave" aria-label="Mostrar contraseña" aria-pressed="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <p class="aviso-mayus" hidden>Las mayúsculas están activadas.</p>
                </div>

                <button type="submit" class="btn-ingresar">Ingresar</button>

                <a href="index.php" class="volver-sitio">← Ir al formulario de clientes</a>
            </form>
        </section>
    </main>

    <script>
        (function () {
            var clave = document.getElementById('clave');
            var ver = document.querySelector('.ver-clave');
            var mayus = document.querySelector('.aviso-mayus');
            var form = document.querySelector('.login-form');

            // Mostrar / ocultar contraseña
            ver.addEventListener('click', function () {
                var mostrar = clave.type === 'password';
                clave.type = mostrar ? 'text' : 'password';
                ver.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
                ver.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
                clave.focus();
            });

            // Aviso de Bloq Mayús
            ['keydown', 'keyup'].forEach(function (ev) {
                clave.addEventListener(ev, function (e) {
                    if (e.getModifierState) mayus.hidden = !e.getModifierState('CapsLock');
                });
            });

            // Estado "ingresando…" (evita doble envío)
            form.addEventListener('submit', function () {
                var b = form.querySelector('.btn-ingresar');
                setTimeout(function () { b.disabled = true; b.classList.add('cargando'); }, 0);
            });
        })();
    </script>
</body>
</html>
