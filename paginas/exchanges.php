<?php
require_once __DIR__ . '/../includes/api.php';

$exchanges = [];
try {
    $exchanges = obtenerExchanges()['datos'];
} catch (Throwable $e) {
    $exchanges = [];
}
// El volumen viene en BTC: se convierte a USD con el precio actual de Bitcoin.
$btc = null;
try {
    $btc = buscarEnTop100('bitcoin')['current_price'] ?? null;
} catch (Throwable $e) {
    $btc = null;
}

$titulo = t('nav.exchanges');
$activa = 'exchanges';
require __DIR__ . '/../includes/header.php';

$volumenTotal = $btc ? array_sum(array_map(fn($x) => (float) $x['volumen_btc'], $exchanges)) * $btc : null;
$confiables = count(array_filter($exchanges, fn($x) => ($x['confianza'] ?? 0) >= 9));
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">🏦 <?= e(t('nav.mercado')) ?></div>
        <h1><?= e(t('nav.exchanges')) ?></h1>
        <p><?= e(t('nav.exchanges_d')) ?></p>
    </div>
</section>

<div class="explicacion"><span class="ico">ℹ️</span><p><?= e(t('ex.explicacion')) ?></p></div>

<section class="kpis">
    <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">🏦</span> <?= e(t('ex.analizados')) ?></div><div class="kpi-valor"><?= count($exchanges) ?></div></article>
    <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">💧</span> <?= e(t('ex.volumen_total')) ?></div><div class="kpi-valor"><?= fmt($volumenTotal) ?></div></article>
    <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">🛡️</span> <?= e(t('ex.confiables')) ?></div><div class="kpi-valor"><span class="sube"><?= $confiables ?></span></div><div class="kpi-sub"><?= e(t('ex.confiables_d')) ?></div></article>
</section>

<section class="panel">
    <div class="tabla-scroll">
        <table class="tabla">
            <thead><tr><th>#</th><th>Exchange</th><th><?= e(t('ex.confianza')) ?></th><th class="num"><?= e(t('ex.volumen')) ?></th><th><?= e(t('ex.pais')) ?></th><th class="num"><?= e(t('ex.fundado')) ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($exchanges as $x): $conf = (int) ($x['confianza'] ?? 0); ?>
                <tr>
                    <td class="texto-3"><?= (int) $x['rank'] ?></td>
                    <td><span class="moneda"><img src="<?= e($x['imagen']) ?>" width="26" height="26" alt="" loading="lazy" style="border-radius:8px"><span class="nombre"><?= e($x['nombre']) ?></span></span></td>
                    <td><span class="barra-confianza" title="<?= $conf ?>/10"><?php for ($i = 1; $i <= 10; $i++): ?><i class="<?= $i <= $conf ? 'on' : '' ?>"></i><?php endfor; ?></span> <small class="texto-2"><?= $conf ?>/10</small></td>
                    <td class="num"><?= fmt($btc ? (float) $x['volumen_btc'] * $btc : null) ?></td>
                    <td class="texto-2"><?= e($x['pais'] ?: '—') ?></td>
                    <td class="num texto-2"><?= e($x['anio'] ?: '—') ?></td>
                    <td><?php if ($x['url']): ?><a class="boton mini secundario" href="<?= e($x['url']) ?>" target="_blank" rel="noopener nofollow"><?= e(t('ex.visitar')) ?> ↗</a><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$exchanges): ?><tr><td colspan="7" class="cargando"><?= e(t('js.no_disponible')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="nota"><?= e(t('ex.nota')) ?></p>
</section>

<?php
$scripts = [url('assets/js/app.js')];
require __DIR__ . '/../includes/footer.php';
