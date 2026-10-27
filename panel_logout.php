<?php
declare(strict_types=1);

/**
 * Cierre de sesión del panel — Elegancia a Medida
 * Solo por POST con el token del panel (un enlace externo no puede cerrar la sesión).
 * Si en el mismo navegador hay un cliente con sesión, esa sesión sigue abierta.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && token_modista_valido()) {
    unset($_SESSION['usuario_id'], $_SESSION['token_modista']);
    session_regenerate_id(true);
    flash('ok', 'Cerraste la sesión del panel.');
}

header('Location: panel_login.php');
exit;
