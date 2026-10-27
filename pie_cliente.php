<?php
/**
 * Pie de página de todas las páginas del sitio.
 * Logo y datos de contacto salen de la tabla datos_sitio (panel → Datos del sitio).
 * Requiere $pdo; $cliente puede ser null.
 */
$sitio_pie   = datos_sitio($pdo);
$cliente_pie = $cliente ?? null;
$logo_pie    = url_logo($pdo);
$wa_pie      = enlace_whatsapp($sitio_pie['whatsapp'] ?? '');
$ig_pie      = usuario_instagram($sitio_pie['instagram'] ?? '');
?>
<footer class="pie">
    <div class="pie-inner">
        <div class="pie-col pie-marca">
            <a href="index.php" class="logo">
                <?php if ($logo_pie): ?>
                    <img src="<?= e($logo_pie) ?>" alt="Elegancia a Medida">
                <?php else: ?>
                    <span class="logo-marca">E</span><span class="logo-texto">Elegancia <em>a Medida</em></span>
                <?php endif; ?>
            </a>
            <p>Venta y alquiler de prendas, confección a medida, diseño personalizado y arreglos.</p>
        </div>

        <div class="pie-col">
            <h3>Tienda</h3>
            <ul>
                <li><a href="tienda.php">Shop</a></li>
                <li><a href="tienda.php?modalidad=alquiler">Alquiler</a></li>
                <li><a href="tienda.php?ofertas=1">Ofertas</a></li>
                <li><a href="solicitud.php?id_tipos_servicio=1">Confección</a></li>
                <li><a href="solicitud.php?id_tipos_servicio=2">Arreglos</a></li>
            </ul>
        </div>

        <div class="pie-col">
            <h3>Mi cuenta</h3>
            <ul>
                <?php if ($cliente_pie): ?>
                    <li><a href="carrito.php">Carrito</a></li>
                    <li><a href="mis_pedidos.php">Mis pedidos</a></li>
                    <li><a href="mis_solicitudes.php">Mis solicitudes</a></li>
                <?php else: ?>
                    <li><a href="login.php">Iniciar sesión</a></li>
                    <li><a href="registro.php">Registrarse</a></li>
                <?php endif; ?>
                <li><a href="index.php#historia">Nuestra historia</a></li>
            </ul>
        </div>

        <div class="pie-col">
            <h3>Contacto</h3>
            <ul class="pie-contacto">
                <?php if (!empty($sitio_pie['whatsapp']) && $wa_pie): ?>
                    <li><a href="<?= e($wa_pie) ?>" target="_blank" rel="noopener">WhatsApp: <?= e($sitio_pie['whatsapp']) ?></a></li>
                <?php endif; ?>
                <?php if (!empty($sitio_pie['telefono'])): ?>
                    <li><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $sitio_pie['telefono'])) ?>">Tel: <?= e($sitio_pie['telefono']) ?></a></li>
                <?php endif; ?>
                <?php if (!empty($sitio_pie['email'])): ?>
                    <li><a href="mailto:<?= e($sitio_pie['email']) ?>"><?= e($sitio_pie['email']) ?></a></li>
                <?php endif; ?>
                <?php if ($ig_pie !== ''): ?>
                    <li><a href="https://www.instagram.com/<?= e($ig_pie) ?>/" target="_blank" rel="noopener">Instagram @<?= e($ig_pie) ?></a></li>
                <?php endif; ?>
                <?php if (!empty($sitio_pie['facebook'])): ?>
                    <li><a href="https://www.facebook.com/search/top?q=<?= e(rawurlencode($sitio_pie['facebook'])) ?>" target="_blank" rel="noopener">Facebook: <?= e($sitio_pie['facebook']) ?></a></li>
                <?php endif; ?>
                <?php if (!empty($sitio_pie['horarios'])): ?><li><?= e($sitio_pie['horarios']) ?></li><?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="pie-legal">
        <span>© <?= date('Y') ?> Elegancia a Medida</span>
        <a href="panel_login.php">Acceso modista</a>
    </div>
</footer>
