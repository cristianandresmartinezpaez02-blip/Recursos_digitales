<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.diario');
$activa = 'diario';
require __DIR__ . '/../includes/header.php';

$estrategias = ['tendencia', 'ruptura', 'rebote', 'noticias', 'dca', 'scalping', 'otra'];
$emociones = ['tranquilo' => '😌', 'confiado' => '😎', 'nervioso' => '😬', 'codicia' => '🤑', 'miedo' => '😨', 'revancha' => '😤'];
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">📓 <?= e(t('nav.herramientas')) ?></div>
        <h1><?= e(t('nav.diario')) ?></h1>
        <p><?= e(t('dia.intro')) ?></p>
    </div>
    <div class="hero-acciones">
        <button class="boton secundario" type="button" id="d-importar">🎮 <?= e(t('dia.importar')) ?></button>
        <button class="boton secundario" type="button" id="d-exportar">📥 CSV</button>
    </div>
</section>

<section class="kpis">
    <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">💰</span> <?= e(t('dia.k_resultado')) ?></div><div class="kpi-valor" id="k-pnl">—</div><div class="kpi-sub" id="k-pnl-d">&nbsp;</div></article>
    <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">🎯</span> <?= e(t('dia.k_aciertos')) ?></div><div class="kpi-valor" id="k-acierto">—</div><div class="kpi-sub" id="k-acierto-d">&nbsp;</div></article>
    <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">⚖️</span> <?= e(t('dia.k_factor')) ?></div><div class="kpi-valor" id="k-factor">—</div><div class="kpi-sub"><?= e(t('dia.k_factor_d')) ?></div></article>
    <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">📐</span> <?= e(t('dia.k_esperanza')) ?></div><div class="kpi-valor" id="k-esperanza">—</div><div class="kpi-sub" id="k-esperanza-d"><?= e(t('dia.k_esperanza_d')) ?></div></article>
</section>

<section class="rejilla col-2-1">
    <div class="panel">
        <div class="panel-cabecera">
            <h2>🧾 <?= e(t('dia.operaciones')) ?> <small id="d-cuenta"></small></h2>
            <div class="pestanas" id="d-filtro">
                <button type="button" data-filtro="" class="activa"><?= e(t('comun.todas')) ?></button>
                <button type="button" data-filtro="abiertas"><?= e(t('dia.abiertas')) ?></button>
                <button type="button" data-filtro="ganadoras"><?= e(t('dia.ganadoras')) ?></button>
                <button type="button" data-filtro="perdedoras"><?= e(t('dia.perdedoras')) ?></button>
            </div>
        </div>
        <div class="tabla-scroll lista-alta">
            <table class="tabla compacta">
                <thead><tr><th><?= e(t('trading.fecha')) ?></th><th><?= e(t('col.moneda')) ?></th><th><?= e(t('dia.lado')) ?></th>
                    <th class="num"><?= e(t('dia.entrada')) ?></th><th class="num"><?= e(t('dia.salida')) ?></th><th class="num"><?= e(t('dia.monto')) ?></th>
                    <th class="num"><?= e(t('trading.resultado')) ?></th><th><?= e(t('dia.estrategia')) ?></th><th></th></tr></thead>
                <tbody id="d-tabla"></tbody>
            </table>
        </div>
    </div>

    <div class="panel" id="d-panel-form">
        <h2 id="d-form-titulo">➕ <?= e(t('dia.nueva')) ?></h2>
        <form id="d-form" class="form-cuenta" autocomplete="off">
            <input type="hidden" id="d-id">
            <div class="dos-columnas">
                <label class="campo-form"><?= e(t('trading.fecha')) ?><input type="date" id="d-fecha" required></label>
                <label class="campo-form"><?= e(t('col.moneda')) ?><input type="text" id="d-moneda" list="d-lista-monedas" required placeholder="BTC"></label>
            </div>
            <datalist id="d-lista-monedas"></datalist>
            <div class="selector-lado" role="radiogroup" aria-label="<?= e(t('dia.lado')) ?>">
                <label><input type="radio" name="d-lado" value="compra" checked> ▲ <?= e(t('dia.largo')) ?></label>
                <label><input type="radio" name="d-lado" value="venta"> ▼ <?= e(t('dia.corto')) ?></label>
            </div>
            <div class="dos-columnas">
                <label class="campo-form"><?= e(t('dia.entrada')) ?> (USD)<input type="number" id="d-entrada" min="0" step="any" required></label>
                <label class="campo-form"><?= e(t('dia.salida')) ?> (USD) <small class="texto-3"><?= e(t('dia.salida_d')) ?></small><input type="number" id="d-salida" min="0" step="any"></label>
            </div>
            <div class="dos-columnas">
                <label class="campo-form"><?= e(t('dia.monto')) ?> (USD)<input type="number" id="d-monto" min="0" step="any" required placeholder="1000"></label>
                <label class="campo-form"><?= e(t('dia.comision')) ?> (%)<input type="number" id="d-comision" min="0" max="5" step="any" value="0.1"></label>
            </div>
            <div class="dos-columnas">
                <label class="campo-form"><?= e(t('dia.estrategia')) ?>
                    <select id="d-estrategia"><?php foreach ($estrategias as $es): ?><option value="<?= $es ?>"><?= e(t('dia.e_' . $es)) ?></option><?php endforeach; ?></select>
                </label>
                <label class="campo-form"><?= e(t('dia.emocion')) ?>
                    <select id="d-emocion"><?php foreach ($emociones as $em => $ico): ?><option value="<?= $em ?>"><?= $ico ?> <?= e(t('dia.m_' . $em)) ?></option><?php endforeach; ?></select>
                </label>
            </div>
            <label class="aceptar"><input type="checkbox" id="d-plan" checked> <?= e(t('dia.plan')) ?></label>
            <label class="campo-form"><?= e(t('dia.notas')) ?><textarea id="d-notas" rows="2" maxlength="500" placeholder="<?= e(t('dia.notas_ph')) ?>"></textarea></label>
            <p class="orden-previa" id="d-previa"></p>
            <div class="fila-botones">
                <button class="boton" type="submit" id="d-guardar">💾 <?= e(t('dia.guardar')) ?></button>
                <button class="boton secundario" type="button" id="d-cancelar" hidden><?= e(t('comun.cancelar')) ?></button>
            </div>
            <p class="mensaje" id="d-mensaje" role="status"></p>
        </form>
    </div>
</section>

<section class="rejilla col-2">
    <div class="panel">
        <h2>📈 <?= e(t('dia.curva')) ?></h2>
        <div class="grafico-caja bajo"><canvas id="d-curva" aria-label="<?= e(t('dia.curva')) ?>"></canvas></div>
    </div>
    <div class="panel">
        <h2>🧠 <?= e(t('dia.analisis')) ?></h2>
        <div id="d-analisis"></div>
    </div>
</section>

<?php
$scripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    url('assets/js/app.js'),
    url('assets/js/diario.js'),
];
require __DIR__ . '/../includes/footer.php';
