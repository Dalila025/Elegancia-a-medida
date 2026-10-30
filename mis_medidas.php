<?php
declare(strict_types=1);

/**
 * Mis medidas — Elegancia a Medida
 *
 * Las medidas las toma la modista en el taller (para que sean exactas): acá la
 * clienta pide un turno para medirse (para confeccionar, un arreglo, comprar o
 * alquilar) y ve las medidas que le tomaron. Con ellas el sitio le recomienda
 * el talle de cada prenda y, si pide algo «A medida», viajan con el pedido.
 * La clienta ya no las carga ella misma: las carga la modista en cliente.php.
 * El turno se reserva en reservar_turno.php (tipo=medidas).
 * Tablas: medidas_clientes, turnos_prueba.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$cliente = requerir_cliente($pdo, 'Iniciá sesión para ver tus medidas y pedir turno para medirte.');
$idc     = (int) $cliente['id_clientes'];

try {
    $medidas = medidas_vigentes($pdo, $idc);
    $stmt = $pdo->prepare('SELECT fecha_carga, cargada_por FROM medidas_clientes WHERE id_clientes = :id
                            ORDER BY fecha_carga DESC, id_medidas_clientes DESC LIMIT 6');
    $stmt->execute([':id' => $idc]);
    $historial = $stmt->fetchAll();
    // Turno para que le tomen las medidas en el taller
    $turno_med = turno_medidas_cliente($pdo, $idc);
    $libres    = horarios_libres($pdo);
    $wa_med    = enlace_whatsapp(datos_sitio($pdo)['whatsapp'] ?? '',
        'Hola! Soy ' . explode(' ', trim($cliente['nombre']))[0] . '. Quiero pasar por el taller a que me tomes las medidas, ¿qué día puedo ir?');
} catch (PDOException $e) {
    error_log('Error en mis medidas: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudieron cargar tus medidas.');
}

$flash = tomar_flash_cliente();
$pagina_actual = 'medidas';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis medidas - Elegancia a Medida</title>
    <link rel="stylesheet" href="<?= asset('css/cliente.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/sitio.css') ?>">
</head>
<body>
<?php require __DIR__ . '/encabezado_cliente.php'; ?>

<main class="pagina-sitio" style="max-width:900px">
    <p class="migas"><a href="index.php">Inicio</a> / Mis medidas</p>
    <h1>Mis medidas</h1>
    <p class="sec-sub" style="margin-top:-8px">
        En la ropa de fiesta el talle no depende de la edad: cada cuerpo es distinto.
        Para que sean exactas, <strong>las medidas te las toma la modista en el taller</strong>.
        Con ellas te decimos qué talle te queda en cada prenda, y si pedís algo <strong>a medida</strong>, ya las tiene.
    </p>

    <?php if ($flash): ?><div class="alert alert-<?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></div><?php endif; ?>

    <!-- ¿No sabés medirte? Turno en el taller -->
    <section class="turno-medidas" id="turno-medidas">
        <?php if ($turno_med): ?>
            <p class="tmd-titulo">📏 Tu turno para tomarte las medidas: <strong><?= e(texto_turno($turno_med['fecha_hora'])) ?></strong></p>
            <p class="tmd-texto"><?= e($turno_med['nota']) ?>. La modista te las toma en el taller y quedan guardadas acá.</p>
            <div class="tmd-acciones">
                <?php if ($libres): ?>
                    <details class="tmd-cambiar">
                        <summary>Cambiar el día u horario</summary>
                        <?php mostrar_selector_turno($libres, '', 'Cambiar a este horario', function () use ($turno_med) {
                            $para = array_search($turno_med['nota'], MEDIDAS_PARA, true) ?: 'otro'; ?>
                            <input type="hidden" name="tipo" value="medidas">
                            <input type="hidden" name="para" value="<?= e($para) ?>">
                        <?php }); ?>
                    </details>
                <?php endif; ?>
                <form method="post" action="reservar_turno.php" onsubmit="return confirm('¿Cancelar tu turno para tomarte las medidas?');">
                    <input type="hidden" name="token" value="<?= e(token_cliente()) ?>">
                    <input type="hidden" name="accion" value="cancelar_medidas">
                    <button type="submit" class="tmd-cancelar">Cancelar turno</button>
                </form>
            </div>
        <?php else: ?>
            <div class="tmd-pedir">
                <p class="tmd-titulo">📏 <?= $medidas ? '¿Cambiaron tus medidas? Pasá por el taller y te medimos de nuevo' : 'Pasá por el taller a medirte' ?></p>
                <p class="tmd-texto">Elegí para qué las necesitás y un horario. La modista te toma las medidas en cinco minutos y quedan guardadas en tu cuenta.</p>
                <?php if ($libres): ?>
                    <?php mostrar_selector_turno($libres, '', 'Pedir este turno', function () { ?>
                        <fieldset class="tmd-para">
                            <legend>¿Para qué las necesitás?</legend>
                            <?php $primero = true; foreach (MEDIDAS_PARA as $clave => $txt): ?>
                                <label><input type="radio" name="para" value="<?= e($clave) ?>" <?= $primero ? 'checked' : '' ?>> <?= e($txt) ?></label>
                            <?php $primero = false; endforeach; ?>
                        </fieldset>
                        <input type="hidden" name="tipo" value="medidas">
                    <?php }); ?>
                <?php else: ?>
                    <p class="tmd-texto">Por ahora no hay horarios libres para elegir.</p>
                <?php endif; ?>
                <?php if ($wa_med): ?><p class="tmd-wa">¿Ningún horario te queda bien? <a href="<?= e($wa_med) ?>" target="_blank" rel="noopener">Escribile a la modista por WhatsApp</a></p><?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="medidas-layout">
        <section class="medidas-vista">
            <h2>Tus medidas</h2>
            <?php if ($medidas): ?>
                <p class="medidas-fecha">
                    Del <?= (new DateTime($medidas['fecha_carga']))->format('d/m/Y') ?>
                    <?= $medidas['cargada_por'] === 'modista' ? '· <strong>tomadas por la modista en el taller</strong>' : '· cargadas por vos' ?>
                </p>
                <dl class="medidas-lista">
                    <?php foreach (MEDIDAS_CLIENTE as $c => [$nombre]): ?>
                        <div><dt><?= e($nombre) ?></dt><dd><?= $medidas[$c] !== null ? e(cm((float) $medidas[$c])) . ' cm' : '—' ?></dd></div>
                    <?php endforeach; ?>
                </dl>
                <?php if (trim((string) ($medidas['notas'] ?? '')) !== ''): ?><p class="medidas-nota">«<?= e($medidas['notas']) ?>»</p><?php endif; ?>
                <p class="medidas-uso">Con estas medidas te recomendamos el talle en cada prenda. <a href="tienda.php">Ver la tienda</a></p>
            <?php else: ?>
                <p class="medidas-vacio">Todavía no tenés medidas. Pedí un turno arriba: en cinco minutos la modista te las toma y quedan acá.</p>
            <?php endif; ?>
        </section>

        <aside class="medidas-consejos">
            <h2>¿Por qué en el taller?</h2>
            <ul>
                <li>Medirse sola es difícil: un par de centímetros cambian el talle.</li>
                <li>La modista mide <strong>busto, cintura, cadera, hombros, estatura y largo de brazo</strong> con la cinta como corresponde.</li>
                <li>Si pedís una prenda <strong>a medida</strong>, se confecciona con estas medidas.</li>
                <li>Si después aceptás un presupuesto, el día que llevás la prenda también te las toma.</li>
            </ul>
            <?php if (count($historial) > 1): ?>
                <h3>Historial</h3>
                <ul class="medidas-historial">
                    <?php foreach ($historial as $h): ?>
                        <li><?= (new DateTime($h['fecha_carga']))->format('d/m/Y') ?> · <?= $h['cargada_por'] === 'modista' ? 'modista' : 'vos' ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </aside>
    </div>
</main>

<?php require __DIR__ . '/pie_cliente.php'; ?>
<script src="<?= asset('js/sitio.js') ?>"></script>
<script src="<?= asset('js/notificaciones.js') ?>"></script>
</body>
</html>
