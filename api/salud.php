<?php
/**
 * GET api/salud.php — health check de Render (render.yaml) y de UptimeRobot / Better Stack.
 * Liviano a propósito: no llama a CoinGecko ni a Binance. Comprueba que PHP responde y que la
 * base de datos contesta; si la base falla responde 503 para que la alerta avise.
 */
define('SIN_SESION', true);
define('PERMITIR_SIN_CLOUDFLARE', true); // el health check de Render llega directo, sin Cloudflare
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    db()->query('SELECT 1')->fetchColumn();
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('api/salud.php: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['ok' => false]);
}
