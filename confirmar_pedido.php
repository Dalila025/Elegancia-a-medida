<?php
declare(strict_types=1);

/**
 * Confirmar pedido — Elegancia a Medida
 *
 * Recibe POST de carrito.php. Vuelve a leer el carrito y los precios
 * (no confía en lo que muestra la página), valida las fechas del alquiler,
 * crea el pedido con sus prendas y vacía el carrito, todo junto
 * (transacción). Después lleva a mis_pedidos.php.
 *
 * Tablas: items_carrito, productos, pedidos, items_pedido.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método no permitido.');
}

require_once __DIR__ . '/conexion.php';

$cliente = cliente_logueado($pdo);
if ($cliente === null) {
    flash_cliente('info', 'Tu sesión terminó. Iniciá sesión de nuevo para confirmar el pedido.');
    header('Location: login.php?volver=carrito.php');
    exit;
}

$campo = fn(string $n): string => isset($_POST[$n]) && is_string($_POST[$n]) ? trim($_POST[$n]) : '';
$datos = [
    'fecha_retiro'     => $campo('fecha_retiro'),
    'fecha_devolucion' => $campo('fecha_devolucion'),
    'comentario'       => $campo('comentario'),
];

function volver_carrito(array $errores, array $datos): void
{
    $_SESSION['form_pedido'] = ['errores' => $errores, 'datos' => $datos];
    header('Location: carrito.php');
    exit;
}

if (!token_cliente_valido()) {
    volver_carrito(['El formulario expiró. Probá de nuevo.'], $datos);
}

try {
    $pdo->beginTransaction();

    // Carrito con los datos de hoy de cada prenda (bloqueado mientras se confirma)
    $stmt = $pdo->prepare(
        'SELECT ic.id_items_carrito, ic.modalidad, ic.talle, ic.cantidad,
                p.id_productos, p.nombre, p.precio_venta, p.precio_alquiler, p.descuento_porcentaje, p.oferta_hasta,
                (p.activo = 1 AND c.activo = 1 AND t.activo = 1
                 AND IF(ic.modalidad = \'alquiler\', p.en_alquiler, p.en_venta) = 1) AS disponible
           FROM items_carrito ic
           INNER JOIN productos p      ON p.id_productos = ic.id_productos
           INNER JOIN categorias c     ON c.id_categorias = p.id_categorias
           INNER JOIN tipos_producto t ON t.id_tipos_producto = p.id_tipos_producto
          WHERE ic.id_clientes = :c
          FOR UPDATE'
    );
    $stmt->execute([':c' => $cliente['id_clientes']]);
    $items = $stmt->fetchAll();

    $errores = [];
    if (!$items) {
        $errores[] = 'Tu carrito está vacío.';
    }
    $hay_alquiler = false;
    foreach ($items as $it) {
        if ((int) $it['disponible'] !== 1) {
            $errores[] = '«' . $it['nombre'] . '» ya no está disponible: sacala del carrito.';
        }
        if ($it['modalidad'] === 'alquiler') {
            $hay_alquiler = true;
        }
    }

    // Fechas (solo si hay alquiler)
    $retiro = $devolucion = null;
    if ($hay_alquiler) {
        $r = DateTime::createFromFormat('!Y-m-d', $datos['fecha_retiro']);
        $d = DateTime::createFromFormat('!Y-m-d', $datos['fecha_devolucion']);
        if (!$r || $r->format('Y-m-d') !== $datos['fecha_retiro']) {
            $errores[] = 'Indicá cuándo retirás las prendas en alquiler.';
        } elseif (!$d || $d->format('Y-m-d') !== $datos['fecha_devolucion']) {
            $errores[] = 'Indicá cuándo devolvés las prendas en alquiler.';
        } else {
            $dias_hasta  = dias_desde_hoy($r);
            $dias_alq    = (int) $r->diff($d)->format('%r%a');
            if ($dias_hasta < 0) {
                $errores[] = 'La fecha de retiro no puede ser anterior a hoy.';
            } elseif ($dias_hasta > PEDIDO_ANTICIPO_MAX) {
                $errores[] = 'La fecha de retiro puede ser hasta dentro de ' . PEDIDO_ANTICIPO_MAX . ' días.';
            } elseif ($dias_alq < 0) {
                $errores[] = 'La devolución no puede ser antes del retiro.';
            } elseif ($dias_alq > ALQUILER_MAX_DIAS) {
                $errores[] = 'El alquiler puede durar hasta ' . ALQUILER_MAX_DIAS . ' días.';
            } else {
                $retiro = $datos['fecha_retiro'];
                $devolucion = $datos['fecha_devolucion'];
            }
        }
    }

    if (mb_strlen($datos['comentario']) > PEDIDO_COMENTARIO_MAX) {
        $errores[] = 'Las aclaraciones pueden tener hasta ' . PEDIDO_COMENTARIO_MAX . ' caracteres.';
    }

    if ($errores) {
        $pdo->rollBack();
        volver_carrito($errores, $datos);
    }

    // Total con los precios de hoy
    $total = 0.0; $a_consultar = 0;
    foreach ($items as &$it) {
        $it['precio'] = precio_actual($it, $it['modalidad']);
        $it['desc']   = oferta_vigente($it) ? (int) $it['descuento_porcentaje'] : 0;
        if ($it['precio'] === null) { $a_consultar = 1; } else { $total += $it['precio'] * (int) $it['cantidad']; }
    }
    unset($it);

    $codigo = generar_codigo_pedido($pdo);
    $pdo->prepare('INSERT INTO pedidos (id_clientes, codigo, id_estados_pedido, fecha_retiro, fecha_devolucion,
                                        comentario_cliente, total_estimado, tiene_a_consultar)
                   VALUES (:c, :cod, :est, :r, :d, :com, :tot, :ac)')
        ->execute([
            ':c' => $cliente['id_clientes'], ':cod' => $codigo, ':est' => PEDIDO_PENDIENTE,
            ':r' => $retiro, ':d' => $devolucion, ':com' => $datos['comentario'],
            ':tot' => number_format($total, 2, '.', ''), ':ac' => $a_consultar,
        ]);
    $id_pedido = (int) $pdo->lastInsertId();

    // Si algo es «A medida», se copian las medidas de la clienta de este momento.
    $medidas_txt = '';
    if (in_array(TALLE_A_MEDIDA, array_column($items, 'talle'), true)) {
        $med = medidas_vigentes($pdo, (int) $cliente['id_clientes']);
        $medidas_txt = $med ? texto_medidas($med) : '';
    }

    $ins = $pdo->prepare('INSERT INTO items_pedido (id_pedidos, id_productos, nombre_producto, modalidad, talle, medidas, cantidad, precio_unitario, descuento_porcentaje)
                          VALUES (:p, :prod, :nom, :m, :talle, :medidas, :n, :precio, :desc)');
    foreach ($items as $it) {
        $ins->execute([
            ':p' => $id_pedido, ':prod' => $it['id_productos'], ':nom' => $it['nombre'], ':m' => $it['modalidad'],
            ':talle' => $it['talle'], ':medidas' => $it['talle'] === TALLE_A_MEDIDA ? $medidas_txt : '',
            ':n' => $it['cantidad'], ':precio' => $it['precio'] === null ? null : number_format($it['precio'], 2, '.', ''),
            ':desc' => $it['desc'],
        ]);
    }

    $pdo->prepare('DELETE FROM items_carrito WHERE id_clientes = :c')->execute([':c' => $cliente['id_clientes']]);
    $pdo->commit();
    unset($_SESSION['ultimo_agregado']);

    flash_cliente('ok', '¡Listo! Recibimos tu pedido ' . formatear_codigo($codigo) . '. La modista lo va a revisar y te avisamos acá.');
    header('Location: mis_pedidos.php?codigo=' . $codigo);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error al confirmar pedido: ' . $e->getMessage());
    volver_carrito(['No se pudo confirmar el pedido. Probá de nuevo.'], $datos);
}
