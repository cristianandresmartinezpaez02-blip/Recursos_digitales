<?php
/**
 * Cabecera común. Antes de incluirla se puede definir:
 *   $titulo        (string) título de la página (ya traducido)
 *   $activa        (string) clave de la página activa en el menú
 *   $mostrarEstado (bool)   mostrar el indicador de actualización y la cinta (por defecto true)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/menu.php';
require_once __DIR__ . '/idiomas_mundo.php';
require_once __DIR__ . '/ia.php';

$titulo = $titulo ?? APP_NOMBRE;
$activa = $activa ?? '';
$mostrarEstado = $mostrarEstado ?? true;
$usuario = usuarioActual();
// Las secciones protegidas (editables en el panel de administración) muestran una pantalla de bloqueo a los visitantes.
$protegidas = seccionesProtegidas();
$bloqueada = !$usuario && in_array($activa, $protegidas, true);

// Idioma: es/en/fr con textos propios; cualquier otro, con traducción automática.
$idiomaAuto = idiomaAutomatico();
$langHtml = $idiomaAuto ? (LOCALE_DE_GOOGLE[$idiomaAuto] ?? $idiomaAuto) : idioma();
$rtl = $idiomaAuto && IDIOMAS_MUNDO[$idiomaAuto][2];
$anuncio = ajuste('anuncio', '');
$anuncioTipo = ajuste('anuncio_tipo', 'info');

// Moneda en la que se muestran los precios (⚙ Preferencias). Los datos siguen en USD;
// app.js multiplica por la tasa al formatear, así que toda la página cambia de moneda.
const MONEDAS_VISTA = ['USD', 'EUR', 'COP', 'MXN', 'ARS', 'BRL', 'CLP', 'PEN', 'UYU', 'BOB', 'PYG', 'VES', 'DOP', 'CRC', 'GTQ', 'HNL',
    'NIO', 'PAB', 'CAD', 'GBP', 'CHF', 'JPY', 'CNY', 'KRW', 'INR', 'AUD', 'NZD', 'RUB', 'TRY', 'ZAR', 'NGN', 'EGP', 'AED', 'SAR', 'ILS',
    'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'SGD', 'HKD', 'TWD', 'THB', 'IDR', 'PHP', 'VND', 'MYR', 'PKR'];
$monedaVista = in_array($_COOKIE['moneda'] ?? '', MONEDAS_VISTA, true) ? $_COOKIE['moneda'] : 'USD';
$tasaVista = 1.0;
if ($monedaVista !== 'USD') {
    require_once __DIR__ . '/bolsa.php';
    $tasaVista = tasaDivisa($monedaVista);
    if (!$tasaVista) {
        [$monedaVista, $tasaVista] = ['USD', 1.0]; // sin tasa disponible: se queda en dólares
    }
}

function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function url(string $ruta): string
{
    // Los archivos de assets/ llevan ?v=<fecha de modificación>: así el navegador
    // descarga la versión nueva en cuanto cambian (sin quedarse con una copia vieja).
    if (strncmp($ruta, 'assets/', 7) === 0 && is_file($archivo = __DIR__ . '/../' . $ruta)) {
        return BASE_URL . '/' . $ruta . '?v=' . filemtime($archivo);
    }
    return BASE_URL . '/' . $ruta;
}

/** Número que JavaScript formatea según el idioma (ver formatearDatos en app.js). */
function fmt($valor, string $formato = 'grande'): string
{
    return '<span data-formato="' . $formato . '" data-valor="' . ($valor === null ? '' : e((string) $valor)) . '">—</span>';
}

$menu = menuPrincipal();

// Solo el diccionario que usa JavaScript (claves que empiezan por "js.", "an." y "fng.").
$dicJs = array_filter(diccionario(), fn($k) => strncmp($k, 'js.', 3) === 0, ARRAY_FILTER_USE_KEY);

$urlActual = $_SERVER['REQUEST_URI'] ?? url('index.php');
$urlAcceso = url('paginas/acceso.php') . '?volver=' . rawurlencode($urlActual);
$progreso = [];
if ($usuario) {
    try {
        $progreso = progresoUsuario((int) $usuario['id']);
    } catch (Throwable $e) {
        error_log('progreso: ' . $e->getMessage());
    }
}
$datosUsuario = $usuario ? [
    'id' => (int) $usuario['id'],
    'nombre' => $usuario['nombre'],
    'email' => $usuario['email'],
    'rostro' => $usuario['tiene_rostro'],
    'admin' => $usuario['rol'] === 'admin',
] : null;
$inicial = $usuario ? mb_strtoupper(mb_substr($usuario['nombre'], 0, 1)) : '';
?>
<!DOCTYPE html>
<html lang="<?= e($langHtml) ?>"<?= $rtl ? ' dir="rtl"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($titulo) ?> · <?= e(APP_NOMBRE) ?></title>
    <meta name="description" content="<?= e(t('meta.descripcion')) ?>">
    <meta name="theme-color" content="#7c3aed">
    <link rel="icon" href="<?= url('assets/img/icono.svg') ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?= url('assets/img/icono.svg') ?>">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.webmanifest">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Las fuentes se cargan sin bloquear el primer pintado de la página -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" media="print" onload="this.media=&#39;all&#39;">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap"></noscript>
    <link rel="stylesheet" href="<?= url('assets/css/estilos.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/secciones.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/vivo.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/cuentas.css') ?>">
    <script>
        // Aplica tema, visión de color, contraste y tamaño de letra guardados antes de pintar (evita parpadeos).
        try {
            const raiz = document.documentElement;
            ['theme:tema', 'vision:vision', 'contraste:contraste', 'texto:texto'].forEach(par => {
                const [dato, clave] = par.split(':');
                const valor = localStorage.getItem(clave);
                if (valor) raiz.dataset[dato] = valor;
            });
        } catch (e) {}
        window.APP = {
            base: <?= json_encode(BASE_URL) ?>,
            idioma: <?= json_encode(idioma()) ?>,
            idiomaAuto: <?= json_encode($idiomaAuto) ?>,
            locale: <?= json_encode($idiomaAuto ? $langHtml : LOCALES[idioma()]) ?>,
            refrescoMs: <?= (int) REFRESCO_MS ?>,
            saldoInicial: <?= (float) JUEGO_SALDO_INICIAL ?>,
            comision: <?= (float) JUEGO_COMISION ?>,
            montoMinimo: <?= (float) JUEGO_MONTO_MINIMO ?>,
            iaClaude: <?= json_encode(iaClaudeActiva()) ?>,
            moneda: { codigo: <?= json_encode($monedaVista) ?>, tasa: <?= json_encode($tasaVista) ?> },
            usuario: <?= json_encode($datosUsuario, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
            csrf: <?= json_encode(csrfToken()) ?>,
            clavesProgreso: <?= json_encode(CLAVES_PROGRESO) ?>,
            urlAcceso: <?= json_encode($urlAcceso, JSON_UNESCAPED_SLASHES) ?>
        };
        window.I18N = <?= json_encode($dicJs, JSON_UNESCAPED_UNICODE) ?>;
        // Con sesión: el progreso guardado en la cuenta reemplaza al del navegador antes de que
        // arranquen los juegos. Lo que solo existe en este navegador se sube a la cuenta (app.js).
        (function (guardado) {
            APP.progresoPendiente = [];
            if (!APP.usuario) return;
            try {
                const dueno = localStorage.getItem('progreso_dueno');
                APP.clavesProgreso.forEach(k => {
                    if (k in guardado) localStorage.setItem(k, guardado[k]);
                    else if (dueno && dueno !== String(APP.usuario.id)) localStorage.removeItem(k); // era de otra cuenta
                    else if (localStorage.getItem(k) !== null) APP.progresoPendiente.push(k);
                });
                localStorage.setItem('progreso_dueno', String(APP.usuario.id));
            } catch (e) {}
        })(<?= json_encode((object) $progreso, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>);
    </script>
</head>
<body>
<a class="saltar" href="#contenido"><?= e(t('nav.saltar')) ?></a>
<header class="barra">
    <div class="barra-interior">
        <a class="logo notranslate" translate="no" href="<?= url('index.php') ?>"><span class="logo-marca">◆</span> <?= e(APP_NOMBRE) ?></a>
        <button class="boton-icono boton-menu" id="boton-menu" type="button" aria-label="<?= e(t('nav.abrir_menu')) ?>" aria-expanded="false" aria-controls="nav">☰</button>
        <nav class="nav" id="nav" aria-label="<?= e(t('nav.principal')) ?>">
            <a href="<?= url('index.php') ?>" class="<?= $activa === 'inicio' ? 'activo' : '' ?>"><span aria-hidden="true">🏠</span> <?= e(t('nav.inicio')) ?></a>
            <?php foreach ($menu as $grupo):
                $claves = array_column($grupo['items'], 0); ?>
                <details class="menu <?= in_array($activa, $claves, true) ? 'activo' : '' ?>">
                    <summary><span aria-hidden="true"><?= $grupo['icono'] ?></span> <?= e(t($grupo['grupo'])) ?></summary>
                    <div class="submenu">
                        <?php foreach ($grupo['items'] as [$clave, $ruta, $icono, $tit, $desc]): ?>
                            <?php $candado = !$usuario && in_array($clave, $protegidas, true); ?>
                            <a href="<?= url($ruta) ?>" class="<?= $activa === $clave ? 'activo' : '' ?><?= $candado ? ' con-candado' : '' ?>">
                                <span class="ico" aria-hidden="true"><?= $icono ?></span>
                                <span><strong><?= e(t($tit)) ?><?php if ($candado): ?> <span class="candado" title="<?= e(t('cuenta.solo_miembros')) ?>">🔒</span><?php endif; ?></strong><small><?= e(t($desc)) ?></small></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
            <a href="<?= url('paginas/aprende.php') ?>" class="<?= $activa === 'aprende' ? 'activo' : '' ?>"><span aria-hidden="true">📚</span> <?= e(t('nav.aprende')) ?></a>
        </nav>
        <div class="barra-derecha">
            <div class="buscador">
                <input type="search" id="buscador-global" placeholder="<?= e(t('nav.buscar')) ?>" autocomplete="off" aria-label="<?= e(t('nav.buscar')) ?>">
                <div class="sugerencias" id="sugerencias" hidden></div>
            </div>
            <?php if ($mostrarEstado): ?>
                <div class="estado" id="estado-actualizacion" title="<?= e(t('nav.estado_titulo')) ?>">
                    <span class="punto"></span> <span id="texto-estado"><?= e(t('comun.cargando')) ?></span>
                </div>
            <?php endif; ?>
            <div class="preferencias">
                <button class="boton-icono boton-prefs" id="boton-prefs" type="button" aria-label="<?= e(t('pref.titulo')) ?>" aria-expanded="false" aria-controls="panel-prefs" title="<?= e(t('pref.titulo')) ?>">
                    <span aria-hidden="true">🌐</span><span class="codigo-idioma notranslate" translate="no"><?= e(strtoupper(explode('-', $idiomaAuto ?: idioma())[0])) ?></span>
                </button>
                <div class="panel-prefs" id="panel-prefs" hidden>
                    <div class="panel-prefs-cabeza">
                        <h3><?= e(t('pref.titulo')) ?></h3>
                        <button type="button" class="boton-icono mini-cerrar" id="cerrar-prefs" aria-label="<?= e(t('pref.cerrar')) ?>">✕</button>
                    </div>
                    <div class="grupo-pref">
                        <span><?= e(t('pref.idioma')) ?></span>
                        <div class="segmentado notranslate" id="pref-idioma" translate="no">
                            <?php foreach (IDIOMAS as $codigo => $nombre): ?>
                                <button type="button" data-valor="<?= $codigo ?>" class="<?= !$idiomaAuto && idioma() === $codigo ? 'activa' : '' ?>" lang="<?= $codigo ?>"><?= e($nombre) ?></button>
                            <?php endforeach; ?>
                        </div>
                        <details class="mas-idiomas" <?= $idiomaAuto ? 'open' : '' ?>>
                            <summary>🌍 <?= e(t('pref.mas_idiomas', ['n' => count(IDIOMAS_MUNDO)])) ?></summary>
                            <?php if ($idiomaAuto): ?>
                                <p class="idioma-actual"><span class="notranslate" translate="no"><?= e(IDIOMAS_MUNDO[$idiomaAuto][0]) ?></span>
                                    <button type="button" class="boton mini secundario" id="quitar-traduccion"><?= e(t('pref.original')) ?></button></p>
                            <?php endif; ?>
                            <input type="search" id="buscar-idioma" placeholder="<?= e(t('pref.buscar_idioma')) ?>" aria-label="<?= e(t('pref.buscar_idioma')) ?>" autocomplete="off">
                            <div class="lista-idiomas notranslate" id="lista-idiomas" translate="no">
                                <?php foreach (IDIOMAS_MUNDO as $codigo => [$nativo, $nombreEs]): ?>
                                    <button type="button" data-auto="<?= e($codigo) ?>" data-buscar="<?= e(mb_strtolower($nativo . ' ' . $nombreEs . ' ' . $codigo)) ?>" class="<?= $idiomaAuto === $codigo ? 'activa' : '' ?>" lang="<?= e(LOCALE_DE_GOOGLE[$codigo] ?? $codigo) ?>">
                                        <strong><?= e($nativo) ?></strong><small><?= e($nombreEs) ?></small>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <p class="nota"><?= e(t('pref.auto_nota')) ?></p>
                        </details>
                    </div>
                    <div class="grupo-pref">
                        <span>💱 <?= e(t('pref.moneda')) ?></span>
                        <select id="pref-moneda" class="notranslate" translate="no" aria-label="<?= e(t('pref.moneda')) ?>">
                            <?php foreach (MONEDAS_VISTA as $codigo): ?>
                                <option value="<?= $codigo ?>" <?= $codigo === $monedaVista ? 'selected' : '' ?>><?= $codigo ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="nota"><?= e(t('pref.moneda_nota')) ?></p>
                    </div>
                    <div class="grupo-pref">
                        <span><?= e(t('pref.tema')) ?></span>
                        <div class="segmentado" id="pref-tema">
                            <button type="button" data-valor="light">☀ <?= e(t('pref.claro')) ?></button>
                            <button type="button" data-valor="dark">☾ <?= e(t('pref.oscuro')) ?></button>
                            <button type="button" data-valor="">◐ <?= e(t('pref.sistema')) ?></button>
                        </div>
                    </div>
                    <div class="grupo-pref">
                        <span><?= e(t('pref.zona')) ?></span>
                        <strong class="zona-actual notranslate" id="zona-actual" translate="no">—</strong>
                        <p class="nota" id="zona-ayuda"></p>
                    </div>
                    <div class="grupo-pref">
                        <span><?= e(t('pref.vision')) ?></span>
                        <select id="pref-vision" aria-label="<?= e(t('pref.vision')) ?>">
                            <option value=""><?= e(t('pref.vision_normal')) ?></option>
                            <option value="protanopia"><?= e(t('pref.protanopia')) ?></option>
                            <option value="deuteranopia"><?= e(t('pref.deuteranopia')) ?></option>
                            <option value="tritanopia"><?= e(t('pref.tritanopia')) ?></option>
                            <option value="acromatopsia"><?= e(t('pref.acromatopsia')) ?></option>
                        </select>
                        <p class="nota" id="vision-ayuda"></p>
                        <div class="muestra-vision" aria-hidden="true">
                            <span class="insignia viable">▲ <?= e(t('an.v.viable')) ?></span>
                            <span class="insignia neutral">● <?= e(t('an.v.neutral')) ?></span>
                            <span class="insignia no_viable">▼ <?= e(t('an.v.no_viable')) ?></span>
                        </div>
                    </div>
                    <div class="grupo-pref dos-columnas">
                        <div>
                            <span><?= e(t('pref.contraste')) ?></span>
                            <div class="segmentado" id="pref-contraste">
                                <button type="button" data-valor=""><?= e(t('pref.normal')) ?></button>
                                <button type="button" data-valor="alto">◑ <?= e(t('pref.alto')) ?></button>
                            </div>
                        </div>
                        <div>
                            <span><?= e(t('pref.texto')) ?></span>
                            <div class="segmentado" id="pref-texto">
                                <button type="button" data-valor="" aria-label="100 %">A</button>
                                <button type="button" data-valor="grande" aria-label="115 %">A+</button>
                                <button type="button" data-valor="enorme" aria-label="130 %">A++</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($usuario): ?>
                <details class="menu-usuario">
                    <summary aria-label="<?= e(t('cuenta.mi_cuenta')) ?>" title="<?= e($usuario['nombre']) ?>">
                        <span class="avatar" aria-hidden="true"><?= e($inicial) ?></span>
                        <span class="nombre-usuario"><?= e(explode(' ', $usuario['nombre'])[0]) ?></span>
                    </summary>
                    <div class="submenu submenu-usuario">
                        <div class="cabeza-usuario">
                            <span class="avatar grande" aria-hidden="true"><?= e($inicial) ?></span>
                            <span><strong><?= e($usuario['nombre']) ?></strong><small><?= e($usuario['email']) ?></small></span>
                        </div>
                        <a href="<?= url('paginas/cuenta.php') ?>"><span class="ico" aria-hidden="true">👤</span><span><strong><?= e(t('cuenta.mi_cuenta')) ?></strong><small><?= e(t('cuenta.mi_cuenta_d')) ?></small></span></a>
                        <?php if ($usuario['rol'] === 'admin'): ?>
                            <a href="<?= url('admin/index.php') ?>"><span class="ico" aria-hidden="true">🛡️</span><span><strong><?= e(t('cuenta.panel_admin')) ?></strong><small><?= e(t('cuenta.panel_admin_d')) ?></small></span></a>
                        <?php endif; ?>
                        <a href="<?= url('paginas/cuenta.php') ?>#rostro"><span class="ico" aria-hidden="true">🙂</span><span><strong><?= e(t('cuenta.acceso_facial')) ?></strong><small><?= e(t($usuario['tiene_rostro'] ? 'cuenta.rostro_activo' : 'cuenta.rostro_inactivo')) ?></small></span></a>
                        <button type="button" class="salir" id="boton-salir"><span class="ico" aria-hidden="true">🚪</span><span><strong><?= e(t('cuenta.salir')) ?></strong></span></button>
                    </div>
                </details>
            <?php else: ?>
                <a class="boton mini boton-entrar" href="<?= e($urlAcceso) ?>">👤 <?= e(t('cuenta.entrar')) ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($mostrarEstado): ?>
        <div class="cinta notranslate" translate="no" aria-label="<?= e(t('nav.cinta')) ?>"><div class="cinta-pista" id="cinta"></div></div>
    <?php endif; ?>
</header>
<?php if ($anuncio): ?>
    <div class="anuncio <?= e($anuncioTipo) ?>" role="status">
        <span aria-hidden="true"><?= ['info' => 'ℹ️', 'exito' => '🎉', 'alerta' => '⚠️'][$anuncioTipo] ?? 'ℹ️' ?></span>
        <p><?= e($anuncio) ?></p>
    </div>
<?php endif; ?>
<main class="contenedor" id="contenido" tabindex="-1">
<?php
if ($bloqueada) {
    require __DIR__ . '/bloqueo.php';
    $scripts = [url('assets/js/app.js')];
    require __DIR__ . '/footer.php';
    exit;
}
