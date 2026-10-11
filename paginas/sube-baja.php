<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.subebaja');
$activa = 'subebaja';
$mostrarEstado = false;
require __DIR__ . '/../includes/header.php';
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">🎯 <?= e(t('nav.juegos')) ?></div>
        <h1><?= e(t('nav.subebaja')) ?></h1>
        <p><?= e(t('nav.subebaja_d')) ?></p>
    </div>
</section>

<section class="hud">
    <article class="kpi"><div class="kpi-titulo">⭐ <?= e(t('sb.puntos')) ?></div><div class="kpi-valor" id="sb-puntos">0</div></article>
    <article class="kpi"><div class="kpi-titulo">🔥 <?= e(t('sb.racha')) ?></div><div class="kpi-valor" id="sb-racha">0</div></article>
    <article class="kpi"><div class="kpi-titulo">❤️ <?= e(t('sb.vidas')) ?></div><div class="kpi-valor vidas" id="sb-vidas">❤️❤️❤️</div></article>
    <article class="kpi"><div class="kpi-titulo">🏅 <?= e(t('sb.record')) ?></div><div class="kpi-valor" id="sb-record">0</div></article>
    <article class="kpi"><div class="kpi-titulo">📐 <?= e(t('sb.precision')) ?></div><div class="kpi-valor" id="sb-precision">—</div><div class="kpi-sub" id="sb-precision-sub">&nbsp;</div></article>
</section>

<section class="rejilla col-2-1">
    <div class="panel">
        <div class="panel-cabecera" style="margin-bottom:.5rem">
            <h2 id="sb-moneda">—</h2>
            <button class="boton secundario mini" id="sb-pista" type="button">💡 <?= e(t('sb.pista')) ?></button>
        </div>
        <p class="subtitulo-panel" id="sb-ronda"></p>
        <div class="grafico-caja"><canvas id="sb-grafico" aria-label="<?= e(t('sb.grafico')) ?>"></canvas></div>
    </div>
    <div class="panel">
        <h2><?= e(t('sb.pregunta')) ?></h2>
        <p class="texto-2" style="font-size:.9rem"><?= e(t('sb.pregunta_d')) ?></p>
        <div class="botones-prediccion">
            <button class="boton-prediccion sube" id="sb-sube" type="button">▲ <?= e(t('sb.sube')) ?></button>
            <button class="boton-prediccion baja" id="sb-baja" type="button">▼ <?= e(t('sb.baja')) ?></button>
        </div>
        <button class="boton secundario bloque" id="sb-ia" type="button" style="margin-top:.75rem">🤖 <?= e(t('sb.ia')) ?></button>
        <div id="sb-ia-caja"></div>
        <div id="sb-resultado" role="status"></div>
        <button class="boton bloque" id="sb-siguiente" type="button" hidden style="margin-top:1rem"><?= e(t('sb.siguiente')) ?> →</button>
        <div class="consejo-juego"><span>📏</span><span><?= e(t('sb.reglas')) ?></span></div>
        <div class="consejo-juego"><span>🧠</span><span><?= e(t('sb.como_ganar')) ?> <a href="<?= url('paginas/estrategias.php#subebaja') ?>"><?= e(t('est.ver_guia')) ?> →</a></span></div>
    </div>
</section>

<section class="panel pantalla-central" id="sb-fin" hidden>
    <div class="grande">🏁</div>
    <h2><?= e(t('sb.fin')) ?></h2>
    <div class="kpi-valor" id="sb-final" style="font-size:2.4rem">0</div>
    <p id="sb-final-texto"></p>
    <button class="boton" id="sb-reiniciar" type="button">↺ <?= e(t('reto.otra_vez')) ?></button>
</section>

<?php
$scripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    url('assets/js/app.js'),
    url('assets/js/coach.js'),
    url('assets/js/subebaja.js'),
];
require __DIR__ . '/../includes/footer.php';
