<?php
require_once __DIR__ . '/../includes/api.php';

$idCategoria = strtolower(trim($_GET['id'] ?? ''));
$categorias = [];
$error = false;
try {
    $categorias = obtenerCategorias()['datos'];
} catch (Throwable $e) {
    $error = true;
}
$porId = array_column($categorias, null, 'id');
$actual = $porId[$idCategoria] ?? null;

$monedas = [];
if ($actual) {
    try {
        $monedas = obtenerMonedasCategoria($idCategoria)['datos'];
    } catch (Throwable $e) {
        $error = true;
    }
}

$titulo = $actual ? $actual['nombre'] : t('nav.categorias');
$activa = 'categorias';
require __DIR__ . '/../includes/header.php';

$top = array_slice($categorias, 0, 80);
$conCambio = array_values(array_filter($top, fn($c) => $c['cambio_24h'] !== null));
usort($conCambio, fn($a, $b) => $b['cambio_24h'] <=> $a['cambio_24h']);

function tarjetaCategoria(array $c): string
{
    $logos = implode('', array_map(fn($l) => '<img src="' . e($l) . '" alt="" loading="lazy">', $c['logos']));
    return '<a class="tarjeta-seccion" href="' . url('paginas/categorias.php?id=' . rawurlencode($c['id'])) . '">'
        . '<span class="logos-apilados">' . $logos . '</span>'
        . '<h3>' . e($c['nombre']) . '</h3>'
        . '<p>' . fmt($c['cambio_24h'], 'porcentaje') . ' · ' . fmt($c['cap']) . '</p></a>';
}
?>

<?php if ($actual): ?>
    <p class="migas"><a href="<?= url('paginas/categorias.php') ?>"><?= e(t('nav.categorias')) ?></a> › <?= e($actual['nombre']) ?></p>
    <section class="hero">
        <div>
            <div class="etiqueta-seccion">🗂️ <?= e(t('nav.categorias')) ?></div>
            <h1><?= e($actual['nombre']) ?></h1>
            <p><?= e(t('cat.detalle_d', ['n' => count($monedas)])) ?></p>
        </div>
    </section>
    <section class="kpis">
        <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">🌐</span> <?= e(t('col.cap')) ?></div><div class="kpi-valor"><?= fmt($actual['cap']) ?></div></article>
        <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">📈</span> <?= e(t('cat.cambio')) ?></div><div class="kpi-valor"><?= fmt($actual['cambio_24h'], 'porcentaje') ?></div></article>
        <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">💧</span> <?= e(t('kpi.volumen')) ?></div><div class="kpi-valor"><?= fmt($actual['volumen']) ?></div></article>
        <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">🎯</span> <?= e(t('kpi.senales')) ?></div>
            <div class="kpi-valor"><span class="sube"><?= count(array_filter($monedas, fn($m) => $m['analisis']['veredicto'] === 'viable')) ?></span> <small class="texto-3" style="font-size:.9rem"><?= e(t('comun.viables')) ?></small></div></article>
    </section>
    <section class="panel">
        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th>#</th><th><?= e(t('col.moneda')) ?></th><th class="num"><?= e(t('col.precio')) ?></th><th class="num">24 h</th><th class="num">7 d</th><th class="num"><?= e(t('col.cap')) ?></th><th><?= e(t('col.senal')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($monedas as $m): ?>
                    <tr>
                        <td class="texto-3"><?= (int) ($m['market_cap_rank'] ?? 0) ?: '—' ?></td>
                        <td><span class="moneda"><img src="<?= e($m['image']) ?>" width="24" height="24" alt="" loading="lazy"><span class="nombre"><?= e($m['name']) ?></span> <small><?= e(strtoupper($m['symbol'])) ?></small></span></td>
                        <td class="num"><strong><?= fmt($m['current_price'], 'precio') ?></strong></td>
                        <td class="num"><?= fmt($m['price_change_percentage_24h'], 'porcentaje') ?></td>
                        <td class="num"><?= fmt($m['price_change_percentage_7d_in_currency'] ?? null, 'porcentaje') ?></td>
                        <td class="num"><?= fmt($m['market_cap']) ?></td>
                        <td><span class="insignia <?= e($m['analisis']['veredicto']) ?>"><?= e($m['analisis']['etiqueta']) ?> · <?= (int) $m['analisis']['puntaje'] ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$monedas): ?><tr><td colspan="7" class="cargando"><?= e(t('js.no_disponible')) ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="nota"><?= e(t('cat.nota')) ?></p>
    </section>

<?php else: ?>
    <section class="hero">
        <div>
            <div class="etiqueta-seccion">🗂️ <?= e(t('nav.mercado')) ?></div>
            <h1><?= e(t('nav.categorias')) ?></h1>
            <p><?= e(t('nav.categorias_d')) ?></p>
        </div>
    </section>
    <div class="explicacion"><span class="ico">ℹ️</span><p><?= e(t('cat.explicacion')) ?></p></div>

    <?php if ($error || !$categorias): ?>
        <section class="panel estado-vacio"><div class="grande">⚠️</div><p><?= e(t('js.no_disponible')) ?></p></section>
    <?php else: ?>
        <div class="titulo-bloque"><h2>🔥 <?= e(t('cat.mejores_hoy')) ?></h2></div>
        <section class="explorar"><?php foreach (array_slice($conCambio, 0, 4) as $c) echo tarjetaCategoria($c); ?></section>
        <div class="titulo-bloque"><h2>🧊 <?= e(t('cat.peores_hoy')) ?></h2></div>
        <section class="explorar"><?php foreach (array_slice(array_reverse($conCambio), 0, 4) as $c) echo tarjetaCategoria($c); ?></section>

        <section class="panel">
            <div class="panel-cabecera">
                <h2><?= e(t('cat.todas')) ?></h2>
                <input type="search" id="filtro-categorias" placeholder="<?= e(t('comun.filtrar')) ?>" aria-label="<?= e(t('comun.filtrar')) ?>">
            </div>
            <div class="tabla-scroll">
                <table class="tabla" id="tabla-categorias">
                    <thead><tr><th>#</th><th><?= e(t('cat.categoria')) ?></th><th><?= e(t('cat.principales')) ?></th><th class="num"><?= e(t('col.cap')) ?></th><th class="num">24 h</th><th class="num"><?= e(t('kpi.volumen')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($top as $i => $c): ?>
                        <tr data-nombre="<?= e(strtolower($c['nombre'])) ?>">
                            <td class="texto-3"><?= $i + 1 ?></td>
                            <td><a class="moneda" href="<?= url('paginas/categorias.php?id=' . rawurlencode($c['id'])) ?>"><?= e($c['nombre']) ?></a></td>
                            <td><span class="logos-apilados"><?php foreach ($c['logos'] as $l): ?><img src="<?= e($l) ?>" alt="" loading="lazy"><?php endforeach; ?></span></td>
                            <td class="num"><?= fmt($c['cap']) ?></td>
                            <td class="num"><?= fmt($c['cambio_24h'], 'porcentaje') ?></td>
                            <td class="num"><?= fmt($c['volumen']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <script>
            document.getElementById('filtro-categorias').addEventListener('input', ev => {
                const q = ev.target.value.trim().toLowerCase();
                document.querySelectorAll('#tabla-categorias tbody tr').forEach(tr => { tr.hidden = q && !tr.dataset.nombre.includes(q); });
            });
        </script>
    <?php endif; ?>
<?php endif; ?>

<?php
$scripts = [url('assets/js/app.js')];
require __DIR__ . '/../includes/footer.php';
