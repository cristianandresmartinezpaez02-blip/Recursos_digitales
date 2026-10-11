<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.reto');
$activa = 'reto';
$mostrarEstado = false;
require __DIR__ . '/../includes/header.php';
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">⏩ <?= e(t('nav.juegos')) ?></div>
        <h1><?= e(t('nav.reto')) ?></h1>
        <p><?= e(t('nav.reto_d')) ?></p>
    </div>
</section>

<!-- Pantalla de inicio -->
<section id="pantalla-inicio" class="rejilla col-2-1">
    <div class="panel pantalla-central">
        <div class="grande">⏩</div>
        <h2><?= e(t('reto.como_titulo')) ?></h2>
        <div class="pasos" style="text-align:left;max-width:560px;margin:0 auto 1.5rem">
            <div class="paso"><div><strong><?= e(t('reto.paso1')) ?></strong><p><?= e(t('reto.paso1_d', ['saldo' => num(JUEGO_SALDO_INICIAL, 0)])) ?></p></div></div>
            <div class="paso"><div><strong><?= e(t('reto.paso2')) ?></strong><p><?= e(t('reto.paso2_d')) ?></p></div></div>
            <div class="paso"><div><strong><?= e(t('reto.paso3')) ?></strong><p><?= e(t('reto.paso3_d')) ?></p></div></div>
        </div>
        <div class="controles-reto" style="justify-content:center;margin-bottom:1rem">
            <span class="texto-2"><?= e(t('reto.velocidad')) ?></span>
            <div class="pestanas" id="velocidad-inicio">
                <button data-vel="1">🐢 x1</button>
                <button data-vel="2" class="activa">x2</button>
                <button data-vel="4">🐇 x4</button>
            </div>
        </div>
        <button class="boton" id="empezar" type="button" disabled>▶ <?= e(t('reto.empezar')) ?></button>
    </div>
    <div class="panel">
        <h2>🏅 <?= e(t('reto.records')) ?></h2>
        <div class="records" id="records"></div>
        <h3 style="margin-top:1.25rem">🧠 <?= e(t('est.como_ganar')) ?></h3>
        <ul class="lista-errores consejos">
            <li><?= e(t('reto.tip1')) ?></li>
            <li><?= e(t('reto.tip2')) ?></li>
            <li><?= e(t('reto.tip3')) ?></li>
        </ul>
        <a href="<?= url('paginas/estrategias.php#reto') ?>"><?= e(t('est.ver_guia')) ?> →</a>
    </div>
</section>

<!-- Juego -->
<section id="pantalla-juego" hidden>
    <div class="panel">
        <div class="panel-cabecera" style="margin-bottom:0">
            <div>
                <span class="texto-3" style="font-size:.8rem"><?= e(t('reto.fecha_simulada')) ?></span><br>
                <span class="reloj-reto" id="reloj">—</span>
            </div>
            <div class="controles-reto">
                <button class="boton secundario mini" id="pausa" type="button">⏸ <?= e(t('reto.pausa')) ?></button>
                <div class="pestanas" id="velocidad">
                    <button data-vel="1">x1</button>
                    <button data-vel="2">x2</button>
                    <button data-vel="4">x4</button>
                </div>
                <button class="boton peligro mini" id="terminar" type="button">⏹ <?= e(t('reto.terminar')) ?></button>
            </div>
        </div>
        <div class="barra-tiempo"><span id="barra-tiempo" style="width:0%"></span></div>
    </div>

    <section class="hud">
        <article class="kpi"><div class="kpi-titulo">💵 <?= e(t('trading.efectivo')) ?></div><div class="kpi-valor" id="r-efectivo">—</div></article>
        <article class="kpi"><div class="kpi-titulo">🪙 <?= e(t('trading.en_criptos')) ?></div><div class="kpi-valor" id="r-invertido">—</div></article>
        <article class="kpi"><div class="kpi-titulo">🏦 <?= e(t('trading.patrimonio')) ?></div><div class="kpi-valor" id="r-total">—</div><div class="kpi-sub" id="r-ganancia">&nbsp;</div></article>
        <article class="kpi"><div class="kpi-titulo">🧾 <?= e(t('reto.comisiones')) ?></div><div class="kpi-valor" id="r-comisiones">—</div><div class="kpi-sub" id="r-ops">&nbsp;</div></article>
    </section>

    <div class="panel">
        <div class="panel-cabecera" style="margin-bottom:.6rem">
            <h2>🎯 <?= e(t('reto.objetivos')) ?></h2>
            <small class="texto-3 atajos-teclado">⌨️ <?= e(t('reto.atajos')) ?></small>
        </div>
        <div class="objetivos" id="r-objetivos"></div>
    </div>

    <div class="rejilla col-2-1">
        <div class="panel">
            <div class="panel-cabecera" style="margin-bottom:.5rem">
                <h2 id="r-titulo-grafico">—</h2>
                <span id="r-cambio-grafico"></span>
            </div>
            <div class="grafico-caja bajo"><canvas id="r-grafico" aria-label="<?= e(t('reto.grafico')) ?>"></canvas></div>
        </div>
        <div class="panel">
            <h2><?= e(t('trading.nueva_orden')) ?></h2>
            <div class="orden-info" id="r-info"></div>
            <p class="texto-3" style="font-size:.8rem;margin:.75rem 0 .35rem"><?= e(t('trading.comprar')) ?></p>
            <div class="atajos">
                <button type="button" data-comprar-monto="500">$500</button>
                <button type="button" data-comprar-monto="1000">$1.000</button>
                <button type="button" data-comprar-monto="2500">$2.500</button>
                <button type="button" data-comprar-monto="todo"><?= e(t('trading.max')) ?></button>
            </div>
            <p class="texto-3" style="font-size:.8rem;margin:.75rem 0 .35rem"><?= e(t('trading.vender')) ?></p>
            <div class="atajos">
                <button type="button" data-vender-porc="50">50 %</button>
                <button type="button" data-vender-porc="100"><?= e(t('reto.vender_todo')) ?></button>
            </div>
            <p class="mensaje" id="r-mensaje" role="status" style="margin-top:.75rem"></p>
            <div id="r-explica"></div>
            <div id="r-coach"></div>
        </div>
    </div>

    <div class="panel">
        <h2><?= e(t('reto.monedas')) ?> <small><?= e(t('reto.monedas_d')) ?></small></h2>
        <div class="lista-monedas-reto" id="r-monedas"></div>
    </div>
</section>

<!-- Resultado -->
<section id="pantalla-fin" class="panel pantalla-central" hidden>
    <div class="estrellas" id="f-estrellas"></div>
    <h2 id="f-titulo">—</h2>
    <div class="kpi-valor" id="f-resultado" style="font-size:2.4rem">—</div>
    <p id="f-detalle"></p>
    <div class="rejilla col-3" style="max-width:760px;margin:0 auto 1.5rem;text-align:left" id="f-comparacion"></div>
    <div class="leccion" id="f-leccion"></div>
    <div class="leccion" id="f-ops" style="text-align:left"></div>
    <button class="boton" id="otra-vez" type="button">↺ <?= e(t('reto.otra_vez')) ?></button>
    <a class="boton secundario" href="<?= url('paginas/juegos.php') ?>"><?= e(t('reto.otros_juegos')) ?></a>
</section>

<?php
$scripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    url('assets/js/app.js'),
    url('assets/js/coach.js'),
    url('assets/js/reto.js'),
];
require __DIR__ . '/../includes/footer.php';
