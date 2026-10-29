<?php
declare(strict_types=1);

/**
 * Formulario de solicitud de presupuesto — Elegancia a Medida
 *
 * Solo para clientes con cuenta: si no inició sesión, va a login.php.
 * Nombre y teléfono salen de la cuenta (tabla clientes).
 * Envía por POST a procesar_presupuesto.php.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';

// La sesión debe iniciarse antes de cualquier salida HTML.
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$cliente = requerir_cliente($pdo, 'Para pedir un presupuesto, iniciá sesión o registrate.');

// Los tipos de servicio se leen de la tabla tipos_servicio.
try {
    $tipos_servicio = obtener_tipos_servicio($pdo);
} catch (PDOException $e) {
    error_log('Error al leer tipos_servicio: ' . $e->getMessage());
    $tipos_servicio = [];
}

// Token de un solo uso: evita el reenvío duplicado del formulario
// y valida que el POST provenga de esta misma página. (Corrige P07)
if (empty($_SESSION['token_formulario'])) {
    $_SESSION['token_formulario'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['token_formulario'];

$flash = tomar_flash_cliente();

// Prenda del catálogo elegida como referencia (inicio → "Pedir presupuesto").
// Llega como ?producto=ID, o como ?id_productos=ID al volver con errores.
$id_ref = filter_input(INPUT_GET, 'producto', FILTER_VALIDATE_INT)
       ?: filter_input(INPUT_GET, 'id_productos', FILTER_VALIDATE_INT) ?: 0;
$producto_ref = null;
if ($id_ref > 0) {
    $stmt = $pdo->prepare(
        'SELECT p.id_productos, p.nombre, p.imagen, c.nombre AS categoria
           FROM productos p INNER JOIN categorias c ON c.id_categorias = p.id_categorias
          WHERE p.id_productos = :id AND p.activo = 1 AND c.activo = 1'
    );
    $stmt->execute([':id' => $id_ref]);
    $producto_ref = $stmt->fetch() ?: null;
}

// Valor previo del formulario cuando se vuelve con errores.
function viejo(string $campo): string {
    return isset($_GET[$campo]) && is_string($_GET[$campo]) ? e($_GET[$campo]) : '';
}

// Fecha sugerida sin recargo (tarifas por plazo de la modista). Si viene de la
// lista de precios de arreglos y ese arreglo tarda más, se usa su demora.
try {
    $tarifas = obtener_tarifas($pdo);
    $arreglo_pedido = null;
    if ((int) ($_GET['id_tipos_servicio'] ?? 0) === SERVICIO_ARREGLOS && is_string($_GET['descripcion'] ?? null)) {
        $arreglo_pedido = arreglo_de_solicitud(obtener_arreglos($pdo), $_GET['descripcion']);
    }
} catch (PDOException $e) {
    error_log('Error al leer tarifas: ' . $e->getMessage());
    $tarifas = [];
    $arreglo_pedido = null;
}
$demora = $arreglo_pedido && $arreglo_pedido['demora_dias'] !== null ? (int) $arreglo_pedido['demora_dias'] : 0;
$fecha_sugerida = fecha_sugerida_sin_recargo($tarifas, $demora);
$fecha_inicial = viejo('fecha_requerida') !== '' ? viejo('fecha_requerida') : ($fecha_sugerida ? $fecha_sugerida->format('Y-m-d') : '');
// Recargos para avisar si la quiere antes (solo las tarifas con recargo)
$recargos = [];
$desde = 0;
foreach ($tarifas as $t) {
    if ((float) $t['recargo_porcentaje'] > 0) {
        $recargos[] = ['texto' => texto_rango_tarifa($desde, $t['dias_hasta'] !== null ? (int) $t['dias_hasta'] : null),
                       'recargo' => (float) $t['recargo_porcentaje'], 'nombre' => $t['nombre']];
    }
    $desde = $t['dias_hasta'] !== null ? (int) $t['dias_hasta'] + 1 : $desde;
}

$pagina_actual = 'pedir';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Presupuesto - Elegancia a Medida</title>
    <link rel="stylesheet" href="<?= asset('css/cliente.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/sitio.css') ?>">
</head>
<body>
<?php require __DIR__ . '/encabezado_cliente.php'; ?>
<main class="pagina">
    <div class="container">
        <h1>Solicitud de Presupuesto</h1>
        <p class="subtitle">Contanos qué necesitás y la modista te responde con el precio y la fecha de entrega.</p>

        <?php
        if ($flash) {
            echo '<div class="alert alert-' . e($flash['tipo']) . '">' . e($flash['mensaje']) . '</div>';
        }

        // Mensajes de error devueltos por procesar_presupuesto.php.
        // Todo texto que viene de la URL se escapa antes de imprimirse. (Corrige P02)
        if (isset($_GET['error']) && is_string($_GET['error']) && $_GET['error'] !== '') {
            echo '<div class="alert alert-error"><strong>Corrija los siguientes errores:</strong><ul>';
            foreach (explode('|', $_GET['error']) as $err) {
                $err = trim($err);
                if ($err !== '') {
                    echo '<li>' . e($err) . '</li>';
                }
            }
            echo '</ul></div>';
        }

        // Si la tabla tipos_servicio no existe o está vacía, se avisa en vez de
        // mostrar un desplegable vacío. El detalle técnico queda en el log de PHP.
        if (empty($tipos_servicio)) {
            echo '<div class="alert alert-error"><strong>El formulario no está disponible en este momento.</strong>'
               . '<br>No se pudieron cargar los tipos de servicio. '
               . 'Verifique que la base tenga la tabla <code>tipos_servicio</code> '
               . '(importar <code>elegancia_a_medida.sql</code>).</div>';
        }
        ?>

        <div class="cuenta-dato">
            Pedido a nombre de <strong><?= e($cliente['nombre']) ?></strong> · <?= e($cliente['telefono']) ?>
        </div>

        <!-- Pasos del formulario (js/solicitud.js los va marcando a medida que se completan) -->
        <ol class="pasos-solicitud" id="pasos-solicitud" hidden>
            <li data-paso="1"><span class="ps-num">1</span><span class="ps-txt">Servicio</span></li>
            <li data-paso="2"><span class="ps-num">2</span><span class="ps-txt">Prenda</span></li>
            <li data-paso="3"><span class="ps-num">3</span><span class="ps-txt">Detalle</span></li>
            <li data-paso="4"><span class="ps-num">4</span><span class="ps-txt">Fecha</span></li>
        </ol>

        <form action="procesar_presupuesto.php" method="POST" novalidate id="formPresupuesto" data-desc-min="<?= DESC_MIN ?>">

            <input type="hidden" name="token" value="<?= e($token) ?>">

            <?php if ($producto_ref): $foto_ref = url_imagen_producto($producto_ref['imagen']); ?>
                <input type="hidden" name="id_productos" value="<?= (int) $producto_ref['id_productos'] ?>">
                <div class="ref-producto">
                    <div class="mini">
                        <?php if ($foto_ref): ?>
                            <img src="<?= e($foto_ref) ?>" alt="">
                        <?php else: ?>
                            <div class="sin-foto"><span><?= e(mb_substr($producto_ref['nombre'], 0, 1)) ?></span></div>
                        <?php endif; ?>
                    </div>
                    <div class="txt">
                        Prenda de referencia del catálogo
                        <strong><?= e($producto_ref['nombre']) ?></strong>
                        <?= e($producto_ref['categoria']) ?>
                    </div>
                    <a class="quitar" href="solicitud.php">Quitar</a>
                </div>
            <?php endif; ?>

            <div class="form-group" data-paso="1" data-siguiente="Ahora contanos qué prenda es">
                <label for="id_tipos_servicio">Tipo de servicio <span class="required">*</span></label>
                <select id="id_tipos_servicio" name="id_tipos_servicio" required>
                    <option value="">— Seleccione —</option>
                    <?php
                    // La lista viene de la tabla tipos_servicio: una sola fuente de verdad.
                    $sel = isset($_GET['id_tipos_servicio']) && is_string($_GET['id_tipos_servicio'])
                        ? (int) $_GET['id_tipos_servicio']
                        : 0;
                    foreach ($tipos_servicio as $id_tipo => $nombre_tipo) {
                        $selected = ($sel === $id_tipo) ? ' selected' : '';
                        echo '<option value="' . $id_tipo . '"' . $selected . '>' . e($nombre_tipo) . '</option>';
                    }
                    ?>
                </select>
            </div>

            <div class="form-group" data-paso="2" data-siguiente="Ahora describí el trabajo">
                <label for="tipo_prenda">Tipo de prenda <span class="required">*</span></label>
                <input type="text" id="tipo_prenda" name="tipo_prenda" maxlength="<?= PRENDA_MAX ?>"
                       value="<?= viejo('tipo_prenda') !== '' ? viejo('tipo_prenda') : e($producto_ref['nombre'] ?? '') ?>"
                       required placeholder="Ej: Vestido de fiesta, traje, falda...">
                <div class="prendas-rapidas" hidden>
                    <?php foreach (['Vestido', 'Pantalón', 'Falda', 'Camisa', 'Saco', 'Disfraz', 'Vestido de fiesta', 'Traje'] as $pr): ?>
                        <button type="button" data-prenda="<?= e($pr) ?>"><?= e($pr) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group" data-paso="3" data-siguiente="¡Bien! Ahora elegí la fecha">
                <label for="descripcion">Descripción del trabajo <span class="required">*</span></label>
                <textarea id="descripcion" name="descripcion" required
                          minlength="<?= DESC_MIN ?>" maxlength="<?= DESC_MAX ?>"
                          placeholder="Detalle lo que necesita: medidas aproximadas, tela, estilo, etc."><?= viejo('descripcion') ?></textarea>
                <div class="contador" id="contador-desc">Máximo <?= DESC_MAX ?> caracteres</div>
            </div>

            <div class="form-group" data-paso="4" data-siguiente="¡Listo! Ya podés enviarla">
                <label for="fecha_requerida">Fecha requerida <span class="required">*</span></label>
                <input type="date" id="fecha_requerida" name="fecha_requerida"
                       value="<?= $fecha_inicial ?>"
                       required min="<?= date('Y-m-d') ?>"
                       max="<?= fecha_maxima()->format('Y-m-d') ?>">
                <div class="contador">
                    Hasta el <?= fecha_maxima()->format('d/m/Y') ?> (<?= FECHA_MAX_DIAS ?> días desde hoy)
                </div>
                <?php if ($fecha_sugerida): ?>
                    <div class="fecha-sugerida" id="fecha-sugerida">
                        <p class="fs-titulo">💡 Fecha sugerida, <strong>sin recargo</strong>:
                            desde el <strong><?= e(mb_strtolower(texto_dia($fecha_sugerida->format('Y-m-d')))) ?></strong>
                            <button type="button" class="fs-usar" data-fecha="<?= $fecha_sugerida->format('Y-m-d') ?>"
                                    <?= $fecha_inicial === $fecha_sugerida->format('Y-m-d') ? 'hidden' : '' ?>>Usar esa fecha</button>
                        </p>
                        <?php if ($arreglo_pedido && $demora > (dias_sin_recargo($tarifas) ?? 0)): ?>
                            <p class="fs-texto">El arreglo «<?= e($arreglo_pedido['nombre']) ?>» lleva unos <?= $demora ?> días.</p>
                        <?php endif; ?>
                        <?php if ($recargos): ?>
                            <p class="fs-texto">Si la necesitás antes, se cobra un recargo:</p>
                            <ul class="fs-recargos">
                                <?php foreach ($recargos as $r): ?>
                                    <li><?= e($r['texto']) ?>: <strong>+<?= e(rtrim(rtrim(number_format($r['recargo'], 2, ',', '.'), '0'), ',')) ?> %</strong> (<?= e($r['nombre']) ?>)</li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <p class="fs-elegida" id="fs-elegida" aria-live="polite"></p>
                    </div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn" id="btnEnviar"<?= empty($tipos_servicio) ? ' disabled' : '' ?>>Enviar solicitud de presupuesto</button>
        </form>
    </div>
</main>
<?php require __DIR__ . '/pie_cliente.php'; ?>

<script>
    // Fecha sugerida: botón «Usar esa fecha» y aviso del recargo para la fecha elegida
    (function () {
        var TARIFAS = <?= json_encode(array_map(fn($t) => [
            'hasta' => $t['dias_hasta'] !== null ? (int) $t['dias_hasta'] : null,
            'recargo' => (float) $t['recargo_porcentaje'], 'nombre' => $t['nombre']], $tarifas), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
        var $f = document.getElementById('fecha_requerida');
        var $msg = document.getElementById('fs-elegida');
        var $usar = document.querySelector('.fs-usar');
        if (!$f || !$msg) { return; }
        function dias(valor) {
            var p = valor.split('-'); if (p.length !== 3) { return null; }
            var d = new Date(+p[0], +p[1] - 1, +p[2]), h = new Date(); h.setHours(0, 0, 0, 0);
            return Math.round((d - h) / 86400000);
        }
        function mostrar() {
            var n = dias($f.value);
            if ($usar) { $usar.hidden = $f.value === $usar.dataset.fecha; }
            if (n === null || isNaN(n) || n < 0) { $msg.textContent = ''; return; }
            var t = null;
            for (var i = 0; i < TARIFAS.length; i++) {
                if (TARIFAS[i].hasta === null || TARIFAS[i].hasta >= n) { t = TARIFAS[i]; break; }
            }
            if (!t) { $msg.textContent = ''; return; }
            $msg.className = 'fs-elegida ' + (t.recargo > 0 ? 'con-recargo' : 'sin-recargo');
            $msg.textContent = t.recargo > 0
                ? 'Para la fecha que elegiste (' + n + ' días): recargo del ' + String(t.recargo).replace('.', ',') + ' % (' + t.nombre + ').'
                : '✓ Para la fecha que elegiste no hay recargo.';
        }
        $f.addEventListener('input', mostrar);
        $f.addEventListener('change', mostrar);
        if ($usar) {
            $usar.addEventListener('click', function () { $f.value = $usar.dataset.fecha; mostrar(); });
        }
        mostrar();
    })();

    // Refuerzo visual contra el doble clic. La protección real es el token del servidor.
    document.getElementById('formPresupuesto').addEventListener('submit', function () {
        // setTimeout evita que deshabilitar el botón cancele el envío en curso.
        setTimeout(function () {
            var b = document.getElementById('btnEnviar');
            b.disabled = true;
            b.textContent = 'Enviando...';
        }, 0);
    });
</script>
<script src="<?= asset('js/solicitud.js') ?>"></script>
<script src="<?= asset('js/notificaciones.js') ?>"></script>
<script src="<?= asset('js/sitio.js') ?>"></script>
</body>
</html>
