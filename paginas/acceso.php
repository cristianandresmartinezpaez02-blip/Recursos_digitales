<?php
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/sesion.php';

$volver = rutaVolver($_GET['volver'] ?? null);
if (haySesion()) {
    header('Location: ' . $volver);
    exit;
}
$modo = ($_GET['modo'] ?? '') === 'registro' ? 'registro' : 'entrar';
$registroAbierto = registroAbierto();
$facialActivo = facialActivo();
// Gmail se ve siempre (si falta el Client ID, avisa al pulsarlo). Hotmail y Apple solo aparecen
// cuando tienen su Client ID: sin directorio de Azure ni cuenta de pago de Apple no se pueden activar.
$oauthGoogle = clientIdOauth('google') !== ''; // ID de config.local.php o del panel de administración
$oauthApple = clientIdOauth('apple') !== '';
$oauthMicrosoft = clientIdOauth('microsoft') !== '';
if (empty($_SESSION['oauth_nonce'])) {
    $_SESSION['oauth_nonce'] = bin2hex(random_bytes(16)); // se comprueba dentro del token (api/auth.php)
}

$titulo = t($modo === 'registro' ? 'cuenta.crear' : 'cuenta.entrar');
$activa = 'acceso';
$mostrarEstado = false;
require __DIR__ . '/../includes/header.php';

$beneficios = [
    ['🎮', 'cuenta.ben_juegos'],
    ['🧰', 'cuenta.ben_herramientas'],
    ['☁️', 'cuenta.ben_progreso'],
    ['🙂', 'cuenta.ben_facial'],
];
?>

<section class="acceso">
    <aside class="acceso-lado">
        <div class="etiqueta-seccion">✨ <?= e(t('cuenta.gratis')) ?></div>
        <h1><?= e(t('cuenta.lado_titulo_a')) ?> <span class="titulo-gradiente"><?= e(t('cuenta.lado_titulo_b')) ?></span></h1>
        <p class="texto-2"><?= e(t('cuenta.lado_intro')) ?></p>
        <ul class="beneficios">
            <?php foreach ($beneficios as [$ico, $clave]): ?>
                <li><span aria-hidden="true"><?= $ico ?></span> <?= e(t($clave)) ?></li>
            <?php endforeach; ?>
        </ul>
        <div class="comparativa">
            <div><strong>👀 <?= e(t('cuenta.visitante')) ?></strong><small><?= e(t('cuenta.visitante_d')) ?></small></div>
            <div class="destacada"><strong>⭐ <?= e(t('cuenta.miembro')) ?></strong><small><?= e(t('cuenta.miembro_d')) ?></small></div>
        </div>
    </aside>

    <div class="panel acceso-tarjeta">
        <div class="segmentado acceso-pestanas" role="tablist">
            <button type="button" role="tab" data-modo="entrar" class="<?= $modo === 'entrar' ? 'activa' : '' ?>">👤 <?= e(t('cuenta.entrar')) ?></button>
            <button type="button" role="tab" data-modo="registro" class="<?= $modo === 'registro' ? 'activa' : '' ?>">✨ <?= e(t('cuenta.crear')) ?></button>
        </div>

        <?php /* Acceso en un clic. Hotmail y Apple solo se muestran si tienen su ID configurado. */ ?>
        <div class="acceso-social" id="acceso-social">
            <p class="acceso-social-titulo"><?= e(t('cuenta.rapido')) ?></p>
            <button type="button" class="boton-social" id="boton-google">
                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.6 5.4 2.7 13.3l7.9 6.1C12.5 13.6 17.8 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.7 6c4.5-4.2 6.9-10.3 6.9-17.7z"/><path fill="#FBBC05" d="M10.6 28.6c-.5-1.4-.8-3-.8-4.6s.3-3.2.8-4.6l-7.9-6.1C1 16.6 0 20.2 0 24s1 7.4 2.7 10.7l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.7-6c-2.2 1.5-5 2.3-8.2 2.3-6.2 0-11.5-4.1-13.4-9.9l-7.9 6.1C6.6 42.6 14.6 48 24 48z"/></svg>
                <span><?= e(t('cuenta.con_gmail')) ?></span>
            </button>
            <?php if ($oauthMicrosoft): ?>
            <button type="button" class="boton-social" id="boton-microsoft">
                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 21 21"><path fill="#F25022" d="M0 0h10v10H0z"/><path fill="#7FBA00" d="M11 0h10v10H11z"/><path fill="#00A4EF" d="M0 11h10v10H0z"/><path fill="#FFB900" d="M11 11h10v10H11z"/></svg>
                <span><?= e(t('cuenta.con_hotmail')) ?></span>
            </button>
            <?php endif; ?>
            <?php if ($oauthApple): ?>
            <button type="button" class="boton-social" id="boton-apple">
                <svg class="logo-apple" aria-hidden="true" width="17" height="20" viewBox="0 0 814 1000" fill="currentColor"><path d="M788 341c-6 4-108 62-108 190 0 148 130 200 134 202-1 3-21 72-69 142-43 62-88 123-156 123s-86-40-164-40c-76 0-104 41-166 41s-106-57-156-127C45 790 0 663 0 543 0 349 126 247 250 247c66 0 121 43 162 43 39 0 101-46 176-46 29 0 131 3 200 97zM554 159c31-37 53-88 53-139 0-7-1-14-2-20-50 2-110 34-146 76-28 32-55 83-55 135 0 8 1 16 2 18 3 1 9 2 14 2 45 0 102-30 134-72z"/></svg>
                <span><?= e(t('cuenta.con_apple')) ?></span>
            </button>
            <?php endif; ?>
            <p class="mensaje" id="social-mensaje" role="alert"></p>
        </div>
        <div class="separador-o"><span><?= e(t('cuenta.o_correo')) ?></span></div>

        <!-- Iniciar sesión -->
        <div id="vista-entrar" <?= $modo === 'entrar' ? '' : 'hidden' ?>>
            <h2><?= e(t('cuenta.bienvenido')) ?></h2>
            <div class="metodos" role="radiogroup" aria-label="<?= e(t('cuenta.metodo')) ?>">
                <button type="button" class="metodo activo" data-metodo="clave">📧 <span><?= e(t('cuenta.metodo_clave')) ?></span></button>
                <?php if ($facialActivo): ?><button type="button" class="metodo" data-metodo="facial">🙂 <span><?= e(t('cuenta.metodo_facial')) ?></span></button><?php endif; ?>
            </div>

            <form id="form-entrar" class="form-cuenta" autocomplete="on" novalidate>
                <label class="campo-form"><?= e(t('cuenta.email')) ?>
                    <input type="email" name="email" id="entrar-email" autocomplete="email" required placeholder="tu@correo.com">
                </label>
                <label class="campo-form" id="campo-clave"><?= e(t('cuenta.password')) ?>
                    <span class="con-ojo">
                        <input type="password" name="password" id="entrar-password" autocomplete="current-password" required>
                        <button type="button" class="ojo" data-ojo="entrar-password" aria-label="<?= e(t('cuenta.mostrar')) ?>">👁</button>
                    </span>
                </label>

                <div class="camara" id="camara-entrar" hidden>
                    <div class="camara-marco">
                        <video playsinline muted></video>
                        <canvas></canvas>
                        <div class="camara-guia" aria-hidden="true"></div>
                    </div>
                    <p class="camara-estado" role="status"></p>
                </div>

                <button class="boton bloque" type="submit" id="entrar-enviar"><?= e(t('cuenta.entrar')) ?></button>
                <p class="mensaje" id="entrar-mensaje" role="alert"></p>
            </form>
            <p class="nota centrada"><?= e(t('cuenta.no_tienes')) ?> <a href="#" data-ir="registro"><?= e(t('cuenta.crear_gratis')) ?></a></p>
        </div>

        <!-- Crear cuenta -->
        <div id="vista-registro" <?= $modo === 'registro' ? '' : 'hidden' ?>>
            <h2><?= e(t('cuenta.crear_titulo')) ?></h2>
            <?php if (!$registroAbierto): ?>
                <div class="explicacion"><span class="ico">⛔</span><p><?= e(t('cuenta.registro_cerrado')) ?></p></div>
            <?php else: ?>
            <form id="form-registro" class="form-cuenta" autocomplete="on" novalidate>
                <label class="campo-form"><?= e(t('cuenta.nombre')) ?>
                    <input type="text" name="nombre" autocomplete="name" required minlength="2" maxlength="60">
                </label>
                <label class="campo-form"><?= e(t('cuenta.email')) ?>
                    <input type="email" name="email" autocomplete="email" required placeholder="tu@correo.com">
                </label>
                <label class="campo-form"><?= e(t('cuenta.password')) ?>
                    <span class="con-ojo">
                        <input type="password" name="password" id="registro-password" autocomplete="new-password" required minlength="8">
                        <button type="button" class="ojo" data-ojo="registro-password" aria-label="<?= e(t('cuenta.mostrar')) ?>">👁</button>
                    </span>
                    <span class="fuerza" aria-hidden="true"><span id="fuerza-barra"></span></span>
                    <small class="texto-3" id="fuerza-texto"><?= e(t('cuenta.password_regla')) ?></small>
                </label>
                <label class="campo-form"><?= e(t('cuenta.password2')) ?>
                    <input type="password" name="password2" autocomplete="new-password" required>
                </label>

                <?php if ($facialActivo): ?>
                <fieldset class="caja-facial">
                    <label class="interruptor">
                        <input type="checkbox" id="registro-con-rostro">
                        <span><strong>🙂 <?= e(t('cuenta.registrar_rostro')) ?></strong><small><?= e(t('cuenta.registrar_rostro_d')) ?></small></span>
                    </label>
                    <div id="registro-facial" hidden>
                        <div class="camara" id="camara-registro">
                            <div class="camara-marco">
                                <video playsinline muted></video>
                                <canvas></canvas>
                                <div class="camara-guia" aria-hidden="true"></div>
                            </div>
                            <p class="camara-estado" role="status"><?= e(t('cuenta.rostro_instrucciones')) ?></p>
                        </div>
                        <button class="boton secundario bloque" type="button" id="registro-capturar">📸 <?= e(t('cuenta.capturar_rostro')) ?></button>
                        <label class="aceptar"><input type="checkbox" id="registro-consentimiento"> <?= e(t('cuenta.consentimiento')) ?></label>
                    </div>
                </fieldset>
                <?php endif; ?>

                <button class="boton bloque" type="submit" id="registro-enviar">✨ <?= e(t('cuenta.crear')) ?></button>
                <p class="mensaje" id="registro-mensaje" role="alert"></p>
            </form>
            <?php endif; ?>
            <p class="nota centrada"><?= e(t('cuenta.ya_tienes')) ?> <a href="#" data-ir="entrar"><?= e(t('cuenta.entrar')) ?></a></p>
        </div>

        <p class="privacidad">🔐 <?= e(t('cuenta.privacidad')) ?></p>
    </div>
</section>

<script>
    window.VOLVER = <?= json_encode($volver, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
    window.OAUTH = <?= json_encode([
        'google' => $oauthGoogle ? clientIdOauth('google') : null,
        'apple' => $oauthApple ? clientIdOauth('apple') : null,
        'microsoft' => $oauthMicrosoft ? clientIdOauth('microsoft') : null,
        'retorno' => (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off' ? 'http' : 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . BASE_URL . '/paginas/oauth-retorno.html',
        'nonce' => $_SESSION['oauth_nonce'] ?? '',
        'redirect' => (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off' ? 'http' : 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . BASE_URL . '/paginas/acceso.php',
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
</script>
<?php
$scripts = [url('assets/js/app.js'), url('assets/js/facial.js'), url('assets/js/acceso.js')];
if ($oauthGoogle) $scripts[] = 'https://accounts.google.com/gsi/client';
if ($oauthApple) $scripts[] = 'https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid/1/' . ['es' => 'es_ES', 'en' => 'en_US', 'fr' => 'fr_FR'][idioma()] . '/appleid.auth.js';
$scripts[] = url('assets/js/acceso-social.js');
require __DIR__ . '/../includes/footer.php';
