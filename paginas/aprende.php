<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.aprende');
$activa = 'aprende';
require __DIR__ . '/../includes/header.php';

$factores = ['tamano', 'liquidez', 'impulso', 't7', 't30', 'ath', 'oferta', 'estabilidad'];
$glosario = ['cap', 'volumen', 'ath', 'stablecoin', 'oferta', 'fdv', 'dominancia', 'fng', 'volatilidad', 'stop_loss', 'take_profit', 'comision', 'dca', 'holdear'];
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">📚 <?= e(t('nav.aprende')) ?></div>
        <h1><?= e(t('aprende.titulo_a')) ?> <span class="titulo-gradiente"><?= e(t('aprende.titulo_b')) ?></span></h1>
        <p><?= e(t('aprende.intro')) ?></p>
    </div>
</section>

<nav class="pestanas-seccion" id="pestanas-aprende" role="tablist">
    <a href="#guia" role="tab">🧭 <?= e(t('aprende.tab_guia')) ?></a>
    <a href="#senal" role="tab">🎯 <?= e(t('aprende.tab_senal')) ?></a>
    <a href="#glosario" role="tab">📖 <?= e(t('aprende.tab_glosario')) ?></a>
    <a href="#seguridad" role="tab">🛡️ <?= e(t('aprende.tab_seguridad')) ?></a>
</nav>

<section id="guia" role="tabpanel" class="panel">
    <h2><?= e(t('aprende.guia_titulo')) ?></h2>
    <div class="pasos">
        <?php for ($i = 1; $i <= 6; $i++): ?>
            <div class="paso"><div><strong><?= e(t("aprende.guia{$i}")) ?></strong><p><?= e(t("aprende.guia{$i}_d")) ?></p></div></div>
        <?php endfor; ?>
    </div>
</section>

<section id="senal" role="tabpanel" class="panel" hidden>
    <h2><?= e(t('aprende.senal_titulo')) ?></h2>
    <p class="texto-2"><?= e(t('aprende.senal_intro')) ?></p>
    <div class="rejilla col-3" style="margin:1rem 0">
        <div class="dato"><span class="insignia viable">✔ <?= e(t('an.v.viable')) ?></span><p class="texto-2" style="margin:.5rem 0 0;font-size:.88rem"><?= e(t('aprende.rango_viable')) ?></p></div>
        <div class="dato"><span class="insignia neutral">● <?= e(t('an.v.neutral')) ?></span><p class="texto-2" style="margin:.5rem 0 0;font-size:.88rem"><?= e(t('aprende.rango_neutral')) ?></p></div>
        <div class="dato"><span class="insignia no_viable">✖ <?= e(t('an.v.no_viable')) ?></span><p class="texto-2" style="margin:.5rem 0 0;font-size:.88rem"><?= e(t('aprende.rango_no')) ?></p></div>
    </div>
    <div class="glosario">
        <?php foreach ($factores as $f): ?>
            <details><summary><?= e(t("an.f.$f")) ?></summary><p><?= e(t("aprende.factor_$f")) ?></p></details>
        <?php endforeach; ?>
    </div>
    <p class="nota"><?= e(t('pie.aviso')) ?></p>
</section>

<section id="glosario" role="tabpanel" class="panel" hidden>
    <h2><?= e(t('aprende.tab_glosario')) ?></h2>
    <div class="glosario">
        <?php foreach ($glosario as $g): ?>
            <details><summary><?= e(t("glosario.$g")) ?></summary><p><?= e(t("glosario.{$g}_d")) ?></p></details>
        <?php endforeach; ?>
    </div>
</section>

<section id="seguridad" role="tabpanel" class="panel" hidden>
    <h2><?= e(t('aprende.seguridad_titulo')) ?></h2>
    <div class="pasos">
        <?php for ($i = 1; $i <= 6; $i++): ?>
            <div class="paso"><div><strong><?= e(t("aprende.seg{$i}")) ?></strong><p><?= e(t("aprende.seg{$i}_d")) ?></p></div></div>
        <?php endfor; ?>
    </div>
</section>

<script>document.addEventListener('DOMContentLoaded', () => window.Cripto && window.Cripto.iniciarPestanas('pestanas-aprende'));</script>
<?php
$scripts = [url('assets/js/app.js')];
require __DIR__ . '/../includes/footer.php';
