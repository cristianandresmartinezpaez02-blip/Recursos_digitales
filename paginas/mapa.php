<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.mapa');
$activa = 'mapa';
require __DIR__ . '/../includes/header.php';
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">🗺️ <?= e(t('nav.mercado')) ?></div>
        <h1><?= e(t('nav.mapa')) ?></h1>
        <p><?= e(t('nav.mapa_d')) ?></p>
    </div>
</section>

<div class="explicacion"><span class="ico">ℹ️</span><p><?= e(t('mapa.explicacion')) ?></p></div>

<section class="panel">
    <div class="panel-cabecera">
        <div class="escala-mapa" aria-hidden="true">
            <span id="escala-min">−10 %</span><span class="degradado" id="escala-degradado"></span><span id="escala-max">+10 %</span>
        </div>
        <div class="filtros">
            <div class="pestanas" id="mapa-cantidad">
                <button data-cantidad="50">Top 50</button>
                <button data-cantidad="100" class="activa">Top 100</button>
            </div>
            <div class="pestanas" id="mapa-periodo">
                <button class="activa" data-periodo="24h">24 h</button>
                <button data-periodo="7d">7 d</button>
                <button data-periodo="30d">30 d</button>
            </div>
        </div>
    </div>
    <div class="mapa-calor" id="mapa-calor" role="img" aria-label="<?= e(t('nav.mapa')) ?>"></div>
</section>

<?php
$scripts = [url('assets/js/app.js'), url('assets/js/mapa.js')];
require __DIR__ . '/../includes/footer.php';
