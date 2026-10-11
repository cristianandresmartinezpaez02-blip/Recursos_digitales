<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.juegos');
$activa = 'juegos';
require __DIR__ . '/../includes/header.php';

$juegos = [
    ['paginas/reto.php', '⏩', 'nav.reto', 'juegos.reto_d', ['juegos.tag_rapido', 'juegos.tag_2min', 'juegos.tag_recomendado'], 'reto'],
    ['paginas/sube-baja.php', '🎯', 'nav.subebaja', 'juegos.subebaja_d', ['juegos.tag_rapido', 'juegos.tag_prediccion'], 'subebaja'],
    ['paginas/trading.php', '💹', 'nav.trading', 'juegos.trading_d', ['juegos.tag_tiempo_real', 'juegos.tag_largo'], 'trading'],
];
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">🎮 <?= e(t('nav.juegos')) ?></div>
        <h1><?= e(t('juegos.titulo_a')) ?> <span class="titulo-gradiente"><?= e(t('juegos.titulo_b')) ?></span></h1>
        <p><?= e(t('juegos.intro')) ?></p>
    </div>
</section>

<?php if (!$usuario): ?>
    <div class="aviso-cuenta">
        <span class="ico" aria-hidden="true">🔒</span>
        <p><strong><?= e(t('juegos.requiere_cuenta')) ?></strong><br><span class="texto-2"><?= e(t('juegos.requiere_cuenta_d')) ?></span></p>
        <a class="boton" href="<?= e(url('paginas/acceso.php') . '?modo=registro&volver=' . rawurlencode($urlActual)) ?>">✨ <?= e(t('cuenta.crear_gratis')) ?></a>
    </div>
<?php endif; ?>

<section class="explorar">
    <?php foreach ($juegos as [$ruta, $icono, $tit, $desc, $etiquetas, $clave]): ?>
        <a class="tarjeta-seccion tarjeta-juego" href="<?= url($ruta) ?>">
            <span class="ico-grande" aria-hidden="true"><?= $icono ?></span>
            <h3><?= e(t($tit)) ?><?php if (!$usuario): ?> <span class="candado">🔒</span><?php endif; ?></h3>
            <div class="etiquetas-juego">
                <?php foreach ($etiquetas as $et): ?><span class="chip"><?= e(t($et)) ?></span><?php endforeach; ?>
            </div>
            <p><?= e(t($desc)) ?></p>
            <span class="dato-vivo" data-record="<?= $clave ?>"></span>
            <span class="ir"><?= e(t('juegos.jugar')) ?> →</span>
        </a>
    <?php endforeach; ?>
</section>

<div class="explicacion"><span class="ico">🤖</span><p><strong><?= e(t('juegos.ia_titulo')) ?>.</strong> <?= e(t('juegos.ia_d')) ?></p></div>

<div class="explicacion"><span class="ico">💡</span><p><?= e(t('juegos.consejo')) ?></p></div>

<a class="tarjeta-guia" href="<?= url('paginas/ranking.php') ?>" style="margin-bottom:1rem">
    <span class="ico-grande" aria-hidden="true">🏅</span>
    <span>
        <strong><?= e(t('nav.ranking')) ?></strong>
        <small><?= e(t('nav.ranking_d')) ?></small>
    </span>
    <span class="ir"><?= e(t('comun.abrir')) ?> →</span>
</a>

<a class="tarjeta-guia" href="<?= url('paginas/estrategias.php') ?>">
    <span class="ico-grande" aria-hidden="true">🧠</span>
    <span>
        <strong><?= e(t('nav.estrategias')) ?></strong>
        <small><?= e(t('juegos.guia_d')) ?></small>
    </span>
    <span class="ir"><?= e(t('est.ver_guia')) ?> →</span>
</a>

<script>
    // Muestra el mejor resultado guardado de cada juego.
    (function () {
        const leer = k => { try { return JSON.parse(localStorage.getItem(k)); } catch (e) { return null; } };
        const textos = <?= json_encode([
            'reto' => t('juegos.record_reto'), 'subebaja' => t('juegos.record_subebaja'), 'trading' => t('juegos.record_trading'),
        ], JSON_UNESCAPED_UNICODE) ?>;
        const reto = (leer('reto_records') || [])[0];
        const sube = leer('subebaja_record');
        const trading = leer('minitrading_v1');
        const pon = (k, v) => { const el = document.querySelector(`[data-record="${k}"]`); if (el && v !== null) el.textContent = textos[k].replace('{v}', v); };
        pon('reto', reto ? (reto.ganancia >= 0 ? '+' : '') + reto.ganancia.toFixed(2) + ' %' : null);
        pon('subebaja', sube ? sube : null);
        pon('trading', trading && trading.operaciones ? trading.operaciones.length : null);
    })();
</script>

<?php
$scripts = [url('assets/js/app.js')];
require __DIR__ . '/../includes/footer.php';
