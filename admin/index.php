<?php
/**
 * Panel de administración. Entran los usuarios con rol "admin".
 * El primer administrador se crea iniciando sesión con una cuenta normal y
 * escribiendo ADMIN_PASSWORD (config/config.php) en el formulario de instalación.
 */
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/incluye/acciones.php';
require_once __DIR__ . '/incluye/datos.php';

$yo = usuarioActual();
if (!$yo) {
    header('Location: ' . BASE_URL . '/paginas/acceso.php?volver=' . rawurlencode(BASE_URL . '/admin/index.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCorrecto()) {
        volverAlPanel('La sesión caducó. Inténtalo de nuevo.', 'resumen', 'alerta');
    }
    if (($_POST['accion'] ?? '') === 'reclamar') {
        reclamarAdministracion($yo);
    }
    if (!esAdmin()) {
        http_response_code(403);
        exit;
    }
    ejecutarAccionAdmin($yo);
}
if (esAdmin() && ($_GET['exportar'] ?? '') === 'usuarios') {
    exportarUsuarios();
}

$titulo = 'Administración';
$activa = 'admin';
$mostrarEstado = false;
require __DIR__ . '/../includes/header.php';

$csrf = e(csrfToken());
$flash = $_SESSION['flash_admin'] ?? null;
unset($_SESSION['flash_admin']);
$campoCsrf = '<input type="hidden" name="csrf" value="' . $csrf . '">';

// ---------- Sin permisos ----------
if (!esAdmin()):
    $hayAdmins = (int) db()->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")->fetchColumn(); ?>
    <section class="bloqueo">
        <div class="bloqueo-vista" aria-hidden="true"><span class="bloqueo-icono">🛡️</span><span class="bloqueo-candado">🔒</span></div>
        <?php if ($flash): ?><p class="<?= e($flash[1]) ?>"><?= e($flash[0]) ?></p><?php endif; ?>
        <?php if (!$hayAdmins): ?>
            <h1>Instalar el panel de administración</h1>
            <p class="texto-2">Todavía no hay ningún administrador. Escribe la contraseña de instalación
                (<code>ADMIN_PASSWORD</code> en <code>config/config.php</code>) para convertir tu cuenta,
                <strong><?= e($yo['email']) ?></strong>, en la del administrador.</p>
            <form method="post" class="form-cuenta" style="max-width:360px;margin:1.25rem auto 0">
                <?= $campoCsrf ?><input type="hidden" name="accion" value="reclamar">
                <input type="password" name="password" placeholder="Contraseña de instalación" required autofocus aria-label="Contraseña de instalación">
                <button class="boton bloque">🛡️ Ser administrador</button>
            </form>
        <?php else: ?>
            <h1>Solo para administradores</h1>
            <p class="texto-2">Tu cuenta no tiene permisos de administración. Pide a un administrador que te los dé desde el panel.</p>
            <a class="boton secundario" href="<?= url('index.php') ?>">← Volver al inicio</a>
        <?php endif; ?>
    </section>
<?php
    $scripts = [url('assets/js/app.js')];
    require __DIR__ . '/../includes/footer.php';
    exit;
endif;

// ---------- Datos ----------
$est = estadisticasAdmin();
$registros = registrosPorDia();
$accesos = accesosPorDia();
$q = trim((string) ($_GET['q'] ?? ''));
$filtroRol = (string) ($_GET['rol'] ?? '');
$filtroEstado = (string) ($_GET['estado'] ?? '');
$pagina = max(1, (int) ($_GET['p'] ?? 1));
[$usuarios, $totalFiltrado] = listaUsuarios($q, $filtroRol, $filtroEstado, $pagina);
$paginas = max(1, (int) ceil($totalFiltrado / POR_PAGINA_ADMIN));
$intentos = intentosRecientes();
$actividad = actividadReciente();
$ranking = clasificacion();
$protegidas = seccionesProtegidas();

$uso = usoApi();
$porcentajeUso = min(100, $uso['mes'] / $uso['limite'] * 100);
$proyeccion = (int) round($uso['mes'] / max(1, (int) date('j')) * (int) date('t'));
$archivos = [];
foreach (glob(DIR_CACHE . '/*.json') as $f) {
    $contenido = json_decode(file_get_contents($f), true);
    $archivos[] = ['nombre' => basename($f, '.json'), 'fecha' => $contenido['fecha'] ?? filemtime($f), 'tamano' => filesize($f)];
}
usort($archivos, fn($a, $b) => $b['fecha'] <=> $a['fecha']);

$pct = fn(int $parte, int $total) => $total ? round($parte / $total * 100) : 0;
$enlacePagina = fn(int $p) => '?' . http_build_query(array_filter(['q' => $q, 'rol' => $filtroRol, 'estado' => $filtroEstado, 'p' => $p > 1 ? $p : null])) . '#usuarios';

/** Gráfico de barras accesible (HTML y CSS, sin librerías). */
function barras(array $serie, string $titulo): string
{
    $max = max(1, max($serie));
    $html = '<div class="barras" role="img" aria-label="' . e($titulo . ': ' . array_sum($serie) . ' en 30 días') . '">';
    foreach ($serie as $dia => $n) {
        $html .= '<span style="--alto:' . round($n / $max * 100) . '%" title="' . e(date('d/m', strtotime($dia)) . ': ' . $n) . '"><i></i></span>';
    }
    return $html . '</div><div class="barras-eje"><span>' . date('d/m', strtotime(array_key_first($serie))) . '</span><span>hoy</span></div>';
}
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">🛡️ Administración</div>
        <h1>Panel de <span class="titulo-gradiente">administración</span></h1>
        <p>Usuarios, seguridad, actividad, clasificación de los juegos, ajustes de la página y estado del sistema.</p>
    </div>
    <a class="boton secundario" href="?exportar=usuarios">📥 Exportar usuarios (CSV)</a>
</section>

<?php if ($flash): ?>
    <div class="aviso-flash <?= e($flash[1]) ?>" role="status"><?= $flash[1] === 'exito' ? '✔' : '⚠' ?> <?= e($flash[0]) ?></div>
<?php endif; ?>

<nav class="pestanas-seccion" id="pestanas-admin" role="tablist">
    <a href="#resumen" role="tab">📊 Resumen</a>
    <a href="#usuarios" role="tab">👥 Usuarios <small><?= $est['total'] ?></small></a>
    <a href="#seguridad" role="tab">🔐 Seguridad<?php if ($est['fallos24']): ?> <small class="baja"><?= $est['fallos24'] ?></small><?php endif; ?></a>
    <a href="#actividad" role="tab">🧾 Actividad</a>
    <a href="#clasificacion" role="tab">🏆 Clasificación</a>
    <a href="#ajustes" role="tab">⚙️ Ajustes</a>
    <a href="#sistema" role="tab">🖥️ Sistema</a>
</nav>

<!-- ===================== Resumen ===================== -->
<section id="resumen" role="tabpanel">
    <div class="kpis">
        <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">👥</span> Usuarios</div><div class="kpi-valor"><?= $est['total'] ?></div><div class="kpi-sub">+<?= $est['nuevos7'] ?> en los últimos 7 días</div></article>
        <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">🟢</span> Activos hoy</div><div class="kpi-valor"><?= $est['activos24'] ?></div><div class="kpi-sub"><?= $pct($est['activos24'], $est['total']) ?> % entraron en 24 h</div></article>
        <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">🙂</span> Con rostro</div><div class="kpi-valor"><?= $est['rostro'] ?></div>
            <div class="progreso"><span style="width:<?= $pct($est['rostro'], $est['total']) ?>%"></span></div><div class="kpi-sub"><?= $pct($est['rostro'], $est['total']) ?> % usa acceso facial</div></article>
        <article class="kpi"><div class="kpi-titulo"><span class="kpi-icono">⚠️</span> Intentos fallidos</div><div class="kpi-valor <?= $est['fallos24'] ? 'baja' : '' ?>"><?= $est['fallos24'] ?></div><div class="kpi-sub">últimas 24 h · <?= $est['suspendidos'] ?> cuentas suspendidas</div></article>
    </div>
    <div class="rejilla col-2">
        <div class="panel">
            <h2>✨ Nuevas cuentas <small>últimos 30 días · <?= array_sum($registros) ?></small></h2>
            <?= barras($registros, 'Nuevas cuentas') ?>
        </div>
        <div class="panel">
            <h2>🔑 Inicios de sesión <small>últimos 30 días · <?= array_sum($accesos) ?></small></h2>
            <?= barras($accesos, 'Inicios de sesión') ?>
            <?php $totalLogins = $est['login_clave'] + $est['login_facial']; ?>
            <div class="reparto" aria-label="Forma de entrar">
                <span class="parte clave" style="flex:<?= max(1, $est['login_clave']) ?>">📧 Contraseña <?= $pct($est['login_clave'], $totalLogins) ?> %</span>
                <span class="parte facial" style="flex:<?= max(1, $est['login_facial']) ?>">🙂 Rostro <?= $pct($est['login_facial'], $totalLogins) ?> %</span>
            </div>
        </div>
    </div>
</section>

<!-- ===================== Usuarios ===================== -->
<section id="usuarios" role="tabpanel" hidden>
    <div class="panel">
        <form class="filtros-admin" method="get" action="#usuarios">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre o correo…" aria-label="Buscar usuarios">
            <select name="rol" aria-label="Rol">
                <option value="">Todos los roles</option>
                <option value="admin" <?= $filtroRol === 'admin' ? 'selected' : '' ?>>🛡️ Administradores</option>
                <option value="usuario" <?= $filtroRol === 'usuario' ? 'selected' : '' ?>>👤 Usuarios</option>
            </select>
            <select name="estado" aria-label="Estado">
                <option value="">Todos los estados</option>
                <option value="activos" <?= $filtroEstado === 'activos' ? 'selected' : '' ?>>🟢 Activos</option>
                <option value="suspendidos" <?= $filtroEstado === 'suspendidos' ? 'selected' : '' ?>>⛔ Suspendidos</option>
                <option value="rostro" <?= $filtroEstado === 'rostro' ? 'selected' : '' ?>>🙂 Con rostro</option>
            </select>
            <button class="boton">Filtrar</button>
            <?php if ($q || $filtroRol || $filtroEstado): ?><a href="index.php#usuarios">Quitar filtros</a><?php endif; ?>
        </form>
        <p class="nota"><?= $totalFiltrado ?> resultado<?= $totalFiltrado === 1 ? '' : 's' ?></p>
        <div class="tabla-scroll">
            <table class="tabla tabla-usuarios">
                <thead><tr><th>Usuario</th><th>Registro</th><th>Rol</th><th>Estado</th><th>Rostro</th><th>Creado</th><th>Último acceso</th><th><span class="sr-only">Acciones</span></th></tr></thead>
                <tbody>
                <?php foreach ($usuarios as $u):
                    $soyYo = (int) $u['id'] === (int) $yo['id']; ?>
                    <tr class="<?= $u['activo'] ? '' : 'suspendido' ?>">
                        <td><div class="usuario-celda"><span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($u['nombre'], 0, 1))) ?></span>
                            <span><strong><?= e($u['nombre']) ?><?= $soyYo ? ' <small class="texto-3">(tú)</small>' : '' ?></strong><small><?= e($u['email']) ?></small></span></div></td>
                        <td><?= ['correo' => '📧 Correo', 'facial' => '🙂 Correo + rostro', 'google' => '🟦 Gmail', 'microsoft' => '🟧 Hotmail', 'apple' => '🍎 Apple'][$u['metodo_registro']] ?? e($u['metodo_registro']) ?></td>
                        <td><span class="insignia <?= $u['rol'] === 'admin' ? 'viable' : 'neutral' ?>"><?= $u['rol'] === 'admin' ? '🛡️ Admin' : '👤 Usuario' ?></span></td>
                        <td><?= $u['activo'] ? '<span class="sube">● Activo</span>' : '<span class="baja">⛔ Suspendido</span>' ?></td>
                        <td><?= $u['rostro'] ? '✔' : '<span class="texto-3">—</span>' ?></td>
                        <td class="texto-2"><?= date('d/m/Y', strtotime($u['creado'])) ?></td>
                        <td class="texto-2"><?= e(hace($u['ultimo_acceso'])) ?></td>
                        <td>
                            <details class="acciones-fila">
                                <summary aria-label="Acciones para <?= e($u['nombre']) ?>">⋯</summary>
                                <div class="acciones-menu">
                                    <?php if (!$soyYo): ?>
                                        <form method="post"><?= $campoCsrf ?><input type="hidden" name="accion" value="rol"><input type="hidden" name="id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="rol" value="<?= $u['rol'] === 'admin' ? 'usuario' : 'admin' ?>">
                                            <button><?= $u['rol'] === 'admin' ? '👤 Quitar administrador' : '🛡️ Hacer administrador' ?></button></form>
                                        <form method="post"><?= $campoCsrf ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="activo" value="<?= $u['activo'] ? 0 : 1 ?>">
                                            <button><?= $u['activo'] ? '⛔ Suspender cuenta' : '▶ Reactivar cuenta' ?></button></form>
                                    <?php endif; ?>
                                    <?php if ($u['rostro']): ?>
                                        <form method="post" data-confirmar="¿Borrar el rostro de <?= e($u['nombre']) ?>?"><?= $campoCsrf ?><input type="hidden" name="accion" value="quitar_rostro"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button>🙂 Borrar rostro</button></form>
                                    <?php endif; ?>
                                    <?php if ($u['datos_guardados']): ?>
                                        <form method="post" data-confirmar="¿Reiniciar los juegos, favoritas y alertas de <?= e($u['nombre']) ?>?"><?= $campoCsrf ?><input type="hidden" name="accion" value="reiniciar_progreso"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button>↺ Reiniciar progreso</button></form>
                                    <?php endif; ?>
                                    <?php if (!$soyYo): ?>
                                        <form method="post" data-confirmar="¿Eliminar para siempre la cuenta de <?= e($u['nombre']) ?>? No se puede deshacer."><?= $campoCsrf ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="peligro">🗑 Eliminar cuenta</button></form>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$usuarios): ?><tr><td colspan="8" class="cargando">No hay usuarios con esos filtros.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($paginas > 1): ?>
            <nav class="paginacion" aria-label="Páginas">
                <?php for ($i = 1; $i <= $paginas; $i++): ?>
                    <a href="<?= e($enlacePagina($i)) ?>" class="<?= $i === $pagina ? 'activa' : '' ?>" <?= $i === $pagina ? 'aria-current="page"' : '' ?>><?= $i ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>

<!-- ===================== Seguridad ===================== -->
<section id="seguridad" role="tabpanel" hidden>
    <div class="explicacion"><span class="ico">🔐</span><p>Un correo se bloquea <?= LOGIN_VENTANA_MIN ?> minutos tras <?= LOGIN_MAX_INTENTOS ?> intentos fallidos (contraseña o rostro). Aquí ves los intentos de las últimas 24 h y puedes desbloquear un correo.</p></div>
    <div class="panel">
        <div class="panel-cabecera">
            <h2>⚠️ Intentos fallidos <small>últimas 24 h</small></h2>
            <?php if ($intentos): ?>
                <form method="post" data-confirmar="¿Borrar todos los intentos fallidos?"><?= $campoCsrf ?><input type="hidden" name="accion" value="limpiar_intentos"><button class="boton mini secundario">🧹 Borrar todos</button></form>
            <?php endif; ?>
        </div>
        <div class="tabla-scroll">
            <table class="tabla compacta">
                <thead><tr><th>Correo</th><th class="num">Intentos</th><th>Tipo</th><th>IP</th><th>Último</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($intentos as $i):
                    $bloqueado = (int) $i['recientes'] >= LOGIN_MAX_INTENTOS; ?>
                    <tr>
                        <td><?= e($i['email']) ?></td>
                        <td class="num"><strong><?= (int) $i['intentos'] ?></strong></td>
                        <td><?= e(str_replace(['clave', 'facial'], ['📧 contraseña', '🙂 rostro'], $i['tipos'])) ?></td>
                        <td class="texto-2"><code><?= e($i['ips']) ?></code></td>
                        <td class="texto-2"><?= e(hace($i['ultimo'])) ?></td>
                        <td><?= $bloqueado ? '<span class="insignia no_viable">🔒 Bloqueado</span>' : '<span class="insignia neutral">● Vigilando</span>' ?></td>
                        <td><form method="post"><?= $campoCsrf ?><input type="hidden" name="accion" value="desbloquear"><input type="hidden" name="email" value="<?= e($i['email']) ?>"><button class="boton mini">🔓 Desbloquear</button></form></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$intentos): ?><tr><td colspan="7" class="cargando">✔ Sin intentos fallidos en las últimas 24 h.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ===================== Actividad ===================== -->
<section id="actividad" role="tabpanel" hidden>
    <div class="panel">
        <div class="panel-cabecera">
            <h2>🧾 Actividad reciente <small>últimos <?= count($actividad) ?> eventos</small></h2>
            <form method="post" data-confirmar="¿Borrar la actividad de hace más de 30 días?"><?= $campoCsrf ?><input type="hidden" name="accion" value="limpiar_actividad"><button class="boton mini secundario">🧹 Borrar lo de +30 días</button></form>
        </div>
        <ol class="linea-tiempo">
            <?php foreach ($actividad as $a):
                [$ico, $nombreAccion] = NOMBRES_ACTIVIDAD[$a['accion']] ?? ['•', $a['accion']]; ?>
                <li>
                    <span class="ico" aria-hidden="true"><?= $ico ?></span>
                    <div>
                        <strong><?= e($nombreAccion) ?></strong>
                        <?php if ($a['detalle']): ?><span class="texto-2"> · <?= e($a['detalle']) ?></span><?php endif; ?>
                        <small><?= $a['nombre'] ? e($a['nombre']) . ' · ' : '' ?><?= e(hace($a['fecha'])) ?> · <code><?= e($a['ip']) ?></code></small>
                    </div>
                </li>
            <?php endforeach; ?>
            <?php if (!$actividad): ?><li class="cargando">Todavía no hay actividad.</li><?php endif; ?>
        </ol>
    </div>
</section>

<!-- ===================== Clasificación ===================== -->
<section id="clasificacion" role="tabpanel" hidden>
    <div class="rejilla col-3">
        <?php foreach ([['reto', '⏩', 'Reto 7 días', '%'], ['trading', '💹', 'Trading en vivo', '%'], ['subebaja', '🎯', '¿Sube o baja?', 'pts']] as [$clave, $ico, $nombreJuego, $unidad]): ?>
            <div class="panel">
                <h2><?= $ico ?> <?= e($nombreJuego) ?></h2>
                <div class="records">
                    <?php foreach ($ranking[$clave] as $i => $r): ?>
                        <div class="fila"><span><?= ['🥇', '🥈', '🥉'][$i] ?? ($i + 1) . '.' ?> <?= e($r['nombre']) ?> <small class="texto-3"><?= e($r['extra']) ?></small></span>
                            <strong class="<?= $r['valor'] >= 0 ? 'sube' : 'baja' ?>"><?= $unidad === '%' ? ($r['valor'] >= 0 ? '+' : '') . number_format($r['valor'], 2, ',', '.') . ' %' : (int) $r['valor'] . ' pts' ?></strong></div>
                    <?php endforeach; ?>
                    <?php if (!$ranking[$clave]): ?><p class="texto-3">Nadie ha jugado todavía.</p><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ===================== Ajustes ===================== -->
<section id="ajustes" role="tabpanel" hidden>
    <form method="post" class="rejilla col-2">
        <?= $campoCsrf ?><input type="hidden" name="accion" value="ajustes">
        <div class="panel">
            <h2>🔒 Secciones solo para miembros</h2>
            <p class="subtitulo-panel">Las marcadas piden iniciar sesión; las demás las ve cualquier visitante.</p>
            <?php foreach (menuPrincipal() as $grupo): ?>
                <fieldset class="grupo-secciones">
                    <legend><?= $grupo['icono'] ?> <?= e(t($grupo['grupo'])) ?></legend>
                    <?php foreach ($grupo['items'] as [$clave, , $icono, $tit]): ?>
                        <label class="interruptor-fila"><input type="checkbox" name="secciones[]" value="<?= e($clave) ?>" <?= in_array($clave, $protegidas, true) ? 'checked' : '' ?>> <?= $icono ?> <?= e(t($tit)) ?></label>
                    <?php endforeach; ?>
                </fieldset>
            <?php endforeach; ?>
        </div>
        <div class="panel">
            <h2>📣 Anuncio para todos</h2>
            <p class="subtitulo-panel">Se muestra bajo la barra superior en todas las páginas. Déjalo vacío para quitarlo.</p>
            <label class="campo-form">Texto
                <textarea name="anuncio" rows="3" maxlength="300" placeholder="Ej.: ¡Nuevo torneo del Reto 7 días este fin de semana!"><?= e(ajuste('anuncio', '')) ?></textarea>
            </label>
            <div class="segmentado" style="margin:.6rem 0 1.4rem" role="radiogroup" aria-label="Tipo de anuncio">
                <?php foreach (['info' => 'ℹ️ Información', 'exito' => '🎉 Novedad', 'alerta' => '⚠️ Aviso'] as $valor => $etiqueta): ?>
                    <label class="opcion-radio"><input type="radio" name="anuncio_tipo" value="<?= $valor ?>" <?= ajuste('anuncio_tipo', 'info') === $valor ? 'checked' : '' ?>> <?= $etiqueta ?></label>
                <?php endforeach; ?>
            </div>
            <h2>👤 Cuentas</h2>
            <label class="interruptor-fila"><input type="checkbox" name="registro_abierto" value="1" <?= registroAbierto() ? 'checked' : '' ?>> ✨ Permitir crear cuentas nuevas</label>
            <label class="interruptor-fila"><input type="checkbox" name="facial_activo" value="1" <?= facialActivo() ? 'checked' : '' ?>> 🙂 Permitir el acceso con rostro</label>
            <p class="nota">Si desactivas el acceso con rostro, los usuarios siguen entrando con su contraseña.</p>
            <h2 style="margin-top:1.2rem">🔐 Iniciar sesión con Gmail, Hotmail y Apple</h2>
            <label class="campo-form">ID de cliente de Google <small class="texto-3">(termina en .apps.googleusercontent.com)</small>
                <input type="text" name="google_client_id" maxlength="200" value="<?= e(clientIdOauth('google')) ?>" placeholder="1234567890-abc123.apps.googleusercontent.com"<?= GOOGLE_CLIENT_ID !== '' ? ' disabled title="Definido en config.local.php"' : '' ?>>
            </label>
            <label class="campo-form">ID de aplicación de Microsoft (Hotmail / Outlook) <small class="texto-3">(Azure, gratis)</small>
                <input type="text" name="microsoft_client_id" maxlength="200" value="<?= e(clientIdOauth('microsoft')) ?>" placeholder="00000000-0000-0000-0000-000000000000"<?= MICROSOFT_CLIENT_ID !== '' ? ' disabled title="Definido en config.local.php"' : '' ?>>
            </label>
            <label class="campo-form">Services ID de Apple <small class="texto-3">(requiere Apple Developer)</small>
                <input type="text" name="apple_client_id" maxlength="200" value="<?= e(clientIdOauth('apple')) ?>" placeholder="com.tusitio.web"<?= APPLE_CLIENT_ID !== '' ? ' disabled title="Definido en config.local.php"' : '' ?>>
            </label>
            <p class="nota">Cómo conseguir cada ID: archivo LEEME.txt (Gmail y Hotmail son gratis; Apple exige Apple Developer). Mientras un ID esté vacío, su botón se muestra pero avisa que falta activarlo.</p>
            <button class="boton bloque" style="margin-top:1.2rem">💾 Guardar ajustes</button>
        </div>
    </form>
</section>

<!-- ===================== Sistema ===================== -->
<section id="sistema" role="tabpanel" hidden>
    <div class="kpis">
        <article class="kpi">
            <div class="kpi-titulo"><span class="kpi-icono">📡</span> Peticiones a CoinGecko este mes</div>
            <div class="kpi-valor"><?= number_format($uso['mes'], 0, ',', '.') ?> <small class="texto-3" style="font-size:.9rem">/ <?= number_format($uso['limite'], 0, ',', '.') ?></small></div>
            <div class="progreso"><span style="width:<?= $porcentajeUso ?>%<?= $porcentajeUso > 85 ? ';background:var(--baja)' : '' ?>"></span></div>
            <div class="kpi-sub"><?= number_format($porcentajeUso, 1, ',', '.') ?> % del cupo mensual</div>
        </article>
        <article class="kpi">
            <div class="kpi-titulo"><span class="kpi-icono">🔮</span> Proyección fin de mes</div>
            <div class="kpi-valor <?= $proyeccion > $uso['limite'] ? 'baja' : 'sube' ?>"><?= number_format($proyeccion, 0, ',', '.') ?></div>
            <div class="kpi-sub"><?= $proyeccion > $uso['limite'] ? 'Superará el cupo: sube CACHE_MERCADO_SEG en config.php.' : 'Dentro del cupo mensual.' ?></div>
        </article>
        <article class="kpi">
            <div class="kpi-titulo"><span class="kpi-icono">🗂️</span> Archivos en caché</div>
            <div class="kpi-valor"><?= count($archivos) ?></div>
            <div class="kpi-sub"><?= number_format(array_sum(array_column($archivos, 'tamano')) / 1024, 0, ',', '.') ?> KB en disco</div>
        </article>
        <article class="kpi">
            <div class="kpi-titulo"><span class="kpi-icono">🖥️</span> Servidor</div>
            <div class="kpi-valor" style="font-size:1.1rem">PHP <?= PHP_VERSION ?></div>
            <div class="kpi-sub"><?= e(db()->query('SELECT VERSION()')->fetchColumn()) ?> · <?= esSqlite() ? 'archivo data/basedatos.sqlite' : (esPostgres() ? 'PostgreSQL en la nube (DATABASE_URL)' : 'base «' . e(DB_NOMBRE) . '»') ?></div>
        </article>
    </div>
    <div class="rejilla col-2">
        <div class="panel">
            <h2>Últimas peticiones a CoinGecko</h2>
            <div class="tabla-scroll lista-alta">
                <table class="tabla compacta">
                    <thead><tr><th>Fecha</th><th>Endpoint</th><th>Código</th></tr></thead>
                    <tbody>
                    <?php foreach ($uso['ultimas'] as $u): ?>
                        <tr>
                            <td class="texto-2"><?= date('Y-m-d H:i:s', $u['fecha']) ?></td>
                            <td><code><?= e($u['ruta']) ?></code></td>
                            <td><span class="insignia <?= $u['codigo'] === 200 ? 'viable' : 'no_viable' ?>"><?= (int) $u['codigo'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$uso['ultimas']): ?><tr><td colspan="3" class="cargando">Sin peticiones registradas.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="panel">
            <div class="panel-cabecera">
                <h2>Caché</h2>
                <form method="post"><?= $campoCsrf ?><input type="hidden" name="accion" value="limpiar_cache"><button class="boton mini">Forzar actualización</button></form>
            </div>
            <div class="tabla-scroll lista-alta">
                <table class="tabla compacta">
                    <thead><tr><th>Archivo</th><th>Actualizado</th><th class="num">Tamaño</th></tr></thead>
                    <tbody>
                    <?php foreach ($archivos as $a): ?>
                        <tr>
                            <td><?= e($a['nombre']) ?></td>
                            <td class="texto-2">hace <?= max(0, time() - $a['fecha']) ?> s</td>
                            <td class="num"><?= number_format($a['tamano'] / 1024, 1, ',', '.') ?> KB</td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$archivos): ?><tr><td colspan="3" class="cargando">La caché está vacía.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php
$scripts = [url('assets/js/app.js'), url('assets/js/admin.js')];
require __DIR__ . '/../includes/footer.php';
