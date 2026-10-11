<?php
/**
 * GET api/bolsa.php                          acciones, índices y materias primas en vivo
 * GET api/bolsa.php?historial=AAPL&dias=7    serie de precios (dias: 1, 7, 30, 90 o 365)
 */
require_once __DIR__ . '/../includes/bolsa.php';

try {
    if (isset($_GET['historial'])) {
        $simbolo = strtoupper(trim((string) $_GET['historial']));
        $dias = (int) ($_GET['dias'] ?? 30);
        $permitidos = array_merge(array_keys(ACCIONES), INDICES, MATERIAS, array_map(fn($c) => $c . '=X', DIVISAS_VIVO));
        if (!in_array($simbolo, $permitidos, true)) {
            responderJson(['error' => 'Símbolo no disponible'], 400);
        }
        if (!in_array($dias, RANGOS_HISTORIAL, true)) {
            responderJson(['error' => 'Rango inválido'], 400);
        }
        responderJson(['simbolo' => $simbolo, 'dias' => $dias, 'puntos' => obtenerHistorialBolsa($simbolo, $dias)]);
    }
    responderJson(obtenerBolsa());
} catch (Throwable $e) {
    responderJson(['error' => 'No se pudieron obtener los datos de bolsa: ' . $e->getMessage()], 503);
}
