<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.bolsa');
$activa = 'bolsa';
require __DIR__ . '/../includes/header.php';
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">📈 <?= e(t('nav.mercado')) ?></div>
        <h1><?= e(t('bolsa.titulo_a')) ?> <span class="titulo-gradiente"><?= e(t('bolsa.titulo_b')) ?></span></h1>
        <p><?= e(t('bolsa.intro')) ?></p>
    </div>
    <a class="boton" href="<?= url('paginas/trading.php') ?>?clase=bolsa">💹 <?= e(t('bolsa.invertir_juego')) ?></a>
</section>

<div class="titulo-bloque"><h2>🌐 <?= e(t('bolsa.indices')) ?></h2><span class="texto-3" id="bolsa-hora"></span></div>
<section class="tarjetas-indices" id="lista-indices"><span class="esqueleto"><?= e(t('comun.cargando')) ?></span></section>

<section class="rejilla col-2-1">
    <div class="panel">
        <div class="panel-cabecera">
            <h2 id="graf-titulo">—</h2>
            <div class="chips" id="graf-rango">
                <button class="chip" data-dias="1">1D</button>
                <button class="chip" data-dias="7">7D</button>
                <button class="chip activo" data-dias="30">1M</button>
                <button class="chip" data-dias="90">3M</button>
                <button class="chip" data-dias="365">1A</button>
            </div>
        </div>
        <p class="subtitulo-panel" id="graf-sub">&nbsp;</p>
        <div class="grafico-caja"><canvas id="graf-bolsa" aria-label="<?= e(t('bolsa.grafico')) ?>"></canvas></div>
    </div>
    <div class="panel" id="panel-detalle">
        <h2>🔍 <?= e(t('bolsa.detalle')) ?></h2>
        <div id="detalle-activo"><span class="esqueleto"><?= e(t('comun.cargando')) ?></span></div>
    </div>
</section>

<nav class="pestanas-seccion" id="pestanas-bolsa" role="tablist">
    <a href="#acciones" role="tab">🏢 <?= e(t('bolsa.acciones')) ?></a>
    <a href="#divisas" role="tab">💱 <?= e(t('bolsa.divisas')) ?></a>
    <a href="#materias" role="tab">🛢️ <?= e(t('bolsa.materias')) ?></a>
</nav>

<section id="acciones" role="tabpanel">
    <div class="panel">
        <div class="panel-cabecera">
            <h2>🏢 <?= e(t('bolsa.acciones')) ?></h2>
            <input type="search" id="buscar-accion" placeholder="<?= e(t('comun.filtrar')) ?>" aria-label="<?= e(t('comun.filtrar')) ?>">
        </div>
        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th><?= e(t('bolsa.empresa')) ?></th><th class="num"><?= e(t('col.precio')) ?></th><th class="num"><?= e(t('bolsa.hoy')) ?></th>
                    <th class="ocultar-movil"><?= e(t('bolsa.mes')) ?></th><th><?= e(t('col.senal')) ?></th><th></th></tr></thead>
                <tbody id="tabla-acciones"><tr><td colspan="6" class="cargando"><?= e(t('comun.cargando')) ?></td></tr></tbody>
            </table>
        </div>
        <p class="nota"><?= e(t('bolsa.nota')) ?></p>
    </div>
</section>

<section id="divisas" role="tabpanel" hidden>
    <div class="panel">
        <div class="panel-cabecera">
            <h2>💱 <?= e(t('bolsa.divisas_vivo')) ?></h2>
            <span class="texto-3" id="divisas-fecha"></span>
        </div>
        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th><?= e(t('bolsa.divisa')) ?></th><th class="num"><?= e(t('bolsa.por_dolar')) ?></th><th class="num"><?= e(t('bolsa.dolar_vale')) ?></th>
                    <th class="num"><?= e(t('bolsa.hoy')) ?></th><th class="ocultar-movil"><?= e(t('bolsa.mes')) ?></th><th></th></tr></thead>
                <tbody id="tabla-divisas-vivo"></tbody>
            </table>
        </div>
    </div>
    <div class="panel">
        <div class="panel-cabecera">
            <h2>🌍 <?= e(t('bolsa.todas_divisas')) ?> <small id="divisas-cuenta"></small></h2>
            <input type="search" id="buscar-divisa" placeholder="<?= e(t('bolsa.buscar_divisa')) ?>" aria-label="<?= e(t('bolsa.buscar_divisa')) ?>">
        </div>
        <div class="lista-divisas" id="lista-divisas"></div>
        <p class="nota"><?= e(t('bolsa.nota_divisas')) ?></p>
    </div>
</section>

<section id="materias" role="tabpanel" hidden>
    <div class="panel">
        <h2>🛢️ <?= e(t('bolsa.materias')) ?></h2>
        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th><?= e(t('bolsa.activo')) ?></th><th class="num"><?= e(t('col.precio')) ?></th><th class="num"><?= e(t('bolsa.hoy')) ?></th><th class="ocultar-movil"><?= e(t('bolsa.mes')) ?></th><th></th></tr></thead>
                <tbody id="tabla-materias"></tbody>
            </table>
        </div>
    </div>
</section>

<?php
$scripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js',
    url('assets/js/app.js'),
    url('assets/js/graficos.js'),
    url('assets/js/bolsa.js'),
];
require __DIR__ . '/../includes/footer.php';
