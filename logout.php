<?php
declare(strict_types=1);

/**
 * Cerrar sesión del cliente — Elegancia a Medida
 *
 * Solo por POST y con token (un enlace de otra página no puede cerrar la
 * sesión). Borra solo los datos del cliente: si en el mismo navegador está
 * abierta la sesión de la modista, esa sigue.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && token_cliente_valido()) {
    unset($_SESSION['cliente_id'], $_SESSION['token_cliente'], $_SESSION['token_formulario']);
    session_regenerate_id(true);
    flash_cliente('ok', 'Cerraste sesión.');
}

header('Location: index.php');
exit;
