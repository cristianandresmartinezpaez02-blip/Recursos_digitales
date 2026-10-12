<?php
/**
 * Notificaciones push del usuario conectado.
 *   GET  api/push.php                                   -> {clave, activas: n, umbral}
 *   POST api/push.php?accion=suscribir   {suscripcion, umbral}  (cabecera X-CSRF)
 *   POST api/push.php?accion=cancelar    {endpoint}
 *   POST api/push.php?accion=umbral      {umbral}
 *   POST api/push.php?accion=probar      -> envía un aviso de prueba a todos sus celulares
 */
require_once __DIR__ . '/../includes/push.php';
require_once __DIR__ . '/../includes/i18n.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const UMBRALES_PUSH = [2, 3, 5, 10, 15];

function salir(array $datos, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$u = usuarioActual();
if (!$u) {
    salir(['ok' => false, 'error' => t('cuenta.err_sin_sesion')], 401);
}
$id = (int) $u['id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $st = db()->prepare('SELECT COUNT(*) AS n, MIN(umbral) AS umbral FROM push_suscripciones WHERE usuario_id = ?');
        $st->execute([$id]);
        $fila = $st->fetch();
        salir(['ok' => true, 'clave' => clavesVapid()['publica'], 'activas' => (int) $fila['n'], 'umbral' => (int) ($fila['umbral'] ?? 5)]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfCorrecto()) {
        salir(['ok' => false], 403);
    }
    $datos = json_decode(file_get_contents('php://input'), true) ?: [];
    $umbral = in_array((int) ($datos['umbral'] ?? 0), UMBRALES_PUSH, true) ? (int) $datos['umbral'] : 5;

    switch ($_GET['accion'] ?? '') {
        case 'suscribir':
            $s = $datos['suscripcion'] ?? [];
            $endpoint = (string) ($s['endpoint'] ?? '');
            $p256dh = (string) ($s['keys']['p256dh'] ?? '');
            $auth = (string) ($s['keys']['auth'] ?? '');
            if (!endpointPushValido($endpoint) || strlen($endpoint) > 700 || strlen(desdeB64url($p256dh)) !== 65 || strlen(desdeB64url($auth)) !== 16) {
                salir(['ok' => false, 'error' => t('js.push_error')], 400);
            }
            // Si ese celular ya estaba suscrito (aunque fuera con otra cuenta), pasa a esta cuenta.
            db()->prepare('DELETE FROM push_suscripciones WHERE endpoint = ?')->execute([$endpoint]);
            db()->prepare('INSERT INTO push_suscripciones (usuario_id, endpoint, p256dh, auth, idioma, umbral) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$id, $endpoint, $p256dh, $auth, idioma(), $umbral]);
            db()->prepare('UPDATE push_suscripciones SET umbral = ? WHERE usuario_id = ?')->execute([$umbral, $id]);
            registrarActividad('push_activar', '', $id);
            salir(['ok' => true]);

        case 'cancelar':
            db()->prepare('DELETE FROM push_suscripciones WHERE usuario_id = ? AND endpoint = ?')->execute([$id, (string) ($datos['endpoint'] ?? '')]);
            salir(['ok' => true]);

        case 'umbral':
            db()->prepare('UPDATE push_suscripciones SET umbral = ? WHERE usuario_id = ?')->execute([$umbral, $id]);
            salir(['ok' => true]);

        case 'probar':
            $st = db()->prepare('SELECT id, endpoint, p256dh, auth FROM push_suscripciones WHERE usuario_id = ?');
            $st->execute([$id]);
            $vapid = clavesVapid();
            $enviadas = 0;
            foreach ($st->fetchAll() as $s) {
                $codigo = enviarPush($s, ['titulo' => '🔔 ' . APP_NOMBRE, 'cuerpo' => t('push.prueba'), 'url' => BASE_URL . '/paginas/favoritas.php', 'etiqueta' => 'prueba'], $vapid);
                if ($codigo === 404 || $codigo === 410) {
                    db()->prepare('DELETE FROM push_suscripciones WHERE id = ?')->execute([$s['id']]);
                } elseif ($codigo >= 200 && $codigo < 300) {
                    $enviadas++;
                } else {
                    error_log("push de prueba: el servicio respondió $codigo");
                }
            }
            salir(['ok' => $enviadas > 0, 'enviadas' => $enviadas, 'error' => $enviadas ? null : t('js.push_error')]);
    }
    salir(['ok' => false, 'error' => t('cuenta.err_accion')], 404);
} catch (Throwable $e) {
    error_log('api/push.php: ' . $e->getMessage());
    salir(['ok' => false, 'error' => t('js.push_error')], 500);
}
