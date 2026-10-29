<?php
declare(strict_types=1);

/**
 * Registrar un pago en la cuenta corriente de una clienta (panel) — Elegancia a Medida
 * Recibe POST de cliente.php: id_clientes, trabajo ("pedido-N" o "presupuesto-N"),
 * monto, metodo (efectivo|transferencia) y observaciones.
 * La parte que falte de la seña se guarda como seña y el resto como saldo
 * (registrar_cobro() en funciones_cuenta.php).
 * Con accion = devolver: registra que se le devolvió lo pagado de un trabajo
 * rechazado o cancelado (registrar_devolucion()). Tabla: pagos.
 */

require_once __DIR__ . '/funciones_cuenta.php';
iniciar_sesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método no permitido.');
}

require_once __DIR__ . '/conexion.php';
$usuario = requerir_modista($pdo);

$id_cli = filter_input(INPUT_POST, 'id_clientes', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$volver = 'cliente.php?id=' . $id_cli . '#cuenta';

function salir(string $url, string $tipo, string $mensaje): void
{
    flash($tipo, $mensaje);
    header('Location: ' . $url);
    exit;
}

if (!token_modista_valido()) {
    salir($volver, 'error', 'La sesión expiró. Recargue la página e intente de nuevo.');
}

// Devolución de lo pagado en un trabajo rechazado o cancelado
if (($_POST['accion'] ?? '') === 'devolver') {
    $t = isset($_POST['trabajo']) && is_string($_POST['trabajo']) ? $_POST['trabajo'] : '';
    if (!preg_match('/^(pedido|presupuesto)-(\d+)$/', $t, $d)) {
        salir($volver, 'error', 'Trabajo inválido.');
    }
    try {
        $es_suyo = (bool) array_filter(trabajos_cliente($pdo, $id_cli), fn($x) => $x['tipo'] === $d[1] && $x['id'] === (int) $d[2]);
        if (!$es_suyo) {
            salir($volver, 'error', 'Ese trabajo no es de esta clienta.');
        }
        [$ok, $mensaje] = registrar_devolucion($pdo, $d[1], (int) $d[2]);
        salir($volver, $ok ? 'ok' : 'error', $mensaje);
    } catch (PDOException $e) {
        error_log('Error al registrar devolución: ' . $e->getMessage());
        salir($volver, 'error', 'No se pudo registrar la devolución.');
    }
}

$trabajo = isset($_POST['trabajo']) && is_string($_POST['trabajo']) ? $_POST['trabajo'] : '';
$metodo  = in_array($_POST['metodo'] ?? '', ['efectivo', 'transferencia'], true) ? $_POST['metodo'] : '';
$obs     = isset($_POST['observaciones']) && is_string($_POST['observaciones']) ? trim(preg_replace('/\s+/', ' ', $_POST['observaciones'])) : '';
[$monto, $err] = leer_precio_opcional(isset($_POST['monto']) && is_string($_POST['monto']) ? $_POST['monto'] : '', 'monto');

if (!preg_match('/^(pedido|presupuesto)-(\d+)$/', $trabajo, $m)) {
    salir($volver, 'error', 'Elegí a qué trabajo corresponde el pago.');
}
if ($metodo === '') {
    salir($volver, 'error', 'Elegí cómo te pagó.');
}
if ($err || $monto === null) {
    salir($volver, 'error', $err ?? 'Indicá el monto que cobraste.');
}
if (mb_strlen($obs) > PAGO_OBSERVACIONES_MAX) {
    salir($volver, 'error', 'La nota puede tener hasta ' . PAGO_OBSERVACIONES_MAX . ' caracteres.');
}

try {
    // El trabajo tiene que ser de esta clienta
    $trabajos = trabajos_cliente($pdo, $id_cli);
    $es_suyo = (bool) array_filter($trabajos, fn($t) => $t['tipo'] === $m[1] && $t['id'] === (int) $m[2]);
    if (!$es_suyo) {
        salir($volver, 'error', 'Ese trabajo no es de esta clienta.');
    }
    [$ok, $mensaje] = registrar_cobro($pdo, $m[1], (int) $m[2], (float) $monto, $metodo, $obs);
    salir($volver, $ok ? 'ok' : 'error', $mensaje);
} catch (PDOException $e) {
    error_log('Error al registrar cobro: ' . $e->getMessage());
    salir($volver, 'error', 'No se pudo registrar el pago. Intente nuevamente.');
}
