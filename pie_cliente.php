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
// Contacto: cada dato con su ícono redondo al lado. Se puede tocar el ícono o el texto.
// Al pasar el mouse, el círculo se llena desde abajo con el color de esa red.
$contacto_pie = [];
if (!empty($sitio_pie['whatsapp']) && $wa_pie) {
    $contacto_pie[] = ['whatsapp', 'WhatsApp', $wa_pie, $sitio_pie['whatsapp'], true];
}
if (!empty($sitio_pie['telefono'])) {
    $contacto_pie[] = ['telefono', 'Teléfono', 'tel:' . preg_replace('/[^\d+]/', '', $sitio_pie['telefono']), $sitio_pie['telefono'], false];
}
if (!empty($sitio_pie['email'])) {
    $contacto_pie[] = ['email', 'Email', 'mailto:' . $sitio_pie['email'], $sitio_pie['email'], false];
}
if ($ig_pie !== '') {
    $contacto_pie[] = ['instagram', 'Instagram', 'https://www.instagram.com/' . $ig_pie . '/', '@' . $ig_pie, true];
}
if (!empty($sitio_pie['facebook'])) {
    $contacto_pie[] = ['facebook', 'Facebook', 'https://www.facebook.com/search/top?q=' . rawurlencode($sitio_pie['facebook']), $sitio_pie['facebook'], true];
}

/** Íconos de línea (genéricos, blancos) para el contacto del pie. */
function icono_contacto(string $red): string
{
    $d = [
        'whatsapp'  => '<path d="M20.5 11.6a8.5 8.5 0 0 1-12.6 7.4L3.5 20.5l1.6-4.2a8.5 8.5 0 1 1 15.4-4.7z"/><path d="M9 8.6c.3 2.6 2.6 5 5.4 5.6l1.1-1.1 1.8.9-.6 1.6c-3.6-.1-7.2-3.6-7.4-7.2l1.6-.6.9 1.8z"/>',
        'telefono'  => '<path d="M21 16.4v3a1.8 1.8 0 0 1-2 1.8A17.6 17.6 0 0 1 2.8 5a1.8 1.8 0 0 1 1.8-2h3a1.8 1.8 0 0 1 1.8 1.5c.1.9.4 1.8.7 2.6a1.8 1.8 0 0 1-.4 1.9L8.4 10.3a14.4 14.4 0 0 0 5.3 5.3l1.3-1.3a1.8 1.8 0 0 1 1.9-.4c.8.3 1.7.6 2.6.7a1.8 1.8 0 0 1 1.5 1.8z"/>',
        'email'     => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3.5 7 8.5 6 8.5-6"/>',
        'instagram' => '<path d="M8.5 5.5 10 3.8h4l1.5 1.7H19a2 2 0 0 1 2 2V18a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7.5a2 2 0 0 1 2-2z"/><circle cx="12" cy="12.6" r="3.6"/>',
        'facebook'  => '<path d="M7 10.5v9.5M7 10.5l3.6-6.8a2 2 0 0 1 3.7 1.4l-.7 4.4H19a2 2 0 0 1 2 2.3l-1.2 6.5a2 2 0 0 1-2 1.7H7"/><rect x="3" y="10.5" width="4" height="9.5" rx="1"/>',
        'horario'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($d[$red] ?? '') . '</svg>';
}
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
            <?php if (!empty($sitio_pie['horarios'])): ?>
                <div class="pie-horario-card">
                    <span class="phc-icono"><?= icono_contacto('horario') ?></span>
                    <div>
                        <span class="phc-titulo">Horario de atención</span>
                        <span class="phc-texto"><?= e($sitio_pie['horarios']) ?></span>
                    </div>
                </div>
            <?php endif; ?>
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
                    <li><a href="mis_medidas.php">Mis medidas</a></li>
                    <li><a href="mi_cuenta.php">Estado de cuenta</a></li>
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
                <?php foreach ($contacto_pie as [$red, $nombre, $url, $texto, $nueva]): ?>
                    <li>
                        <a class="pie-dato" data-social="<?= e($red) ?>" href="<?= e($url) ?>" <?= $nueva ? 'target="_blank" rel="noopener"' : '' ?>>
                            <span class="ci-icono"><?= icono_contacto($red) ?></span>
                            <span class="ci-texto"><span class="sr-only"><?= e($nombre) ?>: </span><?= e($texto) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="pie-legal">
        <span>© <?= date('Y') ?> Elegancia a Medida · Hecho a medida, como cada prenda.</span>
        <span class="pie-legal-der">
            <a href="panel_login.php">Acceso modista</a>
            <a href="#" class="pie-arriba" onclick="window.scrollTo({top: 0, behavior: 'smooth'}); return false;" aria-label="Volver arriba"><?= icono('arriba') ?></a>
        </span>
    </div>
</footer>
