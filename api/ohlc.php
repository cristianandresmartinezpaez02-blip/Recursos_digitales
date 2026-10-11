<?php
/**
 * GET api/ohlc.php?id=bitcoin&dias=7
 * Velas japonesas (apertura, máximo, mínimo, cierre) para el gráfico de velas.
 */
require_once __DIR__ . '/../includes/api.php';

$id = strtolower(trim($_GET['id'] ?? ''));
$dias = (int) ($_GET['dias'] ?? 1);

if (!preg_match('/^[a-z0-9\-]+$/', $id) || !in_array($dias, RANGOS_HISTORIAL, true)) {
    responderJson(['error' => 'Parámetros inválidos'], 400);
}

try {
    responderJson(['id' => $id, 'dias' => $dias, 'velas' => obtenerOhlc($id, $dias)]);
} catch (Throwable $e) {
    responderJson(['error' => $e->getMessage()], 503);
}
