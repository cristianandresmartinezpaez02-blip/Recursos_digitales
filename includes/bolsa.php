<?php
/**
 * Bolsa de valores y divisas del mundo.
 *  - Acciones, índices y materias primas: Yahoo Finanzas (sin clave), caché de 2 minutos.
 *  - Tasas de ~160 divisas: open.er-api.com (sin clave, se publican una vez al día).
 *  - Divisas principales en vivo: Yahoo ("COP=X" = pesos colombianos por 1 dólar).
 * Las acciones se devuelven con la misma forma que las criptomonedas (current_price,
 * price_change_percentage_24h, analisis…) para poder operarlas en el juego de trading.
 */
require_once __DIR__ . '/api.php';

/** Acciones que se pueden comprar en el juego: símbolo de Yahoo => sector. */
const ACCIONES = [
    'AAPL' => 'tec', 'MSFT' => 'tec', 'NVDA' => 'tec', 'GOOGL' => 'tec', 'AMZN' => 'consumo', 'META' => 'tec',
    'TSLA' => 'auto', 'NFLX' => 'medios', 'AMD' => 'tec', 'INTC' => 'tec', 'ORCL' => 'tec', 'DIS' => 'medios',
    'JPM' => 'banca', 'V' => 'banca', 'MA' => 'banca', 'BRK-B' => 'banca', 'KO' => 'consumo', 'PEP' => 'consumo',
    'MCD' => 'consumo', 'NKE' => 'consumo', 'WMT' => 'consumo', 'XOM' => 'energia', 'PFE' => 'salud', 'JNJ' => 'salud',
    // Latinoamérica (cotizan en Nueva York)
    'EC' => 'energia', 'CIB' => 'banca', 'MELI' => 'consumo', 'NU' => 'banca', 'PBR' => 'energia', 'AMX' => 'telecom',
];

/** Índices bursátiles y materias primas (solo para ver; no se operan). */
const INDICES = ['^GSPC', '^IXIC', '^DJI', '^FTSE', '^GDAXI', '^N225', '^BVSP', '^MXX'];
const MATERIAS = ['GC=F', 'SI=F', 'CL=F'];

/** Divisas con precio en vivo (el resto usa la tasa diaria). */
const DIVISAS_VIVO = ['EUR', 'GBP', 'JPY', 'CNY', 'CHF', 'CAD', 'AUD', 'INR', 'KRW', 'MXN', 'COP', 'BRL', 'ARS', 'CLP', 'PEN'];

function yahoo(string $ruta, array $params): array
{
    $url = YAHOO_URL . $ruta . '?' . http_build_query($params);
    [$codigo, $cuerpo] = peticionHttp($url, ['User-Agent: Mozilla/5.0 (compatible; CriptoAnalisis/2.0)'], 12);
    if ($codigo !== 200) {
        throw new RuntimeException("Yahoo Finanzas respondió HTTP $codigo");
    }
    $datos = json_decode($cuerpo, true);
    if (!is_array($datos)) {
        throw new RuntimeException('Respuesta inválida de Yahoo Finanzas');
    }
    return $datos;
}

/**
 * Cotización y cierres diarios del último mes de varios símbolos (máx. 20 por petición).
 * @return array<string, array{meta: array, cierres: float[]}>
 */
function yahooSpark(array $simbolos): array
{
    $res = [];
    foreach (array_chunk($simbolos, 20) as $grupo) {
        $d = yahoo('/v7/finance/spark', ['symbols' => implode(',', $grupo), 'range' => '1mo', 'interval' => '1d']);
        foreach ($d['spark']['result'] ?? [] as $r) {
            $x = $r['response'][0] ?? null;
            if (!$x || empty($x['meta']['regularMarketPrice'])) {
                continue;
            }
            $cierres = array_values(array_filter($x['indicators']['quote'][0]['close'] ?? [], 'is_numeric'));
            $res[$r['symbol']] = ['meta' => $x['meta'], 'cierres' => array_map('floatval', $cierres)];
        }
    }
    if (!$res) {
        throw new RuntimeException('Yahoo Finanzas no devolvió cotizaciones');
    }
    return $res;
}

/** Logo simple (iniciales en un círculo de color) como imagen SVG embebida. */
function logoTicker(string $simbolo): string
{
    $tono = hexdec(substr(md5($simbolo), 0, 2)) / 255 * 360;
    $texto = htmlspecialchars(substr(str_replace(['^', '=F', '-'], '', $simbolo), 0, 4), ENT_XML1);
    $tam = strlen($texto) > 3 ? 11 : (strlen($texto) > 2 ? 13 : 16);
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"><circle cx="20" cy="20" r="20" fill="hsl(' . round($tono) . ',55%,42%)"/>'
        . '<text x="20" y="25" font-family="Arial,sans-serif" font-weight="700" font-size="' . $tam . '" fill="#fff" text-anchor="middle">' . $texto . '</text></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/** Análisis educativo de una acción: tendencia del mes, movimiento de hoy y rango de 52 semanas. */
function analizarAccion(string $nombre, float $precio, ?float $cambio, array $cierres, ?float $min52, ?float $max52): array
{
    $puntaje = 50;
    $pros = [];
    $contras = [];
    if (count($cierres) >= 5) {
        $media = array_sum($cierres) / count($cierres);
        $mes = ($precio / $cierres[0] - 1) * 100;
        if ($precio > $media) { $puntaje += 12; $pros[] = t('bolsa.an.sobre_media'); }
        else { $puntaje -= 12; $contras[] = t('bolsa.an.bajo_media'); }
        if ($mes > 3) { $puntaje += 10; $pros[] = t('bolsa.an.mes_sube', ['pct' => num($mes, 1)]); }
        elseif ($mes < -3) { $puntaje -= 10; $contras[] = t('bolsa.an.mes_baja', ['pct' => num(abs($mes), 1)]); }
    }
    if ($cambio !== null) {
        if ($cambio > 4) { $puntaje -= 4; $contras[] = t('bolsa.an.hoy_mucho', ['pct' => num($cambio, 1)]); }
        elseif ($cambio > 0) { $puntaje += 4; }
        elseif ($cambio < -4) { $puntaje -= 8; $contras[] = t('bolsa.an.hoy_cae', ['pct' => num(abs($cambio), 1)]); }
    }
    if ($min52 && $max52 && $max52 > $min52) {
        $pos = ($precio - $min52) / ($max52 - $min52) * 100;
        if ($pos > 90) { $contras[] = t('bolsa.an.cerca_max'); $puntaje -= 3; }
        elseif ($pos < 15) { $contras[] = t('bolsa.an.cerca_min'); $puntaje -= 6; }
        else { $pros[] = t('bolsa.an.rango', ['pct' => round($pos)]); $puntaje += 3; }
    }
    $puntaje = max(5, min(95, $puntaje));
    $veredicto = $puntaje >= 60 ? 'viable' : ($puntaje <= 40 ? 'no_viable' : 'neutral');
    return [
        'puntaje' => $puntaje, 'veredicto' => $veredicto, 'etiqueta' => t('an.v.' . $veredicto),
        'riesgo' => t('an.riesgo.moderado'), 'resumen' => t('bolsa.r.' . $veredicto, ['nombre' => $nombre]),
        'pros' => $pros, 'contras' => $contras, 'factores' => [], 'es_stablecoin' => false,
    ];
}

/** Activo de Yahoo con la forma de una criptomoneda del top 100. */
function activoBolsa(string $simbolo, array $q, int $rango, string $tipo, string $sector = ''): array
{
    $m = $q['meta'];
    $precio = (float) $m['regularMarketPrice'];
    $cambio = isset($m['regularMarketChangePercent']) ? (float) $m['regularMarketChangePercent'] : null;
    $nombre = $m['longName'] ?? $m['shortName'] ?? $simbolo;
    return [
        'id' => 'bolsa-' . strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', trim($simbolo, '^'))),
        'tipo' => $tipo,
        'sector' => $sector,
        'simbolo_yahoo' => $simbolo,
        'symbol' => str_replace('^', '', $simbolo),
        'name' => $nombre,
        'image' => logoTicker($simbolo),
        'url' => BASE_URL . '/paginas/bolsa.php?simbolo=' . rawurlencode($simbolo),
        'market_cap_rank' => $rango,
        'current_price' => $precio,
        'moneda' => $m['currency'] ?? 'USD',
        'price_change_percentage_24h' => $cambio,
        'high_24h' => $m['regularMarketDayHigh'] ?? null,
        'low_24h' => $m['regularMarketDayLow'] ?? null,
        'max_52s' => $m['fiftyTwoWeekHigh'] ?? null,
        'min_52s' => $m['fiftyTwoWeekLow'] ?? null,
        'bolsa' => $m['fullExchangeName'] ?? $m['exchangeName'] ?? '',
        'hora_mercado' => (int) ($m['regularMarketTime'] ?? 0),
        'mercado_abierto' => isset($m['currentTradingPeriod']['regular'])
            && time() >= $m['currentTradingPeriod']['regular']['start'] && time() < $m['currentTradingPeriod']['regular']['end'],
        'sparkline' => $q['cierres'],
        'analisis' => analizarAccion($nombre, $precio, $cambio, $q['cierres'], $m['fiftyTwoWeekLow'] ?? null, $m['fiftyTwoWeekHigh'] ?? null),
    ];
}

/** Acciones, índices y materias primas en vivo. */
function obtenerBolsa(): array
{
    $res = conCache('bolsa_v1', CACHE_BOLSA_SEG, fn() => yahooSpark(array_merge(array_keys(ACCIONES), INDICES, MATERIAS)), CACHE_VIEJA_VIVO_SEG * 5);
    $q = $res['datos'];
    $lista = function (array $simbolos, string $tipo) use ($q) {
        $salida = [];
        foreach ($simbolos as $i => $s) {
            if (isset($q[$s])) {
                $salida[] = activoBolsa($s, $q[$s], $i + 1, $tipo, ACCIONES[$s] ?? '');
            }
        }
        return $salida;
    };
    $acciones = $lista(array_keys(ACCIONES), 'accion');
    // La fecha de la copia: con ella el juego sabe si el precio es reciente (aunque la bolsa esté cerrada).
    foreach ($acciones as &$a) {
        $a['last_updated'] = gmdate('Y-m-d\TH:i:s\Z', $res['fecha']);
    }
    unset($a);
    return [
        'actualizado' => $res['fecha'],
        'acciones' => $acciones,
        'indices' => $lista(INDICES, 'indice'),
        'materias' => $lista(MATERIAS, 'materia'),
    ];
}

/** Serie de precios de un símbolo de Yahoo para el gráfico ({t, p}). */
function obtenerHistorialBolsa(string $simbolo, int $dias): array
{
    $rangos = [1 => ['1d', '5m', 120], 7 => ['5d', '30m', 600], 30 => ['1mo', '1h', 1800], 90 => ['3mo', '1d', 3600], 365 => ['1y', '1d', 7200]];
    [$rango, $intervalo, $ttl] = $rangos[$dias];
    $res = conCache("bolsa_hist_{$simbolo}_{$dias}", $ttl, function () use ($simbolo, $rango, $intervalo) {
        $d = yahoo('/v8/finance/chart/' . rawurlencode($simbolo), ['range' => $rango, 'interval' => $intervalo]);
        $r = $d['chart']['result'][0] ?? null;
        if (!$r) {
            throw new DomainException('Símbolo no encontrado');
        }
        $puntos = [];
        foreach ($r['timestamp'] ?? [] as $i => $t) {
            $p = $r['indicators']['quote'][0]['close'][$i] ?? null;
            if (is_numeric($p)) {
                $puntos[] = ['t' => (int) $t, 'p' => (float) $p];
            }
        }
        return $puntos;
    });
    return $res['datos'];
}

/**
 * Tasas de todas las divisas del mundo (unidades por 1 USD), con las principales en vivo.
 * @return array{actualizado:int, fecha_tasas:int, tasas: array<string,float>, vivo: array}
 */
function obtenerDivisas(): array
{
    $diarias = conCache('divisas_todas', CACHE_DIVISAS_SEG, function () {
        [$codigo, $cuerpo] = peticionHttp(DIVISAS_URL, [], 10);
        $d = json_decode($cuerpo, true);
        if ($codigo !== 200 || ($d['result'] ?? '') !== 'success') {
            throw new RuntimeException('No se pudieron obtener las tasas de cambio');
        }
        return ['fecha' => (int) $d['time_last_update_unix'], 'tasas' => $d['rates']];
    });
    $tasas = $diarias['datos']['tasas'];
    $vivo = [];
    try {
        $simbolos = array_map(fn($c) => $c . '=X', DIVISAS_VIVO);
        $q = conCache('divisas_vivo', CACHE_DIVISAS_VIVO_SEG, fn() => yahooSpark($simbolos), CACHE_VIEJA_VIVO_SEG * 10)['datos'];
        foreach (DIVISAS_VIVO as $c) {
            $x = $q[$c . '=X'] ?? null;
            // Se descarta un dato de Yahoo muy distinto de la tasa oficial (símbolo equivocado).
            if ($x && isset($tasas[$c]) && abs($x['meta']['regularMarketPrice'] / $tasas[$c] - 1) < 0.15) {
                $tasas[$c] = (float) $x['meta']['regularMarketPrice'];
                $vivo[$c] = [
                    'cambio' => $x['meta']['regularMarketChangePercent'] ?? null,
                    'hora' => (int) ($x['meta']['regularMarketTime'] ?? 0),
                    'spark' => $x['cierres'],
                ];
            }
        }
    } catch (Throwable $e) {
        error_log('divisas en vivo: ' . $e->getMessage()); // quedan las tasas diarias
    }
    return [
        'actualizado' => time(),
        'fecha_tasas' => $diarias['datos']['fecha'],
        'tasas' => $tasas,
        'vivo' => $vivo,
    ];
}

/** Unidades de $codigo por 1 USD (1 si no se conoce): la usa la cabecera para mostrar precios. */
function tasaDivisa(string $codigo): ?float
{
    if ($codigo === 'USD') {
        return 1.0;
    }
    try {
        $t = obtenerDivisas()['tasas'][$codigo] ?? null;
        return $t ? (float) $t : null;
    } catch (Throwable $e) {
        return null;
    }
}
