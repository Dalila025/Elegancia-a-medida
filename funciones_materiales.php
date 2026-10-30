<?php
declare(strict_types=1);

/**
 * Tela sugerida y materiales del presupuesto — Elegancia a Medida
 *
 * Al presupuestar, la modista sugiere la tela según el trabajo y anota los
 * materiales que hacen falta (cierre, botones, entretela…), con un precio
 * aproximado. Al aceptar, la clienta elige:
 *   «Los traigo yo»            → se lleva la lista y los trae el día del turno;
 *   «Que los compre la modista» → se suma el costo al total del trabajo.
 *
 * Tablas: materiales_presupuesto (+ presupuestos.nota_materiales,
 * materiales_quien y monto_materiales). La incluye funciones.php.
 */

const MATERIALES_FILAS        = 6;     // filas del formulario del presupuesto
const MATERIAL_DESC_MAX       = 150;
const MATERIAL_CANT_MAX       = 30;
const NOTA_MATERIALES_MAX     = 255;
const MATERIALES_TIPOS        = ['tela' => 'Tela', 'material' => 'Material'];
const MATERIALES_QUIEN        = [
    'clienta' => 'Los traigo yo',
    'modista' => 'Que los compre la modista',
];

/** Materiales de un presupuesto, en orden (la tela primero). */
function materiales_de(PDO $pdo, ?int $id_presupuesto): array
{
    if (!$id_presupuesto) {
        return [];
    }
    $stmt = $pdo->prepare("SELECT * FROM materiales_presupuesto WHERE id_presupuestos = :id
                            ORDER BY tipo = 'material', orden, id_materiales_presupuesto");
    $stmt->execute([':id' => $id_presupuesto]);
    return $stmt->fetchAll();
}

/** Suma de los precios cargados y si quedó alguno «a confirmar». Devuelve [total, hay_sin_precio]. */
function total_materiales(array $filas): array
{
    $total = 0.0;
    $sin_precio = false;
    foreach ($filas as $f) {
        if ($f['precio'] === null || $f['precio'] === '') {
            $sin_precio = true;
        } else {
            $total += (float) $f['precio'];
        }
    }
    return [round($total, 2), $sin_precio];
}

/** «Satén azul (3 m)» */
function texto_material(array $f): string
{
    return $f['descripcion'] . ($f['cantidad'] !== '' ? ' (' . $f['cantidad'] . ')' : '');
}

/**
 * Lee las filas del formulario (materiales[i][tipo|descripcion|cantidad|precio]).
 * Las filas vacías se ignoran. Devuelve [filas, errores].
 */
function leer_materiales(mixed $post): array
{
    $filas = [];
    $errores = [];
    if (!is_array($post)) {
        return [[], []];
    }
    $n = 0;
    foreach (array_slice($post, 0, MATERIALES_FILAS * 2) as $f) {
        if (!is_array($f)) { continue; }
        $txt = fn(string $k): string => isset($f[$k]) && is_string($f[$k]) ? trim(preg_replace('/\s+/', ' ', $f[$k])) : '';
        $desc = $txt('descripcion');
        $cant = $txt('cantidad');
        $prec = $txt('precio');
        $tipo = isset(MATERIALES_TIPOS[$txt('tipo')]) ? $txt('tipo') : 'material';
        if ($desc === '' && $cant === '' && $prec === '') {
            continue;
        }
        $n++;
        if ($desc === '' || mb_strlen($desc) > MATERIAL_DESC_MAX) {
            $errores[] = "Material $n: escribí qué es (hasta " . MATERIAL_DESC_MAX . ' caracteres).';
        }
        if (mb_strlen($cant) > MATERIAL_CANT_MAX) {
            $errores[] = "Material $n: la cantidad puede tener hasta " . MATERIAL_CANT_MAX . ' caracteres.';
        }
        [$precio, $err] = leer_precio_opcional($prec, "precio del material $n");
        if ($err) {
            $errores[] = $err;
        }
        $filas[] = ['tipo' => $tipo, 'descripcion' => $desc, 'cantidad' => $cant, 'precio' => $precio, 'orden' => $n];
    }
    return [$filas, $errores];
}

/** Reemplaza los materiales de un presupuesto (dentro de la transacción de quien llama). */
function guardar_materiales(PDO $pdo, int $id_presupuesto, array $filas, string $nota): void
{
    $pdo->prepare('DELETE FROM materiales_presupuesto WHERE id_presupuestos = :id')->execute([':id' => $id_presupuesto]);
    $ins = $pdo->prepare('INSERT INTO materiales_presupuesto (id_presupuestos, tipo, descripcion, cantidad, precio, orden)
                          VALUES (:p, :t, :d, :c, :pr, :o)');
    foreach ($filas as $f) {
        $ins->execute([':p' => $id_presupuesto, ':t' => $f['tipo'], ':d' => $f['descripcion'],
                       ':c' => $f['cantidad'], ':pr' => $f['precio'], ':o' => $f['orden']]);
    }
    // Presupuesto nuevo o corregido: la elección anterior de la clienta ya no vale
    $pdo->prepare("UPDATE presupuestos SET nota_materiales = :n, materiales_quien = '', monto_materiales = 0
                    WHERE id_presupuestos = :id")
        ->execute([':n' => $nota, ':id' => $id_presupuesto]);
}

/**
 * Lista de materiales para mostrar (clienta o modista).
 * $quien = '' (todavía no eligió), 'clienta' o 'modista'.
 */
function mostrar_materiales(array $filas, string $nota, string $quien = ''): void
{
    if (!$filas && $nota === '') {
        return;
    }
    [$total, $sin_precio] = total_materiales($filas);
    ?>
    <div class="materiales-caja">
        <p class="mat-titulo">🧵 Tela sugerida y materiales</p>
        <?php if ($nota !== ''): ?><p class="mat-nota">«<?= e($nota) ?>»</p><?php endif; ?>
        <?php if ($filas): ?>
            <ul class="mat-lista">
                <?php foreach ($filas as $f): ?>
                    <li class="<?= $f['tipo'] === 'tela' ? 'es-tela' : '' ?>">
                        <span><?= $f['tipo'] === 'tela' ? '<strong>Tela:</strong> ' : '' ?><?= e(texto_material($f)) ?></span>
                        <span class="mat-precio"><?= $f['precio'] !== null ? e(pesos((float) $f['precio'])) : 'a confirmar' ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="mat-total">Si los compra la modista: <strong><?= e(pesos($total)) ?></strong><?= $sin_precio ? ' + lo que quede a confirmar' : '' ?></p>
        <?php endif; ?>
        <?php if ($quien === 'clienta'): ?>
            <p class="mat-quien">✓ Elegiste <strong>traerlos vos</strong>: llevalos el día que vas al taller.</p>
        <?php elseif ($quien === 'modista'): ?>
            <p class="mat-quien">✓ Los <strong>compra la modista</strong>: ya están sumados al total.</p>
        <?php endif; ?>
    </div>
    <?php
}

/** Materiales de un trabajo (por solicitud): ['quien' => ''|'clienta'|'modista', 'filas' => [...], 'monto' => float]. */
function materiales_de_solicitud(PDO $pdo, int $id_sol): array
{
    $stmt = $pdo->prepare('SELECT id_presupuestos, materiales_quien, monto_materiales FROM presupuestos WHERE id_solicitudes_presupuesto = :id');
    $stmt->execute([':id' => $id_sol]);
    $p = $stmt->fetch();
    if (!$p) {
        return ['quien' => '', 'filas' => [], 'monto' => 0.0];
    }
    return ['quien' => (string) $p['materiales_quien'], 'filas' => materiales_de($pdo, (int) $p['id_presupuestos']),
            'monto' => (float) $p['monto_materiales']];
}

/** Línea corta para la modista: «🧵 Materiales: los trae la clienta (Satén azul (3 m), …)». '' si no hay. */
function texto_materiales_modista(array $m): string
{
    if (!$m['filas'] || $m['quien'] === '') {
        return '';
    }
    $lista = implode(', ', array_map('texto_material', $m['filas']));
    return $m['quien'] === 'clienta'
        ? '🧵 Materiales: los trae la clienta — ' . $lista
        : '🧵 Materiales: los comprás vos (' . pesos($m['monto']) . ' en el total) — ' . $lista;
}
