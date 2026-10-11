<?php
require_once __DIR__ . '/includes/i18n.php';
$titulo = t('nav.inicio');
$activa = 'inicio';
require __DIR__ . '/includes/header.php';

$secciones = [
    ['mercado', 'paginas/mercado.php', '🏆', 'nav.top100', 'inicio.sec_top100'],
    ['bolsa', 'paginas/bolsa.php', '📈', 'nav.bolsa', 'inicio.sec_bolsa'],
    ['movimientos', 'paginas/movimientos.php', '🚀', 'nav.movimientos', 'inicio.sec_movimientos'],
    ['mapa', 'paginas/mapa.php', '🗺️', 'nav.mapa', 'inicio.sec_mapa'],
    ['categorias', 'paginas/categorias.php', '🗂️', 'nav.categorias', 'inicio.sec_categorias'],
    ['maximos', 'paginas/maximos.php', '⛰️', 'nav.maximos', 'inicio.sec_maximos'],
    ['exchanges', 'paginas/exchanges.php', '🏦', 'nav.exchanges', 'inicio.sec_exchanges'],
    ['tendencias', 'paginas/tendencias.php', '🔥', 'nav.tendencias', 'inicio.sec_tendencias'],
    ['escaner', 'paginas/escaner.php', '🔎', 'nav.escaner', 'inicio.sec_escaner'],
    ['diario', 'paginas/diario.php', '📓', 'nav.diario', 'inicio.sec_diario'],
    ['calculadoras', 'paginas/calculadoras.php', '➗', 'nav.calculadoras', 'inicio.sec_calculadoras'],
    ['comparar', 'paginas/comparar.php', '⚖️', 'nav.comparar', 'inicio.sec_comparar'],
    ['simulador', 'paginas/simulador.php', '🧮', 'nav.simulador', 'inicio.sec_simulador'],
    ['convertidor', 'paginas/convertidor.php', '🔁', 'nav.convertidor', 'inicio.sec_convertidor'],
    ['alertas', 'paginas/alertas.php', '🔔', 'nav.alertas', 'inicio.sec_alertas'],
    ['juegos', 'paginas/juegos.php', '🎮', 'nav.juegos', 'inicio.sec_juegos'],
    ['ranking', 'paginas/ranking.php', '🏅', 'nav.ranking', 'inicio.sec_ranking'],
    ['estrategias', 'paginas/estrategias.php', '🧠', 'nav.estrategias', 'inicio.sec_estrategias'],
];
?>

<section class="hero-banner">
    <div>
        <div class="etiqueta-seccion">● <?= e(t('inicio.etiqueta')) ?></div>
        <h1><?= e(t('inicio.titulo_a')) ?> <span class="titulo-gradiente"><?= e(t('inicio.titulo_b')) ?></span></h1>
        <p id="resumen-mercado"><span class="esqueleto"><?= e(t('comun.cargando')) ?></span></p>
        <div class="hero-cifras">
            <div><span><?= e(t('kpi.cap_global')) ?></span><strong id="hero-cap">—</strong></div>
            <div><span>Bitcoin</span><strong id="hero-btc">—</strong></div>
            <div><span><?= e(t('inicio.suben_hoy')) ?></span><strong id="hero-suben">—</strong></div>
        </div>
        <div class="hero-acciones">
            <a class="boton" href="<?= url('paginas/movimientos.php') ?>">🚀 <?= e(t('inicio.ver_mejores')) ?></a>
            <a class="boton secundario" href="<?= url('paginas/juegos.php') ?>">🎮 <?= e(t('inicio.jugar')) ?></a>
        </div>
    </div>
    <div class="orbita" id="orbita" aria-hidden="true"><div class="centro">₿</div></div>
</section>

<?php if (!$usuario): ?>
    <section class="aviso-cuenta">
        <span class="ico" aria-hidden="true">✨</span>
        <p><strong><?= e(t('inicio.cuenta_titulo')) ?></strong><br><span class="texto-2"><?= e(t('inicio.cuenta_d')) ?></span></p>
        <a class="boton" href="<?= e(url('paginas/acceso.php') . '?modo=registro') ?>"><?= e(t('cuenta.crear_gratis')) ?></a>
        <a class="boton secundario" href="<?= e($urlAcceso) ?>"><?= e(t('cuenta.entrar')) ?></a>
    </section>
<?php else: ?>
    <section class="aviso-cuenta bienvenida">
        <span class="ico" aria-hidden="true">👋</span>
        <p><strong><?= e(t('inicio.hola', ['nombre' => explode(' ', $usuario['nombre'])[0]])) ?></strong><br><span class="texto-2"><?= e(t('inicio.hola_d')) ?></span></p>
        <a class="boton secundario" href="<?= url('paginas/juegos.php') ?>">🎮 <?= e(t('inicio.jugar')) ?></a>
    </section>
<?php endif; ?>

<section class="kpis">
    <article class="kpi">
        <div class="kpi-titulo"><span class="kpi-icono">🌐</span> <?= e(t('kpi.cap_global')) ?></div>
        <div class="kpi-valor" id="kpi-cap">—</div>
        <div class="kpi-sub" id="kpi-cap-sub">&nbsp;</div>
        <div class="kpi-spark" id="kpi-cap-spark"></div>
    </article>
    <article class="kpi">
        <div class="kpi-titulo"><span class="kpi-icono">💧</span> <?= e(t('kpi.volumen')) ?></div>
        <div class="kpi-valor" id="kpi-vol">—</div>
        <div class="kpi-sub"><?= e(t('kpi.volumen_d')) ?></div>
    </article>
    <a class="kpi" href="<?= url('paginas/tendencias.php') ?>" style="color:inherit;text-decoration:none">
        <div class="kpi-titulo"><span class="kpi-icono">🧭</span> <?= e(t('kpi.fng')) ?></div>
        <div class="con-medidor">
            <div class="kpi-cuerpo">
                <div class="kpi-valor" id="kpi-fng">—</div>
                <div class="kpi-sub"><?= e(t('kpi.fng_d')) ?></div>
            </div>
            <div class="medidor" id="kpi-fng-medidor"></div>
        </div>
    </a>
    <article class="kpi">
        <div class="kpi-titulo"><span class="kpi-icono">🎯</span> <?= e(t('kpi.senales')) ?></div>
        <div class="kpi-valor" id="kpi-senal">—</div>
        <div class="barra-dominancia" id="kpi-senal-barra"></div>
        <div class="leyenda-mini" id="kpi-senal-leyenda"></div>
    </article>
</section>

<div class="titulo-bloque">
    <h2>📊 <?= e(t('inicio.graficos')) ?></h2>
    <span class="texto-3"><?= e(t('inicio.graficos_d')) ?></span>
</div>
<section class="rejilla col-2-1">
    <div class="panel">
        <div class="panel-cabecera">
            <h2 id="ini-precio-titulo">Bitcoin</h2>
            <div class="chips" id="ini-moneda">
                <button class="chip activo" data-id="bitcoin">BTC</button>
                <button class="chip" data-id="ethereum">ETH</button>
                <button class="chip" data-id="solana">SOL</button>
            </div>
        </div>
        <div class="panel-cabecera">
            <p class="subtitulo-panel" id="ini-precio-sub">&nbsp;</p>
            <div class="chips" id="ini-rango">
                <button class="chip" data-dias="1">24 h</button>
                <button class="chip activo" data-dias="7">7 d</button>
                <button class="chip" data-dias="30">30 d</button>
            </div>
        </div>
        <div class="grafico-caja"><canvas id="ini-precio" aria-label="<?= e(t('inicio.graf_precio')) ?>"></canvas></div>
    </div>
    <div class="panel">
        <h2>🥧 <?= e(t('inicio.graf_dominancia')) ?></h2>
        <p class="subtitulo-panel"><?= e(t('inicio.graf_dominancia_d')) ?></p>
        <div class="dona-caja"><canvas id="ini-dominancia" aria-label="<?= e(t('inicio.graf_dominancia')) ?>"></canvas></div>
    </div>
</section>
<section class="rejilla col-2">
    <div class="panel">
        <h2>📶 <?= e(t('inicio.graf_cambios')) ?></h2>
        <p class="subtitulo-panel"><?= e(t('inicio.graf_cambios_d')) ?></p>
        <div class="grafico-caja"><canvas id="ini-cambios" aria-label="<?= e(t('inicio.graf_cambios')) ?>"></canvas></div>
    </div>
    <div class="panel">
        <div class="panel-cabecera">
            <h2>📈 <?= e(t('inicio.graf_bolsa')) ?></h2>
            <a href="<?= url('paginas/bolsa.php') ?>"><?= e(t('comun.abrir')) ?> →</a>
        </div>
        <p class="subtitulo-panel"><?= e(t('inicio.graf_bolsa_d')) ?></p>
        <div class="lista-simple" id="ini-bolsa"><span class="esqueleto"><?= e(t('comun.cargando')) ?></span></div>
    </div>
</section>

<div class="titulo-bloque">
    <h2>🏁 <?= e(t('mov.titulo')) ?> <small class="texto-3" style="font-size:.85rem;font-weight:400">24 h</small></h2>
    <a href="<?= url('paginas/movimientos.php') ?>"><?= e(t('inicio.ver_las_25')) ?> →</a>
</div>
<section class="rejilla col-2">
    <div class="panel columna-mejores">
        <div class="panel-cabecera"><h2>🚀 <?= e(t('mov.mejores25')) ?></h2><a href="<?= url('paginas/movimientos.php') ?>"><?= e(t('inicio.ver_las_25')) ?> →</a></div>
        <div class="lista-simple lista-alta" id="lista-mejores"></div>
    </div>
    <div class="panel columna-peores">
        <div class="panel-cabecera"><h2>📉 <?= e(t('mov.peores25')) ?></h2><a href="<?= url('paginas/movimientos.php') ?>"><?= e(t('inicio.ver_las_25')) ?> →</a></div>
        <div class="lista-simple lista-alta" id="lista-peores"></div>
    </div>
</section>

<div class="titulo-bloque">
    <h2>📐 <?= e(t('radar.titulo')) ?> <small class="texto-3" style="font-size:.85rem;font-weight:400"><?= e(t('radar.subtitulo')) ?></small></h2>
    <a href="<?= url('paginas/escaner.php') ?>"><?= e(t('radar.ver_escaner')) ?> →</a>
</div>
<section class="rejilla col-3 radar">
    <div class="panel">
        <h2>🧊 <?= e(t('radar.sobreventa')) ?> <small>RSI &lt; 30</small></h2>
        <div class="lista-simple" id="radar-sobreventa"><span class="esqueleto"><?= e(t('comun.cargando')) ?></span></div>
    </div>
    <div class="panel">
        <h2>🔥 <?= e(t('radar.sobrecompra')) ?> <small>RSI &gt; 70</small></h2>
        <div class="lista-simple" id="radar-sobrecompra"><span class="esqueleto"><?= e(t('comun.cargando')) ?></span></div>
    </div>
    <div class="panel">
        <h2>⚡ <?= e(t('radar.eventos')) ?></h2>
        <div class="lista-simple" id="radar-eventos"><span class="esqueleto"><?= e(t('comun.cargando')) ?></span></div>
    </div>
</section>

<div class="titulo-bloque">
    <h2>🧭 <?= e(t('inicio.explora')) ?></h2>
    <span class="texto-3"><?= e(t('inicio.explora_d')) ?></span>
</div>
<section class="explorar">
    <?php foreach ($secciones as [$clave, $ruta, $icono, $tit, $desc]): ?>
        <a class="tarjeta-seccion" href="<?= url($ruta) ?>">
            <span class="ico-grande" aria-hidden="true"><?= $icono ?></span>
            <h3><?= e(t($tit)) ?><?php if (!$usuario && in_array($clave, $protegidas, true)): ?> <span class="candado" title="<?= e(t('cuenta.solo_miembros')) ?>">🔒</span><?php endif; ?></h3>
            <p><?= e(t($desc)) ?></p>
            <span class="ir"><?= e(t('comun.abrir')) ?> →</span>
        </a>
    <?php endforeach; ?>
</section>

<?php
$scripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js',
    url('assets/js/app.js'), url('assets/js/graficos.js'), url('assets/js/tecnico.js'), url('assets/js/radar.js'), url('assets/js/inicio.js'),
];
require __DIR__ . '/includes/footer.php';
