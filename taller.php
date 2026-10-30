<?php
declare(strict_types=1);

/**
 * Taller (panel) — Elegancia a Medida
 *
 * Tablero con los trabajos en curso (solicitudes «En proceso»), en columnas
 * según la etapa: Por empezar, Recibido, Corte, Primera prueba, Confección y
 * Lista para retirar. En cada tarjeta la modista puede pasar el trabajo a otra
 * etapa (con una nota para la clienta) o darle un turno: para probarse, para
 * que traiga la prenda (arreglos) o para retirar el trabajo.
 * Arriba está la agenda de los próximos turnos.
 * Guarda en guardar_taller.php. Tablas: etapas_taller, avances_taller, turnos_prueba.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$usuario = requerir_modista($pdo);
$token   = token_modista();
$flash   = tomar_flash();

try {
    $etapas   = obtener_etapas($pdo);
    $trabajos = trabajos_en_taller($pdo);
    $agenda   = agenda_turnos($pdo);
    $gastado  = gastos_por_trabajo($pdo, array_column($trabajos, 'id'));
    $turno_de = proximos_turnos_por_trabajo($pdo, array_column($trabajos, 'id'));
} catch (PDOException $e) {
    error_log('Error en el taller: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudo cargar el taller.');
}

// Columnas: «Por empezar» (sin etapa) y cada etapa menos «Terminado»
$columnas = [0 => 'Por empezar'];
foreach ($etapas as $id => $e) {
    if ($id !== ETAPA_TERMINADO) { $columnas[$id] = $e['nombre']; }
}
$por_columna = array_fill_keys(array_keys($columnas), []);
foreach ($trabajos as $t) {
    $por_columna[(int) ($t['etapa'] ?? 0)][] = $t;
}
$hoy = new DateTime('today');
$pagina_actual = 'taller';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Taller · Panel · Elegancia a Medida</title>
    <link rel="stylesheet" href="<?= asset('css/modista.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/panel.css') ?>">
</head>
<body>
    <?php require __DIR__ . '/encabezado_modista.php'; ?>

    <main class="container contenedor-ancho">
        <?php if ($flash): ?>
            <div class="flash <?= $flash['tipo'] === 'ok' ? 'flash-ok' : 'flash-error' ?>" role="status"><?= e($flash['mensaje']) ?></div>
        <?php endif; ?>

        <section class="tarjeta">
            <div class="pedido-cabeza">
                <div>
                    <h2>Taller</h2>
                    <p class="ayuda" style="margin:0">Los trabajos aceptados (confección y arreglos), según en qué etapa están.
                        Cada cambio de etapa y cada turno le llega como aviso a la clienta. Cuando la clienta acepta, elige uno de tus horarios para acercarse (traer la prenda y que le tomes las medidas). Al pasar a «Terminado», la solicitud queda Completada.</p>
                </div>
                <div class="taller-cabeza-acciones">
                    <span class="estado-badge estado-2"><?= count($trabajos) ?> en curso</span>
                    <a href="horarios.php" class="btn-accion accion-presupuestar">🕒 Horarios para recibir prendas</a>
                </div>
            </div>
        </section>

        <section class="tarjeta">
            <h2>Próximos turnos</h2>
            <?php if (!$agenda): ?>
                <p class="sin-acciones">No hay turnos agendados.</p>
            <?php else: ?>
                <ul class="agenda">
                    <?php foreach ($agenda as $tu): $d = new DateTime($tu['fecha_hora']); ?>
                        <li class="<?= $d->format('Y-m-d') === date('Y-m-d') ? 'hoy' : '' ?>">
                            <div class="agenda-fecha"><strong><?= $d->format('d/m') ?></strong><span><?= $d->format('H:i') ?></span></div>
                            <div class="agenda-info">
                                <span class="motivo-turno motivo-<?= e($tu['motivo']) ?>"><?= e(motivo_turno($tu['motivo'])['nombre']) ?></span>
                                <span><strong><?= e($tu['cliente']) ?></strong> · <?= $tu['motivo'] === 'medidas' ? e($tu['nota'] !== '' ? $tu['nota'] : 'Tomar medidas') : e($tu['tipo_prenda']) ?></span>
                                <?php if ($tu['origen'] === 'clienta'): ?><span class="tc-origen">Lo eligió la clienta</span><?php endif; ?>
                                <?php if ((int) $tu['tomar_medidas'] === 1): ?><span class="tc-medidas">📏 <?= $tu['motivo'] === 'medidas' ? 'Viene solo a que le tomes las medidas' : 'Tomarle las medidas' ?> · <a href="cliente.php?id=<?= (int) $tu['id_clientes'] ?>#medidas">cargarlas</a></span><?php endif; ?>
                                <?php if ($tu['motivo'] === 'retirar'): ?><span class="ref-catalogo">Se lo prueba: si queda bien, se lo lleva.</span><?php endif; ?>
                                <?php if ($tu['nota'] !== '' && $tu['motivo'] !== 'medidas'): ?><span class="ref-catalogo"><?= e($tu['nota']) ?></span><?php endif; ?>
                                <?php
                                $saldo_tu = $tu['motivo'] === 'retirar' ? saldo_trabajo($pdo, (int) $tu['id_solicitudes_presupuesto']) : 0.0;
                                if ($wa_tu = enlace_whatsapp_cliente($tu['telefono'] ?? '', wa_modista_a_clienta($tu['cliente'], $tu['tipo_prenda'], $tu['codigo_seguimiento'], $tu, $saldo_tu))): ?>
                                    <a class="tc-wa" href="<?= e($wa_tu) ?>" target="_blank" rel="noopener">💬 Recordarle por WhatsApp</a>
                                <?php endif; ?>
                            </div>
                            <div class="agenda-acciones">
                                <?php
                                // Botones según para qué es el turno
                                $botones = match ($tu['motivo']) {
                                    'traer'   => ['realizado' => ['Trajo la prenda', 'accion-2']],
                                    'medidas' => ['realizado' => ['Ya le tomé las medidas', 'accion-2']],
                                    'retirar' => ['retiro'    => ['Quedó bien: se lo llevó', 'accion-2']],
                                    default   => ['realizado' => ['Hecho', 'accion-2']],
                                };
                                $botones['cancelado'] = ['Cancelar', 'accion-5'];
                                foreach ($botones as $est => [$txt, $clase]): ?>
                                    <form method="post" action="guardar_taller.php" <?= $est === 'cancelado' ? 'onsubmit="return confirm(\'¿Cancelar este turno? Le avisamos a la clienta.\');"' : '' ?>>
                                        <input type="hidden" name="token" value="<?= e($token) ?>">
                                        <input type="hidden" name="accion" value="turno_<?= $est ?>">
                                        <input type="hidden" name="id_turno" value="<?= (int) $tu['id_turnos_prueba'] ?>">
                                        <button type="submit" class="btn-accion <?= $clase ?>"><?= e($txt) ?></button>
                                    </form>
                                <?php endforeach; ?>
                                <?php if ($tu['motivo'] === 'retirar'): ?>
                                    <details class="retoque">
                                        <summary class="btn-accion accion-presupuestar">Hay que retocar</summary>
                                        <form method="post" action="guardar_taller.php" class="form-panel">
                                            <input type="hidden" name="token" value="<?= e($token) ?>">
                                            <input type="hidden" name="accion" value="turno_retoque">
                                            <input type="hidden" name="id_turno" value="<?= (int) $tu['id_turnos_prueba'] ?>">
                                            <input type="text" name="nota" maxlength="<?= TALLER_NOTA_MAX ?>" placeholder="Qué hay que ajustar (la clienta lo ve)" aria-label="Qué hay que ajustar">
                                            <button type="submit" class="btn-accion accion-presupuestar">Volver a confección</button>
                                        </form>
                                    </details>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <div class="tablero" role="list">
            <?php foreach ($columnas as $col => $nombre): ?>
                <section class="tablero-col" role="listitem" aria-label="<?= e($nombre) ?>">
                    <h3><?= e($nombre) ?> <span class="badge"><?= count($por_columna[$col]) ?></span></h3>
                    <?php if (!$por_columna[$col]): ?><p class="tablero-vacio">—</p><?php endif; ?>
                    <?php foreach ($por_columna[$col] as $t):
                        $idt = (int) $t['id'];
                        $entrega = DateTime::createFromFormat('!Y-m-d', $t['fecha_entrega']);
                        $dias = (int) $hoy->diff($entrega)->format('%r%a');
                        $urgente = $dias <= TALLER_AVISO_DIAS; ?>
                        <article class="trabajo-card <?= $urgente ? 'urgente' : '' ?>">
                            <p class="tc-prenda"><?= e($t['tipo_prenda']) ?></p>
                            <p class="tc-cliente"><a href="cliente.php?id=<?= (int) $t['id_clientes'] ?>"><?= e($t['cliente']) ?></a> · <?= e($t['servicio']) ?></p>
                            <p class="tc-entrega">
                                Entrega <?= $entrega->format('d/m') ?>
                                <span><?= $dias < 0 ? 'atrasado ' . abs($dias) . ' d' : ($dias === 0 ? 'hoy' : 'en ' . $dias . ' d') ?></span>
                            </p>
                            <?php
                            $es_arreglo  = (int) $t['id_tipos_servicio'] === SERVICIO_ARREGLOS;
                            $sin_empezar = $t['etapa'] === null;
                            // Sin empezar y sin turno: la clienta todavía no eligió cuándo acercarse
                            $falta_prenda = $sin_empezar && !$t['proximo_turno'];
                            $motivo_def = $sin_empezar ? 'traer'
                                        : ((int) $t['etapa'] === ETAPA_PRUEBA_FINAL ? 'retirar' : 'prueba'); ?>
                            <?php if (isset($turno_de[$idt])) { mostrar_turno_modista($pdo, $turno_de[$idt], $token, 'taller.php'); } ?>
                            <?php if ($falta_prenda): ?><p class="tc-falta">Todavía no eligió cuándo acercarse</p><?php endif; ?>
                            <?php if ((int) $t['etapa'] === ETAPA_PRUEBA_FINAL && !$t['proximo_turno']):
                                $wa_lista = enlace_whatsapp_cliente($t['telefono'] ?? '',
                                    wa_modista_a_clienta($t['cliente'], $t['tipo_prenda'], $t['codigo_seguimiento'], null, saldo_trabajo($pdo, $idt))); ?>
                                <p class="tc-falta">Lista: todavía no eligió cuándo retirarla</p>
                                <?php if ($wa_lista): ?><p class="tc-link"><a class="tc-wa" href="<?= e($wa_lista) ?>" target="_blank" rel="noopener">💬 Avisale por WhatsApp que ya puede retirar</a></p><?php endif; ?>
                            <?php endif; ?>
                            <p class="tc-link"><a href="cliente.php?id=<?= (int) $t['id_clientes'] ?>#medidas">📏 Medidas de la clienta</a></p>
                            <?php if (($txt_mat = texto_materiales_modista(materiales_de_solicitud($pdo, $idt))) !== ''): ?><p class="tc-materiales"><?= e($txt_mat) ?></p><?php endif; ?>
                            <p class="tc-costos"><a href="trabajo.php?id=<?= $idt ?>">💰 Gastos: <?= e(pesos($gastado[$idt] ?? 0)) ?> · cargar o ver</a></p>
                            <?php
                            // Botón «Siguiente»: pasa a la etapa que sigue con un solo toque.
                            // Si está por empezar y ya tiene turno para traer la prenda, el botón es el del turno.
                            $etapa_act = (int) ($t['etapa'] ?? 0);
                            $sig = etapa_siguiente($etapa_act, $es_arreglo);
                            $tiene_turno_traer = isset($turno_de[$idt]) && $turno_de[$idt]['motivo'] === 'traer';
                            if ($sig !== null && !($sin_empezar && $tiene_turno_traer)): ?>
                                <form method="post" action="guardar_taller.php" class="tc-siguiente">
                                    <input type="hidden" name="token" value="<?= e($token) ?>">
                                    <input type="hidden" name="accion" value="etapa">
                                    <input type="hidden" name="id" value="<?= $idt ?>">
                                    <input type="hidden" name="etapa" value="<?= $sig ?>">
                                    <button type="submit" title="Le avisamos a la clienta">Siguiente: <?= e($etapas[$sig]['nombre']) ?> →</button>
                                </form>
                            <?php elseif ($sig === null): ?>
                                <p class="tc-fin">Cuando se la lleve, tocá «Quedó bien: se la llevó» en su turno.</p>
                            <?php endif; ?>
                            <details class="tc-acciones" <?= $falta_prenda ? 'open' : '' ?>>
                                <summary>Más opciones: otra etapa, nota o turno</summary>
                                <form method="post" action="guardar_taller.php" class="form-panel">
                                    <input type="hidden" name="token" value="<?= e($token) ?>">
                                    <input type="hidden" name="accion" value="etapa">
                                    <input type="hidden" name="id" value="<?= $idt ?>">
                                    <label for="et-<?= $idt ?>">Pasar a</label>
                                    <select id="et-<?= $idt ?>" name="etapa">
                                        <?php foreach ($etapas as $ide => $e): if ($ide === (int) ($t['etapa'] ?? 0)) { continue; } ?>
                                            <option value="<?= $ide ?>" <?= $ide === ($sig ?? ETAPA_TERMINADO) ? 'selected' : '' ?>><?= e($e['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="nota" maxlength="<?= TALLER_NOTA_MAX ?>" placeholder="Nota para la clienta (opcional)" aria-label="Nota para la clienta">
                                    <button type="submit" class="btn-accion accion-2">Guardar etapa</button>
                                </form>
                                <form method="post" action="guardar_taller.php" class="form-panel">
                                    <input type="hidden" name="token" value="<?= e($token) ?>">
                                    <input type="hidden" name="accion" value="turno">
                                    <input type="hidden" name="id" value="<?= $idt ?>">
                                    <label for="mo-<?= $idt ?>">Turno para</label>
                                    <select id="mo-<?= $idt ?>" name="motivo">
                                        <?php foreach (TURNO_MOTIVOS_TALLER as $clave): $m = TURNO_MOTIVOS[$clave]; ?>
                                            <option value="<?= e($clave) ?>" <?= $clave === $motivo_def ? 'selected' : '' ?>><?= e($m['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label class="tc-check"><input type="checkbox" name="medidas" value="1"> Aprovechar para tomarle las medidas</label>
                                    <div class="tc-fecha-hora">
                                        <input type="date" name="fecha" min="<?= date('Y-m-d') ?>" aria-label="Día del turno" required>
                                        <input type="time" name="hora" min="<?= TURNO_HORA_MIN ?>" max="<?= TURNO_HORA_MAX ?>" step="900" aria-label="Hora del turno" required>
                                    </div>
                                    <input type="text" name="nota" maxlength="<?= TALLER_NOTA_MAX ?>" placeholder="Ej: traé los zapatos de la fiesta" aria-label="Nota del turno">
                                    <?php if ($wa_t = enlace_whatsapp_cliente($t['telefono'] ?? '', 'Hola ' . explode(' ', trim($t['cliente']))[0] . '! Te escribo de Elegancia a Medida por tu ' . prenda_en_frase($t['tipo_prenda']) . '. ¿Qué día y horario te queda bien para ' . ['traer' => 'traerme la prenda', 'retirar' => 'pasar a retirarla', 'prueba' => 'venir a probártela'][$motivo_def] . '?')): ?>
                                        <a class="tc-wa" href="<?= e($wa_t) ?>" target="_blank" rel="noopener">Acordar el horario por WhatsApp</a>
                                    <?php endif; ?>
                                    <button type="submit" class="btn-accion accion-presupuestar">Dar turno</button>
                                </form>
                            </details>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>
