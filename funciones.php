<?php
declare(strict_types=1);

/**
 * Funciones compartidas — Elegancia a Medida
 *
 * Requiere config.php incluido antes.
 */


/** Escapa texto para imprimirlo en HTML. */
function e(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}


/** Formato de dinero argentino: $ 85.000,00 */
function pesos(float $monto): string
{
    return '$ ' . number_format($monto, 2, ',', '.');
}


/** Porcentaje con signo y sin ceros de más: +40 %, -10 %, 12,5 % */
function porcentaje(float $valor): string
{
    $txt = rtrim(rtrim(number_format(abs($valor), 2, ',', ''), '0'), ',');
    $signo = $valor > 0 ? '+' : ($valor < 0 ? '−' : '');
    return $signo . $txt . ' %';
}


/** Fecha máxima aceptada para fecha_requerida (hoy + FECHA_MAX_DIAS). */
function fecha_maxima(): DateTime
{
    return (new DateTime('today'))->modify('+' . FECHA_MAX_DIAS . ' days');
}


/** Días enteros desde hoy hasta $fecha (0 = hoy). */
function dias_desde_hoy(DateTime $fecha): int
{
    $hoy = new DateTime('today');
    $dias = (int) $hoy->diff($fecha)->format('%r%a');
    return $dias;
}


/**
 * Tipos de servicio desde la tabla tipos_servicio.
 * @return array<int,string> id_tipos_servicio => nombre
 */
function obtener_tipos_servicio(PDO $pdo, bool $solo_activos = true): array
{
    $sql = 'SELECT id_tipos_servicio, nombre FROM tipos_servicio'
         . ($solo_activos ? ' WHERE activo = 1' : '')
         . ' ORDER BY id_tipos_servicio';

    $tipos = [];
    foreach ($pdo->query($sql) as $fila) {
        $tipos[(int) $fila['id_tipos_servicio']] = $fila['nombre'];
    }
    return $tipos;
}


/**
 * Todas las tarifas por plazo, ordenadas de menor a mayor plazo
 * ("sin límite" al final).
 */
function obtener_tarifas(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id_tarifas_plazo, nombre, dias_hasta, recargo_porcentaje
           FROM tarifas_plazo
          ORDER BY dias_hasta IS NULL, dias_hasta'
    )->fetchAll();
}


/**
 * Tarifa que corresponde a un plazo en días:
 * la de menor dias_hasta que sea >= $dias, o la "sin límite".
 * Devuelve null si ninguna cubre ese plazo.
 */
function tarifa_para_plazo(PDO $pdo, int $dias): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id_tarifas_plazo, nombre, dias_hasta, recargo_porcentaje
           FROM tarifas_plazo
          WHERE dias_hasta IS NULL OR dias_hasta >= :dias
          ORDER BY dias_hasta IS NULL, dias_hasta
          LIMIT 1'
    );
    $stmt->execute([':dias' => $dias]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}


/** monto_total = monto_base × (1 + recargo / 100), redondeado a centavos. */
function calcular_total(float $monto_base, float $recargo_porcentaje): float
{
    return round($monto_base * (100 + $recargo_porcentaje) / 100, 2);
}


/** Texto legible para el rango de una tarifa. */
function texto_rango_tarifa(?int $desde, ?int $hasta): string
{
    if ($hasta === null) {
        return $desde === null || $desde === 0 ? 'Cualquier plazo' : "Más de " . ($desde - 1) . " días";
    }
    return ($desde ?? 0) === 0 ? "Hasta $hasta días" : "De $desde a $hasta días";
}


/**
 * Token del panel de la modista (protección CSRF de los formularios POST).
 * No es de un solo uso: la modista hace varios cambios seguidos.
 */
function token_modista(): string
{
    if (empty($_SESSION['token_modista'])) {
        $_SESSION['token_modista'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['token_modista'];
}

function token_modista_valido(): bool
{
    $token = isset($_POST['token']) && is_string($_POST['token']) ? $_POST['token'] : '';
    return $token !== ''
        && !empty($_SESSION['token_modista'])
        && hash_equals($_SESSION['token_modista'], $token);
}


/**
 * URL del panel conservando los filtros activos.
 * Omite los filtros vacíos (0, '' o null) para que la URL quede limpia.
 * Ej.: url_panel(['estado' => 1, 'servicio' => 2, 'q' => '']) → modista.php?estado=1&servicio=2
 */
function url_panel(array $filtros): string
{
    $filtros = array_filter($filtros, fn($v) => $v !== 0 && $v !== '' && $v !== null);
    return 'modista.php' . ($filtros ? '?' . http_build_query($filtros) : '');
}


/** Mensaje de una sola vez para el panel (ok / error). */
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash_modista'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function tomar_flash(): ?array
{
    $f = $_SESSION['flash_modista'] ?? null;
    unset($_SESSION['flash_modista']);
    return $f;
}