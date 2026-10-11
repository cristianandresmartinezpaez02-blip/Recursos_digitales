<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.trading');
$activa = 'trading';
require __DIR__ . '/../includes/header.php';
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">💹 <?= e(t('nav.juegos')) ?></div>
        <h1><?= e(t('nav.trading')) ?></h1>
        <p><?= e(t('trading.intro', ['saldo' => num(JUEGO_SALDO_INICIAL, 0), 'comision' => num(JUEGO_COMISION * 100, 1)])) ?></p>
    </div>
</section>

<section class="panel">
    <div class="nivel">
        <div class="nivel-icono" id="nivel-icono">🐣</div>
        <div class="nivel-info">
            <span class="texto-3" style="font-size:.8rem"><?= e(t('trading.tu_nivel')) ?></span><br>
            <strong id="nivel-nombre">—</strong>
            <div class="progreso"><span id="nivel-progreso" style="width:0%"></span></div>
            <span class="nota" id="nivel-texto" style="margin:0">&nbsp;</span>
        </div>
        <button class="boton secundario" id="j-reiniciar" type="button">↺ <?= e(t('trading.reiniciar')) ?></button>
    </div>
    <div class="confirmacion" id="j-confirmar" hidden>
        <?= e(t('trading.confirmar')) ?>
        <button class="boton peligro mini" id="j-confirmar-si" type="button"><?= e(t('trading.si_reiniciar')) ?></button>
        <button class="boton secundario mini" id="j-confirmar-no" type="button"><?= e(t('comun.cancelar')) ?></button>
    </div>
</section>

<details class="panel guia-rapida">
    <summary>🧠 <?= e(t('est.como_ganar')) ?> <small><?= e(t('est.trading_objetivo')) ?></small></summary>
    <div class="pasos">
        <?php for ($i = 1; $i <= 5; $i++): ?>
            <div class="paso"><div><strong><?= e(t("est.trading_p{$i}")) ?></strong><p><?= e(t("est.trading_p{$i}_d")) ?></p></div></div>
        <?php endfor; ?>
    </div>
    <a href="<?= url('paginas/estrategias.php#trading') ?>"><?= e(t('est.ver_guia')) ?> →</a>
</details>

<section class="hud">
    <article class="kpi"><div class="kpi-titulo">💵 <?= e(t('trading.efectivo')) ?></div><div class="kpi-valor" id="j-efectivo">—</div><div class="kpi-sub" id="j-reservado">&nbsp;</div></article>
    <article class="kpi"><div class="kpi-titulo">🪙 <?= e(t('trading.en_criptos')) ?></div><div class="kpi-valor" id="j-invertido">—</div></article>
    <article class="kpi"><div class="kpi-titulo">🏦 <?= e(t('trading.patrimonio')) ?></div><div class="kpi-valor" id="j-total">—</div><div class="kpi-sub" id="j-ganancia">&nbsp;</div></article>
    <article class="kpi"><div class="kpi-titulo">🎯 <?= e(t('trading.aciertos')) ?></div><div class="kpi-valor" id="j-aciertos">—</div><div class="kpi-sub" id="j-aciertos-sub">&nbsp;</div></article>
</section>

<nav class="pestanas-seccion" id="pestanas-trading" role="tablist">
    <a href="#operar" role="tab">🛒 <?= e(t('trading.tab_operar')) ?></a>
    <a href="#portafolio" role="tab">💼 <?= e(t('trading.tab_portafolio')) ?></a>
    <a href="#logros" role="tab">🏆 <?= e(t('trading.tab_logros')) ?></a>
    <a href="#historial" role="tab">🧾 <?= e(t('trading.tab_historial')) ?></a>
</nav>

<section id="operar" role="tabpanel">
    <div class="rejilla col-2-1">
        <div class="panel">
            <div class="panel-cabecera">
                <h2><?= e(t('trading.elige')) ?></h2>
                <input type="search" id="j-buscar" placeholder="<?= e(t('comun.filtrar')) ?>" aria-label="<?= e(t('comun.filtrar')) ?>">
            </div>
            <div class="segmentado selector-clase" id="j-clase" role="tablist" aria-label="<?= e(t('trading.mercado')) ?>">
                <button type="button" data-clase="cripto" class="activa">🪙 <?= e(t('trading.clase_cripto')) ?></button>
                <button type="button" data-clase="bolsa">📈 <?= e(t('trading.clase_bolsa')) ?></button>
            </div>
            <div class="chips" id="j-filtro" style="margin-bottom:.75rem">
                <button class="chip activo" data-filtro=""><?= e(t('comun.todas')) ?></button>
                <button class="chip" data-filtro="viable">✔ <?= e(t('comun.viables')) ?></button>
                <button class="chip" data-filtro="neutral">● <?= e(t('an.v.neutral')) ?></button>
                <button class="chip" data-filtro="no_viable">✖ <?= e(t('comun.no_viables')) ?></button>
            </div>
            <div class="tabla-scroll lista-alta">
                <table class="tabla compacta">
                    <thead><tr><th><?= e(t('col.moneda')) ?></th><th class="num"><?= e(t('col.precio')) ?></th><th class="num">24 h</th><th><?= e(t('col.senal')) ?></th><th></th></tr></thead>
                    <tbody id="tabla-juego"><tr><td colspan="5" class="cargando"><?= e(t('comun.cargando')) ?></td></tr></tbody>
                </table>
            </div>
        </div>

        <div class="panel" id="panel-orden">
            <h2><?= e(t('trading.nueva_orden')) ?></h2>
            <form id="form-orden" autocomplete="off">
                <label class="campo-form"><?= e(t('col.moneda')) ?><select id="o-moneda"></select></label>
                <div class="orden-info" id="o-info"></div>
                <div class="grafico-orden">
                    <div class="panel-cabecera">
                        <small class="texto-2" id="o-graf-sub">&nbsp;</small>
                        <div class="chips" id="o-graf-rango">
                            <button type="button" class="chip activo" data-dias="1">1D</button>
                            <button type="button" class="chip" data-dias="7">7D</button>
                            <button type="button" class="chip" data-dias="30">1M</button>
                        </div>
                    </div>
                    <div class="grafico-caja mini"><canvas id="o-grafico" aria-label="<?= e(t('trading.grafico_precio')) ?>"></canvas></div>
                </div>
                <div class="selector-lado" role="radiogroup" aria-label="<?= e(t('trading.tipo')) ?>">
                    <label><input type="radio" name="lado" value="compra" checked> <?= e(t('trading.comprar')) ?></label>
                    <label><input type="radio" name="lado" value="venta"> <?= e(t('trading.vender')) ?></label>
                </div>
                <div id="o-tipo-caja">
                    <label class="campo-form"><?= e(t('trading.tipo_orden')) ?>
                        <select id="o-tipo">
                            <option value="mercado"><?= e(t('trading.al_mercado')) ?></option>
                            <option value="limite"><?= e(t('trading.limite')) ?></option>
                        </select>
                    </label>
                    <div id="o-limite-caja" hidden>
                        <label class="campo-form"><?= e(t('trading.precio_limite')) ?><input type="number" id="o-limite" min="0" step="any"></label>
                        <p class="nota"><?= e(t('trading.limite_d')) ?></p>
                    </div>
                </div>
                <label class="campo-form"><?= e(t('trading.monto')) ?> <small class="texto-3"><?= e(t('trading.minimo', ['monto' => '$' . num(JUEGO_MONTO_MINIMO, 0)])) ?></small>
                    <input type="number" id="o-monto" min="<?= (float) JUEGO_MONTO_MINIMO ?>" step="0.01" placeholder="100"></label>
                <div class="atajos">
                    <button type="button" data-porc="10">10 %</button>
                    <button type="button" data-porc="25">25 %</button>
                    <button type="button" data-porc="50">50 %</button>
                    <button type="button" data-porc="100"><?= e(t('trading.max')) ?></button>
                </div>
                <details class="avanzado" id="o-avanzado">
                    <summary>🛡️ <?= e(t('trading.proteccion')) ?></summary>
                    <div class="protecciones">
                        <label class="campo-form"><?= e(t('trading.stop_loss')) ?><input type="number" id="o-sl" min="0.5" max="90" step="0.1" placeholder="5"></label>
                        <label class="campo-form"><?= e(t('trading.take_profit')) ?><input type="number" id="o-tp" min="0.5" max="1000" step="0.1" placeholder="10"></label>
                    </div>
                    <p class="nota"><?= e(t('trading.proteccion_d')) ?></p>
                </details>
                <p class="orden-previa" id="o-previa"></p>
                <button type="submit" class="boton bloque" id="o-enviar"><?= e(t('trading.ejecutar')) ?></button>
                <p class="mensaje" id="o-mensaje" role="status"></p>
            </form>
            <div id="o-explica"></div>
            <div id="o-coach"></div>
            <div class="consejo-juego"><span>💡</span><span><?= e(t('trading.por_que')) ?> <a href="<?= url('paginas/reto.php') ?>"><?= e(t('nav.reto')) ?> →</a></span></div>
        </div>
    </div>
    <div class="panel panel-explicacion" id="panel-explicacion" hidden>
        <div class="panel-cabecera">
            <h2 id="exp-titulo">📊 <?= e(t('trading.por_que_titulo')) ?></h2>
            <button type="button" class="boton-icono mini-cerrar" id="exp-cerrar" aria-label="<?= e(t('pref.cerrar')) ?>">✕</button>
        </div>
        <div class="exp-resumen" id="exp-resumen"></div>
        <div class="rejilla col-2">
            <div>
                <h3><?= e(t('trading.exp_camino')) ?></h3>
                <div class="grafico-caja bajo"><canvas id="exp-precio" aria-label="<?= e(t('trading.exp_camino')) ?>"></canvas></div>
                <p class="nota" id="exp-precio-nota"></p>
            </div>
            <div>
                <h3><?= e(t('trading.exp_cuentas')) ?></h3>
                <div class="grafico-caja bajo"><canvas id="exp-cascada" aria-label="<?= e(t('trading.exp_cuentas')) ?>"></canvas></div>
            </div>
        </div>
        <ul class="lista-razones" id="exp-razones"></ul>
        <div class="consejo-juego" id="exp-leccion"></div>
    </div>
    <div class="panel" id="panel-ordenes">
        <h2>⏳ <?= e(t('trading.ordenes_pendientes')) ?></h2>
        <div id="lista-ordenes"></div>
    </div>
</section>

<section id="portafolio" role="tabpanel" hidden>
    <div class="panel">
        <h2><?= e(t('trading.mis_monedas')) ?></h2>
        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th><?= e(t('col.moneda')) ?></th><th class="num"><?= e(t('trading.cantidad')) ?></th><th class="num"><?= e(t('trading.compra_prom')) ?></th>
                    <th class="num"><?= e(t('trading.precio_actual')) ?></th><th class="num"><?= e(t('trading.valor')) ?></th><th class="num"><?= e(t('trading.gp')) ?></th>
                    <th><?= e(t('trading.proteccion_col')) ?></th><th><?= e(t('trading.consejo')) ?></th><th></th></tr></thead>
                <tbody id="tabla-portafolio"></tbody>
            </table>
        </div>
    </div>
    <div class="rejilla col-2-1">
        <div class="panel">
            <h2><?= e(t('trading.evolucion')) ?></h2>
            <div class="grafico-caja bajo"><canvas id="grafico-patrimonio" aria-label="<?= e(t('trading.evolucion')) ?>"></canvas></div>
            <p class="nota" id="nota-patrimonio"></p>
        </div>
        <div class="panel">
            <h2><?= e(t('trading.distribucion')) ?></h2>
            <div class="dona-caja"><canvas id="grafico-dona" aria-label="<?= e(t('trading.distribucion')) ?>"></canvas></div>
        </div>
    </div>
    <div class="panel">
        <h2>🤖 <?= e(t('trading.sugerencias')) ?></h2>
        <div id="sugerencias-bot"></div>
    </div>
</section>

<section id="logros" role="tabpanel" hidden>
    <div class="panel">
        <h2>🏆 <?= e(t('trading.tab_logros')) ?> <small id="logros-cuenta"></small></h2>
        <div class="logros" id="lista-logros"></div>
    </div>
</section>

<section id="historial" role="tabpanel" hidden>
    <div class="panel">
        <div class="panel-cabecera">
            <h2>🧾 <?= e(t('trading.tab_historial')) ?></h2>
            <button class="boton secundario mini" type="button" id="j-exportar">📥 <?= e(t('trading.exportar')) ?></button>
        </div>
        <div class="tabla-scroll lista-alta">
            <table class="tabla compacta">
                <thead><tr><th><?= e(t('trading.fecha')) ?></th><th><?= e(t('trading.tipo')) ?></th><th><?= e(t('col.moneda')) ?></th><th class="num"><?= e(t('trading.cantidad')) ?></th>
                    <th class="num"><?= e(t('col.precio')) ?></th><th class="num"><?= e(t('trading.total')) ?></th><th class="num"><?= e(t('trading.resultado')) ?></th><th><?= e(t('trading.senal_al_operar')) ?></th><th></th></tr></thead>
                <tbody id="tabla-historial"></tbody>
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
    url('assets/js/coach.js'),
    url('assets/js/trading.js'),
];
require __DIR__ . '/../includes/footer.php';
