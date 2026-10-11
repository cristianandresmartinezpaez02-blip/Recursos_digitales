<?php
/**
 * Sesión de usuario: quién está conectado, protección CSRF y qué secciones
 * necesitan cuenta. Las cuentas viven en MySQL (ver includes/db.php).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

/**
 * Secciones (clave $activa de cada página) que solo ven los usuarios con sesión.
 * Los visitantes ven el resto: inicio, top 100, mejores/peores, mapa,
 * tendencias, ficha de cada moneda, Aprende y la guía de estrategias.
 */
const CLAVES_PROTEGIDAS = [
    'categorias', 'maximos', 'exchanges',
    'escaner', 'diario', 'calculadoras', 'comparar', 'simulador', 'convertidor', 'favoritas', 'alertas',
    'trading', 'reto', 'subebaja', 'ranking',
];

/** Claves de localStorage que se guardan en la cuenta del usuario. */
const CLAVES_PROGRESO = ['minitrading_v1', 'reto_records', 'subebaja_record', 'subebaja_stats', 'coach_stats', 'favoritos', 'alertas', 'diario_trading'];

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

/** Usuario conectado (id, nombre, email, rol, tiene_rostro, creado) o null. */
function usuarioActual(): ?array
{
    static $usuario = false;
    if ($usuario !== false) {
        return $usuario;
    }
    $usuario = null;
    if (empty($_SESSION['usuario_id'])) {
        return null;
    }
    try {
        $st = db()->prepare("SELECT id, nombre, email, rol, activo, metodo_registro, password_hash <> '' AS tiene_clave, descriptor_facial IS NOT NULL AS tiene_rostro, facial_fecha, creado FROM usuarios WHERE id = ?");
        $st->execute([$_SESSION['usuario_id']]);
        $fila = $st->fetch();
    } catch (Throwable $e) {
        error_log('usuarioActual: ' . $e->getMessage());
        return null;
    }
    if (!$fila || !$fila['activo']) {
        unset($_SESSION['usuario_id']); // la cuenta ya no existe o fue suspendida
        return null;
    }
    $fila['tiene_rostro'] = (bool) $fila['tiene_rostro'];
    $fila['tiene_clave'] = (bool) $fila['tiene_clave'];
    return $usuario = $fila;
}

function haySesion(): bool
{
    return usuarioActual() !== null;
}

function esAdmin(): bool
{
    $u = usuarioActual();
    return $u !== null && $u['rol'] === 'admin';
}

// ---------- Ajustes editables desde el panel de administración ----------

function ajuste(string $clave, $defecto = null)
{
    static $cache = null;
    if ($cache === null) {
        try {
            $cache = array_column(db()->query('SELECT clave, valor FROM ajustes')->fetchAll(), 'valor', 'clave');
        } catch (Throwable $e) {
            error_log('ajustes: ' . $e->getMessage());
            $cache = [];
        }
    }
    return array_key_exists($clave, $cache) ? json_decode($cache[$clave], true) : $defecto;
}

function guardarAjuste(string $clave, $valor): void
{
    db()->prepare('INSERT INTO ajustes (clave, valor) VALUES (?, ?)' . siExisteActualizar('clave', 'valor'))
        ->execute([$clave, json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)]);
}

/** Secciones que exigen sesión (el administrador puede cambiarlas). */
function seccionesProtegidas(): array
{
    $lista = ajuste('secciones_protegidas');
    return is_array($lista) ? $lista : CLAVES_PROTEGIDAS;
}

function registroAbierto(): bool
{
    return (bool) ajuste('registro_abierto', true);
}

function facialActivo(): bool
{
    return (bool) ajuste('facial_activo', true);
}

/** Deja constancia de lo que pasa en la cuenta (inicios de sesión, cambios del administrador…). */
function registrarActividad(string $accion, string $detalle = '', ?int $usuarioId = null): void
{
    try {
        db()->prepare('INSERT INTO actividad (usuario_id, accion, detalle, ip) VALUES (?, ?, ?, ?)')
            ->execute([$usuarioId, $accion, mb_substr($detalle, 0, 255), substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45)]);
    } catch (Throwable $e) {
        error_log('actividad: ' . $e->getMessage());
    }
}

function iniciarSesionUsuario(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $id;
    db()->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')->execute([$id]);
}

function cerrarSesionUsuario(): void
{
    unset($_SESSION['usuario_id']);
    session_regenerate_id(true);
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_usuario'])) {
        $_SESSION['csrf_usuario'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_usuario'];
}

/** El token llega en la cabecera X-CSRF (fetch) o en el campo csrf (formulario). */
function csrfCorrecto(): bool
{
    $enviado = $_SERVER['HTTP_X_CSRF'] ?? ($_POST['csrf'] ?? '');
    return is_string($enviado) && $enviado !== '' && hash_equals(csrfToken(), $enviado);
}

/** Ruta interna a la que volver tras iniciar sesión (evita redirecciones a otros sitios). */
function rutaVolver(?string $ruta): string
{
    $ruta = (string) $ruta;
    if ($ruta === '' || !str_starts_with($ruta, BASE_URL . '/') || str_contains($ruta, '//') || str_contains($ruta, '\\')) {
        return BASE_URL . '/index.php';
    }
    return $ruta;
}

/** Progreso guardado del usuario: [clave => texto JSON]. */
function progresoUsuario(int $id): array
{
    $st = db()->prepare('SELECT clave, valor FROM progreso WHERE usuario_id = ?');
    $st->execute([$id]);
    return array_column($st->fetchAll(), 'valor', 'clave');
}
require_once __DIR__ . '/oauth.php';
