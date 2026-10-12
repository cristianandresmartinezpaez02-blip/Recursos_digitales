<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.favoritas');
$activa = 'favoritas';
require __DIR__ . '/../includes/header.php';
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">★ <?= e(t('nav.herramientas')) ?></div>
        <h1><?= e(t('nav.favoritas')) ?></h1>
        <p><?= e(t('nav.favoritas_d')) ?></p>
    </div>
</section>

<div class="explicacion"><span class="ico">ℹ️</span><p><?= e(t('fav.explicacion')) ?></p></div>

<?php require __DIR__ . '/../includes/panel_push.php'; ?>

<section id="favoritas" class="tarjetas-favoritas">
    <div class="tarjeta-favorita esqueleto" style="height:190px"></div>
    <div class="tarjeta-favorita esqueleto" style="height:190px"></div>
    <div class="tarjeta-favorita esqueleto" style="height:190px"></div>
</section>

<?php
$scripts = [url('assets/js/app.js'), url('assets/js/favoritas.js'), url('assets/js/push.js')];
require __DIR__ . '/../includes/footer.php';
