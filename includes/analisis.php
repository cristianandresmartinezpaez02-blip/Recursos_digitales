<?php
/**
 * Motor de análisis: da un puntaje de 0 a 100 a cada criptomoneda a partir de
 * sus datos de mercado y explica por qué es (o no) viable invertir.
 * Los textos salen en el idioma del visitante (ver includes/i18n.php).
 *
 * Es un modelo educativo basado en reglas, NO una recomendación financiera.
 */
require_once __DIR__ . '/i18n.php';

const STABLECOINS = [
    'usdt', 'usdc', 'dai', 'fdusd', 'tusd', 'usde', 'usds', 'pyusd', 'usdd', 'busd',
    'frax', 'usd0', 'rlusd', 'usdtb', 'susds', 'susde', 'gusd', 'lusd', 'eurc', 'usdy',
    'bfusd', 'usd1', 'usdf', 'usdg', 'buidl', 'usyc', 'syrupusdc', 'usdx', 'eurs',
    'eursafo', 'eutbl', 'eurcv', 'eure', 'eurr', 'eurt', 'usdgo', 'ustb', 'gho',
];

function esStablecoin(array $m): bool
{
    if (in_array(strtolower($m['symbol'] ?? ''), STABLECOINS, true)) {
        return true;
    }
    // Heurística: precio ~1 USD y casi sin movimiento en 7 días.
    $precio = (float) ($m['current_price'] ?? 0);
    $cambio24h = abs((float) ($m['price_change_percentage_24h'] ?? 99));
    $cambio7d = abs((float) ($m['price_change_percentage_7d_in_currency'] ?? 99));
    $cambio30d = abs((float) ($m['price_change_percentage_30d_in_currency'] ?? 99));
    if ($precio > 0.97 && $precio < 1.03 && $cambio7d < 0.6) {
        return true;
    }
    // Monedas atadas a otra divisa (euro, etc.): casi no se mueven en un mes.
    return $cambio24h < 0.3 && $cambio7d < 0.5 && $cambio30d < 1.5;
}

/**
 * @return array{puntaje:int, veredicto:string, etiqueta:string, resumen:string, riesgo:string,
 *               pros:string[], contras:string[], factores:array, es_stablecoin:bool}
 */
function analizarMoneda(array $m): array
{
    $nombre = $m['name'] ?? '?';

    if (esStablecoin($m)) {
        return [
            'puntaje'       => 50,
            'veredicto'     => 'neutral',
            'etiqueta'      => t('an.stable.etiqueta'),
            'riesgo'        => t('an.riesgo.bajo'),
            'resumen'       => t('an.stable.resumen', ['nombre' => $nombre]),
            'pros'          => [t('an.stable.pro')],
            'contras'       => [t('an.stable.con1'), t('an.stable.con2')],
            'factores'      => [],
            'es_stablecoin' => true,
        ];
    }

    $pros = [];
    $contras = [];
    $factores = [];
    $sumar = function (string $clave, int $puntos, int $max) use (&$factores) {
        $factores[] = ['nombre' => t($clave), 'puntos' => $puntos, 'max' => $max];
    };

    $rank     = (int) ($m['market_cap_rank'] ?? 999);
    $precio   = (float) ($m['current_price'] ?? 0);
    $mcap     = (float) ($m['market_cap'] ?? 0);
    $volumen  = (float) ($m['total_volume'] ?? 0);
    $c24h     = (float) ($m['price_change_percentage_24h'] ?? 0);
    $c7d      = (float) ($m['price_change_percentage_7d_in_currency'] ?? 0);
    $c30d     = (float) ($m['price_change_percentage_30d_in_currency'] ?? 0);
    $athDist  = (float) ($m['ath_change_percentage'] ?? 0);
    $circ     = (float) ($m['circulating_supply'] ?? 0);
    $maxSup   = $m['max_supply'] !== null ? (float) $m['max_supply'] : null;
    $totalSup = $m['total_supply'] !== null ? (float) $m['total_supply'] : null;
    $rango24h = $precio > 0 ? ((float) $m['high_24h'] - (float) $m['low_24h']) / $precio * 100 : 0;

    // 1) Tamaño y solidez (20).
    if ($rank <= 10) {
        $p = 20;
        $pros[] = t('an.pro.top10', ['rank' => $rank]);
    } elseif ($rank <= 25) {
        $p = 15;
        $pros[] = t('an.pro.top25', ['rank' => $rank]);
    } elseif ($rank <= 50) {
        $p = 10;
    } else {
        $p = 5;
        $contras[] = t('an.con.rank', ['rank' => $rank]);
    }
    $sumar('an.f.tamano', $p, 20);

    // 2) Liquidez: volumen diario frente a capitalización (15).
    $liquidez = $mcap > 0 ? $volumen / $mcap : 0;
    if ($liquidez >= 0.10) {
        $p = 15;
        $pros[] = t('an.pro.liq_alta');
    } elseif ($liquidez >= 0.04) {
        $p = 10;
        $pros[] = t('an.pro.liq_ok');
    } elseif ($liquidez >= 0.015) {
        $p = 5;
    } else {
        $p = 0;
        $contras[] = t('an.con.liq_baja');
    }
    $sumar('an.f.liquidez', $p, 15);

    // 3) Impulso de 24 h (10).
    if ($c24h > 15) {
        $p = 2;
        $contras[] = t('an.con.24h_fuerte', ['v' => num($c24h)]);
    } elseif ($c24h > 2) {
        $p = 10;
        $pros[] = t('an.pro.24h', ['v' => num($c24h)]);
    } elseif ($c24h >= -2) {
        $p = 7;
    } elseif ($c24h >= -8) {
        $p = 3;
        $contras[] = t('an.con.24h_cae', ['v' => num(abs($c24h))]);
    } else {
        $p = 0;
        $contras[] = t('an.con.24h_caida', ['v' => num(abs($c24h))]);
    }
    $sumar('an.f.impulso', $p, 10);

    // 4) Tendencia semanal (15).
    if ($c7d > 30) {
        $p = 6;
        $contras[] = t('an.con.7d_sobre', ['v' => num($c7d)]);
    } elseif ($c7d > 3) {
        $p = 15;
        $pros[] = t('an.pro.7d', ['v' => num($c7d)]);
    } elseif ($c7d >= -3) {
        $p = 9;
    } elseif ($c7d >= -12) {
        $p = 4;
        $contras[] = t('an.con.7d_baja', ['v' => num(abs($c7d))]);
    } else {
        $p = 0;
        $contras[] = t('an.con.7d_caida', ['v' => num(abs($c7d))]);
    }
    $sumar('an.f.t7', $p, 15);

    // 5) Tendencia mensual (10).
    if ($c30d > 0) {
        $p = 10;
        $pros[] = t('an.pro.30d', ['v' => num($c30d)]);
    } elseif ($c30d > -15) {
        $p = 5;
    } else {
        $p = 0;
        $contras[] = t('an.con.30d', ['v' => num(abs($c30d))]);
    }
    $sumar('an.f.t30', $p, 10);

    // 6) Distancia al máximo histórico (10).
    if ($athDist > -10) {
        $p = 6;
        $pros[] = t('an.pro.ath_cerca');
    } elseif ($athDist > -60) {
        $p = 10;
        $pros[] = t('an.pro.ath_margen', ['v' => num(abs($athDist), 0)]);
    } elseif ($athDist > -90) {
        $p = 4;
        $contras[] = t('an.con.ath_lejos', ['v' => num(abs($athDist), 0)]);
    } else {
        $p = 0;
        $contras[] = t('an.con.ath_perdida', ['v' => num(abs($athDist), 0)]);
    }
    $sumar('an.f.ath', $p, 10);

    // 7) Emisión / dilución (10).
    if ($maxSup) {
        $porcentaje = $circ / $maxSup * 100;
        if ($porcentaje >= 70) {
            $p = 10;
            $pros[] = t('an.pro.oferta', ['v' => num($porcentaje, 0)]);
        } else {
            $p = 5;
            $contras[] = t('an.con.oferta', ['v' => num($porcentaje, 0)]);
        }
    } elseif ($totalSup && $circ / $totalSup < 0.5) {
        $p = 0;
        $contras[] = t('an.con.inflacion');
    } else {
        $p = 3;
        $contras[] = t('an.con.sin_max');
    }
    $sumar('an.f.oferta', $p, 10);

    // 8) Volatilidad del día (10): menos volatilidad = menos riesgo.
    if ($rango24h < 3) {
        $p = 10;
        $pros[] = t('an.pro.estable', ['v' => num($rango24h)]);
    } elseif ($rango24h < 7) {
        $p = 6;
    } elseif ($rango24h < 15) {
        $p = 3;
    } else {
        $p = 0;
        $contras[] = t('an.con.volatil', ['v' => num($rango24h)]);
    }
    $sumar('an.f.estabilidad', $p, 10);

    $puntaje = max(0, min(100, array_sum(array_column($factores, 'puntos'))));

    if ($rank <= 20 && $rango24h < 7) {
        $riesgo = t('an.riesgo.moderado');
    } elseif ($rank <= 60 && $rango24h < 12) {
        $riesgo = t('an.riesgo.alto');
    } else {
        $riesgo = t('an.riesgo.muy_alto');
    }

    $veredicto = $puntaje >= 65 ? 'viable' : ($puntaje >= 45 ? 'neutral' : 'no_viable');

    return [
        'puntaje'       => $puntaje,
        'veredicto'     => $veredicto,
        'etiqueta'      => t('an.v.' . $veredicto),
        'riesgo'        => $riesgo,
        'resumen'       => t('an.r.' . $veredicto, ['nombre' => $nombre]),
        'pros'          => $pros,
        'contras'       => $contras,
        'factores'      => $factores,
        'es_stablecoin' => false,
    ];
}
