<?php
declare(strict_types=1);

/**
 * Acciones del carrito — Elegancia a Medida
 *
 * Recibe POST con accion =
 *   agregar   id_productos, modalidad (venta|alquiler), cantidad (opcional),
 *             talle (obligatorio si la prenda tiene guía de talles; «A medida» solo en venta)
 *   sumar     id (items_carrito) → +1
 *   restar    id → −1 (nunca baja de 1)
 *   quitar    id
 *   deshacer  id → saca lo último agregado; solo durante CARRITO_DESHACER_MIN minutos
 * Hace falta iniciar sesión: sin sesión, manda al login y vuelve a la prenda.
 * Tablas: items_carrito (y talles_producto / medidas_clientes para validar el talle).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método no permitido.');
}

require_once __DIR__ . '/conexion.php';

$campo  = fn(string $n): string => isset($_POST[$n]) && is_string($_POST[$n]) ? trim($_POST[$n]) : '';
$accion = $campo('accion');
$volver = destino_seguro($campo('volver'), 'carrito.php');

function volver_a(string $url, string $tipo = '', string $mensaje = ''): void
{
    if ($mensaje !== '') {
        flash_cliente($tipo, $mensaje);
    }
    header('Location: ' . $url);
    exit;
}

if (!token_cliente_valido()) {
    volver_a($volver, 'error', 'El formulario expiró. Probá de nuevo.');
}

$cliente = cliente_logueado($pdo);
if ($cliente === null) {
    // Vuelve a la ficha de la prenda después de iniciar sesión.
    $id_prod = filter_input(INPUT_POST, 'id_productos', FILTER_VALIDATE_INT) ?: 0;
    $despues = $id_prod ? 'prenda.php?id=' . $id_prod : $volver;
    volver_a('login.php?volver=' . urlencode($despues), 'info', 'Iniciá sesión o registrate para agregar prendas al carrito.');
}
$idc = $cliente['id_clientes'];

try {

    // ---------- AGREGAR ----------
    if ($accion === 'agregar') {
        $id_prod   = filter_input(INPUT_POST, 'id_productos', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
        $modalidad = $campo('modalidad');
        $cantidad  = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => CARRITO_CANT_MAX]]) ?: 1;

        if (!in_array($modalidad, ['venta', 'alquiler'], true)) {
            volver_a($volver, 'error', 'Elegí si la querés comprar o alquilar.');
        }

        // La prenda tiene que estar visible y tener esa modalidad.
        $col = $modalidad === 'alquiler' ? 'en_alquiler' : 'en_venta';
        $stmt = $pdo->prepare("SELECT p.nombre FROM productos p
                                 INNER JOIN categorias c ON c.id_categorias = p.id_categorias AND c.activo = 1
                                 INNER JOIN tipos_producto t ON t.id_tipos_producto = p.id_tipos_producto AND t.activo = 1
                                WHERE p.id_productos = :id AND p.activo = 1 AND p.$col = 1");
        $stmt->execute([':id' => $id_prod]);
        $nombre = $stmt->fetchColumn();
        if ($nombre === false) {
            volver_a($volver, 'error', 'Esa prenda no está disponible para ' . ($modalidad === 'alquiler' ? 'alquiler' : 'venta') . '.');
        }

        // Talle: obligatorio si la prenda tiene guía de talles.
        [$talle, $err] = validar_talle_elegido($pdo, $id_prod, $modalidad, $campo('talle'), (int) $idc);
        if ($err) {
            volver_a('prenda.php?id=' . $id_prod . '#elegir-talle', 'error', $err);
        }

        // Si ya estaba (misma prenda, modalidad y talle), se suma sin pasar el máximo.
        // La fecha se renueva para el aviso "Deshacer".
        $stmt = $pdo->prepare('SELECT cantidad FROM items_carrito WHERE id_clientes = :c AND id_productos = :p AND modalidad = :m AND talle = :t');
        $stmt->execute([':c' => $idc, ':p' => $id_prod, ':m' => $modalidad, ':t' => $talle]);
        $ya = $stmt->fetchColumn();
        if ($ya !== false && (int) $ya >= CARRITO_CANT_MAX) {
            volver_a($volver, 'error', 'Ya tenés el máximo de ' . CARRITO_CANT_MAX . ' unidades de «' . $nombre . '» en el carrito.');
        }

        $pdo->prepare('INSERT INTO items_carrito (id_clientes, id_productos, modalidad, talle, cantidad, fecha_agregado)
                       VALUES (:c, :p, :m, :t, :n, NOW())
                       ON DUPLICATE KEY UPDATE cantidad = LEAST(cantidad + VALUES(cantidad), ' . CARRITO_CANT_MAX . '),
                                               fecha_agregado = NOW()')
            ->execute([':c' => $idc, ':p' => $id_prod, ':m' => $modalidad, ':t' => $talle, ':n' => $cantidad]);

        // Para "Deshacer": cuánto se agregó en este clic.
        $_SESSION['ultimo_agregado'] = ['clave' => "$id_prod-$modalidad-$talle", 'cantidad' => $cantidad];
        volver_a($volver);
    }

    // ---------- Acciones sobre una fila del carrito ----------
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $stmt = $pdo->prepare('SELECT c.*, p.nombre,
                                  (c.fecha_agregado > NOW() - INTERVAL ' . CARRITO_DESHACER_MIN . ' MINUTE) AS deshacible
                             FROM items_carrito c INNER JOIN productos p ON p.id_productos = c.id_productos
                            WHERE c.id_items_carrito = :id AND c.id_clientes = :c');
    $stmt->execute([':id' => $id, ':c' => $idc]);
    $item = $stmt->fetch();
    if (!$item) {
        volver_a($volver, 'error', 'Esa prenda ya no está en tu carrito.');
    }

    $borrar = fn() => $pdo->prepare('DELETE FROM items_carrito WHERE id_items_carrito = :id')->execute([':id' => $id]);
    $poner  = fn(int $n) => $pdo->prepare('UPDATE items_carrito SET cantidad = :n WHERE id_items_carrito = :id')->execute([':n' => $n, ':id' => $id]);

    switch ($accion) {
        case 'sumar':
            $poner(min((int) $item['cantidad'] + 1, CARRITO_CANT_MAX));
            volver_a($volver);

        case 'restar':
            // No baja de 1: para sacar la prenda está el botón «Quitar».
            $poner(max((int) $item['cantidad'] - 1, 1));
            volver_a($volver);

        case 'quitar':
            $borrar();
            volver_a($volver, 'ok', 'Sacaste «' . $item['nombre'] . '» del carrito.');

        case 'deshacer':
            if ((int) $item['deshacible'] !== 1) {
                volver_a('carrito.php', 'info', 'Ya pasaron ' . CARRITO_DESHACER_MIN . ' minutos: podés sacarla desde el carrito.');
            }
            // Se resta solo lo que se agregó en el último clic.
            $ultimo = $_SESSION['ultimo_agregado'] ?? null;
            $resta  = ($ultimo && $ultimo['clave'] === $item['id_productos'] . '-' . $item['modalidad'] . '-' . $item['talle'])
                ? (int) $ultimo['cantidad'] : (int) $item['cantidad'];
            $quedan = (int) $item['cantidad'] - $resta;
            if ($quedan > 0) {
                // Queda en el carrito, pero ya no como "recién agregado".
                $pdo->prepare('UPDATE items_carrito SET cantidad = :n, fecha_agregado = NOW() - INTERVAL 1 DAY WHERE id_items_carrito = :id')
                    ->execute([':n' => $quedan, ':id' => $id]);
            } else {
                $borrar();
            }
            unset($_SESSION['ultimo_agregado']);
            volver_a($volver, 'ok', 'Listo: deshiciste el agregado de «' . $item['nombre'] . '».');
    }

    volver_a($volver, 'error', 'Acción inválida.');

} catch (PDOException $e) {
    error_log('Error en el carrito: ' . $e->getMessage());
    volver_a($volver, 'error', 'No se pudo actualizar el carrito. Probá de nuevo.');
}
