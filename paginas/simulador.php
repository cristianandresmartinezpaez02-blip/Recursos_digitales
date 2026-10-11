<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.simulador');
$activa = 'simulador';
require __DIR__ . '/../includes/header.php';
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">🧮 <?= e(t('nav.herramientas')) ?></div>
        <h1><?= e(t('nav.simulador')) ?></h1>
        <p><?= e(t('nav.simulador_d')) ?></p>
    </div>
</section>

<div class="explicacion"><span class="ico">ℹ️</span><p><?= e(t('sim.explicacion')) ?></p></div>

<section class="panel">
    <form id="form-sim" class="form-linea" autocomplete="off">
        <label class="campo-form"><?= e(t('col.moneda')) ?><select id="sim-moneda"></select></label>
        <label class="campo-form"><?= e(t('sim.monto')) ?><input type="number" id="sim-monto" value="100" min="<?= (float) JUEGO_MONTO_MINIMO ?>" step="any" required></label>
        <label class="campo-form"><?= e(t('sim.estrategia')) ?>
            <select id="sim-estrategia">
                <option value="unica"><?= e(t('sim.unica')) ?></option>
                <option value="7" selected><?= e(t('sim.semanal')) ?></option>
                <option value="30"><?= e(t('sim.mensual')) ?></option>
            </select>
        </label>
        <label class="campo-form"><?= e(t('sim.desde')) ?>
            <select id="sim-periodo">
                <option value="90"><?= e(t('sim.90d')) ?></option>
                <option value="180"><?= e(t('sim.180d')) ?></option>
                <option value="365" selected><?= e(t('sim.365d')) ?></option>
            </select>
        </label>
        <button class="boton" type="submit">🧮 <?= e(t('sim.calcular')) ?></button>
    </form>
</section>

<div class="titulo-bloque"><h2>🕰️ <?= e(t('fut.pasado_titulo')) ?></h2><span class="texto-3"><?= e(t('fut.pasado_d')) ?></span></div>
<section class="kpis" id="sim-kpis">
    <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">💵</span> <?= e(t('sim.invertido')) ?></div><div class="kpi-valor" id="r-invertido">—</div><div class="kpi-sub" id="r-compras">&nbsp;</div></article>
    <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">🏦</span> <?= e(t('sim.valor_hoy')) ?></div><div class="kpi-valor" id="r-valor">—</div><div class="kpi-sub" id="r-cantidad">&nbsp;</div></article>
    <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">📈</span> <?= e(t('sim.ganancia')) ?></div><div class="kpi-valor" id="r-ganancia">—</div><div class="kpi-sub" id="r-ganancia-pct">&nbsp;</div></article>
    <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">⚖️</span> <?= e(t('sim.precio_prom')) ?></div><div class="kpi-valor" id="r-promedio">—</div><div class="kpi-sub" id="r-actual">&nbsp;</div></article>
</section>

<section class="rejilla col-2-1">
    <div class="panel">
        <h2><?= e(t('sim.evolucion')) ?></h2>
        <p class="subtitulo-panel"><?= e(t('sim.evolucion_d')) ?></p>
        <div class="grafico-caja"><canvas id="sim-grafico" aria-label="<?= e(t('sim.evolucion')) ?>"></canvas></div>
    </div>
    <div class="panel">
        <h2>🔍 <?= e(t('sim.comparacion')) ?></h2>
        <div id="sim-comparacion" class="rejilla-datos" style="grid-template-columns:minmax(0,1fr)"></div>
        <p class="nota"><?= e(t('sim.nota')) ?></p>
    </div>
</section>

<section class="panel panel-explicacion" id="sim-explicacion" hidden>
    <h2 id="sim-exp-titulo">📊 <?= e(t('sim.por_que')) ?></h2>
    <div class="exp-resumen" id="sim-exp-resumen"></div>
    <div class="rejilla col-2">
        <div>
            <h3><?= e(t('trading.exp_cuentas')) ?></h3>
            <div class="grafico-caja bajo"><canvas id="sim-exp-cascada" aria-label="<?= e(t('trading.exp_cuentas')) ?>"></canvas></div>
        </div>
        <div>
            <h3><?= e(t('sim.exp_precio')) ?></h3>
            <div class="grafico-caja bajo"><canvas id="sim-exp-precio" aria-label="<?= e(t('sim.exp_precio')) ?>"></canvas></div>
        </div>
    </div>
    <ul class="lista-razones" id="sim-exp-razones"></ul>
    <div class="consejo-juego" id="sim-exp-leccion"></div>
</section>

<div class="titulo-bloque" id="futuro">
    <h2>🔮 <?= e(t('fut.titulo')) ?></h2>
    <span class="texto-3"><?= e(t('fut.subtitulo')) ?></span>
</div>
<section class="panel">
    <form id="form-futuro" class="form-linea" autocomplete="off">
        <label class="campo-form"><?= e(t('col.moneda')) ?><select id="fut-moneda"></select></label>
        <label class="campo-form"><?= e(t('fut.monto')) ?><input type="number" id="fut-monto" value="100" min="<?= (float) JUEGO_MONTO_MINIMO ?>" step="any" required></label>
        <label class="campo-form"><?= e(t('fut.plazo')) ?>
            <select id="fut-plazo">
                <option value="7"><?= e(t('fut.7d')) ?></option>
                <option value="30" selected><?= e(t('fut.30d')) ?></option>
                <option value="90"><?= e(t('fut.90d')) ?></option>
                <option value="180"><?= e(t('fut.180d')) ?></option>
                <option value="365"><?= e(t('fut.365d')) ?></option>
            </select>
        </label>
        <button class="boton" type="submit">🔮 <?= e(t('fut.calcular')) ?></button>
    </form>
    <p class="nota" id="fut-compra">&nbsp;</p>
</section>

<section class="kpis escenarios" id="fut-kpis">
    <article class="kpi escenario baja-e"><div class="kpi-titulo"><span class="kpi-icono">🌧️</span> <?= e(t('fut.pesimista')) ?></div><div class="kpi-valor" id="fut-p10">—</div><div class="kpi-sub" id="fut-p10-sub">&nbsp;</div></article>
    <article class="kpi escenario"><div class="kpi-titulo"><span class="kpi-icono">⛅</span> <?= e(t('fut.probable')) ?></div><div class="kpi-valor" id="fut-p50">—</div><div class="kpi-sub" id="fut-p50-sub">&nbsp;</div></article>
    <article class="kpi escenario sube-e"><div class="kpi-titulo"><span class="kpi-icono">☀️</span> <?= e(t('fut.optimista')) ?></div><div class="kpi-valor" id="fut-p90">—</div><div class="kpi-sub" id="fut-p90-sub">&nbsp;</div></article>
    <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">🎲</span> <?= e(t('fut.probabilidad')) ?></div><div class="kpi-valor" id="fut-prob">—</div><div class="kpi-sub" id="fut-prob-sub">&nbsp;</div></article>
</section>

<section class="rejilla col-2-1">
    <div class="panel">
        <h2><?= e(t('fut.grafico')) ?></h2>
        <p class="subtitulo-panel"><?= e(t('fut.grafico_d')) ?></p>
        <div class="grafico-caja"><canvas id="fut-grafico" aria-label="<?= e(t('fut.grafico')) ?>"></canvas></div>
    </div>
    <div class="panel">
        <h2>🧠 <?= e(t('fut.como')) ?></h2>
        <ul class="lista-razones" id="fut-razones"></ul>
        <div class="explicacion"><span class="ico">⚠️</span><p><?= e(t('fut.aviso')) ?></p></div>
    </div>
</section>

<?php
$scripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js',
    url('assets/js/app.js'),
    url('assets/js/graficos.js'),
    url('assets/js/simulador.js'),
];
require __DIR__ . '/../includes/footer.php';
