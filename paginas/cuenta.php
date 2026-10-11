<?php
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/sesion.php';

if (!haySesion()) {
    header('Location: ' . BASE_URL . '/paginas/acceso.php?volver=' . rawurlencode(BASE_URL . '/paginas/cuenta.php'));
    exit;
}

$titulo = t('cuenta.mi_cuenta');
$activa = 'cuenta';
$mostrarEstado = false;
require __DIR__ . '/../includes/header.php';

$u = usuarioActual();
?>

<section class="hero">
    <div class="perfil">
        <span class="avatar enorme" aria-hidden="true"><?= e($inicial) ?></span>
        <div>
            <div class="etiqueta-seccion">⭐ <?= e(t('cuenta.miembro')) ?></div>
            <h1><?= e($u['nombre']) ?></h1>
            <p><?= e($u['email']) ?> · <?= e(t('cuenta.desde', ['fecha' => date('d/m/Y', strtotime($u['creado']))])) ?>
                · <?= e(t('cuenta.registro_con_' . $u['metodo_registro'])) ?></p>
        </div>
    </div>
    <button class="boton secundario" type="button" id="cuenta-salir">🚪 <?= e(t('cuenta.salir')) ?></button>
</section>

<section class="kpis" id="cuenta-stats">
    <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">💹</span> <?= e(t('nav.trading')) ?></div><div class="kpi-valor" id="st-trading">—</div><div class="kpi-sub" id="st-trading-sub">&nbsp;</div></article>
    <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">⏩</span> <?= e(t('nav.reto')) ?></div><div class="kpi-valor" id="st-reto">—</div><div class="kpi-sub"><?= e(t('cuenta.mejor_resultado')) ?></div></article>
    <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">🎯</span> <?= e(t('nav.subebaja')) ?></div><div class="kpi-valor" id="st-subebaja">—</div><div class="kpi-sub" id="st-subebaja-sub"><?= e(t('sb.record')) ?></div></article>
    <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">★</span> <?= e(t('nav.favoritas')) ?> · 🔔</div><div class="kpi-valor" id="st-favs">—</div><div class="kpi-sub" id="st-alertas">&nbsp;</div></article>
</section>

<section class="rejilla col-2">
    <div class="panel" id="rostro">
        <div class="panel-cabecera">
            <h2>🙂 <?= e(t('cuenta.acceso_facial')) ?></h2>
            <span class="insignia <?= $u['tiene_rostro'] ? 'viable' : 'neutral' ?>" id="rostro-estado">
                <?= $u['tiene_rostro'] ? '✔ ' . e(t('cuenta.rostro_activo')) : '● ' . e(t('cuenta.rostro_inactivo')) ?>
            </span>
        </div>
        <p class="texto-2"><?= e(t('cuenta.facial_explica')) ?></p>
        <?php if (!facialActivo()): ?>
            <div class="explicacion"><span class="ico">⛔</span><p><?= e(t('cuenta.facial_desactivado')) ?></p></div>
        <?php endif; ?>
        <?php if ($u['tiene_rostro'] && $u['facial_fecha']): ?>
            <p class="nota"><?= e(t('cuenta.rostro_fecha', ['fecha' => date('d/m/Y H:i', strtotime($u['facial_fecha']))])) ?></p>
        <?php endif; ?>

        <div class="camara" id="camara-cuenta" hidden>
            <div class="camara-marco">
                <video playsinline muted></video>
                <canvas></canvas>
                <div class="camara-guia" aria-hidden="true"></div>
            </div>
            <p class="camara-estado" role="status"></p>
        </div>

        <form id="form-rostro" class="form-cuenta" autocomplete="off">
            <?php if ($u['tiene_clave']): /* las cuentas de Google o Apple no tienen contraseña */ ?>
            <label class="campo-form"><?= e(t('cuenta.confirma_password')) ?>
                <input type="password" id="rostro-password" autocomplete="current-password" required>
            </label>
            <?php endif; ?>
            <label class="aceptar"><input type="checkbox" id="rostro-consentimiento"> <?= e(t('cuenta.consentimiento')) ?></label>
            <div class="fila-botones">
                <button class="boton" type="submit" id="rostro-guardar"<?= facialActivo() ? '' : ' disabled' ?>>📸 <?= e(t($u['tiene_rostro'] ? 'cuenta.actualizar_rostro' : 'cuenta.registrar_rostro')) ?></button>
                <?php if ($u['tiene_rostro']): ?>
                    <button class="boton peligro" type="button" id="rostro-quitar">🗑 <?= e(t('cuenta.quitar_rostro')) ?></button>
                <?php endif; ?>
            </div>
            <p class="mensaje" id="rostro-mensaje" role="alert"></p>
        </form>
    </div>

    <div class="panel">
        <h2>🔓 <?= e(t('cuenta.desbloqueado')) ?></h2>
        <p class="texto-2"><?= e(t('cuenta.desbloqueado_d')) ?></p>
        <div class="lista-desbloqueos">
            <?php foreach ($menu as $grupo):
                foreach ($grupo['items'] as [$clave, $ruta, $icono, $tit]):
                    if (!in_array($clave, $protegidas, true)) continue; ?>
                    <a href="<?= url($ruta) ?>"><span aria-hidden="true"><?= $icono ?></span> <?= e(t($tit)) ?></a>
            <?php endforeach; endforeach; ?>
        </div>
        <div class="consejo-juego"><span>☁️</span><span><?= e(t('cuenta.sincronizado')) ?></span></div>
    </div>
</section>

<?php
$scripts = [url('assets/js/app.js'), url('assets/js/facial.js'), url('assets/js/cuenta.js')];
require __DIR__ . '/../includes/footer.php';
