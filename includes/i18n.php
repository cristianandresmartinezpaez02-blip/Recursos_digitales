<?php
/**
 * Traducciones (español, inglés, francés).
 * El idioma se guarda en la cookie "idioma"; también se puede forzar con ?lang=en.
 * Los textos viven en includes/idiomas/{es,en,fr}.php. Si falta una clave en
 * inglés o francés se usa el texto en español.
 */
require_once __DIR__ . '/../config/config.php';

const IDIOMAS = ['es' => 'Español', 'en' => 'English', 'fr' => 'Français'];
const LOCALES = ['es' => 'es-CO', 'en' => 'en-US', 'fr' => 'fr-FR'];

function idioma(): string
{
    static $actual = null;
    if ($actual !== null) {
        return $actual;
    }
    $pedido = $_GET['lang'] ?? null;
    if ($pedido && isset(IDIOMAS[$pedido])) {
        if (!headers_sent()) {
            setcookie('idioma', $pedido, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
        }
        return $actual = $pedido;
    }
    $cookie = $_COOKIE['idioma'] ?? 'es';
    return $actual = isset(IDIOMAS[$cookie]) ? $cookie : 'es';
}

function diccionario(): array
{
    static $dic = null;
    if ($dic === null) {
        $es = require __DIR__ . '/idiomas/es.php';
        $dic = idioma() === 'es' ? $es : array_merge($es, require __DIR__ . '/idiomas/' . idioma() . '.php');
    }
    return $dic;
}

/**
 * Traduce una clave. Los parámetros reemplazan {nombre} dentro del texto.
 */
function t(string $clave, array $params = []): string
{
    $texto = diccionario()[$clave] ?? $clave;
    if (!$params) {
        return $texto;
    }
    $reemplazos = [];
    foreach ($params as $k => $v) {
        $reemplazos['{' . $k . '}'] = (string) $v;
    }
    return strtr($texto, $reemplazos);
}

/**
 * Número con los separadores del idioma actual.
 */
function num(float $valor, int $decimales = 1): string
{
    switch (idioma()) {
        case 'en':
            return number_format($valor, $decimales, '.', ',');
        case 'fr':
            return number_format($valor, $decimales, ',', ' ');
        default:
            return number_format($valor, $decimales, ',', '.');
    }
}
