<?php
/**
 * GET api/historial.php?id=bitcoin&dias=1
 * Serie de precios para el gráfico (dias: 1, 7, 30, 90 o 365).
 */
require_once __DIR__ . '/../includes/api.php';

$id = strtolower(trim($_GET['id'] ?? ''));
$dias = (int) ($_GET['dias'] ?? 1);

if (!preg_match('/^[a-z0-9\-]+$/', $id)) {
    responderJson(['error' => 'Id de moneda inválido'], 400);
}
if (!in_array($dias, RANGOS_HISTORIAL, true)) {
    responderJson(['error' => 'Rango inválido'], 400);
}

try {
    responderJson(['id' => $id, 'dias' => $dias, 'puntos' => obtenerHistorial($id, $dias)]);
} catch (Throwable $e) {
    responderJson(['error' => $e->getMessage()], 503);
}
