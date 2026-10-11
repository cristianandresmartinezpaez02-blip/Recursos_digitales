<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.comparar');
$activa = 'comparar';
require __DIR__ . '/../includes/header.php';
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">⚖️ <?= e(t('nav.herramientas')) ?></div>
        <h1><?= e(t('comparar.titulo_a')) ?> <span class="titulo-gradiente"><?= e(t('comparar.titulo_b')) ?></span></h1>
        <p><?= e(t('nav.comparar_d')) ?></p>
    </div>
</section>

<section class="panel">
    <div class="selector-comparar">
        <div class="chips" id="seleccionadas"></div>
        <select id="agregar-moneda" aria-label="<?= e(t('comparar.agregar')) ?>"></select>
    </div>
</section>

<section class="rejilla col-2-1">
    <div class="panel">
        <h2><?= e(t('comparar.rendimiento')) ?></h2>
        <p class="subtitulo-panel"><?= e(t('comparar.rendimiento_d')) ?></p>
        <div class="grafico-caja"><canvas id="grafico-comparar" aria-label="<?= e(t('comparar.rendimiento')) ?>"></canvas></div>
    </div>
    <div class="panel">
        <h2><?= e(t('comparar.factores')) ?></h2>
        <p class="subtitulo-panel"><?= e(t('comparar.factores_d')) ?></p>
        <div class="grafico-caja"><canvas id="grafico-radar" aria-label="<?= e(t('comparar.factores')) ?>"></canvas></div>
    </div>
</section>

<section class="panel">
    <h2><?= e(t('comparar.metricas')) ?></h2>
    <p class="subtitulo-panel"><?= e(t('comparar.metricas_d')) ?></p>
    <div class="tabla-scroll">
        <table class="tabla" id="tabla-comparar"></table>
    </div>
</section>

<?php
$scripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js',
    url('assets/js/app.js'),
    url('assets/js/comparar.js'),
];
require __DIR__ . '/../includes/footer.php';
