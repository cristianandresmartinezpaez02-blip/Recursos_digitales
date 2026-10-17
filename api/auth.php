<?php
/**
 * POST api/auth.php?accion=...
 * Cuerpo JSON. Todas las acciones exigen la cabecera X-CSRF.
 *
 *   registro       {nombre, email, password, descriptores?} crea la cuenta e inicia sesión
 *   login          {email, password}
 *   login_facial   {email, descriptor}                     compara el rostro con el guardado
 *   guardar_rostro {password, descriptores}                (con sesión) registra o cambia el rostro
 *   quitar_rostro  {password}                              (con sesión)
 *   oauth          {proveedor: google|apple, token, nombre?}  valida el id_token; entra o crea la cuenta
 *   logout
 *
 * "descriptor" son los 128 números que calcula face-api.js en el navegador ("descriptores":
 * uno por expresión registrada: neutral, sonrisa, sorpresa);
 * la foto nunca sale del equipo del usuario.
 */
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/oauth.php';

function responder(array $datos, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fallar(string $clave, int $codigo = 400): void
{
    responder(['ok' => false, 'error' => t($clave)], $codigo);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fallar('cuenta.err_metodo', 405);
}
if (!csrfCorrecto()) {
    fallar('cuenta.err_csrf', 403);
}

$entrada = json_decode(file_get_contents('php://input'), true);
if (!is_array($entrada)) {
    $entrada = [];
}
$accion = $_GET['accion'] ?? '';

// ---------- Validaciones ----------

function emailValido($email): ?string
{
    $email = mb_strtolower(trim((string) $email));
    return filter_var($email, FILTER_VALIDATE_EMAIL) && mb_strlen($email) <= 190 ? $email : null;
}

/** 128 números finitos, o null si no es un descriptor de face-api.js. */
function descriptorValido($d): ?array
{
    if (!is_array($d) || count($d) !== 128 || !array_is_list_compat($d)) {
        return null;
    }
    $limpio = [];
    foreach ($d as $v) {
        if (!is_int($v) && !is_float($v)) {
            return null;
        }
        $v = (float) $v;
        if (!is_finite($v) || abs($v) > 2) {
            return null;
        }
        $limpio[] = round($v, 6);
    }
    return $limpio;
}

/** Lista de 1 a 5 descriptores (uno por expresión: neutral, sonrisa, sorpresa…). */
function descriptoresValidos($lista): ?array
{
    if (!is_array($lista) || !$lista || count($lista) > 5 || !array_is_list_compat($lista)) {
        return null;
    }
    $limpios = [];
    foreach ($lista as $d) {
        $v = descriptorValido($d);
        if (!$v) {
            return null;
        }
        $limpios[] = $v;
    }
    return $limpios;
}

/** Descriptores guardados de un usuario. Las cuentas antiguas tienen uno solo (128 números). */
function descriptoresGuardados(string $json): array
{
    $d = json_decode($json, true);
    return is_array($d) && isset($d[0]) && is_array($d[0]) ? $d : [$d];
}

/** Entrada del cliente: "descriptores" (nuevo) o "descriptor" (versión anterior). */
function descriptoresDeEntrada(array $entrada): ?array
{
    if (isset($entrada['descriptores'])) {
        return descriptoresValidos($entrada['descriptores']);
    }
    $uno = isset($entrada['descriptor']) ? descriptorValido($entrada['descriptor']) : null;
    return $uno ? [$uno] : null;
}

function array_is_list_compat(array $a): bool
{
    return array_keys($a) === range(0, count($a) - 1);
}

function distanciaEuclidiana(array $a, array $b): float
{
    $suma = 0.0;
    foreach ($a as $i => $v) {
        $suma += ($v - $b[$i]) ** 2;
    }
    return sqrt($suma);
}

// ---------- Límite de intentos (ipCliente() está en includes/sesion.php) ----------

/** Cuentas creadas desde una misma IP en la última hora (frena la creación masiva de cuentas). */
function demasiadosRegistros(): bool
{
    $st = db()->prepare("SELECT COUNT(*) FROM actividad WHERE accion = 'registro' AND fecha > ? AND ip = ?");
    $st->execute([fechaHace(3600), ipCliente()]);
    return (int) $st->fetchColumn() >= 5;
}

/**
 * Cloudflare Turnstile (captcha gratis, sin dominio propio): solo si TURNSTILE_SECRET está definida.
 * Si Cloudflare no responde se deja pasar: los límites por IP siguen protegiendo el registro.
 */
function turnstileValido(string $token): bool
{
    if (TURNSTILE_SECRET === '') {
        return true;
    }
    if ($token === '' || strlen($token) > 2048) {
        return false;
    }
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['secret' => TURNSTILE_SECRET, 'response' => $token, 'remoteip' => ipCliente()]),
    ]);
    $cuerpo = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($cuerpo === false || $codigo >= 500) {
        error_log('Turnstile sin respuesta (HTTP ' . $codigo . '): se deja pasar');
        return true;
    }
    return (json_decode((string) $cuerpo, true)['success'] ?? false) === true;
}

function bloqueado(string $email): bool
{
    $st = db()->prepare('SELECT
            SUM(CASE WHEN email = ? THEN 1 ELSE 0 END) AS por_email,
            SUM(CASE WHEN ip = ? THEN 1 ELSE 0 END)    AS por_ip
        FROM intentos_acceso WHERE fecha > ?');
    $st->execute([$email, ipCliente(), fechaHace(LOGIN_VENTANA_MIN * 60)]);
    $f = $st->fetch();
    return (int) $f['por_email'] >= LOGIN_MAX_INTENTOS || (int) $f['por_ip'] >= LOGIN_MAX_INTENTOS * 4;
}

function registrarFallo(string $email, string $tipo): void
{
    db()->prepare('INSERT INTO intentos_acceso (email, ip, tipo) VALUES (?, ?, ?)')->execute([$email, ipCliente(), $tipo]);
    // Limpieza ocasional de intentos viejos.
    if (random_int(1, 50) === 1) {
        db()->prepare('DELETE FROM intentos_acceso WHERE fecha < ?')->execute([fechaHace(86400)]);
    }
}

function limpiarFallos(string $email): void
{
    db()->prepare('DELETE FROM intentos_acceso WHERE email = ?')->execute([$email]);
}

function buscarPorEmail(string $email): ?array
{
    $st = db()->prepare('SELECT * FROM usuarios WHERE email = ?');
    $st->execute([$email]);
    return $st->fetch() ?: null;
}

function datosPublicos(array $u): array
{
    return ['nombre' => $u['nombre'], 'email' => $u['email']];
}

/** Credenciales correctas: comprueba que la cuenta no esté suspendida e inicia la sesión. */
function entrar(array $u, string $metodo): void
{
    if (!$u['activo']) {
        registrarActividad('login_suspendida', $metodo, (int) $u['id']);
        fallar('cuenta.err_suspendida', 403);
    }
    limpiarFallos($u['email']);
    iniciarSesionUsuario((int) $u['id']);
    registrarActividad('login', $metodo, (int) $u['id']);
    responder(['ok' => true, 'usuario' => datosPublicos($u)]);
}

// ---------- Acciones ----------

try {
    switch ($accion) {
        case 'registro':
            if (!registroAbierto()) fallar('cuenta.err_registro_cerrado', 403);
            if (demasiadosRegistros()) fallar('cuenta.err_muchos_registros', 429);
            if (!turnstileValido((string) ($entrada['turnstile'] ?? ''))) fallar('cuenta.err_captcha', 400);
            $nombre = trim((string) ($entrada['nombre'] ?? ''));
            $email = emailValido($entrada['email'] ?? '');
            $password = (string) ($entrada['password'] ?? '');
            // Si el administrador desactivó el acceso facial, el rostro enviado se ignora.
            $conRostro = (isset($entrada['descriptores']) || isset($entrada['descriptor'])) && facialActivo();
            $descriptores = $conRostro ? descriptoresDeEntrada($entrada) : null;
            if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 60) fallar('cuenta.err_nombre');
            if (!$email) fallar('cuenta.err_email');
            if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) fallar('cuenta.err_password');
            if ($conRostro && !$descriptores) fallar('cuenta.err_rostro');
            if (buscarPorEmail($email)) fallar('cuenta.err_email_usado', 409);

            db()->prepare('INSERT INTO usuarios (nombre, email, password_hash, metodo_registro, descriptor_facial, facial_fecha) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT), $descriptores ? 'facial' : 'correo',
                    $descriptores ? json_encode($descriptores) : null, $descriptores ? date('Y-m-d H:i:s') : null]);
            $id = (int) db()->lastInsertId();
            iniciarSesionUsuario($id);
            registrarActividad('registro', $descriptores ? 'con rostro (' . count($descriptores) . ' expresiones)' : 'sin rostro', $id);
            responder(['ok' => true, 'usuario' => ['nombre' => $nombre, 'email' => $email]]);

        case 'login':
            $email = emailValido($entrada['email'] ?? '');
            $password = (string) ($entrada['password'] ?? '');
            if (!$email || $password === '') fallar('cuenta.err_credenciales', 401);
            if (bloqueado($email)) fallar('cuenta.err_bloqueado', 429);
            $u = buscarPorEmail($email);
            // password_verify se ejecuta aunque el usuario no exista para no revelar qué correos están registrados.
            $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
            if (!password_verify($password, $hash) || !$u) {
                registrarFallo($email, 'clave');
                fallar('cuenta.err_credenciales', 401);
            }
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
            }
            entrar($u, 'clave');

        case 'login_facial':
            if (!facialActivo()) fallar('cuenta.err_facial_off', 403);
            $email = emailValido($entrada['email'] ?? '');
            $descriptor = descriptorValido($entrada['descriptor'] ?? null);
            if (!$email) fallar('cuenta.err_email');
            if (!$descriptor) fallar('cuenta.err_rostro');
            if (bloqueado($email)) fallar('cuenta.err_bloqueado', 429);
            $u = buscarPorEmail($email);
            if (!$u || !$u['descriptor_facial']) {
                registrarFallo($email, 'facial');
                fallar('cuenta.err_sin_rostro', 401);
            }
            // Se compara con cada expresión guardada y vale la más parecida.
            $distancia = min(array_map(fn($g) => distanciaEuclidiana($descriptor, $g), descriptoresGuardados($u['descriptor_facial'])));
            if ($distancia > FACIAL_UMBRAL) {
                registrarFallo($email, 'facial');
                fallar('cuenta.err_rostro_no_coincide', 401);
            }
            entrar($u, 'facial');

        case 'oauth':
            // Google o Apple: el token llega firmado por el proveedor y se valida en includes/oauth.php.
            $proveedor = (string) ($entrada['proveedor'] ?? '');
            if (!isset(PROVEEDORES_OAUTH[$proveedor]) || clientIdOauth($proveedor) === '') fallar('cuenta.err_oauth_off', 403);
            $nonce = (string) ($_SESSION['oauth_nonce'] ?? ''); // lo crea paginas/acceso.php; evita reutilizar tokens ajenos
            try {
                // Gmail: token de acceso del selector de cuentas. Apple: id_token firmado.
                $datos = $proveedor === 'google'
                    ? verificarAccesoGoogle((string) ($entrada['token'] ?? ''))
                    : verificarIdToken($proveedor, (string) ($entrada['token'] ?? ''), $nonce);
            } catch (DomainException | RuntimeException $e) {
                registrarActividad('login_fallido', $proveedor . ': ' . $e->getMessage());
                fallar('cuenta.err_oauth', 401);
            }
            if ($proveedor === 'microsoft') {
                // Cuentas personales (Hotmail/Outlook): Microsoft ya verificó el correo de la cuenta.
                $datos['email'] = $datos['email'] ?? ($datos['preferred_username'] ?? '');
                $datos['email_verified'] = true;
            }
            $columna = $proveedor . '_sub'; // google_sub, apple_sub o microsoft_sub (lista cerrada)
            $sub = substr((string) $datos['sub'], 0, 64);
            $email = emailValido($datos['email'] ?? '');
            $verificado = in_array($datos['email_verified'] ?? false, [true, 'true'], true);

            $st = db()->prepare("SELECT * FROM usuarios WHERE $columna = ?");
            $st->execute([$sub]);
            $u = $st->fetch() ?: null;
            if (!$u && $email && $verificado && ($u = buscarPorEmail($email))) {
                // Ya tenía cuenta con ese correo: se enlaza con Google/Apple.
                db()->prepare("UPDATE usuarios SET $columna = ? WHERE id = ?")->execute([$sub, $u['id']]);
                registrarActividad('enlace', $proveedor, (int) $u['id']);
            }
            if (!$u) {
                if (!registroAbierto()) fallar('cuenta.err_registro_cerrado', 403);
                if (!$email || !$verificado) fallar('cuenta.err_oauth_email', 400);
                if (demasiadosRegistros()) fallar('cuenta.err_muchos_registros', 429);
                // Apple solo envía el nombre la primera vez (desde el navegador, no dentro del token).
                $nombre = trim((string) ($datos['name'] ?? ($entrada['nombre'] ?? '')));
                if (mb_strlen($nombre) < 2) $nombre = ucfirst(explode('@', $email)[0]);
                $nombre = mb_substr($nombre, 0, 60);
                db()->prepare("INSERT INTO usuarios (nombre, email, password_hash, metodo_registro, $columna) VALUES (?, ?, '', ?, ?)")
                    ->execute([$nombre, $email, $proveedor, $sub]);
                $id = (int) db()->lastInsertId();
                registrarActividad('registro', 'con ' . $proveedor, $id);
                $u = buscarPorEmail($email);
            }
            entrar($u, $proveedor);

        case 'guardar_rostro':
        case 'quitar_rostro':
            $actual = usuarioActual();
            if (!$actual) fallar('cuenta.err_sin_sesion', 401);
            $st = db()->prepare('SELECT password_hash FROM usuarios WHERE id = ?');
            $st->execute([$actual['id']]);
            $hashActual = (string) $st->fetchColumn();
            // Las cuentas creadas con Google o Apple no tienen contraseña: basta con la sesión.
            if ($hashActual !== '' && !password_verify((string) ($entrada['password'] ?? ''), $hashActual)) fallar('cuenta.err_password_actual', 401);
            if ($accion === 'quitar_rostro') {
                db()->prepare('UPDATE usuarios SET descriptor_facial = NULL, facial_fecha = NULL WHERE id = ?')->execute([$actual['id']]);
                registrarActividad('rostro', 'quitado por el usuario', (int) $actual['id']);
                responder(['ok' => true]);
            }
            if (!facialActivo()) fallar('cuenta.err_facial_off', 403);
            $descriptores = descriptoresDeEntrada($entrada);
            if (!$descriptores) fallar('cuenta.err_rostro');
            db()->prepare('UPDATE usuarios SET descriptor_facial = ?, facial_fecha = NOW() WHERE id = ?')->execute([json_encode($descriptores), $actual['id']]);
            registrarActividad('rostro', 'registrado', (int) $actual['id']);
            responder(['ok' => true]);

        case 'cerrar_otras':
            $actual = usuarioActual();
            if (!$actual) fallar('cuenta.err_sin_sesion', 401);
            cerrarOtrasSesiones((int) $actual['id']);
            registrarActividad('cerrar_otras', '', (int) $actual['id']);
            responder(['ok' => true]);

        case 'eliminar_cuenta':
            // Lo exigen Google Play y App Store: el usuario puede borrar su cuenta él mismo.
            $actual = usuarioActual();
            if (!$actual) fallar('cuenta.err_sin_sesion', 401);
            $id = (int) $actual['id'];
            $st = db()->prepare('SELECT password_hash FROM usuarios WHERE id = ?');
            $st->execute([$id]);
            $hashActual = (string) $st->fetchColumn();
            // Con contraseña: la contraseña. Cuentas de Google/Apple/Microsoft: escribir su correo.
            if ($hashActual !== '' ? !password_verify((string) ($entrada['password'] ?? ''), $hashActual)
                    : emailValido($entrada['confirmacion'] ?? '') !== $actual['email']) {
                fallar($hashActual !== '' ? 'cuenta.err_password_actual' : 'cuenta.err_confirmacion', 401);
            }
            if ($actual['rol'] === 'admin' && (int) db()->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")->fetchColumn() <= 1) {
                fallar('cuenta.err_unico_admin', 409);
            }
            // progreso, push_suscripciones y push_avisos se borran solos (ON DELETE CASCADE);
            // la actividad queda sin dueño (solo para estadísticas y seguridad).
            db()->prepare('UPDATE actividad SET usuario_id = NULL WHERE usuario_id = ?')->execute([$id]);
            db()->prepare('DELETE FROM progreso WHERE usuario_id = ?')->execute([$id]); // MySQL/SQLite antiguos sin cascada
            db()->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
            registrarActividad('cuenta_eliminada', 'por el usuario');
            cerrarSesionUsuario();
            responder(['ok' => true]);

        case 'logout':
            $actual = usuarioActual();
            if ($actual) registrarActividad('logout', '', (int) $actual['id']);
            cerrarSesionUsuario();
            responder(['ok' => true]);

        default:
            fallar('cuenta.err_accion', 404);
    }
} catch (PDOException $e) {
    error_log('api/auth.php: ' . $e->getMessage());
    fallar('cuenta.err_bd', 500);
}
