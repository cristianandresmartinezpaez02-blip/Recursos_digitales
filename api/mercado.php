<?php
/**
 * GET api/mercado.php
 * Top 100 con análisis, mejores/peores 25 (por variación en 24 h) y datos globales.
 */
require_once __DIR__ . '/../includes/api.php';

try {
    $res = obtenerTop100();
} catch (Throwable $e) {
    fallarApi($e, 'No se pudieron obtener los precios.');
}

$monedas = $res['datos'];
$conCambio = array_values(array_filter($monedas, fn($m) => $m['price_change_percentage_24h'] !== null));
usort($conCambio, fn($a, $b) => $b['price_change_percentage_24h'] <=> $a['price_change_percentage_24h']);

responderJson([
    'actualizado' => max($res['fecha'], preciosBinance()['fecha']),
    'monedas'     => $monedas,
    'mejores'     => array_column(array_slice($conCambio, 0, 25), 'id'),
    'peores'      => array_column(array_slice(array_reverse($conCambio), 0, 25), 'id'),
    'global'      => obtenerGlobal(),
]);
