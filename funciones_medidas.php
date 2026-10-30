<?php
declare(strict_types=1);

/**
 * Guía de talles y medidas de las clientas — Elegancia a Medida
 *
 * Idea: en la ropa de fiesta el talle por edad no sirve (cada cuerpo es
 * distinto). Por eso la modista carga, para cada prenda, una guía de talles
 * con medidas del cuerpo en centímetros (como en las tiendas online grandes),
 * y la clienta carga sus propias medidas. Con eso el sitio le recomienda el
 * talle y le avisa si algo le puede quedar justo; si ningún talle le queda
 * bien, puede pedir la prenda «A medida» y sus medidas viajan con el pedido.
 *
 * Tablas: talles_producto, medidas_clientes. Constantes en config.php.
 * Este archivo lo incluye funciones.php, así que está disponible en todo el sitio.
 */


/** Número de centímetros para mostrar: 88 → "88", 88.5 → "88,5". */
function cm(float $valor): string
{
    return rtrim(rtrim(number_format($valor, 1, ',', ''), '0'), ',');
}

/**
 * Lee una medida escrita en un formulario ("88", "88,5", "88.5").
 * Vacío = NULL (no se cargó). Devuelve [valor|null, error|null].
 */
function leer_medida(string $texto, string $nombre): array
{
    $t = str_replace(',', '.', trim($texto));
    if ($t === '') {
        return [null, null];
    }
    if (!preg_match('/^\d{1,3}(\.\d)?$/', $t) || (float) $t < MEDIDA_MIN_CM || (float) $t > MEDIDA_MAX_CM) {
        return [null, "$nombre: escribí los centímetros (entre " . MEDIDA_MIN_CM . ' y ' . MEDIDA_MAX_CM . ', con un decimal como máximo).'];
    }
    return [round((float) $t, 1), null];
}


// =====================================================
// GUÍA DE TALLES DE UNA PRENDA
// =====================================================

/** Talles de una prenda, en orden (cada uno con sus medidas en cm). */
function talles_de_producto(PDO $pdo, int $id_producto): array
{
    $stmt = $pdo->prepare('SELECT * FROM talles_producto WHERE id_productos = :id ORDER BY orden, id_talles_producto');
    $stmt->execute([':id' => $id_producto]);
    return $stmt->fetchAll();
}

/** De una lista de ids de productos, cuáles tienen guía de talles (para las tarjetas). */
function productos_con_talles(PDO $pdo, array $ids): array
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT DISTINCT id_productos FROM talles_producto WHERE id_productos IN ('
                          . implode(',', array_fill(0, count($ids), '?')) . ')');
    $stmt->execute($ids);
    return array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
}

/** Columnas de la guía que tienen al menos un dato (las vacías no se muestran). */
function columnas_con_datos(array $talles): array
{
    $cols = [];
    foreach (array_keys(MEDIDAS_TALLE) as $c) {
        foreach ($talles as $t) {
            if ($t[$c] !== null) {
                $cols[] = $c;
                break;
            }
        }
    }
    return $cols;
}


// =====================================================
// MEDIDAS DE LA CLIENTA
// =====================================================

/** Medidas vigentes de una clienta (la carga más reciente) o null si no cargó. */
function medidas_vigentes(PDO $pdo, int $id_cliente): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM medidas_clientes WHERE id_clientes = :id
                            ORDER BY fecha_carga DESC, id_medidas_clientes DESC LIMIT 1');
    $stmt->execute([':id' => $id_cliente]);
    return $stmt->fetch() ?: null;
}

/** ¿Tiene cargadas las medidas mínimas para pedir algo a medida? */
function medidas_suficientes(?array $m): bool
{
    return $m !== null && $m['busto'] !== null && $m['cintura'] !== null && $m['cadera'] !== null;
}

/** Medidas en una línea, para copiar en el pedido: "Busto 89 · Cintura 71 · …". */
function texto_medidas(array $m): string
{
    $partes = [];
    foreach (MEDIDAS_CLIENTE as $c => [$nombre]) {
        if (isset($m[$c]) && $m[$c] !== null) {
            $partes[] = $nombre . ' ' . cm((float) $m[$c]);
        }
    }
    $texto = $partes ? implode(' · ', $partes) . ' cm' : '';
    if (trim((string) ($m['notas'] ?? '')) !== '') {
        $texto .= ' — ' . trim($m['notas']);
    }
    return mb_substr($texto, 0, 255);
}

/**
 * Guarda una carga nueva de medidas (queda el historial).
 * $datos: lo enviado por el formulario. Devuelve la lista de errores (vacía = se guardó).
 */
function guardar_medidas(PDO $pdo, int $id_cliente, array $datos, string $cargada_por): array
{
    $errores = [];
    $valores = [];
    foreach (MEDIDAS_CLIENTE as $c => [$nombre]) {
        [$valores[$c], $err] = leer_medida(isset($datos[$c]) && is_string($datos[$c]) ? $datos[$c] : '', $nombre);
        if ($err) { $errores[] = $err; }
    }
    $notas = isset($datos['notas']) && is_string($datos['notas']) ? trim(preg_replace('/\s+/', ' ', $datos['notas'])) : '';
    if (mb_strlen($notas) > MEDIDAS_NOTAS_MAX) {
        $errores[] = 'Las notas pueden tener hasta ' . MEDIDAS_NOTAS_MAX . ' caracteres.';
    }
    if (!$errores && count(array_filter($valores, fn($v) => $v !== null)) === 0) {
        $errores[] = 'Cargá al menos una medida.';
    }
    if ($errores) {
        return $errores;
    }
    $cols = array_keys(MEDIDAS_CLIENTE);
    $pdo->prepare('INSERT INTO medidas_clientes (id_clientes, ' . implode(', ', $cols) . ', notas, cargada_por)
                   VALUES (:cli, :' . implode(', :', $cols) . ', :notas, :por)')
        ->execute(array_merge(
            [':cli' => $id_cliente, ':notas' => $notas, ':por' => $cargada_por === 'modista' ? 'modista' : 'cliente'],
            array_combine(array_map(fn($c) => ":$c", $cols), array_values($valores))
        ));
    return [];
}


// =====================================================
// RECOMENDACIÓN DE TALLE
// =====================================================

/**
 * Compara las medidas de la clienta con la guía de talles de una prenda.
 * Elige el talle cuyas medidas están más cerca de las de ella y arma avisos:
 *   - mide MÁS que el talle por más de TALLE_HOLGURA_CM → "te puede quedar justo de …"
 *   - mide MENOS por más de TALLE_SOBRA_CM              → "te puede quedar holgado de …"
 *   - estatura distinta por más de TALLE_ESTATURA_CM    → "corto" / "largo"
 * Devuelve null si no se puede comparar nada (sin guía o sin medidas en común).
 * Si hay avisos fuertes, sugiere pedirla a medida.
 */
function recomendar_talle(array $talles, ?array $medidas): ?array
{
    if (!$talles || $medidas === null) {
        return null;
    }
    $mejor = null;
    foreach ($talles as $t) {
        $suma = 0.0;
        $n = 0;
        foreach (MEDIDAS_COMPARABLES as $c) {
            if ($t[$c] === null || $medidas[$c] === null) {
                continue;
            }
            $dif = abs((float) $medidas[$c] - (float) $t[$c]);
            $suma += $c === 'estatura' ? $dif / 2 : $dif;   // la estatura pesa menos que los contornos
            $n++;
        }
        if ($n > 0 && ($mejor === null || $suma / $n < $mejor['puntaje'])) {
            $mejor = ['fila' => $t, 'puntaje' => $suma / $n, 'comparadas' => $n];
        }
    }
    if ($mejor === null) {
        return null;
    }

    $t = $mejor['fila'];
    $avisos = [];
    $fuerte = false;
    foreach (MEDIDAS_COMPARABLES as $c) {
        if ($t[$c] === null || $medidas[$c] === null) {
            continue;
        }
        $dif = (float) $medidas[$c] - (float) $t[$c];
        $nombre = mb_strtolower(MEDIDAS_TALLE[$c][0]);
        if ($c === 'estatura') {
            if ($dif > TALLE_ESTATURA_CM) {
                $avisos[] = 'Sos ' . cm($dif) . ' cm más alta que la estatura del talle: el largo te puede quedar corto.';
            } elseif ($dif < -TALLE_ESTATURA_CM) {
                $avisos[] = 'Sos ' . cm(-$dif) . ' cm más baja que la estatura del talle: el largo te puede quedar largo (se puede acortar).';
            }
        } elseif ($dif > TALLE_HOLGURA_CM) {
            $avisos[] = 'Te puede quedar justo de ' . $nombre . ' (' . cm($dif) . ' cm más que el talle).';
            $fuerte = $fuerte || $dif > TALLE_HOLGURA_CM * 2;
        } elseif ($dif < -TALLE_SOBRA_CM) {
            $avisos[] = 'Te puede quedar holgado de ' . $nombre . ' (' . cm(-$dif) . ' cm menos que el talle).';
            $fuerte = $fuerte || $dif < -TALLE_SOBRA_CM * 2;
        }
    }
    return [
        'talle'           => $t['talle'],
        'avisos'          => $avisos,
        'sugerir_medida'  => $fuerte,
        'comparadas'      => $mejor['comparadas'],
    ];
}


// =====================================================
// TALLE ELEGIDO AL AGREGAR AL CARRITO
// =====================================================

/**
 * Valida el talle que eligió la clienta para una prenda.
 *   - Prenda sin guía de talles → talle '' (no se elige).
 *   - Con guía → tiene que ser uno de sus talles, o «A medida» (solo en venta,
 *     y con las medidas cargadas).
 * Devuelve [talle normalizado, error|null].
 */
function validar_talle_elegido(PDO $pdo, int $id_producto, string $modalidad, string $talle, int $id_cliente): array
{
    $talles = talles_de_producto($pdo, $id_producto);
    if (!$talles) {
        return ['', null];
    }
    $talle = trim($talle);
    if ($talle === TALLE_A_MEDIDA) {
        if ($modalidad !== 'venta') {
            return ['', 'Las prendas en alquiler se eligen por talle (no se hacen a medida).'];
        }
        if (!medidas_suficientes(medidas_vigentes($pdo, $id_cliente))) {
            return ['', 'Para pedirla a medida, primero pasá por el taller a que te tomen las medidas (lo pedís desde «Mis medidas»).'];
        }
        return [TALLE_A_MEDIDA, null];
    }
    foreach ($talles as $t) {
        if ($t['talle'] === $talle) {
            return [$talle, null];
        }
    }
    return ['', 'Elegí un talle de la guía' . ($modalidad === 'venta' ? ' o «A medida»' : '') . '.'];
}

/** Texto corto del talle para listas: "Talle M", "A medida" o "". */
function texto_talle(string $talle): string
{
    if ($talle === '') {
        return '';
    }
    return $talle === TALLE_A_MEDIDA ? 'A medida' : 'Talle ' . $talle;
}
