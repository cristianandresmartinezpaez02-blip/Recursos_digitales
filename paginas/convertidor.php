<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.convertidor');
$activa = 'convertidor';
require __DIR__ . '/../includes/header.php';
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">🔁 <?= e(t('nav.herramientas')) ?></div>
        <h1><?= e(t('nav.convertidor')) ?></h1>
        <p><?= e(t('nav.convertidor_d')) ?></p>
    </div>
</section>

<section class="rejilla col-2-1">
    <div class="panel">
        <h2><?= e(t('conv.titulo')) ?></h2>
        <div class="form-linea" style="margin-bottom:1rem">
            <label class="campo-form"><?= e(t('conv.cantidad')) ?><input type="number" id="cv-cantidad" value="1" min="0" step="any"></label>
            <label class="campo-form"><?= e(t('conv.de')) ?><select id="cv-de"></select></label>
            <button class="boton secundario" id="cv-invertir" type="button" title="<?= e(t('conv.invertir')) ?>" style="align-self:end">⇄</button>
            <label class="campo-form"><?= e(t('conv.a')) ?><select id="cv-a"></select></label>
        </div>
        <div class="resultado-grande" id="cv-resultado">—</div>
        <p class="texto-2" id="cv-tasa"></p>
    </div>
    <div class="panel">
        <h2>💵 <?= e(t('conv.con_dolares')) ?></h2>
        <p class="subtitulo-panel"><?= e(t('conv.con_dolares_d')) ?></p>
        <label class="campo-form"><?= e(t('alertas.precio_usd')) ?><input type="number" id="cv-usd" value="100" min="0" step="any"></label>
        <div id="cv-compras" style="margin-top:1rem" class="lista-simple"></div>
    </div>
</section>

<section class="panel">
    <h2><?= e(t('conv.equivalencias')) ?> <small id="cv-equiv-titulo"></small></h2>
    <div class="lista-conversion" id="cv-lista"></div>
</section>

<?php
$scripts = [url('assets/js/app.js'), url('assets/js/convertidor.js')];
require __DIR__ . '/../includes/footer.php';
