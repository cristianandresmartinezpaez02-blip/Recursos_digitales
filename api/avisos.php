<?php
/**
 * GET api/avisos.php — revisa los precios y manda notificaciones push a quien las activó:
 *   - Favoritas: cuando suben o bajan el % elegido (umbral) desde el último aviso (máx. 24 h).
 *   - Alertas de precio: cuando se alcanza el precio de la alerta (una sola vez).
 *
 * La llama cron-job.org cada 10 minutos (README.md); también mantiene despierto el servidor
 * gratuito de Render. Como cualquiera puede abrir esta URL, como mucho revisa una vez cada
 * 4 minutos, y responde al instante: el trabajo se hace después de responder.
 */
define('SIN_SESION', true);
require_once __DIR__ . '/../includes/avisos.php';

const AVISOS_CADA_SEG = 240;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $ultima = (int) ajuste('avisos_ultima_revision', 0);
    if (time() - $ultima < AVISOS_CADA_SEG) {
        echo json_encode(['ok' => true, 'revisado' => false]);
        exit;
    }
    guardarAjuste('avisos_ultima_revision', time());
} catch (Throwable $e) {
    error_log('api/avisos.php: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['ok' => false]);
    exit;
}

echo json_encode(['ok' => true, 'revisado' => true]);
despuesDeResponder('avisos_push', function () {
    try {
        $r = revisarAvisos();
        if ($r['enviados']) {
            error_log("avisos push: {$r['enviados']} enviados a {$r['usuarios']} usuarios");
        }
    } catch (Throwable $e) {
        error_log('avisos push: ' . $e->getMessage());
    }
});
