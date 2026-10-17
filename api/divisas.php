<?php
/**
 * GET api/divisas.php
 * Tasas de todas las divisas del mundo (unidades por 1 USD); las principales, en vivo.
 */
require_once __DIR__ . '/../includes/bolsa.php';

try {
    responderJson(obtenerDivisas());
} catch (Throwable $e) {
    fallarApi($e);
}
