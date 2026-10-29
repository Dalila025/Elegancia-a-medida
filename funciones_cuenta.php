<?php
declare(strict_types=1);

/**
 * Cuenta corriente de cada clienta — Elegancia a Medida
 *
 * No hace falta una tabla nueva: la cuenta se arma con lo que ya está guardado.
 *   DEBE  (lo que la clienta tiene que pagar) = cada trabajo confirmado:
 *         - pedidos del carrito confirmados por la modista (total del pedido)
 *         - presupuestos que la clienta aceptó (total del presupuesto)
 *   HABER (lo que pagó) = la tabla pagos con estado «aprobado»
 *         (señas por Mercado Pago o simuladas, y cobros en efectivo/transferencia)
 *   SALDO = debe − haber. Si es negativo, la clienta tiene saldo a favor
 *         (por ejemplo, pagó la seña y después el pedido se rechazó). Cuando la
 *         modista le devuelve la plata, esos pagos pasan a «devuelto» y dejan de contar.
 *
 * Tablas: pedidos, presupuestos, solicitudes_presupuesto, pagos, notificaciones.
 */

require_once __DIR__ . '/funciones_pagos.php';

// Estados en los que el trabajo ya es una deuda de la clienta
const PEDIDO_ESTADOS_CON_CARGO    = [PEDIDO_CONFIRMADO, PEDIDO_LISTO, PEDIDO_ENTREGADO, PEDIDO_DEVUELTO];
const SOLICITUD_ESTADOS_CON_CARGO = [ESTADO_EN_PROCESO, ESTADO_COMPLETADO];


/**
 * Trabajos de una clienta que entran en la cuenta: los que generan deuda
 * y los que, sin generar deuda, tienen algún pago (saldo a favor).
 * Cada uno: tipo ('pedido'|'presupuesto'), id, codigo, titulo, estado, fecha,
 * total (cargo; 0 si no corresponde), pagado, saldo, a_consultar (bool).
 */
function trabajos_cliente(PDO $pdo, int $id_cliente): array
{
    $pagado = function (string $col, int $id) use ($pdo): float {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE $col = :id AND estado = 'aprobado'");
        $stmt->execute([':id' => $id]);
        return (float) $stmt->fetchColumn();
    };
    // Trabajos que tuvieron pagos devueltos: siguen apareciendo (para ver la devolución)
    $tuvo_pagos = function (string $col, int $id) use ($pdo): bool {
        $stmt = $pdo->prepare("SELECT 1 FROM pagos WHERE $col = :id AND estado = 'devuelto' LIMIT 1");
        $stmt->execute([':id' => $id]);
        return (bool) $stmt->fetchColumn();
    };
    $trabajos = [];

    $stmt = $pdo->prepare('SELECT id_pedidos, codigo, id_estados_pedido, total_estimado, tiene_a_consultar,
                                  fecha_pedido, fecha_actualizacion
                             FROM pedidos WHERE id_clientes = :c ORDER BY fecha_pedido');
    $stmt->execute([':c' => $id_cliente]);
    foreach ($stmt->fetchAll() as $p) {
        $est   = (int) $p['id_estados_pedido'];
        $cargo = in_array($est, PEDIDO_ESTADOS_CON_CARGO, true);
        $pag   = $pagado('id_pedidos', (int) $p['id_pedidos']);
        if (!$cargo && $pag <= 0 && !$tuvo_pagos('id_pedidos', (int) $p['id_pedidos'])) {
            continue;
        }
        $a_consultar = $cargo && (int) $p['tiene_a_consultar'] === 1;
        $total = $cargo && !$a_consultar ? (float) $p['total_estimado'] : 0.0;
        $trabajos[] = [
            'tipo' => 'pedido', 'id' => (int) $p['id_pedidos'], 'codigo' => $p['codigo'],
            'titulo' => 'Pedido ' . formatear_codigo($p['codigo']),
            'estado' => ESTADOS_PEDIDO[$est] ?? '', 'activo' => $cargo,
            'fecha' => $p['fecha_actualizacion'] ?: $p['fecha_pedido'],
            'total' => $total, 'pagado' => $pag, 'saldo' => round($total - $pag, 2), 'a_consultar' => $a_consultar,
        ];
    }

    $stmt = $pdo->prepare('SELECT p.id_presupuestos, p.monto_total, p.fecha_respuesta_cliente, p.fecha_creacion,
                                  s.codigo_seguimiento, s.tipo_prenda, s.id_estados_solicitud
                             FROM presupuestos p
                             INNER JOIN solicitudes_presupuesto s ON s.id_solicitudes_presupuesto = p.id_solicitudes_presupuesto
                            WHERE s.id_clientes = :c ORDER BY p.fecha_creacion');
    $stmt->execute([':c' => $id_cliente]);
    foreach ($stmt->fetchAll() as $p) {
        $est   = (int) $p['id_estados_solicitud'];
        $cargo = in_array($est, SOLICITUD_ESTADOS_CON_CARGO, true);
        $pag   = $pagado('id_presupuestos', (int) $p['id_presupuestos']);
        if (!$cargo && $pag <= 0 && !$tuvo_pagos('id_presupuestos', (int) $p['id_presupuestos'])) {
            continue;
        }
        $total = $cargo ? (float) $p['monto_total'] : 0.0;
        $trabajos[] = [
            'tipo' => 'presupuesto', 'id' => (int) $p['id_presupuestos'], 'codigo' => $p['codigo_seguimiento'],
            'titulo' => $p['tipo_prenda'] . ' (' . formatear_codigo($p['codigo_seguimiento']) . ')',
            'estado' => ESTADOS[$est] ?? '', 'activo' => $cargo,
            'fecha' => $p['fecha_respuesta_cliente'] ?: $p['fecha_creacion'],
            'total' => $total, 'pagado' => $pag, 'saldo' => round($total - $pag, 2), 'a_consultar' => false,
        ];
    }

    usort($trabajos, fn($a, $b) => strcmp((string) $a['fecha'], (string) $b['fecha']));
    return $trabajos;
}

/** Totales de la cuenta: debe, haber, saldo (positivo = la clienta debe; negativo = a favor). */
function resumen_cuenta(array $trabajos): array
{
    $debe  = round(array_sum(array_column($trabajos, 'total')), 2);
    $haber = round(array_sum(array_column($trabajos, 'pagado')), 2);
    return ['debe' => $debe, 'haber' => $haber, 'saldo' => round($debe - $haber, 2),
            'a_consultar' => (bool) array_filter($trabajos, fn($t) => $t['a_consultar'])];
}

/**
 * Movimientos de la cuenta en orden de fecha, como un resumen de banco:
 * cada trabajo confirmado suma al DEBE y cada pago aprobado al HABER,
 * con el saldo acumulado después de cada movimiento.
 */
function movimientos_cliente(PDO $pdo, array $trabajos): array
{
    $mov = [];
    foreach ($trabajos as $t) {
        if ($t['total'] > 0) {
            $mov[] = ['fecha' => $t['fecha'], 'concepto' => $t['titulo'] . ($t['tipo'] === 'pedido' ? ' — confirmado' : ' — presupuesto aceptado'),
                      'debe' => $t['total'], 'haber' => 0.0, 'orden' => 0];
        }
        $col = $t['tipo'] === 'pedido' ? 'id_pedidos' : 'id_presupuestos';
        $stmt = $pdo->prepare("SELECT * FROM pagos WHERE $col = :id AND estado IN ('aprobado', 'devuelto') ORDER BY fecha_pago, id_pagos");
        $stmt->execute([':id' => $t['id']]);
        foreach ($stmt->fetchAll() as $p) {
            $mov[] = ['fecha' => $p['fecha_pago'] ?? $p['fecha_creacion'],
                      'concepto' => ($p['concepto'] === 'sena' ? 'Seña' : 'Pago') . ' · ' . $t['titulo'] . ' · ' . (PAGO_METODOS[$p['metodo']] ?? $p['metodo'])
                                    . ($p['observaciones'] !== '' ? ' — ' . $p['observaciones'] : ''),
                      'debe' => 0.0, 'haber' => (float) $p['monto'], 'orden' => 1];
            if ($p['estado'] === 'devuelto') {
                $mov[] = ['fecha' => $p['fecha_devolucion'] ?? $p['fecha_pago'], 'concepto' => 'Devolución · ' . $t['titulo'],
                          'debe' => (float) $p['monto'], 'haber' => 0.0, 'orden' => 2];
            }
        }
    }
    usort($mov, fn($a, $b) => [(string) $a['fecha'], $a['orden']] <=> [(string) $b['fecha'], $b['orden']]);
    $saldo = 0.0;
    foreach ($mov as &$m) {
        $saldo = round($saldo + $m['debe'] - $m['haber'], 2);
        $m['saldo'] = $saldo;
    }
    unset($m);
    return $mov;
}

/** Monto con signo menos delante: "− $ 1.000,00" (pesos() lo pondría después del $). */
function pesos_signo(float $m): string
{
    return ($m < -0.004 ? '− ' : '') . pesos(abs($m));
}

/**
 * Devuelve a la clienta lo que pagó por un trabajo que ya no corresponde
 * (pedido rechazado o cancelado, presupuesto cancelado). Los pagos pasan a
 * «devuelto»: quedan en el historial pero no cuentan como pagados.
 * Si se pagó por Mercado Pago, la devolución de la plata se hace desde la cuenta de Mercado Pago.
 */
function registrar_devolucion(PDO $pdo, string $tipo, int $id): array
{
    $col = $tipo === 'pedido' ? 'id_pedidos' : 'id_presupuestos';
    if ($tipo === 'pedido') {
        $stmt = $pdo->prepare('SELECT id_estados_pedido AS estado FROM pedidos WHERE id_pedidos = :id');
    } else {
        $stmt = $pdo->prepare('SELECT s.id_estados_solicitud AS estado FROM presupuestos p
                                 INNER JOIN solicitudes_presupuesto s USING (id_solicitudes_presupuesto) WHERE p.id_presupuestos = :id');
    }
    $stmt->execute([':id' => $id]);
    $estado = $stmt->fetchColumn();
    $activos = $tipo === 'pedido' ? PEDIDO_ESTADOS_CON_CARGO : SOLICITUD_ESTADOS_CON_CARGO;
    if ($estado === false || in_array((int) $estado, $activos, true)) {
        return [false, 'Solo se devuelve lo pagado de un trabajo rechazado o cancelado.'];
    }
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0), SUM(metodo = 'mercadopago') FROM pagos WHERE $col = :id AND estado = 'aprobado'");
    $stmt->execute([':id' => $id]);
    [$total, $por_mp] = $stmt->fetch(PDO::FETCH_NUM);
    if ((float) $total <= 0) {
        return [false, 'Ese trabajo no tiene pagos para devolver.'];
    }
    $pdo->prepare("UPDATE pagos SET estado = 'devuelto', fecha_devolucion = NOW() WHERE $col = :id AND estado = 'aprobado'")
        ->execute([':id' => $id]);

    $txt = 'Registramos la devolución de ' . pesos((float) $total) . '.';
    try {
        if ($tipo === 'pedido') {
            $pdo->prepare('INSERT INTO notificaciones (id_pedidos, titulo, mensaje) VALUES (:id, :t, :m)')
                ->execute([':id' => $id, ':t' => 'Te devolvimos tu pago', ':m' => $txt]);
        } else {
            $stmt = $pdo->prepare('SELECT id_solicitudes_presupuesto FROM presupuestos WHERE id_presupuestos = :id');
            $stmt->execute([':id' => $id]);
            $pdo->prepare('INSERT INTO notificaciones (id_solicitudes_presupuesto, titulo, mensaje) VALUES (:id, :t, :m)')
                ->execute([':id' => (int) $stmt->fetchColumn(), ':t' => 'Te devolvimos tu pago', ':m' => $txt]);
        }
    } catch (PDOException $e) {
        error_log('No se pudo avisar la devolución: ' . $e->getMessage());
    }
    return [true, 'Se registró la devolución de ' . pesos((float) $total) . '.'
                  . ((int) $por_mp > 0 ? ' Ojo: una parte se pagó por Mercado Pago; devolvé esa plata desde tu cuenta de Mercado Pago.' : '')];
}

/** Texto del saldo: "Debe $ 1.000,00", "A favor $ 500,00" o "Al día". */
function texto_saldo(float $saldo): string
{
    if (abs($saldo) < 0.01) {
        return 'Al día';
    }
    return ($saldo > 0 ? 'Debe ' : 'A favor ') . pesos(abs($saldo));
}

/**
 * Registra un cobro de la modista (efectivo o transferencia) a un trabajo.
 * Si todavía falta parte de la seña, esa parte se guarda como «seña» y el
 * resto como «saldo», así la clienta ve su seña al día.
 * Devuelve [ok, mensaje].
 */
function registrar_cobro(PDO $pdo, string $tipo, int $id, float $monto, string $metodo, string $obs): array
{
    $col = $tipo === 'pedido' ? 'id_pedidos' : 'id_presupuestos';
    $pdo->beginTransaction();
    try {
        // Se bloquea el trabajo mientras se calcula el saldo (dos cobros a la vez no se pisan).
        if ($tipo === 'pedido') {
            $stmt = $pdo->prepare('SELECT id_estados_pedido AS estado, total_estimado, tiene_a_consultar, codigo FROM pedidos WHERE id_pedidos = :id FOR UPDATE');
        } else {
            $stmt = $pdo->prepare('SELECT s.id_estados_solicitud AS estado, p.monto_total, s.codigo_seguimiento AS codigo
                                     FROM presupuestos p INNER JOIN solicitudes_presupuesto s USING (id_solicitudes_presupuesto)
                                    WHERE p.id_presupuestos = :id FOR UPDATE');
        }
        $stmt->execute([':id' => $id]);
        $o = $stmt->fetch();
        $estados = $tipo === 'pedido' ? PEDIDO_ESTADOS_CON_CARGO : SOLICITUD_ESTADOS_CON_CARGO;
        if (!$o || !in_array((int) $o['estado'], $estados, true)) {
            $pdo->rollBack();
            return [false, 'A ese trabajo no le corresponde registrar pagos ahora.'];
        }
        if ($tipo === 'pedido' && (int) $o['tiene_a_consultar'] === 1) {
            $pdo->rollBack();
            return [false, 'El pedido tiene precios «a consultar»: primero hay que definir el total.'];
        }
        $total = $tipo === 'pedido' ? (float) $o['total_estimado'] : (float) $o['monto_total'];

        $stmt = $pdo->prepare("SELECT concepto, COALESCE(SUM(monto), 0) AS s FROM pagos WHERE $col = :id AND estado = 'aprobado' GROUP BY concepto");
        $stmt->execute([':id' => $id]);
        $por = ['sena' => 0.0, 'saldo' => 0.0, 'total' => 0.0];
        foreach ($stmt->fetchAll() as $f) { $por[$f['concepto']] = (float) $f['s']; }
        $saldo = round($total - array_sum($por), 2);
        if ($monto > $saldo + 0.001) {
            $pdo->rollBack();
            return [false, 'El monto supera el saldo de ese trabajo (' . pesos(max(0, $saldo)) . ').'];
        }

        $falta_sena = max(0.0, round(sena_requerida($total) - $por['sena'], 2));
        $partes = [];
        if ($falta_sena > 0) {
            $partes['sena'] = min($monto, $falta_sena);
        }
        if ($monto - ($partes['sena'] ?? 0) > 0.001) {
            $partes['saldo'] = round($monto - ($partes['sena'] ?? 0), 2);
        }
        $ins = $pdo->prepare("INSERT INTO pagos ($col, concepto, monto, metodo, estado, referencia, observaciones, fecha_pago)
                              VALUES (:id, :c, :m, :met, 'aprobado', :ref, :obs, NOW())");
        foreach ($partes as $concepto => $m) {
            $ins->execute([':id' => $id, ':c' => $concepto, ':m' => $m, ':met' => $metodo,
                           ':ref' => generar_referencia_pago(), ':obs' => mb_substr($obs, 0, PAGO_OBSERVACIONES_MAX)]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }

    // Aviso a la clienta
    $queda = round($saldo - $monto, 2);
    $txt = 'Registramos tu pago de ' . pesos($monto) . ' (' . (PAGO_METODOS[$metodo] ?? $metodo) . '). '
         . ($queda > 0 ? 'Te queda un saldo de ' . pesos($queda) . '.' : '¡Ya no tenés saldo pendiente en este trabajo!');
    try {
        if ($tipo === 'pedido') {
            $pdo->prepare('INSERT INTO notificaciones (id_pedidos, titulo, mensaje) VALUES (:id, :t, :m)')
                ->execute([':id' => $id, ':t' => 'Recibimos tu pago', ':m' => $txt]);
        } else {
            $stmt = $pdo->prepare('SELECT id_solicitudes_presupuesto FROM presupuestos WHERE id_presupuestos = :id');
            $stmt->execute([':id' => $id]);
            $pdo->prepare('INSERT INTO notificaciones (id_solicitudes_presupuesto, titulo, mensaje) VALUES (:id, :t, :m)')
                ->execute([':id' => (int) $stmt->fetchColumn(), ':t' => 'Recibimos tu pago', ':m' => $txt]);
        }
    } catch (PDOException $e) {
        error_log('No se pudo avisar el cobro: ' . $e->getMessage());
    }
    return [true, 'Se registró el pago de ' . pesos($monto) . '. ' . ($queda > 0 ? 'Saldo de ese trabajo: ' . pesos($queda) . '.' : 'Ese trabajo quedó pagado.')];
}

/**
 * Saldo de todas las clientas en una sola consulta (para la lista del panel).
 * Devuelve [id_clientes => ['debe' => , 'haber' => , 'saldo' => ]].
 */
function saldos_clientes(PDO $pdo): array
{
    $ep = implode(',', array_map('intval', PEDIDO_ESTADOS_CON_CARGO));
    $es = implode(',', array_map('intval', SOLICITUD_ESTADOS_CON_CARGO));
    $sql = "SELECT c.id_clientes,
                   (SELECT COALESCE(SUM(p.total_estimado), 0) FROM pedidos p
                     WHERE p.id_clientes = c.id_clientes AND p.id_estados_pedido IN ($ep) AND p.tiene_a_consultar = 0)
                 + (SELECT COALESCE(SUM(pr.monto_total), 0) FROM presupuestos pr
                     INNER JOIN solicitudes_presupuesto s ON s.id_solicitudes_presupuesto = pr.id_solicitudes_presupuesto
                     WHERE s.id_clientes = c.id_clientes AND s.id_estados_solicitud IN ($es)) AS debe,
                   (SELECT COALESCE(SUM(pg.monto), 0) FROM pagos pg
                     LEFT JOIN pedidos p ON p.id_pedidos = pg.id_pedidos
                     LEFT JOIN presupuestos pr ON pr.id_presupuestos = pg.id_presupuestos
                     LEFT JOIN solicitudes_presupuesto s ON s.id_solicitudes_presupuesto = pr.id_solicitudes_presupuesto
                     WHERE pg.estado = 'aprobado' AND (p.id_clientes = c.id_clientes OR s.id_clientes = c.id_clientes)) AS haber
              FROM clientes c";
    $out = [];
    foreach ($pdo->query($sql) as $f) {
        $out[(int) $f['id_clientes']] = ['debe' => (float) $f['debe'], 'haber' => (float) $f['haber'],
                                         'saldo' => round((float) $f['debe'] - (float) $f['haber'], 2)];
    }
    return $out;
}
