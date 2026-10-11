<?php
/**
 * GET api/extras.php
 * Monedas en tendencia e índice de miedo y codicia (cambian poco: caché larga).
 */
require_once __DIR__ . '/../includes/api.php';

responderJson([
    'tendencias'    => obtenerTendencias(),
    'miedo_codicia' => obtenerMiedoCodicia(),
]);
