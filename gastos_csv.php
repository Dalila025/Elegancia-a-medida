<?php
declare(strict_types=1);

/**
 * Descargar los gastos de un mes para abrir en Excel — Elegancia a Medida
 * gastos_csv.php?mes=2026-10 → archivo gastos_2026-10.csv
 * Separado por punto y coma y con coma decimal (como lo espera Excel en Argentina).
 * Tablas: gastos, categorias_gasto.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$usuario = requerir_modista($pdo);

$mes = isset($_GET['mes']) && is_string($_GET['mes']) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $_GET['mes']) ? $_GET['mes'] : date('Y-m');
$ini = $mes . '-01';
$fin = DateTime::createFromFormat('!Y-m-d', $ini)->modify('first day of next month')->format('Y-m-d');

try {
    $stmt = $pdo->prepare('SELECT g.fecha, r.nombre AS rubro, g.descripcion, g.cantidad, g.proveedor, g.medio_pago, g.monto,
                                  CONCAT(s.tipo_prenda, " — ", c.nombre) AS trabajo
                             FROM gastos g INNER JOIN categorias_gasto r ON r.id_categorias_gasto = g.id_categorias_gasto
                             LEFT JOIN solicitudes_presupuesto s ON s.id_solicitudes_presupuesto = g.id_solicitudes_presupuesto
                             LEFT JOIN clientes c ON c.id_clientes = s.id_clientes
                            WHERE g.fecha >= :ini AND g.fecha < :fin ORDER BY g.fecha, g.id_gastos');
    $stmt->execute([':ini' => $ini, ':fin' => $fin]);
    $filas = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error al exportar gastos: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudieron exportar los gastos.');
}

/** Evita que Excel tome un texto como fórmula (=, +, -, @ al principio). */
function celda(string $t): string
{
    return preg_match('/^[=+\-@]/', $t) ? "'" . $t : $t;
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="gastos_' . $mes . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");   // BOM: para que Excel muestre bien las tildes
fputcsv($out, ['Fecha', 'Rubro', 'Detalle', 'Cantidad', 'Dónde', 'Medio de pago', 'Monto', 'Trabajo'], ';');
$total = 0.0;
foreach ($filas as $f) {
    $total += (float) $f['monto'];
    fputcsv($out, [
        DateTime::createFromFormat('!Y-m-d', $f['fecha'])->format('d/m/Y'),
        celda($f['rubro']), celda($f['descripcion']), celda($f['cantidad']), celda($f['proveedor']),
        GASTO_MEDIOS[$f['medio_pago']] ?? $f['medio_pago'],
        number_format((float) $f['monto'], 2, ',', ''),
        celda((string) ($f['trabajo'] ?? '')),
    ], ';');
}
fputcsv($out, ['', '', '', '', '', 'Total', number_format($total, 2, ',', '')], ';');
fclose($out);
