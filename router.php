<?php
/**
 * Enrutador para el servidor integrado de PHP (iniciar.bat): php -S localhost:8000 router.php
 * Ese servidor no lee los archivos .htaccess, así que aquí se bloquean las carpetas privadas
 * (la base de datos data/basedatos.sqlite, la configuración, etc.). En Apache no se usa.
 */
$ruta = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

if (preg_match('#^/(config|data|includes|sql|publicar|quizpy|php)(/|$)#i', $ruta)
    || preg_match('#/\.|\.(sql|sqlite|md|txt|bat|py|zip|log)$#i', $ruta)) {
    http_response_code(403);
    exit('403 · Acceso denegado');
}

// Carpeta sin archivo (p. ej. "/"): su index.php.
$archivo = __DIR__ . $ruta;
if (is_dir($archivo) && is_file(rtrim($archivo, '/') . '/index.php')) {
    $_SERVER['SCRIPT_NAME'] = rtrim($ruta, '/') . '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = rtrim($archivo, '/') . '/index.php';
    chdir(dirname($_SERVER['SCRIPT_FILENAME']));
    require $_SERVER['SCRIPT_FILENAME'];
    return true;
}
return false; // el resto (php, css, js, imágenes) lo sirve el servidor integrado tal cual
