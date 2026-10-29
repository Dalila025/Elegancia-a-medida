<?php
declare(strict_types=1);

/**
 * Horarios para recibir prendas (panel) — Elegancia a Medida
 *
 * La modista marca en qué horarios de la semana está en el taller para
 * recibir prendas y tomar medidas, y qué días puntuales no atiende.
 * Cuando una clienta acepta un presupuesto, elige uno de estos horarios
 * (los que no estén ocupados) desde su solicitud.
 * Guarda en guardar_horarios.php. Tablas: horarios_recepcion, dias_sin_atencion.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$usuario = requerir_modista($pdo);
$token   = token_modista();
$flash   = tomar_flash();

try {
    $semana  = horarios_recepcion($pdo);
    $cerrado = dias_sin_atencion($pdo);
    $libres  = horarios_libres($pdo);
} catch (PDOException $e) {
    error_log('Error en horarios: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudieron cargar los horarios.');
}
$horas = grilla_horas();
$total_libres = array_sum(array_map(fn($d) => count($d['horas']), $libres));
$pagina_actual = 'taller';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Horarios · Panel · Elegancia a Medida</title>
    <link rel="stylesheet" href="<?= asset('css/modista.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/panel.css') ?>">
</head>
<body>
    <?php require __DIR__ . '/encabezado_modista.php'; ?>

    <main class="container contenedor-ancho">
        <?php if ($flash): ?>
            <div class="flash <?= $flash['tipo'] === 'ok' ? 'flash-ok' : 'flash-error' ?>" role="status"><?= e($flash['mensaje']) ?></div>
        <?php endif; ?>

        <p><a href="taller.php" class="btn-secundario">← Volver al taller</a></p>

        <section class="tarjeta">
            <h2>Horarios para recibir prendas</h2>
            <p class="ayuda">Marcá los horarios en que estás en el taller para recibir prendas y tomar medidas.
                Cuando una clienta acepta un presupuesto, elige uno de estos desde su solicitud.
                Si un horario ya está tomado, a las demás no les aparece.</p>
            <p class="horarios-resumen">Ahora las clientas ven <strong><?= $total_libres ?></strong> horarios libres en los próximos <?= RESERVA_DIAS ?> días.</p>

            <form method="post" action="guardar_horarios.php">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="hidden" name="accion" value="semana">
                <div class="grilla-scroll">
                    <table class="grilla-horarios">
                        <thead>
                            <tr><th scope="col">Hora</th>
                                <?php foreach (DIAS_SEMANA as $d => $nombre): ?>
                                    <th scope="col"><button type="button" class="col-toggle" data-dia="<?= $d ?>" title="Marcar o desmarcar todo el <?= e(mb_strtolower($nombre)) ?>"><?= e(mb_substr($nombre, 0, 3)) ?></button></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($horas as $h): ?>
                                <tr>
                                    <th scope="row"><?= e($h) ?></th>
                                    <?php foreach (DIAS_SEMANA as $d => $nombre): $on = in_array($h, $semana[$d] ?? [], true); ?>
                                        <td>
                                            <label class="celda-hora <?= $on ? 'on' : '' ?>">
                                                <input type="checkbox" name="h[]" value="<?= $d ?>|<?= e($h) ?>" data-dia="<?= $d ?>" <?= $on ? 'checked' : '' ?>
                                                       aria-label="<?= e($nombre . ' ' . $h) ?>">
                                            </label>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="botonera">
                    <button type="submit" class="btn-guardar">Guardar horarios</button>
                </div>
            </form>
        </section>

        <section class="tarjeta">
            <h2>Días que no atendés</h2>
            <p class="ayuda">Feriados, viajes o un día que no vas a estar. Esos días no se ofrecen horarios (los turnos que ya diste no se tocan).</p>
            <form method="post" action="guardar_horarios.php" class="form-panel form-linea">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="hidden" name="accion" value="cerrar">
                <input type="date" name="fecha" min="<?= date('Y-m-d') ?>" required aria-label="Día que no atendés">
                <input type="text" name="motivo" maxlength="<?= DIA_SIN_ATENCION_MOTIVO_MAX ?>" placeholder="Motivo (solo lo ves vos)" aria-label="Motivo">
                <button type="submit" class="btn-accion accion-5">No atiendo ese día</button>
            </form>
            <?php if ($cerrado): ?>
                <ul class="dias-cerrados">
                    <?php foreach ($cerrado as $f => $m): ?>
                        <li>
                            <strong><?= e(texto_dia($f)) ?></strong><?= $m !== '' ? ' · ' . e($m) : '' ?>
                            <form method="post" action="guardar_horarios.php">
                                <input type="hidden" name="token" value="<?= e($token) ?>">
                                <input type="hidden" name="accion" value="abrir">
                                <input type="hidden" name="fecha" value="<?= e($f) ?>">
                                <button type="submit" class="btn-accion accion-2">Volver a atender</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="sin-acciones">No marcaste días sin atención.</p>
            <?php endif; ?>
        </section>
    </main>
    <script>
    (function () {
        // Pinta la celda al tildar y permite marcar o desmarcar un día entero
        document.querySelectorAll('.celda-hora input').forEach(function (c) {
            c.addEventListener('change', function () { c.parentNode.classList.toggle('on', c.checked); });
        });
        document.querySelectorAll('.col-toggle').forEach(function (b) {
            b.addEventListener('click', function () {
                var cajas = document.querySelectorAll('.celda-hora input[data-dia="' + b.dataset.dia + '"]');
                var todas = Array.prototype.every.call(cajas, function (c) { return c.checked; });
                cajas.forEach(function (c) { c.checked = !todas; c.parentNode.classList.toggle('on', !todas); });
            });
        });
    })();
    </script>
</body>
</html>
