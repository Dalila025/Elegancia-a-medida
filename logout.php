<?php
declare(strict_types=1);

/**
 * Cerrar sesión — Elegancia a Medida
 *
 * Solo por POST y con el token del panel, para que un link de otro sitio
 * no pueda cerrar la sesión de la modista.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';

session_start();

require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !token_modista_valido()) {
    header('Location: modista.php');
    exit;
}

cerrar_sesion();

session_start();
$_SESSION['aviso_login'] = 'Cerraste la sesión correctamente.';

header('Location: login.php');
exit;
