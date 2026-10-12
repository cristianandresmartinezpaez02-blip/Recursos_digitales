<?php
/**
 * Notificaciones push (Web Push) sin librerías: cifrado aes128gcm (RFC 8291) y firma VAPID (RFC 8292)
 * con las funciones de OpenSSL que ya trae PHP.
 *
 * Las claves VAPID se crean solas la primera vez y se guardan en la tabla `ajustes` (en Render,
 * en Supabase): no hace falta configurar nada. Si se borran, las suscripciones dejan de valer y
 * cada usuario tiene que volver a pulsar «Activar notificaciones».
 */
require_once __DIR__ . '/sesion.php';

function b64url(string $datos): string
{
    return rtrim(strtr(base64_encode($datos), '+/', '-_'), '=');
}

function desdeB64url(string $texto): string
{
    return (string) base64_decode(strtr($texto, '-_', '+/'), true);
}

/** Opciones de openssl_pkey_new: en Windows (XAMPP) OpenSSL necesita saber dónde está openssl.cnf. */
function opcionesClaveEc(): array
{
    $op = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
    if (PHP_OS_FAMILY === 'Windows' && !getenv('OPENSSL_CONF')) {
        foreach ([dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf', dirname(PHP_BINARY, 2) . '/apache/conf/openssl.cnf'] as $cnf) {
            if (is_file($cnf)) {
                $op['config'] = $cnf;
                break;
            }
        }
    }
    return $op;
}

/** Punto público P-256 sin comprimir (0x04 || X || Y, 65 bytes). */
function puntoPublico($clave): string
{
    $ec = openssl_pkey_get_details($clave)['ec'];
    return "\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
}

/** Convierte un punto público de 65 bytes en una clave que entiende OpenSSL. */
function clavePublicaDesdePunto(string $punto)
{
    // Cabecera DER de SubjectPublicKeyInfo para una clave EC P-256.
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $punto;
    return openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n");
}

/** Claves VAPID del sitio: ['publica' => base64url, 'privada' => PEM]. */
function clavesVapid(): array
{
    $guardadas = ajuste('vapid');
    if (is_array($guardadas) && !empty($guardadas['publica']) && !empty($guardadas['privada'])) {
        return $guardadas;
    }
    $clave = openssl_pkey_new(opcionesClaveEc());
    if (!$clave || !openssl_pkey_export($clave, $pem, null, opcionesClaveEc())) {
        throw new RuntimeException('No se pudieron crear las claves de notificaciones: ' . openssl_error_string());
    }
    $nuevas = ['publica' => b64url(puntoPublico($clave)), 'privada' => $pem];
    guardarAjuste('vapid', $nuevas);
    return $nuevas;
}

/** Firma ES256: OpenSSL la da en DER y JWT la quiere como R || S (64 bytes). */
function firmaEs256(string $datos, $privada): string
{
    if (!openssl_sign($datos, $der, $privada, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('No se pudo firmar la notificación');
    }
    $pos = 3 + (ord($der[1]) & 0x80 ? ord($der[1]) & 0x7f : 0); // SEQUENCE + longitud
    $partes = '';
    for ($i = 0; $i < 2; $i++) {
        $largo = ord($der[$pos]);
        $entero = ltrim(substr($der, $pos + 1, $largo), "\0");
        $partes .= str_pad($entero, 32, "\0", STR_PAD_LEFT);
        $pos += 2 + $largo;
    }
    return $partes;
}

/** Cabecera Authorization VAPID para el servicio de push de esa suscripción. */
function cabeceraVapid(string $endpoint, array $vapid): string
{
    $u = parse_url($endpoint);
    $jwtCabecera = b64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $jwtDatos = b64url(json_encode([
        'aud' => $u['scheme'] . '://' . $u['host'],
        'exp' => time() + 12 * 3600,
        'sub' => 'mailto:avisos@criptoanalisis.app',
    ]));
    $firma = firmaEs256("$jwtCabecera.$jwtDatos", openssl_pkey_get_private($vapid['privada']));
    return 'vapid t=' . "$jwtCabecera.$jwtDatos." . b64url($firma) . ', k=' . $vapid['publica'];
}

/**
 * Cifra el mensaje para el navegador (RFC 8291, aes128gcm). $p256dh y $auth vienen de la
 * suscripción del navegador (base64url). $efimera y $sal solo se pasan en las pruebas.
 */
function cifrarPush(string $mensaje, string $p256dh, string $auth, $efimera = null, ?string $sal = null): string
{
    $puntoNavegador = desdeB64url($p256dh);
    $secretoAuth = desdeB64url($auth);
    if (strlen($puntoNavegador) !== 65 || strlen($secretoAuth) !== 16) {
        throw new InvalidArgumentException('Suscripción dañada');
    }
    $efimera ??= openssl_pkey_new(opcionesClaveEc());
    $puntoServidor = puntoPublico($efimera);
    $secretoEcdh = openssl_pkey_derive(clavePublicaDesdePunto($puntoNavegador), $efimera, 32);
    if ($secretoEcdh === false) {
        throw new RuntimeException('No se pudo cifrar la notificación');
    }
    $ikm = hash_hkdf('sha256', $secretoEcdh, 32, "WebPush: info\0" . $puntoNavegador . $puntoServidor, $secretoAuth);
    $sal ??= random_bytes(16);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $sal);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $sal);
    $cifrado = openssl_encrypt($mensaje . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $etiqueta);
    return $sal . pack('N', 4096) . chr(65) . $puntoServidor . $cifrado . $etiqueta;
}

/**
 * Envía una notificación. Devuelve el código HTTP del servicio de push
 * (201 = enviada; 404 o 410 = la suscripción ya no existe y hay que borrarla).
 */
function enviarPush(array $suscripcion, array $aviso, array $vapid): int
{
    $ch = curl_init($suscripcion['endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => cifrarPush(json_encode($aviso, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $suscripcion['p256dh'], $suscripcion['auth']),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => [
            'Authorization: ' . cabeceraVapid($suscripcion['endpoint'], $vapid),
            'Content-Encoding: aes128gcm',
            'Content-Type: application/octet-stream',
            'TTL: 21600',          // si el celular está apagado, el aviso espera hasta 6 h
            'Urgency: high',
        ],
    ]);
    curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $codigo;
}

/** Solo se aceptan suscripciones de los servicios de push de los navegadores (evita usar el servidor para llamar a cualquier URL). */
function endpointPushValido(string $endpoint): bool
{
    $u = parse_url($endpoint);
    if (($u['scheme'] ?? '') !== 'https' || empty($u['host'])) {
        return false;
    }
    return (bool) preg_match('/(^|\.)(googleapis\.com|mozilla\.com|mozaws\.net|push\.apple\.com|notify\.windows\.com)$/i', $u['host']);
}
