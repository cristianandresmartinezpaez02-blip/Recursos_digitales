<?php
/** robots.txt (ver .htaccess): qué pueden recorrer los buscadores y dónde está el sitemap. */
require_once __DIR__ . '/includes/seo.php';

// Solo la ruta: si BASE_URL trajera el dominio (https://…/carpeta), "Disallow" lo ignoraría Google.
$rutaBase = rtrim((string) parse_url(BASE_URL, PHP_URL_PATH), '/');

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: public, max-age=86400');
echo "User-agent: *\n";
echo 'Disallow: ' . $rutaBase . "/admin/\n";
echo 'Disallow: ' . $rutaBase . "/api/\n";
echo "\nSitemap: " . urlPublica('sitemap.xml') . "\n";
