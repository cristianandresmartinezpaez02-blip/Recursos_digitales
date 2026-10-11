</main>
<footer class="pie">
    <div class="pie-interior">
        <div class="pie-marca">
            <a class="logo notranslate" translate="no" href="<?= url('index.php') ?>"><span class="logo-marca">◆</span> <?= e(APP_NOMBRE) ?></a>
            <p><?= t('pie.datos', [
                    'coingecko' => '<a href="https://www.coingecko.com" target="_blank" rel="noopener">CoinGecko</a>',
                    'alternative' => '<a href="https://alternative.me/crypto/fear-and-greed-index/" target="_blank" rel="noopener">alternative.me</a>',
                ]) ?></p>
            <p><?= e(t('pie.actualizacion')) ?></p>
        </div>
        <?php foreach ($menu as $grupo): ?>
            <nav class="pie-columna" aria-label="<?= e(t($grupo['grupo'])) ?>">
                <h4><?= $grupo['icono'] ?> <?= e(t($grupo['grupo'])) ?></h4>
                <?php foreach ($grupo['items'] as [$clave, $ruta, $icono, $tit]): ?>
                    <a href="<?= url($ruta) ?>"><?= e(t($tit)) ?><?= !$usuario && in_array($clave, $protegidas, true) ? ' 🔒' : '' ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endforeach; ?>
        <nav class="pie-columna" aria-label="<?= e(t('cuenta.mi_cuenta')) ?>">
            <h4>👤 <?= e(t('cuenta.mi_cuenta')) ?></h4>
            <?php if ($usuario): ?>
                <a href="<?= url('paginas/cuenta.php') ?>"><?= e(t('cuenta.mi_cuenta')) ?></a>
                <?php if ($usuario['rol'] === 'admin'): ?><a href="<?= url('admin/index.php') ?>"><?= e(t('cuenta.panel_admin')) ?></a><?php endif; ?>
            <?php else: ?>
                <a href="<?= e($urlAcceso) ?>"><?= e(t('cuenta.entrar')) ?></a>
                <a href="<?= url('paginas/acceso.php') ?>?modo=registro"><?= e(t('cuenta.crear')) ?></a>
            <?php endif; ?>
            <a href="<?= url('paginas/aprende.php') ?>"><?= e(t('nav.aprende')) ?></a>
        </nav>
    </div>
    <div class="pie-legal">
        <p class="aviso"><?= e(t('pie.aviso')) ?></p>
        <p>♿ <?= e(t('pie.accesible')) ?></p>
    </div>
</footer>
<button type="button" class="volver-arriba" id="volver-arriba" aria-label="<?= e(t('nav.arriba')) ?>" title="<?= e(t('nav.arriba')) ?>" hidden>↑</button>
<div class="toasts" id="toasts" aria-live="polite"></div>
<div class="tooltip" id="tooltip" hidden></div>
<?php foreach ($scripts ?? [] as $src): ?>
    <script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
<?php if (!empty($idiomaAuto)): ?>
    <!-- Traducción automática (Google Translate) al idioma elegido en ⚙ Preferencias -->
    <div id="google_translate_element" hidden></div>
    <script>
        function iniciarTraductor() {
            new google.translate.TranslateElement({ pageLanguage: <?= json_encode(idioma()) ?>, autoDisplay: false }, 'google_translate_element');
        }
    </script>
    <script src="https://translate.google.com/translate_a/element.js?cb=iniciarTraductor"></script>
<?php endif; ?>
</body>
</html>
