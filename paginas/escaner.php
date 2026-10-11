<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.escaner');
$activa = 'escaner';
require __DIR__ . '/../includes/header.php';

$filtros = [
    '' => '🌐 ' . t('comun.todas'),
    'compra' => '▲ ' . t('esc.f_compra'),
    'venta' => '▼ ' . t('esc.f_venta'),
    'sobreventa' => '🧊 ' . t('esc.f_sobreventa'),
    'sobrecompra' => '🔥 ' . t('esc.f_sobrecompra'),
    'cruce_dorado' => '✨ ' . t('esc.f_cruce_dorado'),
    'cruce_muerte' => '☠️ ' . t('esc.f_cruce_muerte'),
    'macd_alcista' => '📈 ' . t('esc.f_macd_alcista'),
    'ruptura_alcista' => '🚀 ' . t('esc.f_ruptura'),
    'volatil' => '🌪️ ' . t('esc.f_volatil'),
];
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">🔎 <?= e(t('nav.herramientas')) ?></div>
        <h1><?= e(t('nav.escaner')) ?></h1>
        <p><?= e(t('esc.intro')) ?></p>
    </div>
</section>

<nav class="pestanas-seccion" id="pestanas-escaner" role="tablist">
    <a href="#senales" role="tab">📐 <?= e(t('esc.tab_senales')) ?></a>
    <a href="#correlacion" role="tab">🔗 <?= e(t('esc.tab_correlacion')) ?></a>
</nav>

<section id="senales" role="tabpanel">
    <section class="kpis" id="esc-resumen">
        <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">▲</span> <?= e(t('esc.k_compra')) ?></div><div class="kpi-valor sube" id="k-compra">—</div><div class="kpi-sub"><?= e(t('esc.k_compra_d')) ?></div></article>
        <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">▼</span> <?= e(t('esc.k_venta')) ?></div><div class="kpi-valor baja" id="k-venta">—</div><div class="kpi-sub"><?= e(t('esc.k_venta_d')) ?></div></article>
        <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">🌡️</span> <?= e(t('esc.k_rsi')) ?></div><div class="kpi-valor" id="k-rsi">—</div><div class="kpi-sub" id="k-rsi-d">&nbsp;</div></article>
        <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">⚡</span> <?= e(t('esc.k_eventos')) ?></div><div class="kpi-valor" id="k-eventos">—</div><div class="kpi-sub"><?= e(t('esc.k_eventos_d')) ?></div></article>
    </section>

    <div class="panel">
        <div class="panel-cabecera">
            <h2><?= e(t('esc.tabla')) ?> <small><?= e(t('esc.tabla_d')) ?></small></h2>
            <input type="search" id="esc-buscar" placeholder="<?= e(t('comun.filtrar')) ?>" aria-label="<?= e(t('comun.filtrar')) ?>">
        </div>
        <div class="chips" id="esc-filtros" style="margin-bottom:.9rem">
            <?php foreach ($filtros as $clave => $texto): ?>
                <button type="button" class="chip<?= $clave === '' ? ' activo' : '' ?>" data-filtro="<?= e($clave) ?>"><?= e($texto) ?> <small data-cuenta="<?= e($clave) ?>"></small></button>
            <?php endforeach; ?>
        </div>
        <div class="tabla-scroll">
            <table class="tabla compacta tabla-escaner">
                <thead><tr>
                    <th class="ordenable" data-orden="rank">#</th>
                    <th><?= e(t('col.moneda')) ?></th>
                    <th class="num ordenable" data-orden="precio"><?= e(t('col.precio')) ?></th>
                    <th class="num ordenable" data-orden="c1h">1 h</th>
                    <th class="num ordenable" data-orden="c24h">24 h</th>
                    <th class="num ordenable" data-orden="c7d">7 d</th>
                    <th class="ordenable" data-orden="rsi">RSI</th>
                    <th><?= e(t('esc.tendencia')) ?></th>
                    <th class="num ordenable" data-orden="vol"><?= e(t('esc.volatilidad')) ?></th>
                    <th class="ordenable" data-orden="puntaje"><?= e(t('esc.senal')) ?></th>
                    <th><?= e(t('esc.eventos')) ?></th>
                </tr></thead>
                <tbody id="esc-tabla"><tr><td colspan="11" class="cargando"><?= e(t('comun.cargando')) ?></td></tr></tbody>
            </table>
        </div>
        <p class="nota"><?= e(t('esc.nota')) ?></p>
    </div>
</section>

<section id="correlacion" role="tabpanel" hidden>
    <div class="explicacion"><span class="ico">🔗</span><p><?= e(t('esc.corr_explica')) ?></p></div>
    <div class="panel">
        <div class="panel-cabecera">
            <h2><?= e(t('esc.corr_titulo')) ?> <small><?= e(t('esc.corr_d')) ?></small></h2>
            <div class="leyenda-corr" aria-hidden="true"><span>−1</span><i></i><span>+1</span></div>
        </div>
        <div class="tabla-scroll"><table class="tabla-corr" id="esc-corr"></table></div>
        <div class="consejo-juego"><span>💡</span><span><?= e(t('esc.corr_consejo')) ?></span></div>
    </div>
</section>

<?php
$scripts = [url('assets/js/app.js'), url('assets/js/tecnico.js'), url('assets/js/escaner.js')];
require __DIR__ . '/../includes/footer.php';
