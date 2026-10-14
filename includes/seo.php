<?php
/**
 * Datos para buscadores (Google, Bing…): direcciones completas con el dominio real,
 * usadas en robots.txt, sitemap.xml y las etiquetas <link rel="canonical"> y Open Graph.
 */
require_once __DIR__ . '/../config/config.php';

/** Dirección completa (https://dominio/base/ruta) de una página del proyecto. */
function urlPublica(string $ruta = ''): string
{
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host = preg_replace('/[^a-z0-9.:\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https' : 'http') . '://' . $host . BASE_URL . '/' . ltrim($ruta, '/');
}

/** Dirección canónica de la página actual: sin «?lang=…», que solo cambia el idioma. */
function urlCanonica(): string
{
    $ruta = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $ruta = substr($ruta, strlen(BASE_URL));
    $ruta = preg_replace('#(^|/)index\.php$#', '$1', $ruta);
    $parametros = $_GET;
    unset($parametros['lang']);
    ksort($parametros);
    return urlPublica($ruta) . ($parametros ? '?' . http_build_query($parametros) : '');
}
