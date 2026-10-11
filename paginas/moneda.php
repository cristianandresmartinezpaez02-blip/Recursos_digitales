<?php
require_once __DIR__ . '/../includes/api.php';

$id = strtolower(trim($_GET['id'] ?? ''));
try {
    $moneda = preg_match('/^[a-z0-9\-]+$/', $id) ? buscarEnTop100($id) : null;
} catch (Throwable $e) {
    $moneda = null;
}

if (!$moneda) {
    http_response_code(404);
    $titulo = t('moneda.no_encontrada');
    require __DIR__ . '/../includes/header.php';
    echo '<section class="panel estado-vacio"><div class="grande">🔍</div><h1>' . e(t('moneda.no_encontrada')) . '</h1>'
        . '<p>' . e(t('moneda.solo_top100')) . '</p>'
        . '<a class="boton" href="' . url('paginas/mercado.php') . '">' . e(t('moneda.volver')) . '</a></section>';
    $scripts = [url('assets/js/app.js')];
    require __DIR__ . '/../includes/footer.php';
    exit;
}

try {
    $detalle = obtenerDetalle($id);
} catch (Throwable $e) {
    $detalle = [];
}
$detalle += ['categorias' => [], 'descripcion' => '', 'descripcion_traducida' => true, 'web' => '', 'explorador' => '',
    'github' => '', 'reddit' => '', 'twitter' => '', 'genesis' => null, 'algoritmo' => null, 'sentimiento' => null,
    'whitepaper' => '', 'telegram' => '', 'watchlist' => null, 'rendimiento' => [], 'mercados' => [], 'n_mercados' => 0, 'tvl' => null, 'mcap_tvl' => null];

$a = $moneda['analisis'];
$titulo = $moneda['name'];
require __DIR__ . '/../includes/header.php';

$descripcion = trim(html_entity_decode(strip_tags($detalle['descripcion']), ENT_QUOTES, 'UTF-8'));
$parrafos = array_values(array_filter(array_map('trim', preg_split('/\r?\n\s*\r?\n/', $descripcion))));
$simbolo = strtoupper($moneda['symbol']);

$enlaces = array_filter([
    '🌐 ' . t('moneda.sitio') => $detalle['web'],
    '🔎 ' . t('moneda.explorador') => $detalle['explorador'],
    '💻 GitHub' => $detalle['github'],
    '📄 Whitepaper' => $detalle['whitepaper'],
    '💬 Reddit' => $detalle['reddit'],
    '✈️ Telegram' => $detalle['telegram'] ? 'https://t.me/' . $detalle['telegram'] : '',
    '𝕏 Twitter / X' => $detalle['twitter'] ? 'https://x.com/' . $detalle['twitter'] : '',
]);
?>

<p class="migas"><a href="<?= url('paginas/mercado.php') ?>"><?= e(t('nav.top100')) ?></a> › <?= e($moneda['name']) ?></p>

<section class="cabecera-moneda" id="cabecera-moneda" data-id="<?= e($id) ?>" data-simbolo="<?= e($simbolo) ?>">
    <div class="identidad">
        <img src="<?= e($moneda['image']) ?>" alt="">
        <div>
            <h1><?= e($moneda['name']) ?> <span class="simbolo"><?= e($simbolo) ?></span>
                <button class="estrella" data-fav="<?= e($id) ?>" aria-pressed="false" title="<?= e(t('js.fav_agregar')) ?>" aria-label="<?= e(t('js.fav_agregar')) ?>">★</button></h1>
            <div class="meta-moneda">
                <span class="rank"><?= e(t('moneda.puesto', ['n' => (int) $moneda['market_cap_rank']])) ?></span>
                <span class="insignia <?= e($a['veredicto']) ?>" id="insignia-cabecera"><?= e($a['etiqueta']) ?> · <?= (int) $a['puntaje'] ?></span>
            </div>
        </div>
    </div>
    <div class="precio-grande">
        <strong id="precio-actual">—</strong>
        <div class="cambios-periodo" id="cambios-periodo"></div>
    </div>
    <div class="fila-meta" style="grid-column:1/-1">
        <span>₿ <?= e(t('moneda.en_btc')) ?>: <strong id="precio-btc">—</strong></span>
        <span>🏦 <?= e(t('col.cap')) ?>: <strong><?= fmt($moneda['market_cap']) ?></strong></span>
        <span>💧 <?= e(t('kpi.volumen')) ?>: <strong><?= fmt($moneda['total_volume']) ?></strong></span>
        <?php if (!empty($detalle['n_mercados'])): ?><span>🛒 <?= e(t('moneda.en_mercados', ['n' => $detalle['n_mercados'] >= 100 ? '100+' : $detalle['n_mercados']])) ?></span><?php endif; ?>
        <?php if (!empty($detalle['watchlist'])): ?><span>👀 <strong><?= fmt($detalle['watchlist'], 'compacto') ?></strong> <?= e(t('moneda.siguiendo')) ?></span><?php endif; ?>
        <span class="acciones" style="margin:0 0 0 auto;justify-content:flex-end">
            <a class="boton mini" href="<?= url('paginas/simulador.php?moneda=' . rawurlencode($id)) ?>">🧮 <?= e(t('moneda.simular')) ?></a>
            <a class="boton mini secundario" href="#herramientas" data-ir-pestana="herramientas">🔔 <?= e(t('moneda.crear_alerta')) ?></a>
            <a class="boton mini secundario" href="<?= url('paginas/comparar.php?ids=' . rawurlencode($id)) ?>">⚖️ <?= e(t('nav.comparar')) ?></a>
        </span>
    </div>
</section>

<nav class="pestanas-seccion" id="pestanas-moneda" role="tablist" aria-label="<?= e(t('moneda.secciones')) ?>">
    <a href="#resumen" role="tab">📈 <?= e(t('moneda.tab_resumen')) ?></a>
    <a href="#analisis" role="tab">🎯 <?= e(t('moneda.tab_analisis')) ?></a>
    <a href="#tecnico" role="tab">📐 <?= e(t('tec.tab')) ?></a>
    <a href="#datos" role="tab">📋 <?= e(t('moneda.tab_datos')) ?></a>
    <?php if ($detalle['mercados'] ?? []): ?><a href="#mercados" role="tab">🛒 <?= e(t('moneda.tab_mercados')) ?></a><?php endif; ?>
    <a href="#herramientas" role="tab">🧰 <?= e(t('moneda.tab_herramientas')) ?></a>
    <a href="#acerca" role="tab">📖 <?= e(t('moneda.tab_acerca')) ?></a>
</nav>

<!-- Resumen: gráfico -->
<section id="resumen" role="tabpanel">
    <div class="panel">
        <div class="panel-cabecera">
            <h2><?= e(t('moneda.grafico')) ?> <small id="subtitulo-grafico"></small></h2>
            <div class="interruptor-grafico" id="tipo-grafico" aria-label="<?= e(t('moneda.tipo_grafico')) ?>">
                <button type="button" data-tipo="linea" class="activa">📈 <?= e(t('moneda.linea')) ?></button>
                <button type="button" data-tipo="velas">🕯️ <?= e(t('moneda.velas')) ?></button>
            </div>
            <div class="pestanas" id="rango-grafico" aria-label="<?= e(t('moneda.rango')) ?>">
                <button class="activa" data-dias="1">24 h</button>
                <button data-dias="7">7 d</button>
                <button data-dias="30">30 d</button>
                <button data-dias="90">90 d</button>
                <button data-dias="365"><?= e(t('moneda.1ano')) ?></button>
            </div>
        </div>
        <div class="grafico-caja"><canvas id="grafico-precio" aria-label="<?= e(t('moneda.grafico')) ?>"></canvas></div>
        <div class="resumen-rango" id="resumen-rango"></div>
        <p class="nota" id="nota-velas" hidden><?= e(t('moneda.velas_d')) ?></p>
    </div>
    <?php if ($detalle['rendimiento'] ?? []): ?>
        <div class="panel">
            <h2>📊 <?= e(t('moneda.rendimiento')) ?></h2>
            <p class="subtitulo-panel"><?= e(t('moneda.rendimiento_d')) ?></p>
            <div class="grafico-caja mini"><canvas id="grafico-rendimiento" aria-label="<?= e(t('moneda.rendimiento')) ?>"></canvas></div>
        </div>
    <?php endif; ?>
    <div class="rejilla col-2">
        <div class="panel rango-dia">
            <h2><?= e(t('moneda.rango_dia')) ?></h2>
            <p class="subtitulo-panel"><?= e(t('moneda.rango_dia_d')) ?></p>
            <div class="pista"><span class="marcador" id="marcador-dia"></span></div>
            <div class="extremos"><span id="minimo-dia">—</span><span id="maximo-dia">—</span></div>
        </div>
        <a class="panel veredicto veredicto-<?= e($a['veredicto']) ?>" href="#analisis" id="atajo-analisis" style="color:inherit;text-decoration:none">
            <h2><?= e(t('moneda.viable_pregunta')) ?></h2>
            <div class="insignia grande <?= e($a['veredicto']) ?>" id="atajo-etiqueta"><?= e($a['etiqueta']) ?> · <?= (int) $a['puntaje'] ?>/100</div>
            <p><?= e(t('moneda.ver_analisis')) ?> →</p>
        </a>
    </div>
</section>

<!-- Análisis técnico (assets/js/moneda-tecnico.js) -->
<section id="tecnico" role="tabpanel" hidden>
    <div class="explicacion"><span class="ico">📐</span><p><?= e(t('tec.explicacion')) ?></p></div>
    <div class="rejilla col-2-1">
        <div class="panel">
            <div class="panel-cabecera">
                <h2><?= e(t('tec.grafico')) ?> <small id="tec-subtitulo"></small></h2>
                <div class="pestanas" id="tec-marco" aria-label="<?= e(t('tec.marco')) ?>">
                    <button type="button" data-marco="h4" class="activa"><?= e(t('tec.marco_h4')) ?></button>
                    <button type="button" data-marco="d1"><?= e(t('tec.marco_d1')) ?></button>
                </div>
            </div>
            <div class="chips" id="tec-capas" style="margin-bottom:.6rem">
                <button type="button" class="chip activo" data-capa="corta"><span class="muestra-linea serie-2"></span> <?= e(t('tec.sma', ['n' => 20])) ?></button>
                <button type="button" class="chip activo" data-capa="larga"><span class="muestra-linea serie-4"></span> <?= e(t('tec.sma', ['n' => 50])) ?></button>
                <button type="button" class="chip activo" data-capa="bollinger"><span class="muestra-linea punteada"></span> <?= e(t('tec.bollinger')) ?></button>
            </div>
            <div class="grafico-caja"><canvas id="tec-precio" aria-label="<?= e(t('tec.grafico')) ?>"></canvas></div>
            <h3 class="titulo-sub">RSI (14) <small><?= e(t('tec.rsi_d')) ?></small></h3>
            <div class="grafico-caja mini"><canvas id="tec-rsi" aria-label="RSI"></canvas></div>
            <h3 class="titulo-sub">MACD (12, 26, 9) <small><?= e(t('tec.macd_d')) ?></small></h3>
            <div class="grafico-caja mini"><canvas id="tec-macd" aria-label="MACD"></canvas></div>
        </div>
        <div class="panel">
            <h2><?= e(t('tec.resumen')) ?></h2>
            <div class="medidor tec-medidor" id="tec-medidor"></div>
            <p class="tec-veredicto" id="tec-veredicto">—</p>
            <div class="tec-votos" id="tec-votos"></div>
            <h3 class="titulo-sub">🧱 <?= e(t('tec.niveles')) ?></h3>
            <div id="tec-niveles" class="tec-niveles"></div>
            <h3 class="titulo-sub">⚡ <?= e(t('tec.eventos')) ?></h3>
            <div id="tec-eventos" class="chips"></div>
            <p class="nota"><?= e(t('tec.aviso')) ?></p>
        </div>
    </div>
    <div class="panel">
        <h2>📚 <?= e(t('tec.que_es')) ?></h2>
        <div class="glosario-tecnico">
            <?php foreach (['rsi', 'macd', 'sma', 'bollinger', 'niveles', 'cruces'] as $ind): ?>
                <details><summary><?= e(t("tec.g_{$ind}")) ?></summary><p><?= e(t("tec.g_{$ind}_d")) ?></p></details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Análisis -->
<section id="analisis" role="tabpanel" hidden>
    <div class="explicacion"><span class="ico">ℹ️</span><p><?= e(t('moneda.analisis_explicacion')) ?></p></div>
    <div class="rejilla col-2-1">
        <div class="panel">
            <h2><?= e(t('moneda.factores')) ?></h2>
            <p class="subtitulo-panel"><?= e(t('moneda.factores_d')) ?></p>
            <div class="factores" id="factores" style="margin-top:0"></div>
        </div>
        <div class="panel veredicto veredicto-<?= e($a['veredicto']) ?>" id="panel-veredicto">
            <h2><?= e(t('moneda.viable_pregunta')) ?></h2>
            <div class="medidor" id="medidor-puntaje"></div>
            <div class="insignia grande <?= e($a['veredicto']) ?>" id="etiqueta"><?= e($a['etiqueta']) ?></div>
            <p id="resumen-analisis"><?= e($a['resumen']) ?></p>
            <div class="datos-riesgo"><span class="chip" id="chip-riesgo"><?= e(t('moneda.riesgo')) ?>: <?= e($a['riesgo']) ?></span></div>
            <div class="acciones">
                <a class="boton" href="<?= url('paginas/trading.php?moneda=' . rawurlencode($id)) ?>">🎮 <?= e(t('moneda.practicar')) ?></a>
                <a class="boton secundario" href="<?= url('paginas/comparar.php?ids=' . rawurlencode($id)) ?>">⚖️ <?= e(t('nav.comparar')) ?></a>
            </div>
        </div>
    </div>
    <div class="rejilla col-2">
        <div class="panel">
            <h2>✅ <?= e(t('moneda.pros')) ?></h2>
            <ul class="lista-razones pros" id="lista-pros"></ul>
        </div>
        <div class="panel">
            <h2>⚠️ <?= e(t('moneda.contras')) ?></h2>
            <ul class="lista-razones contras" id="lista-contras"></ul>
        </div>
    </div>
</section>

<!-- Datos -->
<section id="datos" role="tabpanel" hidden>
    <div class="panel">
        <h2><?= e(t('moneda.datos_mercado')) ?></h2>
        <div class="rejilla-datos" id="datos-mercado"></div>
    </div>
    <div class="rejilla col-2">
        <div class="panel">
            <h2><?= e(t('moneda.oferta')) ?></h2>
            <p class="subtitulo-panel"><?= e(t('moneda.oferta_d')) ?></p>
            <div id="oferta"></div>
        </div>
        <div class="panel">
            <h2><?= e(t('moneda.records')) ?></h2>
            <div class="rejilla-datos" id="records" style="grid-template-columns:minmax(0,1fr)"></div>
        </div>
    </div>
    <?php if ($detalle['sentimiento'] !== null): $up = (float) $detalle['sentimiento']; ?>
        <div class="panel sentimiento">
            <h2><?= e(t('moneda.sentimiento')) ?></h2>
            <div class="pista"><span style="width:<?= $up ?>%"></span><span style="width:<?= 100 - $up ?>%"></span></div>
            <div class="extremos"><span class="sube">▲ <?= e(t('moneda.optimista', ['n' => num($up, 0)])) ?></span><span class="baja"><?= e(t('moneda.pesimista', ['n' => num(100 - $up, 0)])) ?> ▼</span></div>
            <p class="nota"><?= e(t('moneda.sentimiento_d')) ?></p>
        </div>
    <?php endif; ?>
</section>

<!-- Mercados: dónde se compra y vende -->
<?php if ($detalle['mercados'] ?? []): ?>
<?php $hayConfianza = (bool) array_filter(array_column($detalle['mercados'], 'confianza')); ?>
<section id="mercados" role="tabpanel" hidden>
    <div class="explicacion"><span class="ico">ℹ️</span><p><?= e(t('moneda.mercados_explicacion')) ?></p></div>
    <div class="panel">
        <h2>🛒 <?= e(t('moneda.donde_comprar', ['nombre' => $moneda['name']])) ?></h2>
        <div class="tabla-scroll">
            <table class="tabla tabla-mercados">
                <thead><tr><th>#</th><th>Exchange</th><th><?= e(t('moneda.par')) ?></th><th class="num"><?= e(t('col.precio')) ?></th><th class="num"><?= e(t('kpi.volumen')) ?></th><th class="num">Spread</th><?php if ($hayConfianza): ?><th><?= e(t('ex.confianza')) ?></th><?php endif; ?><th></th></tr></thead>
                <tbody>
                <?php foreach ($detalle['mercados'] as $i => $mk):
                    $colorConf = ['green' => 'var(--sube)', 'yellow' => 'var(--neutral)', 'red' => 'var(--baja)'][$mk['confianza']] ?? 'var(--gris-medio)';
                    $textoConf = ['green' => t('moneda.conf_alta'), 'yellow' => t('moneda.conf_media'), 'red' => t('moneda.conf_baja')][$mk['confianza']] ?? '—'; ?>
                    <tr>
                        <td class="texto-3"><?= $i + 1 ?></td>
                        <td><strong><?= e($mk['exchange']) ?></strong></td>
                        <td class="texto-2"><?= e($mk['par']) ?></td>
                        <td class="num"><?= fmt($mk['precio'], 'precio') ?></td>
                        <td class="num"><?= fmt($mk['volumen']) ?></td>
                        <td class="num texto-2"><?= $mk['spread'] !== null ? e(num((float) $mk['spread'], 2)) . ' %' : '—' ?></td>
                        <?php if ($hayConfianza): ?><td><span class="confianza" style="background:<?= $colorConf ?>"></span><?= e($textoConf) ?></td><?php endif; ?>
                        <td><?php if ($mk['url']): ?><a class="boton mini secundario" href="<?= e($mk['url']) ?>" target="_blank" rel="noopener nofollow"><?= e(t('ex.visitar')) ?> ↗</a><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="nota"><?= e(t('moneda.mercados_nota')) ?></p>
    </div>
</section>
<?php endif; ?>

<!-- Herramientas -->
<section id="herramientas" role="tabpanel" hidden>
    <div class="rejilla col-3">
        <div class="panel">
            <h2>🔁 <?= e(t('moneda.conversor')) ?></h2>
            <div class="conversor">
                <label class="campo"><input type="number" id="conv-cripto" value="1" min="0" step="any" aria-label="<?= e($simbolo) ?>"><span><?= e($simbolo) ?></span></label>
                <span class="intercambio texto-3" aria-hidden="true">⇅</span>
                <label class="campo"><input type="number" id="conv-usd" min="0" step="any" aria-label="USD"><span>USD</span></label>
                <p class="nota"><?= e(t('moneda.conversor_d')) ?></p>
            </div>
        </div>
        <div class="panel">
            <h2>⏳ <?= e(t('moneda.simulador')) ?></h2>
            <p class="subtitulo-panel"><?= e(t('moneda.simulador_d')) ?></p>
            <div id="simulador-pasado" class="rejilla-datos" style="grid-template-columns:repeat(2,minmax(0,1fr))"></div>
        </div>
        <div class="panel">
            <h2>🔔 <?= e(t('moneda.crear_alerta')) ?></h2>
            <form id="form-alerta" class="conversor" autocomplete="off">
                <select id="alerta-condicion" aria-label="<?= e(t('alertas.condicion')) ?>">
                    <option value="mayor"><?= e(t('alertas.mayor')) ?></option>
                    <option value="menor"><?= e(t('alertas.menor')) ?></option>
                </select>
                <label class="campo"><input type="number" id="alerta-precio" min="0" step="any" required aria-label="<?= e(t('col.precio')) ?>"><span>USD</span></label>
                <button class="boton" type="submit"><?= e(t('alertas.crear')) ?></button>
                <p class="nota"><?= e(t('moneda.alerta_d')) ?> <a href="<?= url('paginas/alertas.php') ?>"><?= e(t('alertas.ver')) ?></a></p>
            </form>
        </div>
    </div>
</section>

<!-- Acerca de -->
<section id="acerca" role="tabpanel" hidden>
    <div class="panel">
        <h2><?= e(t('moneda.sobre', ['nombre' => $moneda['name']])) ?></h2>
        <?php if ($detalle['categorias']): ?>
            <div class="chips" style="margin-bottom:1rem">
                <?php foreach (array_slice($detalle['categorias'], 0, 6) as $c): ?><span class="chip"><?= e($c) ?></span><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($parrafos): ?>
            <?php if (!$detalle['descripcion_traducida']): ?><p class="nota" style="margin:0 0 .75rem"><?= e(t('moneda.solo_ingles')) ?></p><?php endif; ?>
            <div class="descripcion <?= count($parrafos) > 2 ? 'recortada' : '' ?>" id="descripcion">
                <?php foreach ($parrafos as $p): ?><p><?= e($p) ?></p><?php endforeach; ?>
            </div>
            <?php if (count($parrafos) > 2): ?>
                <button class="boton secundario mini" id="leer-mas" type="button" data-mas="<?= e(t('moneda.leer_mas')) ?>" data-menos="<?= e(t('moneda.leer_menos')) ?>"><?= e(t('moneda.leer_mas')) ?></button>
            <?php endif; ?>
        <?php else: ?>
            <p class="texto-3"><?= e(t('moneda.sin_descripcion')) ?></p>
        <?php endif; ?>
        <?php if ($enlaces): ?>
            <div class="enlaces">
                <?php foreach ($enlaces as $texto => $enlace): ?>
                    <a href="<?= e($enlace) ?>" target="_blank" rel="noopener nofollow"><?= e($texto) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <p class="nota">
            <?php if ($detalle['genesis']): ?><?= e(t('moneda.lanzamiento')) ?>: <?= e($detalle['genesis']) ?>. <?php endif; ?>
            <?php if ($detalle['algoritmo']): ?><?= e(t('moneda.algoritmo')) ?>: <?= e($detalle['algoritmo']) ?>.<?php endif; ?>
        </p>
    </div>
    <div class="panel">
        <h2><?= e(t('moneda.cercanas')) ?></h2>
        <div class="similares" id="similares"></div>
    </div>
</section>

<script>
    window.ANALISIS_INICIAL = <?= json_encode($a, JSON_UNESCAPED_UNICODE) ?>;
    window.RENDIMIENTO = <?= json_encode((object) $detalle['rendimiento']) ?>;
</script>
<?php
$scripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js',
    'https://cdn.jsdelivr.net/npm/chartjs-chart-financial@0.2.1/dist/chartjs-chart-financial.min.js',
    url('assets/js/app.js'),
    url('assets/js/moneda.js'),
    url('assets/js/tecnico.js'),
    url('assets/js/moneda-tecnico.js'),
];
require __DIR__ . '/../includes/footer.php';
