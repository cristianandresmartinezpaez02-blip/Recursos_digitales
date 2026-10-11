<?php
/**
 * GET api/categoria.php?id=decentralized-finance-defi
 * Las 50 monedas más grandes de una categoría, con análisis.
 */
require_once __DIR__ . '/../includes/api.php';

$id = strtolower(trim($_GET['id'] ?? ''));
if (!preg_match('/^[a-z0-9\-]+$/', $id)) {
    responderJson(['error' => 'Categoría inválida'], 400);
}

try {
    $res = obtenerMonedasCategoria($id);
    responderJson(['actualizado' => $res['fecha'], 'monedas' => $res['datos']]);
} catch (Throwable $e) {
    responderJson(['error' => $e->getMessage()], 503);
}
