<?php
/**
 * Menú principal: grupos con sus páginas [clave, ruta, icono, título, descripción].
 * Lo usan la cabecera y el panel de administración (para elegir qué secciones exigen sesión).
 */
function menuPrincipal(): array
{
    return [
        ['grupo' => 'nav.mercado', 'icono' => '📊', 'items' => [
            ['mercado', 'paginas/mercado.php', '🏆', 'nav.top100', 'nav.top100_d'],
            ['bolsa', 'paginas/bolsa.php', '📈', 'nav.bolsa', 'nav.bolsa_d'],
            ['movimientos', 'paginas/movimientos.php', '🚀', 'nav.movimientos', 'nav.movimientos_d'],
            ['mapa', 'paginas/mapa.php', '🗺️', 'nav.mapa', 'nav.mapa_d'],
            ['categorias', 'paginas/categorias.php', '🗂️', 'nav.categorias', 'nav.categorias_d'],
            ['maximos', 'paginas/maximos.php', '⛰️', 'nav.maximos', 'nav.maximos_d'],
            ['exchanges', 'paginas/exchanges.php', '🏦', 'nav.exchanges', 'nav.exchanges_d'],
            ['tendencias', 'paginas/tendencias.php', '🔥', 'nav.tendencias', 'nav.tendencias_d'],
        ]],
        ['grupo' => 'nav.herramientas', 'icono' => '🧰', 'items' => [
            ['escaner', 'paginas/escaner.php', '🔎', 'nav.escaner', 'nav.escaner_d'],
            ['diario', 'paginas/diario.php', '📓', 'nav.diario', 'nav.diario_d'],
            ['calculadoras', 'paginas/calculadoras.php', '➗', 'nav.calculadoras', 'nav.calculadoras_d'],
            ['comparar', 'paginas/comparar.php', '⚖️', 'nav.comparar', 'nav.comparar_d'],
            ['simulador', 'paginas/simulador.php', '🧮', 'nav.simulador', 'nav.simulador_d'],
            ['convertidor', 'paginas/convertidor.php', '🔁', 'nav.convertidor', 'nav.convertidor_d'],
            ['favoritas', 'paginas/favoritas.php', '★', 'nav.favoritas', 'nav.favoritas_d'],
            ['alertas', 'paginas/alertas.php', '🔔', 'nav.alertas', 'nav.alertas_d'],
        ]],
        ['grupo' => 'nav.juegos', 'icono' => '🎮', 'items' => [
            ['juegos', 'paginas/juegos.php', '🕹️', 'nav.todos_juegos', 'nav.todos_juegos_d'],
            ['trading', 'paginas/trading.php', '💹', 'nav.trading', 'nav.trading_d'],
            ['reto', 'paginas/reto.php', '⏩', 'nav.reto', 'nav.reto_d'],
            ['subebaja', 'paginas/sube-baja.php', '🎯', 'nav.subebaja', 'nav.subebaja_d'],
            ['ranking', 'paginas/ranking.php', '🏅', 'nav.ranking', 'nav.ranking_d'],
            ['estrategias', 'paginas/estrategias.php', '🧠', 'nav.estrategias', 'nav.estrategias_d'],
        ]],
    ];
}
