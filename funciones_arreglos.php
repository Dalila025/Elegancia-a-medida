<?php
declare(strict_types=1);

/**
 * Lista de precios de arreglos — Elegancia a Medida
 * Tabla: arreglos. La incluye funciones.php (está disponible en todo el sitio).
 */

/** Arreglos de la lista (solo visibles o todos), por nombre. */
function obtener_arreglos(PDO $pdo, bool $solo_activos = true): array
{
    return $pdo->query('SELECT * FROM arreglos' . ($solo_activos ? ' WHERE activo = 1' : '') . ' ORDER BY nombre')->fetchAll();
}

/** Precio para mostrar: "$ 6.000,00", "desde $ 9.500,00" o "A consultar". */
function precio_arreglo(array $a): string
{
    if ($a['precio'] === null) {
        return 'A consultar';
    }
    return ((int) $a['precio_desde'] === 1 ? 'desde ' : '') . pesos((float) $a['precio']);
}

/** Demora para mostrar: "2 días", "1 día" o "". */
function demora_arreglo(array $a): string
{
    if ($a['demora_dias'] === null) {
        return '';
    }
    $d = (int) $a['demora_dias'];
    return $d . ($d === 1 ? ' día' : ' días');
}

/** Enlace al formulario de solicitud (el aprobado en TP11) con el arreglo ya escrito. */
/**
 * Arreglo de la lista que eligió la clienta, si pidió desde la lista de precios
 * (la descripción empieza con «Arreglo: {nombre}.», ver enlace_pedir_arreglo).
 */
function arreglo_de_solicitud(array $lista, string $descripcion): ?array
{
    $texto = mb_strtolower(trim($descripcion));
    foreach ($lista as $a) {
        if (str_starts_with($texto, mb_strtolower('Arreglo: ' . $a['nombre'] . '.'))) {
            return $a;
        }
    }
    return null;
}

function enlace_pedir_arreglo(array $a): string
{
    return 'solicitud.php?' . http_build_query([
        'id_tipos_servicio' => SERVICIO_ARREGLOS,
        'descripcion'       => 'Arreglo: ' . $a['nombre'] . '. ',
    ]);
}
