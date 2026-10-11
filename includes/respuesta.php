<?php
/**
 * Respuesta rápida para todas las páginas y endpoints:
 *  - Comprime con gzip (el HTML y el JSON pesan ~5 veces menos).
 *  - Envía la respuesta completa al navegador y DESPUÉS ejecuta las tareas pendientes
 *    (p. ej. renovar datos caducados de CoinGecko), así nadie espera a la API externa.
 */

$GLOBALS['tareas_pendientes'] = [];

/** Programa una tarea para cuando el navegador ya haya recibido la respuesta. */
function despuesDeResponder(string $clave, callable $tarea): void
{
    $GLOBALS['tareas_pendientes'][$clave] = $tarea;
}

if (PHP_SAPI !== 'cli') {
    ob_start();
    register_shutdown_function(function () {
        $cuerpo = '';
        while (ob_get_level() > 0) {
            $cuerpo = ob_get_clean() . $cuerpo;
        }
        $tareas = $GLOBALS['tareas_pendientes'];

        if (!headers_sent()) {
            $yaComprimido = (bool) array_filter(headers_list(), fn($h) => stripos($h, 'Content-Encoding:') === 0);
            if (!$yaComprimido && strlen($cuerpo) > 1024 && !ini_get('zlib.output_compression')
                && function_exists('gzencode') && stripos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
                $cuerpo = gzencode($cuerpo, 5);
                header('Content-Encoding: gzip');
                header('Vary: Accept-Encoding');
            }
            // Con la longitud exacta el navegador da la página por terminada aunque PHP siga trabajando.
            header('Content-Length: ' . strlen($cuerpo));
            if ($tareas) {
                header('Connection: close');
            }
        }
        echo $cuerpo;
        flush();
        if (!$tareas) {
            return;
        }
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close(); // no bloquear la siguiente página del mismo visitante
        }
        ignore_user_abort(true);
        @set_time_limit(60);
        foreach ($tareas as $tarea) {
            try {
                $tarea();
            } catch (Throwable $e) {
                error_log('tarea en segundo plano: ' . $e->getMessage());
            }
        }
    });
}
