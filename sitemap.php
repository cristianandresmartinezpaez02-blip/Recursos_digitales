<?php
/** sitemap.xml (ver .htaccess): lista de páginas públicas para Google y otros buscadores. */
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/menu.php';

$paginas = ['' => '1.0'];
foreach (menuPrincipal() as $grupo) {
    foreach ($grupo['items'] as $item) {
        $paginas[$item[1]] = '0.8';
    }
}

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=86400');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($paginas as $ruta => $prioridad) {
    $archivo = __DIR__ . '/' . ($ruta === '' ? 'index.php' : $ruta);
    echo '  <url><loc>' . htmlspecialchars(urlPublica($ruta), ENT_XML1, 'UTF-8') . '</loc>'
        . (is_file($archivo) ? '<lastmod>' . date('Y-m-d', filemtime($archivo)) . '</lastmod>' : '')
        . '<priority>' . $prioridad . "</priority></url>\n";
}
echo "</urlset>\n";
