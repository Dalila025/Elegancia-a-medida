<?php
declare(strict_types=1);

/**
 * Mis pedidos — Elegancia a Medida
 *
 * Pedidos del carrito del cliente, con sus prendas, estado y la nota de la
 * modista. Mientras estén pendientes, el cliente los puede cancelar
 * (cancelar_pedido.php). Al abrir esta página, los avisos de pedidos
 * quedan leídos.
 *
 * Tablas: pedidos, items_pedido, notificaciones.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$cliente = requerir_cliente($pdo, 'Iniciá sesión para ver tus pedidos.');
$flash   = tomar_flash_cliente();
$token   = token_cliente();
$resaltar = isset($_GET['codigo']) && is_string($_GET['codigo']) ? normalizar_codigo($_GET['codigo']) : '';

try {
    $stmt = $pdo->prepare('SELECT * FROM pedidos WHERE id_clientes = :c ORDER BY fecha_pedido DESC, id_pedidos DESC');
    $stmt->execute([':c' => $cliente['id_clientes']]);
    $pedidos = $stmt->fetchAll();

    $items = [];
    if ($pedidos) {
        $ids = array_column($pedidos, 'id_pedidos');
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT ip.*, p.imagen, p.activo FROM items_pedido ip
                                 LEFT JOIN productos p ON p.id_productos = ip.id_productos
                                WHERE ip.id_pedidos IN ($marcas) ORDER BY ip.id_items_pedido");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $it) {
            $items[(int) $it['id_pedidos']][] = $it;
        }

        // Avisos de pedidos: se marcan como leídos
        $pdo->prepare("UPDATE notificaciones SET leida = 1, fecha_lectura = NOW()
                        WHERE leida = 0 AND id_pedidos IN ($marcas)")->execute($ids);
    }
} catch (PDOException $e) {
    error_log('Error en mis pedidos: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudieron cargar tus pedidos.');
}

$pagina_actual = 'pedidos';
$f = fn(?string $fecha) => $fecha ? DateTime::createFromFormat('!Y-m-d', $fecha)->format('d/m/Y') : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis pedidos - Elegancia a Medida</title>
    <link rel="stylesheet" href="css/cliente.css">
    <link rel="stylesheet" href="css/sitio.css">
</head>
<body>
<?php require __DIR__ . '/encabezado_cliente.php'; ?>

<main class="pagina-sitio" style="max-width:900px">
    <p class="migas"><a href="index.php">Inicio</a> / Mis pedidos</p>
    <h1>Mis pedidos</h1>
    <?php if ($flash): ?><div class="alert alert-<?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></div><?php endif; ?>

    <?php if (!$pedidos): ?>
        <div class="vacio-grande">
            <p>Todavía no hiciste ningún pedido.</p>
            <a class="btn-pill" href="tienda.php">Ir a la tienda</a>
        </div>
    <?php endif; ?>

    <?php foreach ($pedidos as $p):
        $idp = (int) $p['id_pedidos'];
        $est = (int) $p['id_estados_pedido'];
        $lista = $items[$idp] ?? []; ?>
        <article class="pedido-card" id="pedido-<?= e($p['codigo']) ?>" <?= $resaltar === $p['codigo'] ? 'style="outline:2px solid var(--dorado)"' : '' ?>>
            <div class="cabeza">
                <div>
                    <h2>Pedido <span class="cod"><?= e(formatear_codigo($p['codigo'])) ?></span></h2>
                    <span class="sec-sub"><?= (new DateTime($p['fecha_pedido']))->format('d/m/Y H:i') ?></span>
                </div>
                <span class="est-ped est-ped-<?= $est ?>"><?= e(ESTADOS_PEDIDO[$est] ?? '') ?></span>
            </div>

            <table>
                <thead><tr><th>Prenda</th><th>Modalidad</th><th class="num">Cant.</th><th class="num">Subtotal</th></tr></thead>
                <tbody>
                <?php foreach ($lista as $it): ?>
                    <tr>
                        <td>
                            <?php if ($it['id_productos'] !== null && (int) $it['activo'] === 1): ?>
                                <a href="prenda.php?id=<?= (int) $it['id_productos'] ?>"><?= e($it['nombre_producto']) ?></a>
                            <?php else: ?><?= e($it['nombre_producto']) ?><?php endif; ?>
                            <?php if ((int) $it['descuento_porcentaje'] > 0): ?><small style="color:var(--acento)"> (−<?= (int) $it['descuento_porcentaje'] ?> %)</small><?php endif; ?>
                        </td>
                        <td><?= $it['modalidad'] === 'alquiler' ? 'Alquiler' : 'Venta' ?></td>
                        <td class="num"><?= (int) $it['cantidad'] ?></td>
                        <td class="num"><?= $it['precio_unitario'] === null ? 'A consultar' : e(pesos((float) $it['precio_unitario'] * (int) $it['cantidad'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($p['fecha_retiro']): ?>
                <p style="margin-top:10px;font-size:.92rem">Alquiler: retiro el <strong><?= $f($p['fecha_retiro']) ?></strong> y devolución el <strong><?= $f($p['fecha_devolucion']) ?></strong>.</p>
            <?php endif; ?>
            <?php if ($p['comentario_cliente'] !== ''): ?><p style="font-size:.9rem;color:#8a817d">Tus aclaraciones: <?= e($p['comentario_cliente']) ?></p><?php endif; ?>
            <?php if ($p['nota_modista'] !== ''): ?><div class="nota-modista"><strong>Nota de la modista:</strong> <?= e($p['nota_modista']) ?></div><?php endif; ?>

            <div class="pie-pedido">
                <strong>Total estimado: <?= e(pesos((float) $p['total_estimado'])) ?><?= (int) $p['tiene_a_consultar'] ? ' + a consultar' : '' ?></strong>
                <?php if ($est === PEDIDO_PENDIENTE): ?>
                    <form method="post" action="cancelar_pedido.php" onsubmit="return confirm('¿Cancelar el pedido <?= e(formatear_codigo($p['codigo'])) ?>?');">
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <input type="hidden" name="codigo" value="<?= e($p['codigo']) ?>">
                        <button type="submit" class="btn-pill borde">Cancelar pedido</button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</main>

<?php require __DIR__ . '/pie_cliente.php'; ?>
<script src="js/sitio.js"></script>
<script src="js/notificaciones.js"></script>
</body>
</html>
