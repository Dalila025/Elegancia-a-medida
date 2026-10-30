<?php
/**
 * Encabezado de las páginas del sitio (clientes y visitantes).
 *
 *  [ logo ]   Shop ▾  Alquiler ▾  Confección ▾  Arreglos ▾      🔍  👤  🛒
 *
 * Cada opción del menú abre una barra desplegable con sus opciones
 * (js/sitio.js). Buscador, cuenta y carrito son solo íconos.
 * Si el cliente agregó algo al carrito hace menos de CARRITO_DESHACER_MIN
 * minutos, debajo aparece el aviso para deshacerlo.
 *
 * Antes de incluirlo definir: $cliente (o null), $pdo, $pagina_actual.
 */
$pagina_actual = $pagina_actual ?? '';
$logo          = url_logo($pdo);
$sin_leer      = !empty($cliente) ? avisos_sin_leer($pdo, (int) $cliente['id_clientes']) : 0;
$en_carrito    = !empty($cliente) ? cantidad_carrito($pdo, (int) $cliente['id_clientes']) : 0;
$deshacer      = !empty($cliente) ? ultimo_agregado($pdo, (int) $cliente['id_clientes']) : null;
$primer_nombre = !empty($cliente) ? explode(' ', trim($cliente['nombre']))[0] : '';
// «No te olvides…»: turnos de los próximos días y prendas listas para retirar (funciones_taller.php)
$recordatorios = [];
if (!empty($cliente)) {
    try {
        $recordatorios = recordatorios_cliente($pdo, (int) $cliente['id_clientes']);
    } catch (PDOException $e) {
        error_log('No se pudieron leer los recordatorios: ' . $e->getMessage());
    }
}

// Opciones de los menús: solo categorías y tipos que tienen prendas visibles.
$menu = ['cat' => [], 'tipo' => [], 'cat_alq' => [], 'tipo_alq' => [], 'cat_venta' => [], 'tipos_todos' => []];
try {
    $base = 'FROM productos p
             INNER JOIN categorias c     ON c.id_categorias = p.id_categorias AND c.activo = 1
             INNER JOIN tipos_producto t ON t.id_tipos_producto = p.id_tipos_producto AND t.activo = 1
             WHERE p.activo = 1';
    foreach ([
        'cat'       => "SELECT DISTINCT c.id_categorias AS id, c.nombre $base ORDER BY c.nombre",
        'tipo'      => "SELECT DISTINCT t.id_tipos_producto AS id, t.nombre $base ORDER BY t.nombre",
        'cat_alq'   => "SELECT DISTINCT c.id_categorias AS id, c.nombre $base AND p.en_alquiler = 1 ORDER BY c.nombre",
        'tipo_alq'  => "SELECT DISTINCT t.id_tipos_producto AS id, t.nombre $base AND p.en_alquiler = 1 ORDER BY t.nombre",
        'cat_venta' => "SELECT DISTINCT c.id_categorias AS id, c.nombre $base AND p.en_venta = 1 ORDER BY c.nombre",
    ] as $clave => $sql) {
        $menu[$clave] = $pdo->query($sql)->fetchAll();
    }
    $menu['tipos_todos'] = $pdo->query('SELECT nombre FROM tipos_producto WHERE activo = 1 ORDER BY nombre')->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('Error al armar el menú: ' . $e->getMessage());
}

/** Ícono SVG (sin depender de internet). */
function icono(string $nombre): string
{
    $trazos = [
        'buscar'  => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'usuario' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
        'carrito' => '<path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h9.2a1 1 0 0 0 1-.8L20.5 8H6.2"/><circle cx="9.5" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/>',
        'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'cerrar'  => '<path d="M6 6l12 12M18 6 6 18"/>',
        'flecha'  => '<path d="m6 9 6 6 6-6"/>',
        // Pie: redes y contacto (íconos genéricos)
        'chat'     => '<path d="M21 11.5a8.4 8.4 0 0 1-12.4 7.4L3 21l2.1-5.6A8.4 8.4 0 1 1 21 11.5z"/><path d="M8.5 9.5c.5 2 2 3.6 4 4.3l1.2-1.1 2 .9-.5 1.6c-3.4-.2-6.7-3.4-7-6.8l1.6-.5.9 2z"/>',
        'camara'   => '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>',
        'pulgar'   => '<path d="M7 10v11M7 10l4-8a2.5 2.5 0 0 1 2.5 3L13 10h6a2 2 0 0 1 2 2.3l-1.4 7A2 2 0 0 1 17.6 21H7"/><path d="M3 10h4v11H3z"/>',
        'telefono' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
        'sobre'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'arriba'   => '<path d="m6 15 6-6 6 6"/>',
    ];
    return '<svg class="icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . ($trazos[$nombre] ?? '') . '</svg>';
}

/** Lista de enlaces de un menú desplegable. */
function lista_menu(array $filas, callable $url): void
{
    foreach ($filas as $f) {
        echo '<li><a href="' . e($url($f)) . '">' . e($f['nombre']) . '</a></li>';
    }
}
?>
<header class="cabecera" id="cabecera">
    <div class="cabecera-barra">
        <button type="button" class="icono-btn solo-movil" id="btn-menu" aria-controls="menu-principal" aria-expanded="false" aria-label="Abrir menú">
            <?= icono('menu') ?>
        </button>

        <a href="index.php" class="logo" aria-label="Elegancia a Medida - Inicio">
            <?php if ($logo): ?>
                <img src="<?= e($logo) ?>" alt="Elegancia a Medida">
            <?php else: ?>
                <span class="logo-marca">E</span><span class="logo-texto">Elegancia <em>a Medida</em></span>
            <?php endif; ?>
        </a>

        <nav class="menu-principal" id="menu-principal" aria-label="Menú principal">
            <ul class="menu-lista">
                <li class="menu-item">
                    <button type="button" class="menu-boton" aria-expanded="false" aria-controls="panel-shop">Shop <?= icono('flecha') ?></button>
                    <div class="menu-panel" id="panel-shop" hidden>
                        <div class="menu-panel-inner">
                            <div class="menu-col"><h3>Categorías</h3><ul>
                                <?php lista_menu($menu['cat'], fn($f) => 'tienda.php?categoria=' . $f['id']); ?>
                            </ul></div>
                            <div class="menu-col"><h3>Tipo de prenda</h3><ul>
                                <?php lista_menu($menu['tipo'], fn($f) => 'tienda.php?tipo=' . $f['id']); ?>
                            </ul></div>
                            <div class="menu-col"><h3>Destacados</h3><ul>
                                <li><a href="tienda.php?ofertas=1">Ofertas</a></li>
                                <li><a href="tienda.php?temporada=1">Temporada</a></li>
                                <li><a href="tienda.php?modalidad=venta">En venta</a></li>
                                <li><a href="tienda.php?modalidad=alquiler">En alquiler</a></li>
                            </ul></div>
                            <div class="menu-col menu-destacado">
                                <p>Todas las prendas de la tienda, en venta y en alquiler.</p>
                                <a class="btn-pill" href="tienda.php">Ver toda la tienda</a>
                            </div>
                        </div>
                    </div>
                </li>
                <li class="menu-item">
                    <button type="button" class="menu-boton" aria-expanded="false" aria-controls="panel-alquiler">Alquiler <?= icono('flecha') ?></button>
                    <div class="menu-panel" id="panel-alquiler" hidden>
                        <div class="menu-panel-inner">
                            <div class="menu-col"><h3>Por ocasión</h3><ul>
                                <?php lista_menu($menu['cat_alq'], fn($f) => 'tienda.php?modalidad=alquiler&categoria=' . $f['id']); ?>
                            </ul></div>
                            <div class="menu-col"><h3>Tipo de prenda</h3><ul>
                                <?php lista_menu($menu['tipo_alq'], fn($f) => 'tienda.php?modalidad=alquiler&tipo=' . $f['id']); ?>
                            </ul></div>
                            <div class="menu-col menu-destacado">
                                <h3>¿Cómo funciona?</h3>
                                <p>Elegí la prenda, agregala al carrito como alquiler, indicá cuándo la retirás y la devolvés, y confirmá el pedido.</p>
                                <a class="btn-pill" href="tienda.php?modalidad=alquiler">Ver todo el alquiler</a>
                            </div>
                        </div>
                    </div>
                </li>
                <li class="menu-item">
                    <button type="button" class="menu-boton" aria-expanded="false" aria-controls="panel-confeccion">Confección <?= icono('flecha') ?></button>
                    <div class="menu-panel" id="panel-confeccion" hidden>
                        <div class="menu-panel-inner">
                            <div class="menu-col"><h3>A medida</h3><ul>
                                <li><a href="solicitud.php?id_tipos_servicio=1">Solicitud de presupuesto</a></li>
                                <li><a href="solicitud.php?id_tipos_servicio=3">Diseño personalizado</a></li>
                                <li><a href="mis_medidas.php">Mis medidas</a></li>
                                <li><a href="mis_solicitudes.php">Mis solicitudes</a></li>
                            </ul></div>
                            <div class="menu-col"><h3>Inspirate en</h3><ul>
                                <?php lista_menu($menu['cat_venta'], fn($f) => 'tienda.php?modalidad=venta&categoria=' . $f['id']); ?>
                            </ul></div>
                            <div class="menu-col menu-destacado">
                                <h3>Tu prenda, a tu medida</h3>
                                <p>Contanos qué necesitás y la modista te responde con el precio y la fecha de entrega.</p>
                                <a class="btn-pill" href="solicitud.php?id_tipos_servicio=1">Pedir presupuesto</a>
                            </div>
                        </div>
                    </div>
                </li>
                <li class="menu-item">
                    <button type="button" class="menu-boton" aria-expanded="false" aria-controls="panel-arreglos">Arreglos <?= icono('flecha') ?></button>
                    <div class="menu-panel" id="panel-arreglos" hidden>
                        <div class="menu-panel-inner">
                            <div class="menu-col"><h3>Arreglos</h3><ul>
                                <li><a href="precios_arreglos.php">Lista de precios</a></li>
                                <li><a href="solicitud.php?id_tipos_servicio=2">Solicitud de presupuesto</a></li>
                                <li><a href="mis_solicitudes.php">Mis solicitudes</a></li>
                            </ul></div>
                            <div class="menu-col"><h3>Arreglo de…</h3><ul class="dos-columnas">
                                <?php foreach ($menu['tipos_todos'] as $t): ?>
                                    <li><a href="<?= e('solicitud.php?' . http_build_query(['id_tipos_servicio' => 2, 'tipo_prenda' => $t])) ?>"><?= e($t) ?></a></li>
                                <?php endforeach; ?>
                            </ul></div>
                            <div class="menu-col menu-destacado">
                                <h3>Ruedos, entallados, cierres…</h3>
                                <p>Mirá los precios de los arreglos más comunes y pedí el tuyo en un minuto.</p>
                                <a class="btn-pill" href="precios_arreglos.php">Ver lista de precios</a>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>
        </nav>

        <div class="iconos">
            <button type="button" class="icono-btn" id="btn-buscar" aria-controls="barra-busqueda" aria-expanded="false" aria-label="Buscar">
                <?= icono('buscar') ?>
            </button>

            <?php if (!empty($cliente)): ?>
                <div class="cuenta">
                    <button type="button" class="icono-btn" id="btn-cuenta" aria-controls="menu-cuenta" aria-expanded="false" aria-label="Mi cuenta">
                        <?= icono('usuario') ?>
                        <span class="globo" id="campana-avisos" <?= $sin_leer ? '' : 'hidden' ?>><?= $sin_leer ?></span>
                    </button>
                    <div class="menu-cuenta" id="menu-cuenta" hidden>
                        <p class="menu-cuenta-hola">Hola, <strong><?= e($primer_nombre) ?></strong></p>
                        <a href="mis_pedidos.php">Mis pedidos</a>
                        <a href="mis_medidas.php">Mis medidas</a>
                        <a href="mi_cuenta.php">Estado de cuenta</a>
                        <a href="mis_solicitudes.php">Mis solicitudes<?= $sin_leer ? ' <span class="globo globo-texto">' . $sin_leer . '</span>' : '' ?></a>
                        <a href="solicitud.php">Pedir presupuesto</a>
                        <form method="post" action="logout.php">
                            <input type="hidden" name="token" value="<?= e(token_cliente()) ?>">
                            <button type="submit">Cerrar sesión</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="icono-btn" aria-label="Iniciar sesión" title="Iniciar sesión"><?= icono('usuario') ?></a>
            <?php endif; ?>

            <a href="carrito.php" class="icono-btn" aria-label="Carrito<?= $en_carrito ? " ($en_carrito)" : '' ?>" title="Carrito">
                <?= icono('carrito') ?>
                <?php if ($en_carrito): ?><span class="globo"><?= $en_carrito ?></span><?php endif; ?>
            </a>
        </div>
    </div>

    <form class="barra-busqueda" id="barra-busqueda" action="tienda.php" method="get" role="search" hidden>
        <div class="barra-busqueda-inner">
            <?= icono('buscar') ?>
            <input type="search" name="q" placeholder="Buscar vestidos, trajes, accesorios…" aria-label="Buscar en la tienda" maxlength="60">
            <button type="submit" class="btn-pill">Buscar</button>
        </div>
    </form>

    <?php if ($deshacer): ?>
        <div class="aviso-deshacer" id="aviso-deshacer" data-segundos="<?= max(1, (int) $deshacer['segundos']) ?>" role="status">
            <div class="aviso-deshacer-inner">
                <span>Agregaste <strong><?= e($deshacer['nombre']) ?></strong>
                    (<?= $deshacer['modalidad'] === 'alquiler' ? 'alquiler' : 'venta' ?><?= $deshacer['talle'] !== '' ? ', ' . e(mb_strtolower(texto_talle($deshacer['talle']))) : '' ?>) al carrito.</span>
                <form method="post" action="carrito_accion.php">
                    <input type="hidden" name="token" value="<?= e(token_cliente()) ?>">
                    <input type="hidden" name="accion" value="deshacer">
                    <input type="hidden" name="id" value="<?= (int) $deshacer['id_items_carrito'] ?>">
                    <input type="hidden" name="volver" value="<?= e(pagina_actual()) ?>">
                    <button type="submit">Deshacer</button>
                </form>
                <a href="carrito.php">Ver carrito</a>
                <span class="aviso-deshacer-tiempo" aria-hidden="true"></span>
            </div>
        </div>
    <?php endif; ?>
</header>
<?php if ($recordatorios): ?>
    <div class="recordatorios" role="status">
        <?php foreach ($recordatorios as $rec): ?>
            <a href="<?= e($rec['url']) ?>" class="recordatorio">
                <span aria-hidden="true">📅</span> <?= e($rec['texto']) ?> <span class="rec-ver">Ver →</span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
