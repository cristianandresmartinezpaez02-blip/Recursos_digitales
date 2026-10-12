<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.estafas');
$activa = 'estafas';
require __DIR__ . '/../includes/header.php';

// Señales de alerta del detector: [clave, peso]. Las de peso 3 bastan para sospechar en serio.
$senales = [['garantia', 3], ['claves', 3], ['retiros', 3], ['referidos', 2], ['rapida', 2], ['urgencia', 2], ['no_regulada', 2], ['canal', 1], ['famoso', 1], ['secreto', 1]];
$casos = [['ponzi', 'estafa'], ['dosfa', 'legitimo'], ['soporte', 'estafa'], ['dca', 'legitimo'], ['liberacion', 'estafa'], ['deepfake', 'estafa'], ['x100', 'estafa'], ['practica', 'legitimo']];
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">🛡️ <?= e(t('nav.aprende')) ?></div>
        <h1><?= e(t('estafas.titulo_a')) ?> <span class="titulo-gradiente"><?= e(t('estafas.titulo_b')) ?></span></h1>
        <p><?= e(t('estafas.intro')) ?></p>
    </div>
</section>

<nav class="pestanas-seccion" id="pestanas-estafas" role="tablist">
    <a href="#detector" role="tab">🚨 <?= e(t('estafas.tab_detector')) ?></a>
    <a href="#calculadora" role="tab">🧮 <?= e(t('estafas.tab_calculadora')) ?></a>
    <a href="#quiz" role="tab">🎯 <?= e(t('estafas.tab_quiz')) ?></a>
    <a href="#ayuda" role="tab">🆘 <?= e(t('estafas.tab_ayuda')) ?></a>
</nav>

<!-- Detector de señales de alerta -->
<section id="detector" role="tabpanel" class="panel">
    <h2><?= e(t('estafas.detector_titulo')) ?></h2>
    <p class="texto-2"><?= e(t('estafas.detector_intro')) ?></p>
    <form id="form-detector" class="lista-senales">
        <?php foreach ($senales as [$clave, $peso]): ?>
            <label class="senal">
                <input type="checkbox" value="<?= $peso ?>" data-senal="<?= $clave ?>">
                <span><strong><?= e(t("estafas.s_$clave")) ?></strong><small><?= e(t("estafas.s_{$clave}_d")) ?></small></span>
            </label>
        <?php endforeach; ?>
    </form>
    <div class="resultado-riesgo" id="resultado-detector" aria-live="polite">
        <span class="insignia viable" id="detector-nivel">✔ <?= e(t('estafas.riesgo_bajo')) ?></span>
        <div class="medidor-riesgo"><span id="detector-barra" style="width:0%"></span></div>
        <p id="detector-texto"><?= e(t('estafas.riesgo_bajo_d')) ?></p>
    </div>
</section>

<!-- Calculadora de rentabilidad imposible -->
<section id="calculadora" role="tabpanel" class="panel" hidden>
    <h2><?= e(t('estafas.calc_titulo')) ?></h2>
    <p class="texto-2"><?= e(t('estafas.calc_intro')) ?></p>
    <form id="form-calc" class="form-linea" onsubmit="return false">
        <label class="campo-form"><?= e(t('estafas.calc_monto')) ?><input type="number" id="calc-monto" min="1" step="any" value="1000000"></label>
        <label class="campo-form"><?= e(t('estafas.calc_pct')) ?><input type="number" id="calc-pct" min="0" max="500" step="any" value="10"></label>
        <label class="campo-form"><?= e(t('estafas.calc_periodo')) ?>
            <select id="calc-periodo">
                <option value="365"><?= e(t('estafas.p_dia')) ?></option>
                <option value="52"><?= e(t('estafas.p_semana')) ?></option>
                <option value="12" selected><?= e(t('estafas.p_mes')) ?></option>
            </select>
        </label>
    </form>
    <div class="rejilla col-3" style="margin-top:1rem">
        <div class="dato"><span><?= e(t('estafas.calc_anual')) ?></span><strong id="calc-anual">—</strong></div>
        <div class="dato"><span><?= e(t('estafas.calc_final')) ?></span><strong id="calc-final">—</strong></div>
        <div class="dato"><span><?= e(t('estafas.calc_veredicto')) ?></span><strong id="calc-veredicto">—</strong></div>
    </div>
    <p class="texto-2" id="calc-explicacion"></p>
    <div class="explicacion"><span class="ico">💡</span><p><?= e(t('estafas.calc_referencia')) ?></p></div>
</section>

<!-- Quiz: ¿estafa o no? -->
<section id="quiz" role="tabpanel" class="panel" hidden>
    <div class="panel-cabecera">
        <h2><?= e(t('estafas.quiz_titulo')) ?></h2>
        <span class="chip" id="quiz-progreso">1 / <?= count($casos) ?></span>
    </div>
    <div id="quiz-caja">
        <p class="caso" id="quiz-caso"></p>
        <div class="botones-quiz">
            <button class="boton" type="button" data-resp="estafa">🚨 <?= e(t('estafas.es_estafa')) ?></button>
            <button class="boton secundario" type="button" data-resp="legitimo">✅ <?= e(t('estafas.es_legitimo')) ?></button>
        </div>
        <p class="mensaje" id="quiz-feedback" role="status"></p>
        <button class="boton secundario" type="button" id="quiz-siguiente" hidden><?= e(t('estafas.siguiente')) ?> →</button>
    </div>
    <div id="quiz-final" hidden>
        <p class="quiz-puntaje" id="quiz-puntaje"></p>
        <p class="texto-2" id="quiz-comentario"></p>
        <button class="boton" type="button" id="quiz-reiniciar">🔁 <?= e(t('estafas.reintentar')) ?></button>
    </div>
</section>

<!-- Qué hacer si te estafaron -->
<section id="ayuda" role="tabpanel" class="panel" hidden>
    <h2><?= e(t('estafas.ayuda_titulo')) ?></h2>
    <div class="pasos">
        <?php for ($i = 1; $i <= 6; $i++): ?>
            <div class="paso"><div><strong><?= e(t("estafas.ayuda{$i}")) ?></strong><p><?= e(t("estafas.ayuda{$i}_d")) ?></p></div></div>
        <?php endfor; ?>
    </div>
    <h3><?= e(t('estafas.enlaces_titulo')) ?></h3>
    <div class="chips">
        <a class="chip" href="https://caivirtual.policia.gov.co" target="_blank" rel="noopener">👮 <?= e(t('estafas.enlace_cai')) ?></a>
        <a class="chip" href="https://www.fiscalia.gov.co" target="_blank" rel="noopener">⚖️ <?= e(t('estafas.enlace_fiscalia')) ?></a>
        <a class="chip" href="https://www.superfinanciera.gov.co" target="_blank" rel="noopener">🏛️ <?= e(t('estafas.enlace_sfc')) ?></a>
    </div>
    <p class="nota"><?= e(t('estafas.enlaces_nota')) ?></p>
</section>

<script>
    window.CASOS_ESTAFA = <?= json_encode(array_map(fn($c) => ['texto' => t("estafas.caso_{$c[0]}"), 'explica' => t("estafas.caso_{$c[0]}_d"), 'resp' => $c[1]], $casos), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    document.addEventListener('DOMContentLoaded', () => window.Cripto && window.Cripto.iniciarPestanas('pestanas-estafas'));
</script>
<?php
$scripts = [url('assets/js/app.js'), url('assets/js/estafas.js')];
require __DIR__ . '/../includes/footer.php';
