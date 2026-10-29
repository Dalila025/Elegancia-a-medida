<?php
declare(strict_types=1);

/**
 * Funciones de la seña y de Mercado Pago — Elegancia a Medida
 *
 * Cómo funciona el pago con Mercado Pago (Checkout Pro):
 *   1. pagar_sena.php crea una fila en `pagos` (estado pendiente) con una
 *      referencia única y le pide a Mercado Pago una "preferencia" de pago.
 *   2. El cliente paga en la página de Mercado Pago.
 *   3. Mercado Pago lo devuelve a pago_resultado.php con el número de pago.
 *   4. El sitio NO confía en lo que dice la dirección: le pregunta a Mercado
 *      Pago por ese pago (con el Access Token) y solo lo da por aprobado si
 *      la referencia, el monto y la moneda coinciden.
 *
 * En MODO SIMULADO (sin mercadopago_config.php) los pasos 1 a 3 los hace
 * pago_simulado.php, sin internet, para poder hacer la demo.
 *
 * Tabla: pagos (+ pedidos, presupuestos, solicitudes_presupuesto, notificaciones).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';


// =====================================================
// CÁLCULO DE LA SEÑA
// =====================================================

/** Seña que corresponde a un total (SENA_PORCENTAJE %), redondeada a centavos. */
function sena_requerida(float $total): float
{
    return round($total * SENA_PORCENTAJE / 100, 2);
}

/**
 * Estado de la seña de un pedido o de un presupuesto.
 *   $tipo: 'pedido' (id = id_pedidos) o 'presupuesto' (id = id_presupuestos).
 *   $total: total del trabajo, o null si todavía no se sabe ("a consultar").
 * Devuelve: requerida, pagado, falta (null si no hay total), cubierta (bool)
 * y la lista de pagos aprobados.
 */
function estado_sena(PDO $pdo, string $tipo, int $id, ?float $total): array
{
    $col = $tipo === 'pedido' ? 'id_pedidos' : 'id_presupuestos';
    $stmt = $pdo->prepare("SELECT * FROM pagos WHERE $col = :id AND estado = 'aprobado' AND concepto = 'sena'
                            ORDER BY fecha_pago, id_pagos");
    $stmt->execute([':id' => $id]);
    $aprobados = $stmt->fetchAll();
    $pagado = round(array_sum(array_map(fn($p) => (float) $p['monto'], $aprobados)), 2);

    // Todo lo pagado (seña + saldos), para mostrar cuánto queda
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE $col = :id AND estado = 'aprobado'");
    $stmt->execute([':id' => $id]);
    $pagado_total = round((float) $stmt->fetchColumn(), 2);

    $requerida = $total !== null && $total > 0 ? sena_requerida($total) : null;
    $falta = $requerida !== null ? max(0.0, round($requerida - $pagado, 2)) : null;

    return [
        'requerida' => $requerida,
        'pagado'    => $pagado,
        'pagado_total' => $pagado_total,
        'falta'     => $falta,
        'cubierta'  => $requerida !== null && $falta <= 0.0,
        'pagos'     => $aprobados,
    ];
}

/** Total de un pedido para calcular la seña (null si tiene precios "a consultar"). */
function total_pedido_para_sena(array $pedido): ?float
{
    return (int) $pedido['tiene_a_consultar'] === 1 ? null : (float) $pedido['total_estimado'];
}


// =====================================================
// A QUÉ SE LE PAGA LA SEÑA ("objetivo")
// =====================================================

/**
 * Busca el pedido o la solicitud de ESTE cliente a la que se le paga la seña.
 *   $tipo: 'pedido' o 'solicitud'; $codigo: código de 8 letras.
 * Devuelve null si no existe, no es del cliente o no está en un estado que lleve seña.
 * Datos devueltos: tipo ('pedido'|'presupuesto'), id, codigo, total, titulo, volver.
 */
function objetivo_sena(PDO $pdo, string $tipo, string $codigo, int $id_cliente): ?array
{
    if ($tipo === 'pedido') {
        $stmt = $pdo->prepare('SELECT id_pedidos, codigo, id_estados_pedido, total_estimado, tiene_a_consultar
                                 FROM pedidos WHERE codigo = :c AND id_clientes = :cli');
        $stmt->execute([':c' => $codigo, ':cli' => $id_cliente]);
        $p = $stmt->fetch();
        if (!$p || !in_array((int) $p['id_estados_pedido'], PEDIDO_ESTADOS_CON_SENA, true)) {
            return null;
        }
        return [
            'tipo'   => 'pedido',
            'id'     => (int) $p['id_pedidos'],
            'codigo' => $p['codigo'],
            'total'  => total_pedido_para_sena($p),
            'titulo' => 'Seña ' . SENA_PORCENTAJE . ' % — Pedido ' . formatear_codigo($p['codigo']),
            'volver' => 'mis_pedidos.php?codigo=' . $p['codigo'] . '#pedido-' . $p['codigo'],
        ];
    }

    if ($tipo === 'solicitud') {
        $stmt = $pdo->prepare('SELECT s.codigo_seguimiento, s.tipo_prenda, s.id_estados_solicitud,
                                      p.id_presupuestos, p.monto_total
                                 FROM solicitudes_presupuesto s
                                 INNER JOIN presupuestos p ON p.id_solicitudes_presupuesto = s.id_solicitudes_presupuesto
                                WHERE s.codigo_seguimiento = :c AND s.id_clientes = :cli');
        $stmt->execute([':c' => $codigo, ':cli' => $id_cliente]);
        $s = $stmt->fetch();
        if (!$s || !in_array((int) $s['id_estados_solicitud'], SOLICITUD_ESTADOS_CON_SENA, true)) {
            return null;
        }
        return [
            'tipo'   => 'presupuesto',
            'id'     => (int) $s['id_presupuestos'],
            'codigo' => $s['codigo_seguimiento'],
            'total'  => (float) $s['monto_total'],
            'titulo' => 'Seña ' . SENA_PORCENTAJE . ' % — ' . mb_substr($s['tipo_prenda'], 0, 60),
            'volver' => 'seguimiento.php?codigo=' . $s['codigo_seguimiento'],
        ];
    }
    return null;
}

/** Página a la que vuelve el cliente después de pagar, según la fila de pagos. */
function destino_pago(PDO $pdo, array $pago): string
{
    if ($pago['id_pedidos'] !== null) {
        $stmt = $pdo->prepare('SELECT codigo FROM pedidos WHERE id_pedidos = :id');
        $stmt->execute([':id' => $pago['id_pedidos']]);
        $c = (string) $stmt->fetchColumn();
        return 'mis_pedidos.php?codigo=' . $c . '#pedido-' . $c;
    }
    $stmt = $pdo->prepare('SELECT s.codigo_seguimiento FROM presupuestos p
                             INNER JOIN solicitudes_presupuesto s ON s.id_solicitudes_presupuesto = p.id_solicitudes_presupuesto
                            WHERE p.id_presupuestos = :id');
    $stmt->execute([':id' => $pago['id_presupuestos']]);
    return 'seguimiento.php?codigo=' . (string) $stmt->fetchColumn();
}


// =====================================================
// FILAS DE LA TABLA pagos
// =====================================================

/** Referencia única que viaja a Mercado Pago (24 caracteres, ej: EAM-3f9a…). */
function generar_referencia_pago(): string
{
    return 'EAM-' . bin2hex(random_bytes(10));
}

/**
 * Crea (o reutiliza) el pago pendiente de la seña. Si ya hay uno pendiente
 * con el mismo monto y método, se reutiliza para no llenar la tabla.
 */
function crear_pago_pendiente(PDO $pdo, array $obj, float $monto, string $metodo): array
{
    $col = $obj['tipo'] === 'pedido' ? 'id_pedidos' : 'id_presupuestos';
    $stmt = $pdo->prepare("SELECT * FROM pagos WHERE $col = :id AND estado = 'pendiente' AND metodo = :m
                              AND monto = :monto AND concepto = 'sena' ORDER BY id_pagos DESC LIMIT 1");
    $stmt->execute([':id' => $obj['id'], ':m' => $metodo, ':monto' => $monto]);
    if ($fila = $stmt->fetch()) {
        return $fila;
    }

    $ref = generar_referencia_pago();
    $pdo->prepare("INSERT INTO pagos ($col, concepto, monto, metodo, estado, referencia)
                   VALUES (:id, 'sena', :monto, :m, 'pendiente', :ref)")
        ->execute([':id' => $obj['id'], ':monto' => $monto, ':m' => $metodo, ':ref' => $ref]);
    return obtener_pago_por_referencia($pdo, $ref);
}

function obtener_pago_por_referencia(PDO $pdo, string $ref): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM pagos WHERE referencia = :r');
    $stmt->execute([':r' => $ref]);
    return $stmt->fetch() ?: null;
}

/**
 * Cambia el estado de un pago. Si pasa a aprobado, guarda la fecha y avisa al
 * cliente. Es "idempotente": si llega dos veces el mismo resultado
 * (por la vuelta del cliente y por el webhook), no duplica nada.
 */
function marcar_pago(PDO $pdo, int $id_pago, string $estado, ?string $payment_id = null, string $detalle = ''): void
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM pagos WHERE id_pagos = :id FOR UPDATE');
        $stmt->execute([':id' => $id_pago]);
        $pago = $stmt->fetch();
        if (!$pago) {
            $pdo->rollBack();
            return;
        }
        $antes = $pago['estado'];
        $pdo->prepare('UPDATE pagos SET estado = :e, mp_payment_id = COALESCE(:pid, mp_payment_id),
                              mp_estado_detalle = :d,
                              fecha_pago = IF(:e2 = \'aprobado\', COALESCE(fecha_pago, NOW()), fecha_pago)
                        WHERE id_pagos = :id')
            ->execute([':e' => $estado, ':pid' => $payment_id, ':d' => mb_substr($detalle, 0, 60),
                       ':e2' => $estado, ':id' => $id_pago]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    if ($antes !== 'aprobado' && $estado === 'aprobado') {
        notificar_pago_aprobado($pdo, $pago);
    }
}

/** Aviso al cliente: «Recibimos tu seña». */
function notificar_pago_aprobado(PDO $pdo, array $pago): void
{
    try {
        $monto = pesos((float) $pago['monto']);
        if ($pago['id_pedidos'] !== null) {
            $stmt = $pdo->prepare('SELECT codigo FROM pedidos WHERE id_pedidos = :id');
            $stmt->execute([':id' => $pago['id_pedidos']]);
            $cod = formatear_codigo((string) $stmt->fetchColumn());
            $pdo->prepare('INSERT INTO notificaciones (id_pedidos, titulo, mensaje) VALUES (:id, :t, :m)')
                ->execute([':id' => $pago['id_pedidos'], ':t' => 'Recibimos tu seña',
                           ':m' => "Registramos la seña de $monto del pedido $cod. ¡Gracias!"]);
        } else {
            $stmt = $pdo->prepare('SELECT id_solicitudes_presupuesto FROM presupuestos WHERE id_presupuestos = :id');
            $stmt->execute([':id' => $pago['id_presupuestos']]);
            $id_sol = (int) $stmt->fetchColumn();
            $pdo->prepare('INSERT INTO notificaciones (id_solicitudes_presupuesto, titulo, mensaje) VALUES (:id, :t, :m)')
                ->execute([':id' => $id_sol, ':t' => 'Recibimos tu seña',
                           ':m' => "Registramos la seña de $monto. ¡Ya reservamos tu turno de trabajo!"]);
        }
    } catch (PDOException $e) {
        error_log('No se pudo avisar el pago ' . $pago['id_pagos'] . ': ' . $e->getMessage());
    }
}


// =====================================================
// MERCADO PAGO (API REST, sin librerías externas)
// =====================================================

/** Dirección pública del sitio (para que Mercado Pago sepa a dónde volver). */
function url_sitio(): string
{
    if (SITIO_URL !== '') {
        return rtrim(SITIO_URL, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

/** ¿El sitio está en la propia computadora? (Mercado Pago no puede llamar a localhost.) */
function sitio_es_local(): bool
{
    $host = parse_url(url_sitio(), PHP_URL_HOST) ?: '';
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.local');
}

/** ¿Está todo listo para cobrar con Mercado Pago de verdad? */
function mp_configurado(): bool
{
    return MP_MODO === 'mercadopago' && MP_ACCESS_TOKEN !== '' && function_exists('curl_init');
}

/**
 * Llamada a la API de Mercado Pago.
 * Devuelve ['ok' => bool, 'status' => código HTTP, 'datos' => array, 'error' => texto].
 */
function mp_api(string $metodo, string $ruta, ?array $cuerpo = null): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'datos' => [], 'error' => 'Falta la extensión curl de PHP.'];
    }
    $ch = curl_init(rtrim(MP_API_URL, '/') . $ruta);
    $cabeceras = [
        'Authorization: Bearer ' . MP_ACCESS_TOKEN,
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    if ($metodo === 'POST') {
        $cabeceras[] = 'X-Idempotency-Key: ' . bin2hex(random_bytes(16));
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_HTTPHEADER     => $cabeceras,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,   // nunca se desactiva: protege el Access Token
    ]);
    // XAMPP en Windows a veces no sabe dónde están los certificados.
    $ca = MP_CA_BUNDLE !== '' ? MP_CA_BUNDLE : (ini_get('curl.cainfo') ?: '');
    if ($ca === '' && is_file('C:/xampp/apache/bin/curl-ca-bundle.crt')) {
        $ca = 'C:/xampp/apache/bin/curl-ca-bundle.crt';
    }
    if ($ca !== '' && is_file($ca)) {
        curl_setopt($ch, CURLOPT_CAINFO, $ca);
    }
    if ($cuerpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    $resp   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error  = $resp === false ? curl_error($ch) : '';
    curl_close($ch);

    $datos = is_string($resp) ? (json_decode($resp, true) ?: []) : [];
    if ($error !== '') {
        error_log('Mercado Pago (conexión): ' . $error);
    } elseif ($status >= 400) {
        error_log('Mercado Pago (' . $status . ' en ' . $ruta . '): ' . substr((string) $resp, 0, 500));
    }
    return ['ok' => $error === '' && $status >= 200 && $status < 300, 'status' => $status, 'datos' => $datos, 'error' => $error];
}

/**
 * Crea la "preferencia" de pago (Checkout Pro) y devuelve la dirección de
 * Mercado Pago a la que hay que mandar al cliente.
 */
function mp_crear_preferencia(array $obj, array $pago, array $cliente): array
{
    $base = url_sitio();
    $vuelta = $base . '/pago_resultado.php';
    $pref = [
        'items' => [[
            'id'          => $pago['referencia'],
            'title'       => $obj['titulo'],
            'quantity'    => 1,
            'currency_id' => 'ARS',
            'unit_price'  => (float) $pago['monto'],
        ]],
        'external_reference' => $pago['referencia'],
        'statement_descriptor' => 'ELEGANCIA MEDIDA',
        'back_urls'          => ['success' => $vuelta, 'failure' => $vuelta, 'pending' => $vuelta],
    ];
    // Mercado Pago no puede volver solo a "localhost" ni avisar a una computadora
    // personal: esas dos opciones se activan solo con el sitio publicado.
    if (!sitio_es_local()) {
        $pref['auto_return'] = 'approved';
        $pref['notification_url'] = $base . '/pago_webhook.php';
    }

    $r = mp_api('POST', '/checkout/preferences', $pref);
    if (!$r['ok']) {
        $msg = $r['status'] === 401 ? 'el Access Token no es válido' :
              ($r['error'] !== '' ? 'no hay conexión con Mercado Pago' : 'Mercado Pago rechazó el pedido de pago');
        return ['ok' => false, 'error' => $msg];
    }
    $url = (MP_USAR_SANDBOX && !empty($r['datos']['sandbox_init_point']))
         ? $r['datos']['sandbox_init_point']
         : ($r['datos']['init_point'] ?? '');
    if ($url === '' || !str_starts_with($url, 'https://')) {
        return ['ok' => false, 'error' => 'Mercado Pago no devolvió la dirección de pago'];
    }
    return ['ok' => true, 'url' => $url, 'preference_id' => (string) ($r['datos']['id'] ?? '')];
}

/** Datos de un pago de Mercado Pago (o null si no existe / no hay conexión). */
function mp_obtener_pago(string $payment_id): ?array
{
    if (!preg_match('/^\d{1,20}$/', $payment_id)) {
        return null;
    }
    $r = mp_api('GET', '/v1/payments/' . $payment_id);
    return $r['ok'] ? $r['datos'] : null;
}

/** Último pago de Mercado Pago con esta referencia (para el botón «Ya pagué, verificar»). */
function mp_buscar_por_referencia(string $ref): ?array
{
    $r = mp_api('GET', '/v1/payments/search?sort=date_created&criteria=desc&external_reference=' . rawurlencode($ref));
    if (!$r['ok'] || empty($r['datos']['results'])) {
        return null;
    }
    // Si hay varios intentos, vale el aprobado; si no, el más reciente.
    foreach ($r['datos']['results'] as $p) {
        if (($p['status'] ?? '') === 'approved') {
            return $p;
        }
    }
    return $r['datos']['results'][0];
}

/** Traduce el estado de Mercado Pago al de la tabla pagos. */
function estado_desde_mp(string $status): string
{
    return match ($status) {
        'approved' => 'aprobado',
        'rejected', 'cancelled', 'refunded', 'charged_back' => 'rechazado',
        default => 'pendiente',   // pending, in_process, authorized, in_mediation…
    };
}

/**
 * Aplica un pago que vino de Mercado Pago a la fila correspondiente.
 * Solo lo acepta si coinciden referencia, moneda y monto.
 * Devuelve la fila de pagos (actualizada) o null si no corresponde a ninguna.
 */
function aplicar_pago_mp(PDO $pdo, array $mp): ?array
{
    $ref  = (string) ($mp['external_reference'] ?? '');
    $pago = $ref !== '' ? obtener_pago_por_referencia($pdo, $ref) : null;
    if (!$pago || $pago['metodo'] !== 'mercadopago') {
        return null;
    }
    $monto_ok  = abs((float) ($mp['transaction_amount'] ?? 0) - (float) $pago['monto']) < 0.01;
    $moneda_ok = ($mp['currency_id'] ?? '') === 'ARS';
    if (!$monto_ok || !$moneda_ok) {
        error_log('Pago ' . ($mp['id'] ?? '?') . ' no coincide con la referencia ' . $ref . ' (monto o moneda).');
        return null;
    }
    $payment_id = (string) ($mp['id'] ?? '');
    // Un mismo pago de Mercado Pago no puede servir para dos señas.
    $stmt = $pdo->prepare('SELECT id_pagos FROM pagos WHERE mp_payment_id = :p AND id_pagos <> :id');
    $stmt->execute([':p' => $payment_id, ':id' => $pago['id_pagos']]);
    if ($stmt->fetchColumn()) {
        return null;
    }
    // Un pago que la modista ya devolvió no se toca más.
    if ($pago['estado'] === 'devuelto') {
        return $pago;
    }
    // Un pago ya aprobado no vuelve a "pendiente" por un aviso viejo que llegue tarde.
    $nuevo = estado_desde_mp((string) ($mp['status'] ?? ''));
    if ($pago['estado'] === 'aprobado' && $nuevo === 'pendiente') {
        return $pago;
    }
    marcar_pago($pdo, (int) $pago['id_pagos'], $nuevo, $payment_id, (string) ($mp['status_detail'] ?? ''));
    return obtener_pago_por_referencia($pdo, $ref);
}

/** Texto para el cliente según el resultado. */
function mensaje_resultado_pago(string $estado): array
{
    return match ($estado) {
        'aprobado'  => ['ok', '¡Listo! Recibimos tu seña. Ya está reservado.'],
        'rechazado' => ['error', 'El pago no se aprobó. Podés intentar de nuevo con otro medio de pago.'],
        default     => ['info', 'Tu pago está en proceso. Cuando Mercado Pago lo apruebe, lo vas a ver acá (podés tocar «Ya pagué, verificar»).'],
    };
}


// =====================================================
// PERMISOS Y VISTA
// =====================================================

/** Id del cliente dueño de un pago (para que nadie vea pagos ajenos). */
function cliente_del_pago(PDO $pdo, array $pago): int
{
    if ($pago['id_pedidos'] !== null) {
        $stmt = $pdo->prepare('SELECT id_clientes FROM pedidos WHERE id_pedidos = :id');
        $stmt->execute([':id' => $pago['id_pedidos']]);
    } else {
        $stmt = $pdo->prepare('SELECT s.id_clientes FROM presupuestos p
                                 INNER JOIN solicitudes_presupuesto s ON s.id_solicitudes_presupuesto = p.id_solicitudes_presupuesto
                                WHERE p.id_presupuestos = :id');
        $stmt->execute([':id' => $pago['id_presupuestos']]);
    }
    return (int) $stmt->fetchColumn();
}

/** ¿Hay un pago de Mercado Pago empezado (pendiente) para esta seña? */
function hay_pago_mp_pendiente(PDO $pdo, string $tipo, int $id): bool
{
    $col = $tipo === 'pedido' ? 'id_pedidos' : 'id_presupuestos';
    $stmt = $pdo->prepare("SELECT 1 FROM pagos WHERE $col = :id AND metodo = 'mercadopago' AND estado = 'pendiente' LIMIT 1");
    $stmt->execute([':id' => $id]);
    return (bool) $stmt->fetchColumn();
}

/** Nombre del modo de cobro, para el panel. */
function modo_cobro_texto(): string
{
    if (MP_MODO !== 'mercadopago') {
        return 'Simulado (pantalla de prueba, sin Mercado Pago)';
    }
    if (!mp_configurado()) {
        return 'Mercado Pago SIN CONFIGURAR (falta el Access Token o la extensión curl)';
    }
    // Ojo: las credenciales de una "cuenta de prueba vendedora" también empiezan con APP_USR-.
    $fin = substr(MP_ACCESS_TOKEN, -6);
    return str_starts_with(MP_ACCESS_TOKEN, 'TEST-')
        ? 'Mercado Pago — credenciales de PRUEBA (TEST-…' . $fin . ')'
        : 'Mercado Pago — Access Token APP_USR-…' . $fin . ' (de prueba si es de la cuenta vendedora de prueba)';
}

/**
 * Recuadro de la seña que ve el cliente (en Mis pedidos y en su solicitud).
 *   $tipo_form: 'pedido' o 'solicitud' (lo que se manda a pagar_sena.php).
 */
function mostrar_caja_sena(string $tipo_form, string $codigo, ?float $total, array $sena, string $token, bool $mp_pendiente): void
{
    if ($sena['requerida'] === null) {
        return;
    }
    $ultimo = $sena['pagos'] ? end($sena['pagos']) : null;
    ?>
    <div class="caja-sena <?= $sena['cubierta'] ? 'pagada' : '' ?>">
        <?php if ($sena['cubierta']): ?>
            <?php $resta = $total !== null ? max(0, round($total - $sena['pagado_total'], 2)) : null; ?>
            <p class="sena-titulo"><?= $resta !== null && $resta < 0.01 ? '✓ Pagado completo: ' . e(pesos($sena['pagado_total'])) : '✓ Seña pagada: ' . e(pesos($sena['pagado'])) ?></p>
            <p class="sena-texto">
                <?= e(PAGO_METODOS[$ultimo['metodo']] ?? '') ?>
                <?= $ultimo['fecha_pago'] ? ' · ' . (new DateTime($ultimo['fecha_pago']))->format('d/m/Y H:i') : '' ?>
                <?= $ultimo['mp_payment_id'] && $ultimo['metodo'] === 'mercadopago' ? ' · Operación N° ' . e($ultimo['mp_payment_id']) : '' ?>
                <?php if ($resta !== null && $resta > 0): ?><br>Saldo a pagar al retirar: <strong><?= e(pesos($resta)) ?></strong><?php if ($sena['pagado_total'] > $sena['pagado']): ?> (ya pagaste <?= e(pesos($sena['pagado_total'])) ?> en total)<?php endif; ?><?php endif; ?>
            </p>
        <?php else: ?>
            <p class="sena-titulo">Seña del <?= SENA_PORCENTAJE ?> %: <?= e(pesos($sena['falta'])) ?></p>
            <p class="sena-texto">
                <?php if ($tipo_form === 'solicitud'): ?>
                    Si querés, dejala ahora; si no, la podés pagar cuando te acerques al taller.
                <?php else: ?>
                    Para reservarlo, pagá la seña.
                <?php endif; ?>
                El resto (<?= e(pesos(max(0, (float) $total - $sena['requerida']))) ?>) se paga al retirar.
                <?php if ($sena['pagado'] > 0): ?><br>Ya pagaste <?= e(pesos($sena['pagado'])) ?>.<?php endif; ?>
            </p>
            <div class="sena-botones">
                <form method="post" action="pagar_sena.php">
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <input type="hidden" name="tipo" value="<?= e($tipo_form) ?>">
                    <input type="hidden" name="codigo" value="<?= e($codigo) ?>">
                    <button type="submit" class="btn-mp">
                        <?= MP_MODO === 'mercadopago' ? 'Pagar seña con Mercado Pago' : 'Pagar seña (simulado)' ?>
                    </button>
                </form>
                <?php if ($mp_pendiente): ?>
                    <form method="post" action="verificar_pago.php">
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <input type="hidden" name="tipo" value="<?= e($tipo_form) ?>">
                        <input type="hidden" name="codigo" value="<?= e($codigo) ?>">
                        <button type="submit" class="btn-pill borde">Ya pagué, verificar</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php if (MP_MODO !== 'mercadopago'): ?>
                <p class="sena-nota">Modo de prueba: el pago se simula dentro del sitio y no se cobra dinero.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}
