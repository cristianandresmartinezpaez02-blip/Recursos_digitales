<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.tendencias');
$activa = 'tendencias';
require __DIR__ . '/../includes/header.php';
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">🔥 <?= e(t('nav.mercado')) ?></div>
        <h1><?= e(t('nav.tendencias')) ?></h1>
        <p><?= e(t('nav.tendencias_d')) ?></p>
    </div>
</section>

<section class="rejilla col-2-1">
    <div class="panel">
        <h2>🧭 <?= e(t('kpi.fng')) ?></h2>
        <p class="subtitulo-panel"><?= e(t('tend.fng_explicacion')) ?></p>
        <div class="con-medidor" style="justify-content:flex-start;gap:1.5rem;flex-wrap:wrap">
            <div class="medidor" id="fng-medidor"></div>
            <div>
                <div class="kpi-valor" id="fng-clasificacion">—</div>
                <div class="texto-2" id="fng-comparacion"></div>
            </div>
        </div>
        <h3 style="margin-top:1.25rem"><?= e(t('tend.fng_30d')) ?></h3>
        <div class="grafico-caja mini"><canvas id="grafico-fng" aria-label="<?= e(t('tend.fng_30d')) ?>"></canvas></div>
    </div>
    <div class="panel">
        <h2>👑 <?= e(t('tend.dominancia')) ?></h2>
        <p class="subtitulo-panel"><?= e(t('tend.dominancia_d')) ?></p>
        <div class="dona-caja"><canvas id="grafico-dominancia" aria-label="<?= e(t('tend.dominancia')) ?>"></canvas></div>
    </div>
</section>

<section class="panel">
    <h2>🔥 <?= e(t('tend.en_tendencia')) ?></h2>
    <p class="subtitulo-panel"><?= e(t('tend.en_tendencia_d')) ?></p>
    <div class="explorar" id="tendencias" style="margin:0"></div>
</section>

<?php
$scripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js',
    url('assets/js/app.js'),
    url('assets/js/tendencias.js'),
];
require __DIR__ . '/../includes/footer.php';
