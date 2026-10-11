<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.calculadoras');
$activa = 'calculadoras';
require __DIR__ . '/../includes/header.php';

/** Campo numérico del formulario. */
function campoNum(string $id, string $etiqueta, $valor, string $extra = 'min="0" step="any"'): string
{
    return '<label class="campo-form">' . e($etiqueta) . '<input type="number" id="' . $id . '" value="' . e((string) $valor) . '" ' . $extra . '></label>';
}
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">🧮 <?= e(t('nav.herramientas')) ?></div>
        <h1><?= e(t('nav.calculadoras')) ?></h1>
        <p><?= e(t('calc.intro')) ?></p>
    </div>
    <label class="campo-form" style="min-width:240px"><?= e(t('calc.usar_precio')) ?>
        <select id="c-moneda"><option value=""><?= e(t('calc.manual')) ?></option></select>
    </label>
</section>

<nav class="pestanas-seccion" id="pestanas-calc" role="tablist">
    <a href="#posicion" role="tab">🎯 <?= e(t('calc.tab_posicion')) ?></a>
    <a href="#apalancamiento" role="tab">⚡ <?= e(t('calc.tab_apalancamiento')) ?></a>
    <a href="#ganancia" role="tab">💵 <?= e(t('calc.tab_ganancia')) ?></a>
    <a href="#promedio" role="tab">⚖️ <?= e(t('calc.tab_promedio')) ?></a>
    <a href="#compuesto" role="tab">📈 <?= e(t('calc.tab_compuesto')) ?></a>
</nav>

<section id="posicion" role="tabpanel">
    <div class="rejilla col-2-1">
        <div class="panel">
            <h2>🎯 <?= e(t('calc.pos_titulo')) ?></h2>
            <p class="subtitulo-panel"><?= e(t('calc.pos_d')) ?></p>
            <form class="form-linea calc" data-calc="posicion">
                <?= campoNum('p-capital', t('calc.capital'), 10000) ?>
                <?= campoNum('p-riesgo', t('calc.riesgo_pct'), 1, 'min="0.1" max="100" step="0.1"') ?>
                <?= campoNum('p-entrada', t('calc.entrada'), 100) ?>
                <?= campoNum('p-stop', t('calc.stop'), 95) ?>
                <?= campoNum('p-rr', t('calc.rr'), 2, 'min="0.5" max="10" step="0.5"') ?>
            </form>
            <div class="rejilla-datos resultado-calc" id="r-posicion"></div>
        </div>
        <div class="panel"><h2>💡 <?= e(t('calc.por_que')) ?></h2><p class="texto-2"><?= e(t('calc.pos_explica')) ?></p></div>
    </div>
</section>

<section id="apalancamiento" role="tabpanel" hidden>
    <div class="rejilla col-2-1">
        <div class="panel">
            <h2>⚡ <?= e(t('calc.ap_titulo')) ?></h2>
            <p class="subtitulo-panel"><?= e(t('calc.ap_d')) ?></p>
            <form class="form-linea calc" data-calc="apalancamiento">
                <label class="campo-form"><?= e(t('dia.lado')) ?>
                    <select id="a-lado"><option value="largo">▲ <?= e(t('dia.largo')) ?></option><option value="corto">▼ <?= e(t('dia.corto')) ?></option></select>
                </label>
                <?= campoNum('a-entrada', t('calc.entrada'), 100) ?>
                <?= campoNum('a-margen', t('calc.margen'), 100) ?>
                <?= campoNum('a-palanca', t('calc.palanca'), 10, 'min="1" max="125" step="1"') ?>
                <?= campoNum('a-mantenimiento', t('calc.mantenimiento'), 0.5, 'min="0" max="10" step="0.1"') ?>
            </form>
            <div class="rejilla-datos resultado-calc" id="r-apalancamiento"></div>
            <h3 class="titulo-sub"><?= e(t('calc.ap_escenarios')) ?></h3>
            <div class="tabla-scroll"><table class="tabla compacta" id="r-escenarios"></table></div>
        </div>
        <div class="panel"><h2>⚠️ <?= e(t('calc.cuidado')) ?></h2><p class="texto-2"><?= e(t('calc.ap_explica')) ?></p></div>
    </div>
</section>

<section id="ganancia" role="tabpanel" hidden>
    <div class="rejilla col-2-1">
        <div class="panel">
            <h2>💵 <?= e(t('calc.g_titulo')) ?></h2>
            <form class="form-linea calc" data-calc="ganancia">
                <label class="campo-form"><?= e(t('dia.lado')) ?>
                    <select id="g-lado"><option value="largo">▲ <?= e(t('dia.largo')) ?></option><option value="corto">▼ <?= e(t('dia.corto')) ?></option></select>
                </label>
                <?= campoNum('g-entrada', t('calc.entrada'), 100) ?>
                <?= campoNum('g-salida', t('calc.salida'), 110) ?>
                <?= campoNum('g-monto', t('calc.monto'), 1000) ?>
                <?= campoNum('g-comision', t('calc.comision'), 0.1, 'min="0" max="5" step="0.01"') ?>
            </form>
            <div class="rejilla-datos resultado-calc" id="r-ganancia"></div>
        </div>
        <div class="panel"><h2>💡 <?= e(t('calc.por_que')) ?></h2><p class="texto-2"><?= e(t('calc.g_explica')) ?></p></div>
    </div>
</section>

<section id="promedio" role="tabpanel" hidden>
    <div class="rejilla col-2-1">
        <div class="panel">
            <h2>⚖️ <?= e(t('calc.pr_titulo')) ?></h2>
            <p class="subtitulo-panel"><?= e(t('calc.pr_d')) ?></p>
            <div id="pr-filas" class="filas-compras"></div>
            <button class="boton mini secundario" type="button" id="pr-agregar">➕ <?= e(t('calc.pr_agregar')) ?></button>
            <div class="rejilla-datos resultado-calc" id="r-promedio" style="margin-top:1rem"></div>
        </div>
        <div class="panel"><h2>💡 <?= e(t('calc.por_que')) ?></h2><p class="texto-2"><?= e(t('calc.pr_explica')) ?></p></div>
    </div>
</section>

<section id="compuesto" role="tabpanel" hidden>
    <div class="rejilla col-2-1">
        <div class="panel">
            <h2>📈 <?= e(t('calc.ic_titulo')) ?></h2>
            <form class="form-linea calc" data-calc="compuesto">
                <?= campoNum('i-capital', t('calc.capital'), 1000) ?>
                <?= campoNum('i-tasa', t('calc.tasa'), 2, 'step="0.1"') ?>
                <?= campoNum('i-periodos', t('calc.periodos'), 24, 'min="1" max="600" step="1"') ?>
                <?= campoNum('i-aporte', t('calc.aporte'), 100) ?>
            </form>
            <div class="rejilla-datos resultado-calc" id="r-compuesto"></div>
            <div class="grafico-caja bajo" style="margin-top:1rem"><canvas id="i-grafico" aria-label="<?= e(t('calc.ic_titulo')) ?>"></canvas></div>
        </div>
        <div class="panel"><h2>💡 <?= e(t('calc.por_que')) ?></h2><p class="texto-2"><?= e(t('calc.ic_explica')) ?></p></div>
    </div>
</section>

<div class="explicacion"><span class="ico">⚠️</span><p><?= e(t('est.aviso')) ?></p></div>

<?php
$scripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    url('assets/js/app.js'),
    url('assets/js/calculadoras.js'),
];
require __DIR__ . '/../includes/footer.php';
