<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.movimientos');
$activa = 'movimientos';
require __DIR__ . '/../includes/header.php';
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">🚀 <?= e(t('nav.mercado')) ?></div>
        <h1><?= e(t('mov.titulo')) ?></h1>
        <p><?= e(t('nav.movimientos_d')) ?></p>
    </div>
    <div class="filtros">
        <span class="texto-3" style="font-size:.85rem"><?= e(t('mov.periodo')) ?></span>
        <div class="pestanas" id="periodo">
            <button data-periodo="1h">1 h</button>
            <button data-periodo="24h" class="activa">24 h</button>
            <button data-periodo="7d">7 d</button>
            <button data-periodo="30d">30 d</button>
        </div>
    </div>
</section>

<div class="explicacion"><span class="ico">ℹ️</span><p><?= e(t('mov.explicacion')) ?></p></div>

<section class="rejilla col-2">
    <div class="panel columna-mejores">
        <div class="panel-cabecera">
            <h2>🚀 <?= e(t('mov.mejores25')) ?></h2>
            <span class="insignia viable" id="resumen-mejores">—</span>
        </div>
        <p class="subtitulo-panel" id="sub-mejores"></p>
        <div class="tabla-scroll">
            <table class="tabla compacta">
                <thead><tr><th>#</th><th><?= e(t('col.moneda')) ?></th><th class="num"><?= e(t('col.precio')) ?></th><th class="num"><?= e(t('col.cambio')) ?></th><th><?= e(t('col.senal')) ?></th></tr></thead>
                <tbody id="cuerpo-mejores"><tr><td colspan="5" class="cargando"><?= e(t('comun.cargando')) ?></td></tr></tbody>
            </table>
        </div>
    </div>
    <div class="panel columna-peores">
        <div class="panel-cabecera">
            <h2>📉 <?= e(t('mov.peores25')) ?></h2>
            <span class="insignia no_viable" id="resumen-peores">—</span>
        </div>
        <p class="subtitulo-panel" id="sub-peores"></p>
        <div class="tabla-scroll">
            <table class="tabla compacta">
                <thead><tr><th>#</th><th><?= e(t('col.moneda')) ?></th><th class="num"><?= e(t('col.precio')) ?></th><th class="num"><?= e(t('col.cambio')) ?></th><th><?= e(t('col.senal')) ?></th></tr></thead>
                <tbody id="cuerpo-peores"><tr><td colspan="5" class="cargando"><?= e(t('comun.cargando')) ?></td></tr></tbody>
            </table>
        </div>
    </div>
</section>

<?php
$scripts = [url('assets/js/app.js'), url('assets/js/movimientos.js')];
require __DIR__ . '/../includes/footer.php';
