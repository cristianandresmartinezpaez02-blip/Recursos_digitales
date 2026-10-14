<?php
/** robots.txt (ver .htaccess): qué pueden recorrer los buscadores y dónde está el sitemap. */
require_once __DIR__ . '/includes/seo.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: public, max-age=86400');
echo "User-agent: *\n";
echo 'Disallow: ' . BASE_URL . "/admin/\n";
echo 'Disallow: ' . BASE_URL . "/api/\n";
echo "\nSitemap: " . urlPublica('sitemap.xml') . "\n";
