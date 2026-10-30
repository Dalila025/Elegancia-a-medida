<?php
declare(strict_types=1);

/**
 * Taller: etapas de cada trabajo y turnos de prueba — Elegancia a Medida
 *
 * Cuando la clienta acepta un presupuesto (confección o arreglo), el trabajo
 * entra al taller. La modista lo va pasando por etapas (Recibido, Corte,
 * Primera prueba, Confección, Lista para retirar, Terminado) y le da turnos para
 * las pruebas, para que traiga la prenda (arreglos) o para retirar el trabajo. Cada cambio le llega como aviso a la clienta, que ve el avance
 * en su solicitud (seguimiento.php). Al llegar a «Terminado», la solicitud
 * pasa sola a «Completado».
 *
 * Tablas: etapas_taller, avances_taller, turnos_prueba (+ solicitudes_presupuesto,
 * presupuestos, notificaciones). La incluye funciones.php.
 */

/** Etapas del taller en orden: [id => fila]. */
function obtener_etapas(PDO $pdo): array
{
    $etapas = [];
    foreach ($pdo->query('SELECT * FROM etapas_taller ORDER BY orden') as $f) {
        $etapas[(int) $f['id_etapas_taller']] = $f;
    }
    return $etapas;
}

/** Historial de etapas de un trabajo, del más viejo al más nuevo. */
function avances_de(PDO $pdo, int $id_sol): array
{
    $stmt = $pdo->prepare('SELECT a.*, e.nombre, e.texto_cliente, e.orden FROM avances_taller a
                             INNER JOIN etapas_taller e ON e.id_etapas_taller = a.id_etapas_taller
                            WHERE a.id_solicitudes_presupuesto = :id ORDER BY a.fecha, a.id_avances_taller');
    $stmt->execute([':id' => $id_sol]);
    return $stmt->fetchAll();
}

/** Turnos de prueba de un trabajo (por fecha). */
function turnos_de(PDO $pdo, int $id_sol): array
{
    $stmt = $pdo->prepare('SELECT * FROM turnos_prueba WHERE id_solicitudes_presupuesto = :id ORDER BY fecha_hora');
    $stmt->execute([':id' => $id_sol]);
    return $stmt->fetchAll();
}

/** Próximo turno programado (desde hoy) o null. */
function proximo_turno(array $turnos): ?array
{
    $hoy = date('Y-m-d 00:00:00');
    foreach ($turnos as $t) {
        if ($t['estado'] === 'programado' && $t['fecha_hora'] >= $hoy) {
            return $t;
        }
    }
    return null;
}

/** Datos del motivo de un turno (TURNO_MOTIVOS), con «prueba» si no se reconoce. */
function motivo_turno(?string $motivo): array
{
    return TURNO_MOTIVOS[$motivo ?? ''] ?? TURNO_MOTIVOS['prueba'];
}

/** Prenda en minúscula para usar dentro de una frase: «Vestido» → «vestido». */
function prenda_en_frase(string $prenda): string
{
    return mb_strtolower(mb_substr($prenda, 0, 1)) . mb_substr($prenda, 1);
}

/** "jueves 05/11 a las 17:00" */
function texto_turno(string $fecha_hora): string
{
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $d = new DateTime($fecha_hora);
    return $dias[(int) $d->format('w')] . ' ' . $d->format('d/m') . ' a las ' . $d->format('H:i');
}

/**
 * Trabajos que están en el taller (solicitudes «En proceso»), con su etapa
 * actual, el próximo turno y la fecha de entrega. Ordenados por entrega.
 */
function trabajos_en_taller(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        "SELECT s.id_solicitudes_presupuesto AS id, s.codigo_seguimiento, s.tipo_prenda, s.descripcion,
                s.id_clientes, s.id_tipos_servicio, c.nombre AS cliente, c.telefono, t.nombre AS servicio,
                p.fecha_entrega, p.monto_total, p.id_presupuestos,
                (SELECT a.id_etapas_taller FROM avances_taller a WHERE a.id_solicitudes_presupuesto = s.id_solicitudes_presupuesto
                  ORDER BY a.fecha DESC, a.id_avances_taller DESC LIMIT 1) AS etapa,
                (SELECT MIN(tp.fecha_hora) FROM turnos_prueba tp WHERE tp.id_solicitudes_presupuesto = s.id_solicitudes_presupuesto
                  AND tp.estado = 'programado' AND tp.fecha_hora >= CURDATE()) AS proximo_turno,
                (SELECT tp.motivo FROM turnos_prueba tp WHERE tp.id_solicitudes_presupuesto = s.id_solicitudes_presupuesto
                  AND tp.estado = 'programado' AND tp.fecha_hora >= CURDATE() ORDER BY tp.fecha_hora LIMIT 1) AS proximo_motivo
           FROM solicitudes_presupuesto s
           INNER JOIN clientes c ON c.id_clientes = s.id_clientes
           INNER JOIN tipos_servicio t ON t.id_tipos_servicio = s.id_tipos_servicio
           INNER JOIN presupuestos p ON p.id_solicitudes_presupuesto = s.id_solicitudes_presupuesto
          WHERE s.id_estados_solicitud = :estado
          ORDER BY p.fecha_entrega, s.id_solicitudes_presupuesto"
    );
    $stmt->execute([':estado' => ESTADO_EN_PROCESO]);
    return $stmt->fetchAll();
}

/** Próximos turnos de todo el taller (agenda), desde hoy. */
function agenda_turnos(PDO $pdo, int $limite = 12): array
{
    // Incluye los turnos solo para tomar medidas (sin trabajo: la clienta sale de tp.id_clientes)
    $stmt = $pdo->prepare("SELECT tp.*, COALESCE(s.tipo_prenda, '') AS tipo_prenda, c.id_clientes,
                                  COALESCE(s.codigo_seguimiento, '') AS codigo_seguimiento, c.nombre AS cliente, c.telefono
                             FROM turnos_prueba tp
                             LEFT JOIN solicitudes_presupuesto s ON s.id_solicitudes_presupuesto = tp.id_solicitudes_presupuesto
                             INNER JOIN clientes c ON c.id_clientes = COALESCE(tp.id_clientes, s.id_clientes)
                            WHERE tp.estado = 'programado' AND tp.fecha_hora >= CURDATE()
                            ORDER BY tp.fecha_hora LIMIT " . $limite);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Aviso a la clienta sobre su trabajo en el taller. */
function avisar_taller(PDO $pdo, int $id_sol, string $titulo, string $mensaje): void
{
    try {
        $pdo->prepare('INSERT INTO notificaciones (id_solicitudes_presupuesto, titulo, mensaje) VALUES (:id, :t, :m)')
            ->execute([':id' => $id_sol, ':t' => mb_substr($titulo, 0, 100), ':m' => mb_substr($mensaje, 0, 500)]);
    } catch (PDOException $e) {
        error_log('No se pudo avisar del taller: ' . $e->getMessage());
    }
}

/** Datos básicos de un trabajo en el taller (o null si no está «En proceso»). */
function trabajo_en_proceso(PDO $pdo, int $id_sol): ?array
{
    $stmt = $pdo->prepare('SELECT id_solicitudes_presupuesto, tipo_prenda, id_estados_solicitud FROM solicitudes_presupuesto WHERE id_solicitudes_presupuesto = :id');
    $stmt->execute([':id' => $id_sol]);
    $s = $stmt->fetch();
    return $s && (int) $s['id_estados_solicitud'] === ESTADO_EN_PROCESO ? $s : null;
}

/**
 * Pasa un trabajo a otra etapa (puede saltear etapas: un arreglo no tiene «Corte»).
 * Si llega a «Terminado», la solicitud queda «Completado».
 * $aviso = [título, mensaje] reemplaza el aviso de siempre a la clienta
 * (se usa cuando ya retiró el trabajo: no hace falta decirle «está listo»).
 * Devuelve [ok, mensaje].
 */
function cambiar_etapa(PDO $pdo, int $id_sol, int $etapa, string $nota, ?array $aviso = null): array
{
    $s = trabajo_en_proceso($pdo, $id_sol);
    if (!$s) {
        return [false, 'Ese trabajo no está en el taller (tiene que estar «En proceso»).'];
    }
    $etapas = obtener_etapas($pdo);
    if (!isset($etapas[$etapa])) {
        return [false, 'Etapa inválida.'];
    }
    $avances = avances_de($pdo, $id_sol);
    $actual = $avances ? (int) end($avances)['id_etapas_taller'] : 0;
    if ($actual === $etapa) {
        return [false, 'El trabajo ya está en «' . $etapas[$etapa]['nombre'] . '».'];
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO avances_taller (id_solicitudes_presupuesto, id_etapas_taller, nota) VALUES (:id, :e, :n)')
            ->execute([':id' => $id_sol, ':e' => $etapa, ':n' => $nota]);
        if ($etapa === ETAPA_TERMINADO) {
            $pdo->prepare('UPDATE solicitudes_presupuesto SET id_estados_solicitud = :c WHERE id_solicitudes_presupuesto = :id AND id_estados_solicitud = :p')
                ->execute([':c' => ESTADO_COMPLETADO, ':id' => $id_sol, ':p' => ESTADO_EN_PROCESO]);
            // Los turnos que quedaron programados ya no hacen falta
            $pdo->prepare("UPDATE turnos_prueba SET estado = 'cancelado' WHERE id_solicitudes_presupuesto = :id AND estado = 'programado' AND fecha_hora >= NOW()")
                ->execute([':id' => $id_sol]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }

    $prenda = prenda_en_frase($s['tipo_prenda']);
    if ($aviso) {
        avisar_taller($pdo, $id_sol, $aviso[0], $aviso[1]);
    } elseif ($etapa === ETAPA_TERMINADO) {
        notificar($pdo, $id_sol, ESTADO_COMPLETADO);   // «Tu pedido está listo»
    } else {
        avisar_taller($pdo, $id_sol, 'Tu ' . $prenda . ': ' . $etapas[$etapa]['nombre'],
                      $etapas[$etapa]['texto_cliente'] . ($nota !== '' ? ' ' . $nota : ''));
    }
    return [true, '«' . $s['tipo_prenda'] . '» pasó a ' . $etapas[$etapa]['nombre'] . '.'
                  . ($etapa === ETAPA_TERMINADO ? ' La solicitud quedó Completada y le avisamos a la clienta.' : ' Le avisamos a la clienta.')];
}

/**
 * Da un turno: para probarse, para que la clienta traiga la prenda (arreglos;
 * ese día se la prueba para marcar el arreglo) o para retirar (se lo prueba
 * y, si queda bien, se lo lleva). Con $medidas se aprovecha el turno para
 * tomarle las medidas. $fecha y $hora como vienen del formulario.
 * Devuelve [ok, mensaje].
 */
function agendar_turno(PDO $pdo, int $id_sol, string $fecha, string $hora, string $nota, string $motivo = 'prueba', bool $medidas = false): array
{
    $s = trabajo_en_proceso($pdo, $id_sol);
    if (!$s) {
        return [false, 'Ese trabajo no está en el taller.'];
    }
    if (!in_array($motivo, TURNO_MOTIVOS_TALLER, true)) {
        return [false, 'Elegí para qué es el turno.'];
    }
    $dt = DateTime::createFromFormat('!Y-m-d H:i', $fecha . ' ' . $hora);
    if (!$dt || $dt->format('Y-m-d H:i') !== $fecha . ' ' . $hora) {
        return [false, 'Indicá el día y la hora del turno.'];
    }
    if ($dt < new DateTime()) {
        return [false, 'El turno tiene que ser más adelante que ahora.'];
    }
    if ($dt > (new DateTime('today'))->modify('+' . TURNO_ANTICIPO_DIAS . ' days')) {
        return [false, 'El turno puede ser como mucho dentro de ' . TURNO_ANTICIPO_DIAS . ' días.'];
    }
    if ($hora < TURNO_HORA_MIN || $hora > TURNO_HORA_MAX) {
        return [false, 'El horario tiene que estar entre las ' . TURNO_HORA_MIN . ' y las ' . TURNO_HORA_MAX . '.'];
    }
    // Aviso si ya hay otro turno a esa misma hora
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM turnos_prueba WHERE estado = 'programado' AND fecha_hora = :f");
    $stmt->execute([':f' => $dt->format('Y-m-d H:i:00')]);
    if ((int) $stmt->fetchColumn() > 0) {
        return [false, 'Ya tenés otro turno ese día a esa hora. Elegí otro horario.'];
    }
    $medidas = $medidas && $motivo !== 'retirar';
    $pdo->prepare('INSERT INTO turnos_prueba (id_solicitudes_presupuesto, fecha_hora, motivo, tomar_medidas, nota) VALUES (:id, :f, :m, :tm, :n)')
        ->execute([':id' => $id_sol, ':f' => $dt->format('Y-m-d H:i:00'), ':m' => $motivo, ':tm' => (int) $medidas, ':n' => $nota]);
    $cuando = texto_turno($dt->format('Y-m-d H:i:s'));
    $m = motivo_turno($motivo);
    avisar_taller($pdo, $id_sol, $m['aviso'],
                  strtr($m['texto'], ['{cuando}' => $cuando, '{prenda}' => prenda_en_frase($s['tipo_prenda'])])
                  . ($medidas ? ' ' . TURNO_TEXTO_MEDIDAS : '')
                  . ($nota !== '' ? ' ' . $nota : ''));
    return [true, 'Turno «' . $m['nombre'] . '» agendado para el ' . $cuando . ($medidas ? ' (con toma de medidas)' : '') . '. Le avisamos a la clienta.'];
}

/**
 * Cierra un turno. $accion:
 *   realizado  la clienta vino. Si era para traer la prenda y el trabajo no
 *              había empezado, pasa solo a «Recibido».
 *   cancelado  no se hace; se le avisa a la clienta.
 *   retiro     (turno para retirar) se lo probó, quedó bien y se lo llevó:
 *              el trabajo pasa a «Terminado» y la solicitud queda Completada.
 *   retoque    (turno para retirar) se lo probó y hay que ajustar algo:
 *              vuelve a «Confección»; después se le da otro turno para retirar.
 * Devuelve [ok, mensaje, adónde ir después (o '')]: si en el turno había que
 * tomarle las medidas, se va a la ficha de la clienta para cargarlas.
 */
function cerrar_turno(PDO $pdo, int $id_turno, string $accion, string $nota = ''): array
{
    if (!in_array($accion, ['realizado', 'cancelado', 'retiro', 'retoque'], true)) {
        return [false, 'Acción inválida.', ''];
    }
    $stmt = $pdo->prepare("SELECT tp.*, COALESCE(tp.id_clientes, s.id_clientes) AS id_clientes, COALESCE(s.tipo_prenda, '') AS tipo_prenda
                             FROM turnos_prueba tp
                             LEFT JOIN solicitudes_presupuesto s ON s.id_solicitudes_presupuesto = tp.id_solicitudes_presupuesto
                            WHERE tp.id_turnos_prueba = :id");
    $stmt->execute([':id' => $id_turno]);
    $t = $stmt->fetch();
    if (!$t || $t['estado'] !== 'programado') {
        return [false, 'Ese turno ya no está programado.', ''];
    }
    $es_retiro = $t['motivo'] === 'retirar';
    if (in_array($accion, ['retiro', 'retoque'], true) !== $es_retiro && $accion !== 'cancelado') {
        return [false, $es_retiro ? 'Indicá si se lo llevó o si hay que retocarlo.' : 'Acción inválida para este turno.', ''];
    }
    $id_sol = (int) $t['id_solicitudes_presupuesto'];
    $prenda = prenda_en_frase($t['tipo_prenda']);
    if ($t['motivo'] === 'medidas') {   // turno sin trabajo: solo tomar medidas
        if ($accion === 'cancelado') {
            $pdo->prepare("UPDATE turnos_prueba SET estado = 'cancelado' WHERE id_turnos_prueba = :id")->execute([':id' => $id_turno]);
            return [true, 'Turno cancelado. Avisale a la clienta por WhatsApp.', ''];
        }
        if ($accion !== 'realizado') {
            return [false, 'Acción inválida para este turno.', ''];
        }
        $pdo->prepare("UPDATE turnos_prueba SET estado = 'realizado' WHERE id_turnos_prueba = :id")->execute([':id' => $id_turno]);
        return [true, 'Turno realizado. Cargá las medidas que le tomaste.', 'cliente.php?id=' . (int) $t['id_clientes'] . '#medidas'];
    }
    $pdo->prepare('UPDATE turnos_prueba SET estado = :e, resultado = :r WHERE id_turnos_prueba = :id')
        ->execute([':e' => $accion === 'cancelado' ? 'cancelado' : 'realizado',
                   ':r' => in_array($accion, ['retiro', 'retoque'], true) ? $accion : '', ':id' => $id_turno]);

    if ($accion === 'cancelado') {
        avisar_taller($pdo, $id_sol, 'Se canceló tu turno',
                      'El turno del ' . texto_turno($t['fecha_hora']) . ' (' . mb_strtolower(motivo_turno($t['motivo'])['nombre'])
                      . ') quedó cancelado. Te avisamos el nuevo turno.');
        return [true, 'Turno cancelado. Le avisamos a la clienta.', ''];
    }

    $en_taller = trabajo_en_proceso($pdo, $id_sol) !== null;
    if ($accion === 'retiro') {
        if ($en_taller) {
            cambiar_etapa($pdo, $id_sol, ETAPA_TERMINADO, '', ['¡Gracias por retirar tu ' . $prenda . '!',
                'Nos alegra que te haya quedado bien. Cualquier cosa que necesites, escribinos.']);
        }
        return [true, 'Listo: se llevó su ' . $prenda . '. El trabajo quedó Terminado y la solicitud Completada.', ''];
    }
    if ($accion === 'retoque') {
        $texto = 'Al probártela vimos que hay que ajustar un detalle. Te avisamos cuando esté para retirar.'
               . ($nota !== '' ? ' ' . $nota : '');
        $avances = avances_de($pdo, $id_sol);
        $actual = $avances ? (int) end($avances)['id_etapas_taller'] : 0;
        if ($en_taller && $actual !== ETAPA_CONFECCION) {
            cambiar_etapa($pdo, $id_sol, ETAPA_CONFECCION, $nota, ['Tu ' . $prenda . ': un retoque más', $texto]);
        } else {
            avisar_taller($pdo, $id_sol, 'Tu ' . $prenda . ': un retoque más', $texto);
        }
        return [true, 'Anotado: hay que retocar su ' . $prenda . '. Volvió a «Confección»; cuando esté, dale otro turno para retirar.', ''];
    }

    // realizado
    $msg = 'Turno marcado como realizado.';
    if ($t['motivo'] === 'traer' && $en_taller && !avances_de($pdo, $id_sol)) {
        cambiar_etapa($pdo, $id_sol, ETAPA_RECIBIDO, '');
        $msg = 'Turno marcado como realizado. El trabajo pasó a «Recibido» y le avisamos a la clienta.';
    }
    if ((int) $t['tomar_medidas'] === 1) {
        return [true, $msg . ' Cargá las medidas que le tomaste.', 'cliente.php?id=' . (int) $t['id_clientes'] . '#medidas'];
    }
    return [true, $msg, ''];
}


// =====================================================
// HORARIOS DE RECEPCIÓN Y TURNOS QUE ELIGE LA CLIENTA
// =====================================================

/** Horarios de la semana: [dia_semana => ['10:00', '10:30', …]]. */
function horarios_recepcion(PDO $pdo): array
{
    $h = [];
    foreach ($pdo->query('SELECT dia_semana, TIME_FORMAT(hora, "%H:%i") AS hora FROM horarios_recepcion ORDER BY dia_semana, hora') as $f) {
        $h[(int) $f['dia_semana']][] = $f['hora'];
    }
    return $h;
}

/** Días sin atención desde hoy: [fecha => motivo]. */
function dias_sin_atencion(PDO $pdo): array
{
    $d = [];
    foreach ($pdo->query('SELECT fecha, motivo FROM dias_sin_atencion WHERE fecha >= CURDATE() ORDER BY fecha') as $f) {
        $d[$f['fecha']] = $f['motivo'];
    }
    return $d;
}

/** Horas de la grilla del panel: 08:00, 08:30 … hasta antes de TURNO_HORA_MAX. */
function grilla_horas(): array
{
    $horas = [];
    $t = DateTime::createFromFormat('H:i', TURNO_HORA_MIN);
    $fin = DateTime::createFromFormat('H:i', TURNO_HORA_MAX);
    while ($t < $fin) {
        $horas[] = $t->format('H:i');
        $t->modify('+' . HORARIO_PASO_MIN . ' minutes');
    }
    return $horas;
}

/**
 * Horarios libres para que la clienta elija, de las próximas RESERVA_DIAS:
 * los de la semana que cargó la modista, menos los días sin atención,
 * los que ya pasaron (o empiezan en menos de RESERVA_ANTICIPO_MIN) y los
 * que ya tienen un turno. Devuelve [['fecha' => 'Y-m-d', 'horas' => [...]], …].
 */
function horarios_libres(PDO $pdo, int $dias = RESERVA_DIAS): array
{
    $semana = horarios_recepcion($pdo);
    if (!$semana) {
        return [];
    }
    $cerrado = dias_sin_atencion($pdo);
    $ocupados = [];
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(fecha_hora, '%Y-%m-%d %H:%i') FROM turnos_prueba
                            WHERE estado = 'programado' AND fecha_hora >= CURDATE() AND fecha_hora < CURDATE() + INTERVAL :d DAY");
    $stmt->bindValue(':d', $dias + 1, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $o) {
        $ocupados[$o] = true;
    }
    $desde = (new DateTime())->modify('+' . RESERVA_ANTICIPO_MIN . ' minutes')->format('Y-m-d H:i');
    $libres = [];
    $dia = new DateTime('today');
    for ($i = 0; $i <= $dias; $i++, $dia->modify('+1 day')) {
        $f = $dia->format('Y-m-d');
        if (isset($cerrado[$f])) {
            continue;
        }
        $horas = [];
        foreach ($semana[(int) $dia->format('w')] ?? [] as $h) {
            if ("$f $h" > $desde && !isset($ocupados["$f $h"])) {
                $horas[] = $h;
            }
        }
        if ($horas) {
            $libres[] = ['fecha' => $f, 'horas' => $horas];
        }
    }
    return $libres;
}

/** «Jueves 30/10» */
function texto_dia(string $fecha): string
{
    $d = new DateTime($fecha);
    return DIAS_SEMANA[(int) $d->format('w')] . ' ' . $d->format('d/m');
}

/** Etapa actual de un trabajo (0 si todavía no empezó). */
function etapa_actual(PDO $pdo, int $id_sol): int
{
    $stmt = $pdo->prepare('SELECT id_etapas_taller FROM avances_taller WHERE id_solicitudes_presupuesto = :id
                            ORDER BY fecha DESC, id_avances_taller DESC LIMIT 1');
    $stmt->execute([':id' => $id_sol]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

/**
 * ¿Qué turno puede elegir la clienta según la etapa del trabajo?
 *   sin empezar          → 'traer'   (lleva la prenda, se la prueba, le toman medidas)
 *   Lista para retirar   → 'retirar' (se la prueba y, si queda bien, se la lleva)
 *   en cualquier otra    → null      (los turnos los da la modista)
 */
function turno_que_elige(int $etapa): ?string
{
    return match ($etapa) {
        0                  => 'traer',
        ETAPA_PRUEBA_FINAL => 'retirar',
        default            => null,
    };
}

/** Lo que falta pagar de un trabajo (total del presupuesto menos los pagos aprobados). */
function saldo_trabajo(PDO $pdo, int $id_sol): float
{
    $stmt = $pdo->prepare("SELECT p.monto_total - COALESCE((SELECT SUM(pg.monto) FROM pagos pg
                                    WHERE pg.id_presupuestos = p.id_presupuestos AND pg.estado = 'aprobado'), 0)
                             FROM presupuestos p WHERE p.id_solicitudes_presupuesto = :id");
    $stmt->execute([':id' => $id_sol]);
    return max(0.0, round((float) $stmt->fetchColumn(), 2));
}

/**
 * La clienta elige un horario libre: para acercar la prenda (y que le tomen
 * las medidas) o, cuando la prenda está lista, para retirarla. Si ya tenía
 * uno programado para lo mismo, lo cambia. Devuelve [ok, mensaje].
 */
function reservar_turno_cliente(PDO $pdo, string $codigo, int $id_cliente, string $fecha_hora): array
{
    $stmt = $pdo->prepare('SELECT id_solicitudes_presupuesto, tipo_prenda, id_tipos_servicio, id_estados_solicitud
                             FROM solicitudes_presupuesto WHERE codigo_seguimiento = :c AND id_clientes = :cl');
    $stmt->execute([':c' => $codigo, ':cl' => $id_cliente]);
    $s = $stmt->fetch();
    if (!$s || (int) $s['id_estados_solicitud'] !== ESTADO_EN_PROCESO) {
        return [false, 'Solo podés elegir un horario cuando aceptaste el presupuesto.'];
    }
    $id_sol = (int) $s['id_solicitudes_presupuesto'];
    $motivo = turno_que_elige(etapa_actual($pdo, $id_sol));
    if ($motivo === null) {
        return [false, 'La modista ya tiene tu prenda y está trabajando. Cuando haga falta, te da un turno.'];
    }
    $dt = DateTime::createFromFormat('!Y-m-d H:i', $fecha_hora);
    if (!$dt || $dt->format('Y-m-d H:i') !== $fecha_hora) {
        return [false, 'Elegí un día y un horario de la lista.'];
    }
    $f = $dt->format('Y-m-d');
    $h = $dt->format('H:i');

    $pdo->beginTransaction();
    try {
        // Bloquea la agenda para que dos clientas no tomen el mismo horario a la vez
        $pdo->query('SELECT id_horarios_recepcion FROM horarios_recepcion FOR UPDATE')->fetchAll();
        $libre = false;
        foreach (horarios_libres($pdo) as $d) {
            if ($d['fecha'] === $f && in_array($h, $d['horas'], true)) {
                $libre = true;
                break;
            }
        }
        if (!$libre) {
            $pdo->rollBack();
            return [false, 'Ese horario ya no está disponible. Elegí otro.'];
        }
        // Si ya tenía un turno para lo mismo, se reemplaza
        $previo = $pdo->prepare("SELECT id_turnos_prueba, fecha_hora FROM turnos_prueba
                                  WHERE id_solicitudes_presupuesto = :id AND motivo = :m AND estado = 'programado'");
        $previo->execute([':id' => $id_sol, ':m' => $motivo]);
        $viejo = $previo->fetch();
        if ($viejo) {
            $pdo->prepare("UPDATE turnos_prueba SET estado = 'cancelado' WHERE id_turnos_prueba = :id")
                ->execute([':id' => $viejo['id_turnos_prueba']]);
        }
        $pdo->prepare("INSERT INTO turnos_prueba (id_solicitudes_presupuesto, fecha_hora, motivo, tomar_medidas, origen)
                       VALUES (:id, :f, :m, :tm, 'clienta')")
            ->execute([':id' => $id_sol, ':f' => "$f $h:00", ':m' => $motivo, ':tm' => (int) ($motivo === 'traer')]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }

    $cuando = texto_turno("$f $h:00");
    $prenda = prenda_en_frase($s['tipo_prenda']);
    if ($motivo === 'retirar') {
        $saldo = saldo_trabajo($pdo, $id_sol);
        avisar_taller($pdo, $id_sol, $viejo ? 'Cambiaste tu turno para retirar' : 'No te olvides de retirar tu ' . $prenda,
                      'Te esperamos el ' . $cuando . ' para retirar tu ' . $prenda . ': te la probás y, si te queda bien, te la llevás.'
                      . ($saldo > 0 ? ' Saldo a pagar: ' . pesos($saldo) . '.' : ''));
    } else {
        $es_arreglo = (int) $s['id_tipos_servicio'] === SERVICIO_ARREGLOS;
        avisar_taller($pdo, $id_sol, $viejo ? 'Cambiaste tu turno' : 'Reservaste tu turno',
                      'Te esperamos el ' . $cuando . ($es_arreglo
                          ? ' con tu ' . $prenda . '. Te la probás para marcar el arreglo'
                          : ' en el taller para charlar tu ' . $prenda)
                      . ' y te tomamos las medidas. Si querés, ese día podés dejar la seña.'
                      . (($m = materiales_de_solicitud($pdo, $id_sol))['quien'] === 'clienta' && $m['filas']
                          ? ' Llevá también los materiales: ' . implode(', ', array_map('texto_material', $m['filas'])) . '.' : ''));
    }
    return [true, ($viejo ? 'Cambiaste tu turno: ' : '¡Listo! Te esperamos el ') . $cuando . '.'];
}

/**
 * Mensaje de WhatsApp ya armado de la clienta para la modista.
 * $motivo = turno que corresponde ('traer' o 'retirar'; '' si no aplica), $turno = el que tiene o null.
 */
function wa_clienta_a_modista(string $nombre, string $prenda, string $codigo, string $motivo, ?array $turno): string
{
    $yo = 'Hola! Soy ' . explode(' ', trim($nombre))[0] . '. ';
    $p  = prenda_en_frase($prenda) . ' (código ' . $codigo . ')';
    if ($turno) {
        return $yo . 'Te escribo por mi turno del ' . texto_turno($turno['fecha_hora']) . ' para '
             . ['traer' => 'llevarte mi ', 'retirar' => 'retirar mi ', 'prueba' => 'probarme mi '][$turno['motivo']] . $p . '. ';
    }
    return $yo . match ($motivo) {
        'retirar' => 'Vi que ya terminaste mi ' . $p . '. ¿Cuándo paso a retirar?',
        'traer'   => 'Acepté el presupuesto de mi ' . $p . '. ¿Qué día y horario me puedo acercar?',
        default   => 'Te escribo por mi ' . $p . '.',
    };
}

/** Mensaje de WhatsApp ya armado de la modista para la clienta (recordatorio de turno o «ya está lista»). */
function wa_modista_a_clienta(string $nombre, string $prenda, string $codigo, ?array $turno, float $saldo): string
{
    $hola = 'Hola ' . explode(' ', trim($nombre))[0] . '! Te escribo de Elegancia a Medida. ';
    $p = prenda_en_frase($prenda);
    $pagar = $saldo > 0 ? ' El saldo a pagar es ' . pesos($saldo) . '.' : '';
    $link = SITIO_URL !== '' ? ' Lo ves en ' . rtrim(SITIO_URL, '/') . '/seguimiento.php?codigo=' . $codigo : '';
    if (!$turno) {
        return $hola . '¡Ya terminé tu ' . $p . '! 🎉 Entrá a tu solicitud en la web y elegí cuándo pasar a probártela y retirar, o decime qué día te queda bien.' . $pagar . $link;
    }
    $cuando = texto_turno($turno['fecha_hora']);
    if ($turno['motivo'] === 'medidas') {
        return $hola . 'Te recuerdo que el ' . $cuando . ' te espero en el taller para tomarte las medidas. ¡Gracias!';
    }
    return $hola . match ($turno['motivo']) {
        'retirar' => 'Te recuerdo que el ' . $cuando . ' te espero para que retires tu ' . $p . '. Te la probás y, si te queda bien, te la llevás.' . $pagar,
        'traer'   => 'Te recuerdo que el ' . $cuando . ' te espero con tu ' . $p . ' para probártela' . ((int) $turno['tomar_medidas'] === 1 ? ' y tomarte las medidas' : '') . '.',
        default   => 'Te recuerdo tu prueba del ' . $cuando . ' para tu ' . $p . '.',
    } . ' ¡Gracias!';
}

/**
 * Recordatorios para mostrar arriba en el sitio a la clienta con sesión:
 * turnos de hoy a RECORDATORIO_DIAS días, y prendas listas sin turno para retirar.
 * Devuelve [['texto' => …, 'codigo' => …], …].
 */
function recordatorios_cliente(PDO $pdo, int $id_cliente): array
{
    $r = [];
    $stmt = $pdo->prepare("SELECT tp.fecha_hora, tp.motivo, s.tipo_prenda, s.codigo_seguimiento
                             FROM turnos_prueba tp
                             INNER JOIN solicitudes_presupuesto s ON s.id_solicitudes_presupuesto = tp.id_solicitudes_presupuesto
                            WHERE s.id_clientes = :c AND tp.estado = 'programado'
                              AND tp.fecha_hora >= NOW() AND tp.fecha_hora < CURDATE() + INTERVAL :d DAY
                            ORDER BY tp.fecha_hora");
    $stmt->bindValue(':c', $id_cliente, PDO::PARAM_INT);
    $stmt->bindValue(':d', RECORDATORIO_DIAS + 1, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt as $t) {
        $dia = substr($t['fecha_hora'], 0, 10);
        $cuando = $dia === date('Y-m-d') ? 'hoy a las ' . substr($t['fecha_hora'], 11, 5)
                : ($dia === date('Y-m-d', strtotime('+1 day')) ? 'mañana a las ' . substr($t['fecha_hora'], 11, 5) : 'el ' . texto_turno($t['fecha_hora']));
        $p = prenda_en_frase($t['tipo_prenda']);
        $r[] = ['url' => 'seguimiento.php?codigo=' . rawurlencode($t['codigo_seguimiento']) . '#turno', 'texto' => match ($t['motivo']) {
            'retirar' => "No te olvides de retirar tu $p $cuando.",
            'traer'   => "No te olvides: $cuando traés tu $p al taller.",
            default   => "No te olvides: $cuando tenés la prueba de tu $p.",
        }];
    }
    // Prendas listas para retirar que todavía no tienen turno
    $stmt = $pdo->prepare("SELECT s.tipo_prenda, s.codigo_seguimiento
                             FROM solicitudes_presupuesto s
                            WHERE s.id_clientes = :c AND s.id_estados_solicitud = :ep
                              AND (SELECT a.id_etapas_taller FROM avances_taller a WHERE a.id_solicitudes_presupuesto = s.id_solicitudes_presupuesto
                                    ORDER BY a.fecha DESC, a.id_avances_taller DESC LIMIT 1) = :lista
                              AND NOT EXISTS (SELECT 1 FROM turnos_prueba tp WHERE tp.id_solicitudes_presupuesto = s.id_solicitudes_presupuesto
                                               AND tp.motivo = 'retirar' AND tp.estado = 'programado' AND tp.fecha_hora >= NOW())");
    $stmt->execute([':c' => $id_cliente, ':ep' => ESTADO_EN_PROCESO, ':lista' => ETAPA_PRUEBA_FINAL]);
    foreach ($stmt as $s) {
        $r[] = ['url' => 'seguimiento.php?codigo=' . rawurlencode($s['codigo_seguimiento']) . '#turno',
                'texto' => '¡Terminamos tu ' . prenda_en_frase($s['tipo_prenda']) . '! Elegí cuándo pasar a retirar.'];
    }
    // Turno para tomarse las medidas
    $tm = turno_medidas_cliente($pdo, $id_cliente);
    if ($tm && $tm['fecha_hora'] < date('Y-m-d', strtotime('+' . (RECORDATORIO_DIAS + 1) . ' days'))) {
        $r[] = ['url' => 'mis_medidas.php#turno-medidas',
                'texto' => 'No te olvides: ' . str_replace('HOY', 'hoy', cuando_turno($tm['fecha_hora'])) . ' vas al taller a que te tomen las medidas.'];
    }
    return $r;
}

/** Guarda los horarios de la semana. $marcados = ['1|10:00', '6|11:30', …]. Devuelve cuántos quedaron. */
function guardar_horarios(PDO $pdo, array $marcados): int
{
    $validas = array_flip(grilla_horas());
    $filas = [];
    foreach ($marcados as $m) {
        if (!is_string($m) || !preg_match('/^([0-6])\|(\d{2}:\d{2})$/', $m, $p) || !isset($validas[$p[2]])) {
            continue;
        }
        $filas[$p[1] . '|' . $p[2]] = [(int) $p[1], $p[2] . ':00'];
    }
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM horarios_recepcion');
        $ins = $pdo->prepare('INSERT INTO horarios_recepcion (dia_semana, hora) VALUES (:d, :h)');
        foreach ($filas as [$d, $h]) {
            $ins->execute([':d' => $d, ':h' => $h]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    return count($filas);
}

/**
 * Selector de horarios para la clienta (seguimiento.php): cada día con sus
 * horarios libres como botones. Envía a reservar_turno.php.
 */
function mostrar_selector_turno(array $libres, string $codigo, string $boton, ?callable $campos_extra = null): void
{
    ?>
    <form method="post" action="reservar_turno.php" class="selector-turno">
        <input type="hidden" name="token" value="<?= e(token_cliente()) ?>">
        <?php if ($codigo !== ''): ?><input type="hidden" name="codigo" value="<?= e($codigo) ?>"><?php endif; ?>
        <?php if ($campos_extra) { $campos_extra(); } ?>
        <div class="st-dias">
            <?php foreach ($libres as $d): ?>
                <fieldset class="st-dia">
                    <legend><?= e(texto_dia($d['fecha'])) ?></legend>
                    <div class="st-horas">
                        <?php foreach ($d['horas'] as $h): ?>
                            <label class="st-hora">
                                <input type="radio" name="turno" value="<?= e($d['fecha'] . ' ' . $h) ?>" required>
                                <span><?= e($h) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn-pill"><?= e($boton) ?></button>
    </form>
    <?php
}


// =====================================================
// GASTOS DE CADA TRABAJO (gastos.id_solicitudes_presupuesto)
// =====================================================

/** Estados en que se le pueden cargar gastos a un trabajo (ya aceptado). */
const TRABAJO_ESTADOS_CON_GASTOS = [ESTADO_EN_PROCESO, ESTADO_COMPLETADO];

/**
 * Trabajos a los que se les puede asignar un gasto: los que están en el taller
 * y los terminados de los últimos 6 meses (más $incluir, si es otro, para no
 * perder el que ya tiene un gasto que se edita).
 */
function trabajos_para_gastos(PDO $pdo, int $incluir = 0): array
{
    $stmt = $pdo->prepare("SELECT s.id_solicitudes_presupuesto AS id, s.tipo_prenda, s.id_estados_solicitud, c.nombre AS cliente
                             FROM solicitudes_presupuesto s
                             INNER JOIN clientes c ON c.id_clientes = s.id_clientes
                             INNER JOIN presupuestos p ON p.id_solicitudes_presupuesto = s.id_solicitudes_presupuesto
                            WHERE s.id_estados_solicitud = :ep
                               OR (s.id_estados_solicitud = :co AND COALESCE(p.fecha_actualizacion, p.fecha_creacion) >= NOW() - INTERVAL 6 MONTH)
                               OR s.id_solicitudes_presupuesto = :inc
                            ORDER BY s.id_estados_solicitud = :ep2 DESC, s.id_solicitudes_presupuesto DESC");
    $stmt->execute([':ep' => ESTADO_EN_PROCESO, ':co' => ESTADO_COMPLETADO, ':inc' => $incluir, ':ep2' => ESTADO_EN_PROCESO]);
    return $stmt->fetchAll();
}

/** Texto de un trabajo para listas: «Conjunto top y falda — Sofía Ramírez». */
function texto_trabajo(array $t): string
{
    return $t['tipo_prenda'] . ' — ' . $t['cliente']
         . ((int) $t['id_estados_solicitud'] === ESTADO_COMPLETADO ? ' (terminado)' : '');
}

/** ¿Se le pueden cargar gastos a esta solicitud? */
function trabajo_admite_gastos(PDO $pdo, int $id_sol): bool
{
    $stmt = $pdo->prepare('SELECT s.id_estados_solicitud FROM solicitudes_presupuesto s
                             INNER JOIN presupuestos p ON p.id_solicitudes_presupuesto = s.id_solicitudes_presupuesto
                            WHERE s.id_solicitudes_presupuesto = :id');
    $stmt->execute([':id' => $id_sol]);
    $e = $stmt->fetchColumn();
    return $e !== false && in_array((int) $e, TRABAJO_ESTADOS_CON_GASTOS, true);
}

/**
 * Costos de un trabajo: datos, gastos cargados, cuánto se cobra, lo gastado
 * y la ganancia (precio del presupuesto − gastos). null si no existe.
 */
function costos_trabajo(PDO $pdo, int $id_sol): ?array
{
    $stmt = $pdo->prepare('SELECT s.id_solicitudes_presupuesto AS id, s.codigo_seguimiento, s.tipo_prenda, s.id_estados_solicitud,
                                  s.id_clientes, c.nombre AS cliente, t.nombre AS servicio,
                                  p.id_presupuestos, p.monto_total, p.fecha_entrega
                             FROM solicitudes_presupuesto s
                             INNER JOIN clientes c ON c.id_clientes = s.id_clientes
                             INNER JOIN tipos_servicio t ON t.id_tipos_servicio = s.id_tipos_servicio
                             LEFT JOIN presupuestos p ON p.id_solicitudes_presupuesto = s.id_solicitudes_presupuesto
                            WHERE s.id_solicitudes_presupuesto = :id');
    $stmt->execute([':id' => $id_sol]);
    $t = $stmt->fetch();
    if (!$t) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT g.*, r.nombre AS rubro FROM gastos g
                             INNER JOIN categorias_gasto r ON r.id_categorias_gasto = g.id_categorias_gasto
                            WHERE g.id_solicitudes_presupuesto = :id ORDER BY g.fecha, g.id_gastos');
    $stmt->execute([':id' => $id_sol]);
    $gastos = $stmt->fetchAll();
    $total_gastos = array_sum(array_map(fn($g) => (float) $g['monto'], $gastos));
    $precio = $t['monto_total'] !== null ? (float) $t['monto_total'] : 0.0;
    $cobrado = 0.0;
    if ($t['id_presupuestos'] !== null) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE id_presupuestos = :p AND estado = 'aprobado'");
        $stmt->execute([':p' => $t['id_presupuestos']]);
        $cobrado = (float) $stmt->fetchColumn();
    }
    return $t + [
        'gastos'       => $gastos,
        'total_gastos' => $total_gastos,
        'precio'       => $precio,
        'cobrado'      => $cobrado,
        'ganancia'     => $precio - $total_gastos,
    ];
}

/** Total gastado en cada trabajo: [id_solicitud => total]. */
function gastos_por_trabajo(PDO $pdo, array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT id_solicitudes_presupuesto, SUM(monto) FROM gastos
                            WHERE id_solicitudes_presupuesto IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
                            GROUP BY id_solicitudes_presupuesto');
    $stmt->execute($ids);
    return array_map('floatval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
}


// =====================================================
// TURNOS BIEN VISIBLES PARA LA MODISTA (Solicitudes y Taller)
// =====================================================

/** Próximo turno programado de cada trabajo: [id_solicitud => fila de turnos_prueba]. */
function proximos_turnos_por_trabajo(PDO $pdo, array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }
    $stmt = $pdo->prepare("SELECT tp.*, s.tipo_prenda, s.id_clientes, s.codigo_seguimiento, c.nombre AS cliente, c.telefono
                             FROM turnos_prueba tp
                             INNER JOIN solicitudes_presupuesto s ON s.id_solicitudes_presupuesto = tp.id_solicitudes_presupuesto
                             INNER JOIN clientes c ON c.id_clientes = s.id_clientes
                            WHERE tp.estado = 'programado' AND tp.fecha_hora >= CURDATE()
                              AND tp.id_solicitudes_presupuesto IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
                            ORDER BY tp.fecha_hora DESC");
    $stmt->execute($ids);
    $r = [];
    foreach ($stmt->fetchAll() as $t) {
        $r[(int) $t['id_solicitudes_presupuesto']] = $t;   // queda el más cercano (orden descendente)
    }
    return $r;
}

/** «HOY a las 11:30», «mañana a las 17:00» o «jueves 05/11 a las 17:00». */
function cuando_turno(string $fecha_hora): string
{
    $dia = substr($fecha_hora, 0, 10);
    $hora = substr($fecha_hora, 11, 5);
    if ($dia === date('Y-m-d')) {
        return 'HOY a las ' . $hora;
    }
    if ($dia === date('Y-m-d', strtotime('+1 day'))) {
        return 'mañana a las ' . $hora;
    }
    return texto_turno($fecha_hora);
}

/** Lo que la clienta viene a hacer, dicho para la modista. */
function titulo_turno_modista(string $motivo): string
{
    return ['traer' => 'Te trae la prenda', 'retirar' => 'Viene a retirar', 'prueba' => 'Viene a probarse',
            'medidas' => 'Viene a que le tomes las medidas'][$motivo] ?? 'Turno';
}

/**
 * Recuadro destacado de un turno para la modista, con el botón para anotar
 * que la clienta vino (y le trajo la prenda, o se la llevó). $volver = página
 * a la que vuelve después (modista.php o taller.php).
 */
function mostrar_turno_modista(PDO $pdo, array $tu, string $token, string $volver, bool $con_nombre = false): void
{
    $id_turno = (int) $tu['id_turnos_prueba'];
    $es_hoy = substr($tu['fecha_hora'], 0, 10) === date('Y-m-d');
    $nuevo = $tu['origen'] === 'clienta' && strtotime($tu['fecha_creacion']) >= time() - 48 * 3600;
    $saldo = $tu['motivo'] === 'retirar' ? saldo_trabajo($pdo, (int) $tu['id_solicitudes_presupuesto']) : 0.0;
    $wa = enlace_whatsapp_cliente($tu['telefono'] ?? '', wa_modista_a_clienta($tu['cliente'], (string) $tu['tipo_prenda'], (string) $tu['codigo_seguimiento'], $tu, $saldo));
    $boton = function (string $accion, string $texto, string $clase) use ($token, $id_turno, $volver): void { ?>
        <form method="post" action="guardar_taller.php">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="accion" value="turno_<?= e($accion) ?>">
            <input type="hidden" name="id_turno" value="<?= $id_turno ?>">
            <input type="hidden" name="volver" value="<?= e($volver) ?>">
            <button type="submit" class="<?= e($clase) ?>"><?= e($texto) ?></button>
        </form>
    <?php };
    ?>
    <div class="turno-modista motivo-<?= e($tu['motivo']) ?> <?= $es_hoy ? 'es-hoy' : '' ?>">
        <p class="tm-titulo">
            <span aria-hidden="true">📅</span>
            <?= e(titulo_turno_modista($tu['motivo'])) ?>: <strong><?= e(cuando_turno($tu['fecha_hora'])) ?></strong>
            <?php if ($nuevo): ?><span class="tm-nuevo">🆕 Lo eligió la clienta</span><?php endif; ?>
        </p>
        <?php if ($con_nombre): ?><p class="tm-quien"><?= e($tu['cliente']) ?><?= $tu['tipo_prenda'] !== '' ? ' · ' . e($tu['tipo_prenda']) : '' ?></p><?php endif; ?>
        <?php if ($tu['motivo'] === 'medidas' && $tu['nota'] !== ''): ?><p class="tm-nota"><?= e($tu['nota']) ?></p><?php endif; ?>
        <?php if ((int) $tu['tomar_medidas'] === 1 && $tu['motivo'] !== 'medidas'): ?><p class="tm-nota">📏 Aprovechá para tomarle las medidas</p><?php endif; ?>
        <?php if ($tu['motivo'] === 'retirar' && $saldo > 0): ?><p class="tm-nota">💵 Saldo a cobrar: <?= e(pesos($saldo)) ?></p><?php endif; ?>
        <?php if ($tu['motivo'] === 'traer' && $tu['id_solicitudes_presupuesto'] !== null
                  && ($mt = materiales_de_solicitud($pdo, (int) $tu['id_solicitudes_presupuesto']))['quien'] === 'clienta' && $mt['filas']): ?>
            <p class="tm-nota">🧵 Trae también los materiales</p>
        <?php endif; ?>
        <div class="tm-botones">
            <?php
            if ($tu['motivo'] === 'traer') {
                $boton('realizado', '✓ Ya me trajo la prenda', 'tm-ok');
            } elseif ($tu['motivo'] === 'medidas') {
                $boton('realizado', '✓ Ya le tomé las medidas', 'tm-ok');
            } elseif ($tu['motivo'] === 'retirar') {
                $boton('retiro', '✓ Quedó bien: se la llevó', 'tm-ok');
            } else {
                $boton('realizado', '✓ Ya vino a probarse', 'tm-ok');
            }
            ?>
            <?php if ($wa): ?><a class="tm-wa" href="<?= e($wa) ?>" target="_blank" rel="noopener">💬 Recordarle</a><?php endif; ?>
        </div>
    </div>
    <?php
}


/**
 * Etapa que sigue en el recorrido habitual (para el botón «Siguiente»), o null
 * si ya está «Lista para retirar» (ahí se termina cuando la clienta se la lleva).
 * Los arreglos saltean Corte y Primera prueba.
 */
function etapa_siguiente(int $actual, bool $es_arreglo): ?int
{
    foreach ($es_arreglo ? ETAPAS_RECORRIDO_ARREGLO : ETAPAS_RECORRIDO as $e) {
        if ($e > $actual) {
            return $e;
        }
    }
    return null;
}


// =====================================================
// TURNO SOLO PARA TOMAR MEDIDAS (la clienta no sabe medirse)
// =====================================================

/** Turno programado (de hoy en adelante) para tomarle las medidas a una clienta, o null. */
function turno_medidas_cliente(PDO $pdo, int $id_cliente): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM turnos_prueba WHERE id_clientes = :c AND motivo = 'medidas'
                              AND estado = 'programado' AND fecha_hora >= NOW() ORDER BY fecha_hora LIMIT 1");
    $stmt->execute([':c' => $id_cliente]);
    return $stmt->fetch() ?: null;
}

/**
 * La clienta pide un turno para que le tomen las medidas en el taller
 * (desde Mis medidas). Si ya tenía uno, lo cambia. $para = clave de MEDIDAS_PARA.
 * Devuelve [ok, mensaje].
 */
function reservar_turno_medidas(PDO $pdo, int $id_cliente, string $fecha_hora, string $para): array
{
    if (!isset(MEDIDAS_PARA[$para])) {
        return [false, 'Contanos para qué necesitás las medidas.'];
    }
    $dt = DateTime::createFromFormat('!Y-m-d H:i', $fecha_hora);
    if (!$dt || $dt->format('Y-m-d H:i') !== $fecha_hora) {
        return [false, 'Elegí un día y un horario de la lista.'];
    }
    $f = $dt->format('Y-m-d');
    $h = $dt->format('H:i');
    $pdo->beginTransaction();
    try {
        $pdo->query('SELECT id_horarios_recepcion FROM horarios_recepcion FOR UPDATE')->fetchAll();
        $libre = false;
        foreach (horarios_libres($pdo) as $d) {
            if ($d['fecha'] === $f && in_array($h, $d['horas'], true)) { $libre = true; break; }
        }
        if (!$libre) {
            $pdo->rollBack();
            return [false, 'Ese horario ya no está disponible. Elegí otro.'];
        }
        $viejo = turno_medidas_cliente($pdo, $id_cliente);
        if ($viejo) {
            $pdo->prepare("UPDATE turnos_prueba SET estado = 'cancelado' WHERE id_turnos_prueba = :id")
                ->execute([':id' => $viejo['id_turnos_prueba']]);
        }
        $pdo->prepare("INSERT INTO turnos_prueba (id_clientes, fecha_hora, motivo, tomar_medidas, origen, nota)
                       VALUES (:c, :f, 'medidas', 1, 'clienta', :n)")
            ->execute([':c' => $id_cliente, ':f' => "$f $h:00", ':n' => MEDIDAS_PARA[$para]]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    $cuando = texto_turno("$f $h:00");
    return [true, ($viejo ? 'Cambiaste tu turno: ' : '¡Listo! Te esperamos en el taller el ') . $cuando . ' para tomarte las medidas.'];
}

/** La clienta cancela su turno para tomarse las medidas. Devuelve [ok, mensaje]. */
function cancelar_turno_medidas(PDO $pdo, int $id_cliente): array
{
    $t = turno_medidas_cliente($pdo, $id_cliente);
    if (!$t) {
        return [false, 'No tenés un turno para tomarte las medidas.'];
    }
    $pdo->prepare("UPDATE turnos_prueba SET estado = 'cancelado' WHERE id_turnos_prueba = :id AND id_clientes = :c")
        ->execute([':id' => $t['id_turnos_prueba'], ':c' => $id_cliente]);
    return [true, 'Cancelaste tu turno para tomarte las medidas.'];
}
