<?php
declare(strict_types=1);

/**
 * Mis medidas — Elegancia a Medida
 *
 * La clienta carga sus medidas (busto, cintura, cadera, hombros, estatura,
 * largo de brazo). Con ellas el sitio le recomienda el talle de cada prenda
 * y, si pide algo «A medida», las medidas viajan con el pedido.
 * Cada vez que guarda se agrega una fila nueva (queda el historial; vale la última).
 *
 * mis_medidas.php?volver=prenda.php%3Fid%3D1 → después de guardar vuelve a la prenda.
 * Tabla: medidas_clientes.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$cliente = requerir_cliente($pdo, 'Iniciá sesión para cargar tus medidas.');
$idc     = (int) $cliente['id_clientes'];
$volver  = destino_seguro($_GET['volver'] ?? $_POST['volver'] ?? null, 'mis_medidas.php');
$errores = [];
$enviado = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!token_cliente_valido()) {
            $errores[] = 'El formulario expiró. Probá de nuevo.';
        } else {
            $errores = guardar_medidas($pdo, $idc, $_POST, 'cliente');
            if (!$errores) {
                flash_cliente('ok', $volver === 'mis_medidas.php'
                    ? '¡Listo! Guardamos tus medidas.'
                    : '¡Listo! Guardamos tus medidas: ahora te mostramos tu talle.');
                header('Location: ' . $volver);
                exit;
            }
        }
        $enviado = $_POST;
    }

    $medidas = medidas_vigentes($pdo, $idc);
    $stmt = $pdo->prepare('SELECT fecha_carga, cargada_por FROM medidas_clientes WHERE id_clientes = :id
                            ORDER BY fecha_carga DESC, id_medidas_clientes DESC LIMIT 6');
    $stmt->execute([':id' => $idc]);
    $historial = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error en mis medidas: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudieron cargar tus medidas.');
}

$flash = tomar_flash_cliente();
$valor = function (string $c) use ($enviado, $medidas): string {
    if ($enviado !== null) {
        return isset($enviado[$c]) && is_string($enviado[$c]) ? $enviado[$c] : '';
    }
    return $medidas && $medidas[$c] !== null && $c !== 'notas' ? cm((float) $medidas[$c]) : ($c === 'notas' ? (string) ($medidas['notas'] ?? '') : '');
};
$pagina_actual = 'medidas';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis medidas - Elegancia a Medida</title>
    <link rel="stylesheet" href="<?= asset('css/cliente.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/sitio.css') ?>">
</head>
<body>
<?php require __DIR__ . '/encabezado_cliente.php'; ?>

<main class="pagina-sitio" style="max-width:900px">
    <p class="migas"><a href="index.php">Inicio</a> / Mis medidas</p>
    <h1>Mis medidas</h1>
    <p class="sec-sub" style="margin-top:-8px">
        En la ropa de fiesta el talle no depende de la edad: cada cuerpo es distinto.
        Con tus medidas te decimos qué talle te queda en cada prenda, y si pedís algo <strong>a medida</strong>,
        la modista ya las tiene.
    </p>

    <?php if ($flash): ?><div class="alert alert-<?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></div><?php endif; ?>
    <?php if ($errores): ?>
        <div class="alert alert-error" role="alert"><strong>Revisá estos datos:</strong>
            <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if ($medidas): ?>
        <p class="medidas-fecha">
            Últimas medidas: <?= (new DateTime($medidas['fecha_carga']))->format('d/m/Y') ?>
            <?= $medidas['cargada_por'] === 'modista' ? '· <strong>tomadas por la modista en el taller</strong>' : '· cargadas por vos' ?>
        </p>
    <?php endif; ?>

    <div class="medidas-layout">
        <form method="post" class="medidas-form" novalidate>
            <input type="hidden" name="token" value="<?= e(token_cliente()) ?>">
            <input type="hidden" name="volver" value="<?= e($volver) ?>">
            <?php foreach (MEDIDAS_CLIENTE as $c => [$nombre, $como]): ?>
                <div class="medida-campo">
                    <label for="m-<?= $c ?>"><?= e($nombre) ?></label>
                    <div class="medida-input">
                        <input type="text" inputmode="decimal" id="m-<?= $c ?>" name="<?= $c ?>" maxlength="5"
                               value="<?= e($valor($c)) ?>" placeholder="—" aria-describedby="ayuda-<?= $c ?>">
                        <span>cm</span>
                    </div>
                    <small id="ayuda-<?= $c ?>"><?= e($como) ?></small>
                </div>
            <?php endforeach; ?>
            <div class="medida-campo medida-notas">
                <label for="m-notas">Notas (opcional)</label>
                <input type="text" id="m-notas" name="notas" maxlength="<?= MEDIDAS_NOTAS_MAX ?>" value="<?= e($valor('notas')) ?>"
                       placeholder="Ej: prefiero las prendas sueltas, uso tacos de 8 cm…">
            </div>
            <div class="medidas-botones">
                <button type="submit" class="btn-pill">Guardar mis medidas</button>
                <?php if ($volver !== 'mis_medidas.php'): ?><a class="link" href="<?= e($volver) ?>">Volver a la prenda</a><?php endif; ?>
            </div>
        </form>

        <aside class="medidas-consejos">
            <h2>Cómo medirte</h2>
            <ul>
                <li>Usá un <strong>centímetro de costura</strong> (el flexible).</li>
                <li>Medite con ropa interior o ropa fina, <strong>sin apretar</strong> la cinta.</li>
                <li>Mantené la cinta derecha y paralela al piso.</li>
                <li>Con <strong>busto, cintura y cadera</strong> ya te podemos recomendar el talle. Para pedir algo <strong>a medida</strong> también son las mínimas.</li>
                <li>¿Dudas? En el taller la modista te las toma en cinco minutos.</li>
            </ul>
            <?php if (count($historial) > 1): ?>
                <h3>Historial</h3>
                <ul class="medidas-historial">
                    <?php foreach ($historial as $h): ?>
                        <li><?= (new DateTime($h['fecha_carga']))->format('d/m/Y') ?> · <?= $h['cargada_por'] === 'modista' ? 'modista' : 'vos' ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </aside>
    </div>
</main>

<?php require __DIR__ . '/pie_cliente.php'; ?>
<script src="<?= asset('js/sitio.js') ?>"></script>
<script src="<?= asset('js/notificaciones.js') ?>"></script>
</body>
</html>
