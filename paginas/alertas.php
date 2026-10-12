<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.alertas');
$activa = 'alertas';
require __DIR__ . '/../includes/header.php';
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">🔔 <?= e(t('nav.herramientas')) ?></div>
        <h1><?= e(t('nav.alertas')) ?></h1>
        <p><?= e(t('nav.alertas_d')) ?></p>
    </div>
</section>

<div class="explicacion"><span class="ico">ℹ️</span><p><?= e(t('alertas.explicacion')) ?></p></div>

<section class="panel">
    <h2><?= e(t('alertas.nueva')) ?></h2>
    <form id="form-alerta" class="form-linea" autocomplete="off">
        <label class="campo-form"><?= e(t('col.moneda')) ?><select id="alerta-moneda"></select></label>
        <label class="campo-form"><?= e(t('alertas.condicion')) ?>
            <select id="alerta-condicion">
                <option value="mayor"><?= e(t('alertas.mayor')) ?></option>
                <option value="menor"><?= e(t('alertas.menor')) ?></option>
            </select>
        </label>
        <label class="campo-form"><?= e(t('alertas.precio_usd')) ?><input type="number" id="alerta-precio" min="0" step="any" required></label>
        <button class="boton" type="submit">🔔 <?= e(t('alertas.crear')) ?></button>
    </form>
    <p class="nota" id="alerta-ayuda"></p>
    <p class="nota"><button class="boton secundario mini" id="permiso-notif" type="button" hidden>🔔 <?= e(t('alertas.permitir')) ?></button></p>
</section>

<?php require __DIR__ . '/../includes/panel_push.php'; ?>

<section class="panel">
    <div class="panel-cabecera">
        <h2><?= e(t('alertas.mis_alertas')) ?></h2>
        <button class="boton secundario mini" id="borrar-disparadas" type="button"><?= e(t('alertas.borrar_disparadas')) ?></button>
    </div>
    <div id="lista-alertas"></div>
</section>

<?php
$scripts = [url('assets/js/app.js'), url('assets/js/alertas.js'), url('assets/js/push.js')];
require __DIR__ . '/../includes/footer.php';
