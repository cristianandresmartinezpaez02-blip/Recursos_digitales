<?php
/**
 * Inicio de sesión con Google y Apple.
 * El navegador recibe del proveedor un "id_token" (un JWT firmado). Aquí se comprueba
 * la firma RS256 con las claves públicas del proveedor (JWKS), el emisor, el destinatario
 * (nuestro Client ID) y la caducidad. Solo entonces se confía en el correo que trae.
 */
require_once __DIR__ . '/../config/config.php';

const PROVEEDORES_OAUTH = [
    'google' => [
        'jwks' => 'https://www.googleapis.com/oauth2/v3/certs',
        'emisores' => ['https://accounts.google.com', 'accounts.google.com'],
    ],
    'apple' => [
        'jwks' => 'https://appleid.apple.com/auth/keys',
        'emisores' => ['https://appleid.apple.com'],
    ],
    // Hotmail / Outlook / Live: solo cuentas personales de Microsoft (inquilino «consumers»).
    'microsoft' => [
        'jwks' => 'https://login.microsoftonline.com/consumers/discovery/v2.0/keys',
        'emisores' => ['https://login.microsoftonline.com/9188040d-6c67-4c5b-b112-36a304b66dad/v2.0'],
    ],
];

/** Client ID del proveedor: el de config.local.php o, si no, el pegado en el panel de administración. */
function clientIdOauth(string $proveedor): string
{
    $fijo = ['google' => GOOGLE_CLIENT_ID, 'apple' => APPLE_CLIENT_ID, 'microsoft' => MICROSOFT_CLIENT_ID][$proveedor] ?? '';
    if ($fijo !== '' || !function_exists('ajuste')) {
        return $fijo;
    }
    return (string) ajuste($proveedor . '_client_id', '');
}

function base64UrlDecode(string $texto): string
{
    $texto = strtr($texto, '-_', '+/');
    return (string) base64_decode($texto . str_repeat('=', (4 - strlen($texto) % 4) % 4), true);
}

/** Claves públicas del proveedor (se guardan 6 horas en data/cache). */
function clavesJwks(string $proveedor, bool $renovar = false): array
{
    $archivo = DIR_CACHE . '/jwks_' . $proveedor . '.json';
    if (!$renovar && is_file($archivo) && time() - filemtime($archivo) < 21600) {
        $claves = json_decode((string) file_get_contents($archivo), true);
        if (is_array($claves)) {
            return $claves;
        }
    }
    $ch = curl_init(PROVEEDORES_OAUTH[$proveedor]['jwks']);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5]);
    $cuerpo = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $datos = $codigo === 200 ? json_decode((string) $cuerpo, true) : null;
    if (!isset($datos['keys']) || !is_array($datos['keys'])) {
        throw new RuntimeException("No se pudieron leer las claves de $proveedor");
    }
    $claves = [];
    foreach ($datos['keys'] as $k) {
        if (($k['kty'] ?? '') === 'RSA' && isset($k['kid'], $k['n'], $k['e'])) {
            $claves[$k['kid']] = ['n' => $k['n'], 'e' => $k['e']];
        }
    }
    if (!is_dir(DIR_CACHE)) {
        mkdir(DIR_CACHE, 0775, true);
    }
    @file_put_contents($archivo, json_encode($claves), LOCK_EX);
    return $claves;
}

/** Longitud en formato DER (ASN.1). */
function derLongitud(int $n): string
{
    if ($n < 0x80) {
        return chr($n);
    }
    $bytes = ltrim(pack('N', $n), "\0");
    return chr(0x80 | strlen($bytes)) . $bytes;
}

/** Convierte una clave RSA en formato JWK (n, e) a PEM, que es lo que entiende OpenSSL. */
function jwkAPem(array $jwk): string
{
    $entero = function (string $bytes): string {
        if (ord($bytes[0]) > 0x7f) {
            $bytes = "\0" . $bytes; // los enteros DER llevan signo
        }
        return "\x02" . derLongitud(strlen($bytes)) . $bytes;
    };
    $rsa = $entero(base64UrlDecode($jwk['n'])) . $entero(base64UrlDecode($jwk['e']));
    $rsa = "\x30" . derLongitud(strlen($rsa)) . $rsa;
    $bitString = "\x03" . derLongitud(strlen($rsa) + 1) . "\0" . $rsa;
    $algoritmo = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00"; // rsaEncryption
    $spki = "\x30" . derLongitud(strlen($algoritmo . $bitString)) . $algoritmo . $bitString;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/**
 * Valida el id_token y devuelve sus datos (sub, email, name…).
 * Lanza DomainException si el token no es válido.
 */
function verificarIdToken(string $proveedor, string $jwt, string $nonce): array
{
    if (!isset(PROVEEDORES_OAUTH[$proveedor]) || clientIdOauth($proveedor) === '') {
        throw new DomainException('Proveedor no configurado');
    }
    $partes = explode('.', $jwt);
    if (count($partes) !== 3) {
        throw new DomainException('Token mal formado');
    }
    $cabecera = json_decode(base64UrlDecode($partes[0]), true);
    $datos = json_decode(base64UrlDecode($partes[1]), true);
    $firma = base64UrlDecode($partes[2]);
    if (!is_array($cabecera) || !is_array($datos) || ($cabecera['alg'] ?? '') !== 'RS256' || empty($cabecera['kid'])) {
        throw new DomainException('Token mal formado');
    }

    $claves = clavesJwks($proveedor);
    if (!isset($claves[$cabecera['kid']])) {
        $claves = clavesJwks($proveedor, true); // el proveedor rotó sus claves
    }
    if (!isset($claves[$cabecera['kid']])) {
        throw new DomainException('Clave de firma desconocida');
    }
    $ok = openssl_verify($partes[0] . '.' . $partes[1], $firma, jwkAPem($claves[$cabecera['kid']]), OPENSSL_ALGO_SHA256);
    if ($ok !== 1) {
        throw new DomainException('Firma inválida');
    }

    $ahora = time();
    $aud = (array) ($datos['aud'] ?? []);
    if (!in_array($datos['iss'] ?? '', PROVEEDORES_OAUTH[$proveedor]['emisores'], true)) {
        throw new DomainException('Emisor inválido');
    }
    if (!in_array(clientIdOauth($proveedor), $aud, true)) {
        throw new DomainException('El token es para otra aplicación');
    }
    if (($datos['exp'] ?? 0) < $ahora - 60 || ($datos['iat'] ?? 0) > $ahora + 300) {
        throw new DomainException('Token caducado');
    }
    if ($nonce === '' || !hash_equals($nonce, (string) ($datos['nonce'] ?? ''))) {
        throw new DomainException('Nonce inválido');
    }
    if (empty($datos['sub'])) {
        throw new DomainException('Token sin usuario');
    }
    return $datos;
}

/** GET JSON a Google con un token de acceso. */
function googleJson(string $url, string $token): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
    ]);
    $cuerpo = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($cuerpo === false) {
        throw new RuntimeException('Sin conexión con Google');
    }
    $datos = json_decode((string) $cuerpo, true);
    return $codigo === 200 && is_array($datos) ? $datos : null;
}

/**
 * "Iniciar sesión con Gmail": el navegador abre el selector de cuentas de Google y recibe
 * un token de acceso. Aquí se pregunta a Google si el token es válido y si se emitió para
 * NUESTRO Client ID (si no, alguien podría usar un token de otra página), y se leen el
 * correo y el nombre de la cuenta elegida.
 */
function verificarAccesoGoogle(string $token): array
{
    if ($token === '' || strlen($token) > 4096 || clientIdOauth('google') === '') {
        throw new DomainException('Token vacío o Google sin configurar');
    }
    $info = googleJson('https://oauth2.googleapis.com/tokeninfo?access_token=' . rawurlencode($token), $token);
    if (!$info || ($info['aud'] ?? '') !== clientIdOauth('google') || (int) ($info['expires_in'] ?? 0) <= 0 || empty($info['sub'])) {
        throw new DomainException('El token no es de esta aplicación o caducó');
    }
    $perfil = googleJson('https://openidconnect.googleapis.com/v1/userinfo', $token) ?? [];
    if (($perfil['sub'] ?? $info['sub']) !== $info['sub']) {
        throw new DomainException('Perfil inconsistente');
    }
    return [
        'sub' => $info['sub'],
        'email' => $info['email'] ?? ($perfil['email'] ?? ''),
        'email_verified' => in_array($info['email_verified'] ?? ($perfil['email_verified'] ?? false), [true, 'true'], true),
        'name' => $perfil['name'] ?? '',
    ];
}
