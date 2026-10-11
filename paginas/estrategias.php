<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.estrategias');
$activa = 'estrategias';
require __DIR__ . '/../includes/header.php';

$reglas = [
    ['💸', 'est.regla1', 'est.regla1_d'],
    ['🧺', 'est.regla2', 'est.regla2_d'],
    ['🛡️', 'est.regla3', 'est.regla3_d'],
    ['📈', 'est.regla4', 'est.regla4_d'],
    ['🧘', 'est.regla5', 'est.regla5_d'],
];
// [clave, icono, ruta, cantidad de pasos, cantidad de errores]
$juegos = [
    ['trading', '💹', 'paginas/trading.php', 5, 3],
    ['reto', '⏩', 'paginas/reto.php', 5, 3],
    ['subebaja', '🎯', 'paginas/sube-baja.php', 4, 3],
];
$niveles = [['🐣', 'novato', '< 0 %'], ['📘', 'aprendiz', '≥ 0 %'], ['🦊', 'trader', '≥ +3 %'], ['🦈', 'tiburon', '≥ +10 %'], ['🐋', 'ballena', '≥ +25 %'], ['👑', 'leyenda', '≥ +50 %']];
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">🧠 <?= e(t('nav.juegos')) ?></div>
        <h1><?= e(t('est.titulo_a')) ?> <span class="titulo-gradiente"><?= e(t('est.titulo_b')) ?></span></h1>
        <p><?= e(t('est.intro')) ?></p>
    </div>
</section>

<div class="titulo-bloque"><h2>🏅 <?= e(t('est.reglas_titulo')) ?></h2></div>
<section class="reglas-oro">
    <?php foreach ($reglas as $i => [$ico, $tit, $desc]): ?>
        <article class="regla">
            <span class="regla-num"><?= $i + 1 ?></span>
            <span class="regla-ico" aria-hidden="true"><?= $ico ?></span>
            <h3><?= e(t($tit)) ?></h3>
            <p><?= e(t($desc)) ?></p>
        </article>
    <?php endforeach; ?>
</section>

<nav class="pestanas-seccion" id="pestanas-estrategias" role="tablist">
    <?php foreach ($juegos as [$clave, $ico]): ?>
        <a href="#<?= $clave ?>" role="tab"><?= $ico ?> <?= e(t('nav.' . $clave)) ?></a>
    <?php endforeach; ?>
    <a href="#calculadora" role="tab">🧮 <?= e(t('est.calc_titulo')) ?></a>
</nav>

<?php foreach ($juegos as [$clave, $ico, $ruta, $nPasos, $nErrores]): ?>
    <section id="<?= $clave ?>" role="tabpanel" hidden>
        <div class="rejilla col-2-1">
            <div class="panel">
                <h2><?= $ico ?> <?= e(t("est.{$clave}_como")) ?></h2>
                <div class="explicacion"><span class="ico">🏁</span><p><strong><?= e(t('est.objetivo')) ?>:</strong> <?= e(t("est.{$clave}_objetivo")) ?></p></div>
                <h3><?= e(t('est.paso_a_paso')) ?></h3>
                <div class="pasos">
                    <?php for ($i = 1; $i <= $nPasos; $i++): ?>
                        <div class="paso"><div><strong><?= e(t("est.{$clave}_p{$i}")) ?></strong><p><?= e(t("est.{$clave}_p{$i}_d")) ?></p></div></div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="panel">
                <h2>📊 <?= e(t('est.puntuacion')) ?></h2>
                <?php if ($clave === 'trading'): ?>
                    <p class="texto-2" style="font-size:.9rem"><?= e(t('est.trading_niveles')) ?></p>
                    <div class="records">
                        <?php foreach ($niveles as [$i, $n, $req]): ?>
                            <div class="fila"><span><?= $i ?> <?= e(t('js.nivel_' . $n)) ?></span><strong><?= $req ?></strong></div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($clave === 'reto'): ?>
                    <div class="records">
                        <div class="fila"><span>⭐ <?= e(t('est.reto_estrella1')) ?></span></div>
                        <div class="fila"><span>⭐⭐ <?= e(t('est.reto_estrella2')) ?></span></div>
                        <div class="fila"><span>⭐⭐⭐ <?= e(t('est.reto_estrella3')) ?></span></div>
                    </div>
                <?php else: ?>
                    <div class="records">
                        <div class="fila"><span>✔ <?= e(t('est.sb_acierto')) ?></span><strong>+10</strong></div>
                        <div class="fila"><span>🔥 <?= e(t('est.sb_racha')) ?></span><strong>+5 × n</strong></div>
                        <div class="fila"><span>💡 <?= e(t('sb.pista')) ?></span><strong>−3</strong></div>
                        <div class="fila"><span>✖ <?= e(t('est.sb_fallo')) ?></span><strong>−❤️</strong></div>
                    </div>
                <?php endif; ?>
                <h3 style="margin-top:1.25rem">⚠️ <?= e(t('est.errores')) ?></h3>
                <ul class="lista-errores">
                    <?php for ($i = 1; $i <= $nErrores; $i++): ?>
                        <li><?= e(t("est.{$clave}_e{$i}")) ?></li>
                    <?php endfor; ?>
                </ul>
                <a class="boton bloque" href="<?= url($ruta) ?>"><?= $ico ?> <?= e(t('est.ir_jugar')) ?></a>
            </div>
        </div>
    </section>
<?php endforeach; ?>

<section id="calculadora" role="tabpanel" hidden>
    <div class="rejilla col-2-1">
        <div class="panel">
            <h2>🧮 <?= e(t('est.calc_titulo')) ?></h2>
            <p class="subtitulo-panel"><?= e(t('est.calc_d')) ?></p>
            <form id="form-calc" class="form-linea" autocomplete="off">
                <label class="campo-form"><?= e(t('est.calc_capital')) ?><input type="number" id="c-capital" value="10000" min="1" step="any"></label>
                <label class="campo-form"><?= e(t('est.calc_posicion')) ?><input type="number" id="c-posicion" value="20" min="1" max="100" step="1"></label>
                <label class="campo-form"><?= e(t('trading.stop_loss')) ?><input type="number" id="c-sl" value="5" min="0.5" max="90" step="0.5"></label>
                <label class="campo-form"><?= e(t('trading.take_profit')) ?><input type="number" id="c-tp" value="10" min="0.5" max="500" step="0.5"></label>
                <label class="campo-form"><?= e(t('est.calc_acierto')) ?><input type="number" id="c-acierto" value="45" min="1" max="99" step="1"></label>
            </form>
            <div class="rejilla-datos" id="c-resultado" style="margin-top:1rem"></div>
            <p class="nota" id="c-veredicto"></p>
        </div>
        <div class="panel">
            <h2>💡 <?= e(t('est.calc_leccion')) ?></h2>
            <p class="texto-2" style="font-size:.9rem"><?= e(t('est.calc_leccion_d')) ?></p>
            <div class="records">
                <div class="fila"><span>SL 5 % · TP 5 %</span><strong>50 %</strong></div>
                <div class="fila"><span>SL 5 % · TP 10 %</span><strong>33 %</strong></div>
                <div class="fila"><span>SL 5 % · TP 15 %</span><strong>25 %</strong></div>
            </div>
            <p class="nota"><?= e(t('est.calc_tabla')) ?></p>
        </div>
    </div>
</section>

<div class="explicacion"><span class="ico">⚠️</span><p><?= e(t('est.aviso')) ?></p></div>

<?php
$scripts = [url('assets/js/app.js'), url('assets/js/estrategias.js')];
require __DIR__ . '/../includes/footer.php';
