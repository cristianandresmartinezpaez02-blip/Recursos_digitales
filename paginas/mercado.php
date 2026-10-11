<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.top100');
$activa = 'mercado';
require __DIR__ . '/../includes/header.php';
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">🏆 <?= e(t('nav.mercado')) ?></div>
        <h1><?= e(t('mercado.titulo')) ?></h1>
        <p><?= e(t('nav.top100_d')) ?></p>
    </div>
</section>

<div class="explicacion"><span class="ico">ℹ️</span><p><?= e(t('mercado.explicacion')) ?></p></div>

<section class="panel">
    <div class="panel-cabecera">
        <div class="chips" id="filtro-senal">
            <button class="chip activo" data-filtro=""><?= e(t('comun.todas')) ?></button>
            <button class="chip" data-filtro="fav">★ <?= e(t('nav.favoritas')) ?></button>
            <button class="chip" data-filtro="viable">✔ <?= e(t('comun.viables')) ?></button>
            <button class="chip" data-filtro="neutral">● <?= e(t('an.v.neutral')) ?></button>
            <button class="chip" data-filtro="no_viable">✖ <?= e(t('comun.no_viables')) ?></button>
        </div>
        <input type="search" id="buscar" placeholder="<?= e(t('comun.filtrar')) ?>" autocomplete="off" aria-label="<?= e(t('comun.filtrar')) ?>">
    </div>
    <div class="tabla-scroll">
        <table class="tabla" id="tabla-top100">
            <thead>
            <tr>
                <th></th>
                <th class="ordenable ordenada asc" data-orden="market_cap_rank">#</th>
                <th class="ordenable" data-orden="name"><?= e(t('col.moneda')) ?></th>
                <th class="ordenable num" data-orden="current_price"><?= e(t('col.precio')) ?></th>
                <th class="ordenable num" data-orden="price_change_percentage_24h">24 h</th>
                <th class="ordenable num" data-orden="price_change_percentage_7d_in_currency">7 d</th>
                <th class="ordenable num" data-orden="market_cap"><?= e(t('col.cap')) ?></th>
                <th><?= e(t('col.7dias')) ?></th>
                <th class="ordenable" data-orden="puntaje"><?= e(t('col.senal')) ?></th>
            </tr>
            </thead>
            <tbody id="cuerpo-top100">
            <tr><td colspan="9" class="cargando"><?= e(t('comun.cargando')) ?></td></tr>
            </tbody>
        </table>
    </div>
    <div class="paginacion" id="paginacion"></div>
</section>

<?php
$scripts = [url('assets/js/app.js'), url('assets/js/mercado.js')];
require __DIR__ . '/../includes/footer.php';
