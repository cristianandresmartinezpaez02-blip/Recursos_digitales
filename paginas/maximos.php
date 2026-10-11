<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.maximos');
$activa = 'maximos';
require __DIR__ . '/../includes/header.php';
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">⛰️ <?= e(t('nav.mercado')) ?></div>
        <h1><?= e(t('nav.maximos')) ?></h1>
        <p><?= e(t('nav.maximos_d')) ?></p>
    </div>
</section>

<div class="explicacion"><span class="ico">ℹ️</span><p><?= e(t('max.explicacion')) ?></p></div>

<section class="kpis">
    <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">🏔️</span> <?= e(t('max.nuevos_30d')) ?></div><div class="kpi-valor" id="k-nuevos">—</div><div class="kpi-sub"><?= e(t('max.nuevos_30d_d')) ?></div></article>
    <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">🎯</span> <?= e(t('max.cerca')) ?></div><div class="kpi-valor" id="k-cerca">—</div><div class="kpi-sub"><?= e(t('max.cerca_d')) ?></div></article>
    <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">📉</span> <?= e(t('max.mediana')) ?></div><div class="kpi-valor" id="k-mediana">—</div><div class="kpi-sub"><?= e(t('max.mediana_d')) ?></div></article>
</section>

<section class="rejilla col-2">
    <div class="panel columna-mejores">
        <h2>🏔️ <?= e(t('max.mas_cerca')) ?></h2>
        <p class="subtitulo-panel"><?= e(t('max.mas_cerca_d')) ?></p>
        <div class="tabla-scroll"><table class="tabla compacta"><tbody id="cuerpo-cerca"></tbody></table></div>
    </div>
    <div class="panel columna-peores">
        <h2>🕳️ <?= e(t('max.mas_lejos')) ?></h2>
        <p class="subtitulo-panel"><?= e(t('max.mas_lejos_d')) ?></p>
        <div class="tabla-scroll"><table class="tabla compacta"><tbody id="cuerpo-lejos"></tbody></table></div>
    </div>
</section>

<?php
$scripts = [url('assets/js/app.js'), url('assets/js/maximos.js')];
require __DIR__ . '/../includes/footer.php';
