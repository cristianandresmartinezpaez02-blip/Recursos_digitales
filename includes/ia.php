<?php
/**
 * Coach IA con Claude (opcional). El Coach IA de los juegos funciona siempre en el navegador
 * (assets/js/coach.js); esto solo añade el botón "Explícamelo con IA (Claude)".
 * Se activa cuando:
 *   1. existe la clave ANTHROPIC_API_KEY (variable de entorno del servidor o
 *      define('ANTHROPIC_API_KEY', '...') en config/config.local.php), y
 *   2. está instalado el SDK oficial de Anthropic (carpeta vendor/, con "composer install";
 *      el Dockerfile lo hace solo). Requiere PHP 8.1 o superior.
 */
require_once __DIR__ . '/../config/config.php';

function claveAnthropic(): string
{
    return defined('ANTHROPIC_API_KEY') ? (string) ANTHROPIC_API_KEY : (string) getenv('ANTHROPIC_API_KEY');
}

function iaClaudeActiva(): bool
{
    return claveAnthropic() !== ''
        && PHP_VERSION_ID >= 80100
        && is_file(__DIR__ . '/../vendor/autoload.php');
}
