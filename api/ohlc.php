<?php
/**
 * GET api/ohlc.php?id=bitcoin&dias=7
 * Velas japonesas (apertura, máximo, mínimo, cierre) para el gráfico de velas.
 */
require_once __DIR__ . '/../includes/api.php';

$id = strtolower(trim($_GET['id'] ?? ''));
$dias = (int) ($_GET['dias'] ?? 1);

if (!in_array($dias, RANGOS_HISTORIAL, true)) {
    responderJson(['error' => 'Parámetros inválidos'], 400);
}
if (!monedaPermitida($id)) {
    responderJson(['error' => 'Moneda no disponible'], 404);
}

try {
    responderJson(['id' => $id, 'dias' => $dias, 'velas' => obtenerOhlc($id, $dias)]);
} catch (Throwable $e) {
    fallarApi($e);
}
