<?php
declare(strict_types=1);

/**
 * Cambio de contraseña de la modista — Elegancia a Medida
 *
 * Obligatorio en el primer ingreso (usuarios.debe_cambiar_clave = 1);
 * después se puede usar en cualquier momento desde el menú del panel.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$usuario     = requerir_modista($pdo);
$obligatorio = (int) $usuario['debe_cambiar_clave'] === 1;
$errores     = [];
$flash       = tomar_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $actual  = isset($_POST['actual'])  && is_string($_POST['actual'])  ? $_POST['actual']  : '';
    $nueva   = isset($_POST['nueva'])   && is_string($_POST['nueva'])   ? $_POST['nueva']   : '';
    $nueva2  = isset($_POST['nueva2'])  && is_string($_POST['nueva2'])  ? $_POST['nueva2']  : '';

    if (!token_modista_valido()) {
        $errores[] = 'El formulario expiró. Volvé a intentarlo.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT clave_hash FROM usuarios WHERE id_usuarios = :id');
            $stmt->execute([':id' => $usuario['id_usuarios']]);
            $hash = (string) $stmt->fetchColumn();

            if (!password_verify($actual, $hash)) {
                $errores[] = 'La contraseña actual no es correcta.';
            } else {
                $errores = validar_clave_nueva($nueva, $nueva2);
                if (!$errores && password_verify($nueva, $hash)) {
                    $errores[] = 'La contraseña nueva tiene que ser distinta de la actual.';
                }
            }

            if (!$errores) {
                $pdo->prepare('UPDATE usuarios SET clave_hash = :h, debe_cambiar_clave = 0 WHERE id_usuarios = :id')
                    ->execute([':h' => password_hash($nueva, PASSWORD_DEFAULT), ':id' => $usuario['id_usuarios']]);
                session_regenerate_id(true);
                flash('ok', 'Contraseña actualizada.');
                header('Location: modista.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Error al cambiar clave del panel: ' . $e->getMessage());
            $errores[] = 'No se pudo cambiar la contraseña. Intente nuevamente.';
        }
    }
}

$token = token_modista();
$pagina_actual = 'clave';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Cambiar contraseña · Elegancia a Medida</title>
    <link rel="stylesheet" href="<?= asset('css/modista.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/panel.css') ?>">
</head>
<body>
    <?php require __DIR__ . '/encabezado_modista.php'; ?>

    <main class="container">
        <section class="tarjeta" style="max-width:520px">
            <h2>Cambiar contraseña</h2>

            <?php if ($obligatorio): ?>
                <div class="aviso">Estás usando una contraseña provisoria. Elegí una nueva para empezar a usar el panel.</div>
            <?php endif; ?>
            <?php if ($flash && !$obligatorio): ?>
                <div class="flash <?= $flash['tipo'] === 'ok' ? 'flash-ok' : 'flash-error' ?>"><?= e($flash['mensaje']) ?></div>
            <?php endif; ?>
            <?php if ($errores): ?>
                <div class="flash flash-error" role="alert"><ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>

            <form method="post" action="panel_clave.php" class="form-panel" novalidate>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="campo">
                    <label for="actual">Contraseña actual</label>
                    <input type="password" id="actual" name="actual" required maxlength="<?= CLAVE_MAX ?>" autocomplete="current-password">
                </div>
                <div class="campo">
                    <label for="nueva">Contraseña nueva</label>
                    <input type="password" id="nueva" name="nueva" required minlength="<?= CLAVE_MIN ?>" maxlength="<?= CLAVE_MAX ?>" autocomplete="new-password">
                    <div class="ayuda">Mínimo <?= CLAVE_MIN ?> caracteres, con letras y números.</div>
                </div>
                <div class="campo">
                    <label for="nueva2">Repetir contraseña nueva</label>
                    <input type="password" id="nueva2" name="nueva2" required maxlength="<?= CLAVE_MAX ?>" autocomplete="new-password">
                </div>
                <div class="botonera">
                    <button type="submit" class="btn-guardar">Guardar contraseña</button>
                    <?php if (!$obligatorio): ?><a href="modista.php" class="btn-secundario">Cancelar</a><?php endif; ?>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
