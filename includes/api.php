<?php
/**
 * Acceso a Binance (precio al segundo y velas) y CoinGecko (datos de mercado, respaldo)
 * con caché en archivos JSON (sin base de datos).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/analisis.php';

const RANGOS_HISTORIAL = [1, 7, 30, 90, 365];

/**
 * GET HTTP. Devuelve [código, cuerpo].
 */
function peticionHttp(string $url, array $cabeceras = [], int $timeout = 20): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_ENCODING       => "", // acepta gzip: descargas hasta 5 veces más pequeñas
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4, // evita esperas por IPv6 en Windows
        CURLOPT_USERAGENT      => 'CriptoAnalisis/2.0',
        CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $cabeceras),
    ]);
    $cuerpo = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch);
    curl_close($ch);

    if ($cuerpo === false) {
        throw new RuntimeException('Error de conexión: ' . $error);
    }
    return [$codigo, $cuerpo];
}

/**
 * Lleva la cuenta de peticiones hechas con la clave en el mes actual.
 */
function registrarUsoApi(string $ruta, int $codigo): void
{
    $archivo = DIR_DATA . '/uso_api.json';
    $fp = fopen($archivo, 'c+');
    if (!$fp) {
        return;
    }
    flock($fp, LOCK_EX);
    $uso = json_decode(stream_get_contents($fp), true) ?: [];
    $mes = date('Y-m');
    $uso['meses'][$mes] = ($uso['meses'][$mes] ?? 0) + 1;
    $uso['ultimas'] = array_slice(
        array_merge([['fecha' => time(), 'ruta' => $ruta, 'codigo' => $codigo]], $uso['ultimas'] ?? []),
        0,
        30
    );
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($uso));
    flock($fp, LOCK_UN);
    fclose($fp);
}

function usoApi(): array
{
    $archivo = DIR_DATA . '/uso_api.json';
    $uso = is_file($archivo) ? json_decode(file_get_contents($archivo), true) : [];
    return [
        'mes'     => $uso['meses'][date('Y-m')] ?? 0,
        'limite'  => LIMITE_MENSUAL_API,
        'ultimas' => $uso['ultimas'] ?? [],
    ];
}

/**
 * Petición a CoinGecko usando la clave Demo. Devuelve el JSON decodificado.
 */
function coingecko(string $ruta, array $params = []): array
{
    $url = COINGECKO_URL . $ruta . ($params ? '?' . http_build_query($params) : '');

    // Sin clave (p. ej. en un servidor nuevo) se usa directamente la API pública.
    [$codigo, $cuerpo] = peticionHttp($url, COINGECKO_API_KEY !== '' ? ['x-cg-demo-api-key: ' . COINGECKO_API_KEY] : []);
    registrarUsoApi($ruta, $codigo);

    // Clave sin cupo o rechazada: probar con la API pública.
    if (in_array($codigo, [401, 403, 429], true) && RESPALDO_SIN_CLAVE) {
        [$codigo, $cuerpo] = peticionHttp($url);
    }

    if ($codigo === 404) {
        throw new DomainException('Criptomoneda no encontrada');
    }
    if ($codigo !== 200) {
        throw new RuntimeException("CoinGecko respondió HTTP $codigo");
    }
    $datos = json_decode($cuerpo, true);
    if (!is_array($datos)) {
        throw new RuntimeException('Respuesta inválida de CoinGecko');
    }
    return $datos;
}

/**
 * Petición a la API pública de datos de mercado de Binance (sin clave).
 */
function binance(string $ruta, array $params = []): array
{
    $url = BINANCE_URL . $ruta . ($params ? '?' . http_build_query($params) : '');
    [$codigo, $cuerpo] = peticionHttp($url, [], 8);
    if ($codigo !== 200) {
        throw new RuntimeException("Binance respondió HTTP $codigo");
    }
    $datos = json_decode($cuerpo, true);
    if (!is_array($datos)) {
        throw new RuntimeException('Respuesta inválida de Binance');
    }
    return $datos;
}

/**
 * Precio actual de todos los pares contra USDT en Binance (caché de 15 s).
 *
 * @return array{precios: array<string, float>, fecha: int}
 */
function preciosBinance(): array
{
    static $memoria = null; // una sola lectura por petición
    if ($memoria !== null) {
        return $memoria;
    }
    if (!USAR_BINANCE) {
        return $memoria = ['precios' => [], 'fecha' => 0];
    }
    try {
        $res = conCache('binance_precios', CACHE_BINANCE_PRECIOS_SEG, function () {
            $precios = [];
            foreach (binance('/ticker/price') as $t) {
                if (substr($t['symbol'], -4) === 'USDT') {
                    $precios[substr($t['symbol'], 0, -4)] = (float) $t['price'];
                }
            }
            return $precios;
        }, CACHE_VIEJA_VIVO_SEG);
    } catch (Throwable $e) {
        return $memoria = ['precios' => [], 'fecha' => 0];
    }
    return $memoria = ['precios' => $res['datos'], 'fecha' => $res['fecha']];
}

/**
 * Par de Binance (p. ej. "BTCUSDT") de una moneda del top 100, o null si no cotiza allí.
 * Se exige que el precio de Binance esté a menos de un 5 % del de CoinGecko para no
 * confundir dos monedas distintas que comparten símbolo.
 */
function parBinance(array $m): ?string
{
    $base = strtoupper($m['symbol'] ?? '');
    $p = preciosBinance()['precios'][$base] ?? null;
    $ref = $m['precio_coingecko'] ?? $m['current_price'] ?? null;
    if ($base === '' || $base === 'USDT' || !$p || !$ref || abs($p / $ref - 1) > 0.05) {
        return null;
    }
    return $base . 'USDT';
}

/**
 * Velas de Binance. Cada vela: [apertura (ms), o, h, l, c, …, cierre (ms), …].
 * La última es la vela en curso: su cierre es el precio de este momento.
 */
function velasBinance(string $par, string $intervalo, int $desdeMs): array
{
    return binance('/klines', ['symbol' => $par, 'interval' => $intervalo, 'startTime' => $desdeMs, 'limit' => 1000]);
}

/**
 * Devuelve datos desde la caché en disco o los pide a la API.
 * Si la API falla sirve la caché vieja para que la página nunca quede vacía.
 * Un dato caducado con menos de $maxViejo segundos se entrega al instante y se renueva
 * después de responder: ninguna visita espera a la API externa (cada conexión cuesta ~1 s).
 *
 * @return array{datos: array, fecha: int, desde_cache: bool}
 */
function conCache(string $clave, int $ttl, callable $obtener, int $maxViejo = CACHE_VIEJA_MAX_SEG): array
{
    if (!is_dir(DIR_CACHE)) {
        mkdir(DIR_CACHE, 0775, true);
    }
    $archivo = DIR_CACHE . '/' . preg_replace('/[^a-z0-9_\-]/i', '_', $clave) . '.json';
    $leer = function () use ($archivo) {
        clearstatcache(true, $archivo);
        return is_file($archivo) ? json_decode(file_get_contents($archivo), true) : null;
    };
    $guardar = function ($datos) use ($archivo) {
        $fecha = time();
        file_put_contents($archivo, json_encode(['fecha' => $fecha, 'datos' => $datos]), LOCK_EX);
        return $fecha;
    };

    $cache = $leer();
    if ($cache && time() - $cache['fecha'] < $ttl) {
        return ['datos' => $cache['datos'], 'fecha' => $cache['fecha'], 'desde_cache' => true];
    }

    // Caducado pero reciente: se entrega YA y se renueva después de responder
    // (la próxima visita recibe el dato nuevo sin haber esperado a la API).
    if ($cache && time() - $cache['fecha'] < $maxViejo) {
        despuesDeResponder($clave, function () use ($clave, $ttl, $obtener, $leer, $guardar) {
            $lock = fopen(DIR_CACHE . '/.lock_' . md5($clave), 'c');
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                fclose($lock); // otra visita ya lo está renovando
                return;
            }
            try {
                $actual = $leer();
                if (!$actual || time() - $actual['fecha'] >= $ttl) {
                    $guardar($obtener());
                }
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        });
        return ['datos' => $cache['datos'], 'fecha' => $cache['fecha'], 'desde_cache' => true];
    }

    // Bloqueo: si llegan varias visitas a la vez, solo una consulta la API.
    $lock = fopen(DIR_CACHE . '/.lock_' . md5($clave), 'c');
    flock($lock, LOCK_EX);
    try {
        $cache = $leer();
        if ($cache && time() - $cache['fecha'] < $ttl) {
            return ['datos' => $cache['datos'], 'fecha' => $cache['fecha'], 'desde_cache' => true];
        }

        try {
            $datos = $obtener();
        } catch (RuntimeException $e) {
            if ($cache) {
                return ['datos' => $cache['datos'], 'fecha' => $cache['fecha'], 'desde_cache' => true];
            }
            throw $e;
        }

        $fecha = $guardar($datos);
        return ['datos' => $datos, 'fecha' => $fecha, 'desde_cache' => false];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/**
 * Top 100 de criptomonedas por capitalización, cada una con su análisis.
 */
function obtenerTop100(): array
{
    $res = conCache('mercado_top100', CACHE_MERCADO_SEG, function () {
        return coingecko('/coins/markets', [
            'vs_currency'             => MONEDA_BASE,
            'order'                   => 'market_cap_desc',
            'per_page'                => 100,
            'page'                    => 1,
            'sparkline'               => 'true',
            'price_change_percentage' => '1h,24h,7d,30d,1y',
        ]);
    });

    // CoinGecko (plan gratuito) actualiza el precio cada 1-2 min; Binance, al segundo.
    $binance = preciosBinance();
    $res['datos'] = array_map(function (array $m) use ($binance) {
        $m['precio_coingecko'] = $m['current_price'];
        $m['fuente'] = 'coingecko';
        $par = $m['current_price'] ? parBinance($m) : null;
        if ($par) {
            $p = $binance['precios'][substr($par, 0, -4)];
            if ($m['price_change_percentage_24h'] !== null) {
                // Recalcula la variación de 24 h con el precio nuevo.
                $ayer = $m['current_price'] / (1 + $m['price_change_percentage_24h'] / 100);
                $m['price_change_percentage_24h'] = ($p / $ayer - 1) * 100;
            }
            $m['current_price'] = $p;
            $m['high_24h'] = max($m['high_24h'] ?? $p, $p);
            $m['low_24h'] = min($m['low_24h'] ?? $p, $p);
            $m['last_updated'] = gmdate('Y-m-d\TH:i:s\Z', $binance['fecha']);
            $m['fuente'] = 'binance';
        }
        $m['analisis'] = analizarMoneda($m);
        // El sparkline de 7 días trae 168 puntos (uno por hora); con 56 basta.
        $spark = $m['sparkline_in_7d']['price'] ?? [];
        $m['sparkline'] = array_values(array_filter($spark, fn($i) => $i % 3 === 0, ARRAY_FILTER_USE_KEY));
        unset($m['sparkline_in_7d']);
        return $m;
    }, $res['datos']);

    return $res;
}

function buscarEnTop100(string $id): ?array
{
    foreach (obtenerTop100()['datos'] as $m) {
        if ($m['id'] === $id) {
            return $m;
        }
    }
    return null;
}

/**
 * Datos globales: capitalización total, volumen y dominancia.
 */
function obtenerGlobal(): ?array
{
    try {
        $d = conCache('global', CACHE_GLOBAL_SEG, fn() => coingecko('/global')['data'])['datos'];
    } catch (Throwable $e) {
        return null;
    }
    $dominancia = $d['market_cap_percentage'] ?? [];
    arsort($dominancia);
    return [
        'capitalizacion' => $d['total_market_cap']['usd'] ?? null,
        'volumen'        => $d['total_volume']['usd'] ?? null,
        'cambio_24h'     => $d['market_cap_change_percentage_24h_usd'] ?? null,
        'monedas'        => $d['active_cryptocurrencies'] ?? null,
        'mercados'       => $d['markets'] ?? null,
        'dominancia'     => array_slice($dominancia, 0, 6, true),
    ];
}

/**
 * Monedas más buscadas en CoinGecko en las últimas 24 h.
 */
function obtenerTendencias(): array
{
    try {
        $d = conCache('tendencias', CACHE_TENDENCIAS_SEG, fn() => coingecko('/search/trending'))['datos'];
    } catch (Throwable $e) {
        return [];
    }
    return array_map(fn($c) => [
        'id'         => $c['item']['id'],
        'nombre'     => $c['item']['name'],
        'simbolo'    => $c['item']['symbol'],
        'imagen'     => $c['item']['small'] ?? $c['item']['thumb'] ?? '',
        'rank'       => $c['item']['market_cap_rank'] ?? null,
        'precio'     => $c['item']['data']['price'] ?? null,
        'cambio_24h' => $c['item']['data']['price_change_percentage_24h']['usd'] ?? null,
        'sparkline'  => $c['item']['data']['sparkline'] ?? null,
    ], array_slice($d['coins'] ?? [], 0, 8));
}

/**
 * Índice de miedo y codicia de los últimos 30 días.
 */
function obtenerMiedoCodicia(): ?array
{
    try {
        $d = conCache('miedo_codicia', CACHE_FNG_SEG, function () {
            [$codigo, $cuerpo] = peticionHttp(FNG_URL . '?limit=30');
            $json = json_decode($cuerpo, true);
            if ($codigo !== 200 || empty($json['data'])) {
                throw new RuntimeException('Índice de miedo y codicia no disponible');
            }
            return $json['data'];
        })['datos'];
    } catch (Throwable $e) {
        return null;
    }
    $claves = [
        'Extreme Fear' => 'fng.miedo_extremo', 'Fear' => 'fng.miedo', 'Neutral' => 'fng.neutral',
        'Greed' => 'fng.codicia', 'Extreme Greed' => 'fng.codicia_extrema',
    ];
    $clasificacion = $d[0]['value_classification'];
    $serie = array_reverse(array_map(fn($x) => ['t' => (int) $x['timestamp'], 'v' => (int) $x['value']], $d));
    return [
        'valor'         => (int) $d[0]['value'],
        'clasificacion' => isset($claves[$clasificacion]) ? t($claves[$clasificacion]) : $clasificacion,
        'ayer'          => isset($d[1]) ? (int) $d[1]['value'] : null,
        'semana'        => isset($d[7]) ? (int) $d[7]['value'] : null,
        'serie'         => $serie,
    ];
}

/**
 * Información detallada (descripción, enlaces, categorías) de una moneda.
 */
function obtenerDetalle(string $id): array
{
    // Una sola petición trae descripción, rendimiento y mercados (caché 24 h).
    $detalle = conCache('detalle_v3_' . $id, CACHE_DETALLE_SEG, function () use ($id) {
        $d = coingecko('/coins/' . rawurlencode($id), [
            'localization'   => 'true',
            'tickers'        => 'true',
            'market_data'    => 'true',
            'community_data' => 'false',
            'developer_data' => 'false',
        ]);
        // Se guarda la descripción en los tres idiomas del sitio.
        $descripciones = [];
        foreach (array_keys(IDIOMAS) as $lang) {
            $descripciones[$lang] = $d['description'][$lang] ?? '';
        }
        $md = $d['market_data'] ?? [];
        $rendimiento = [];
        foreach (['24h', '7d', '14d', '30d', '60d', '200d', '1y'] as $p) {
            $v = $md['price_change_percentage_' . $p] ?? null;
            if ($v !== null && $v !== '') {
                $rendimiento[$p] = (float) $v;
            }
        }
        // Los 12 mercados con más volumen (dónde se compra y vende).
        $tickers = array_filter($d['tickers'] ?? [], fn($t) => empty($t['is_stale']) && empty($t['is_anomaly']));
        usort($tickers, fn($a, $b) => ($b['converted_volume']['usd'] ?? 0) <=> ($a['converted_volume']['usd'] ?? 0));
        $mercados = array_map(fn($t) => [
            'exchange'  => $t['market']['name'] ?? '',
            'par'       => ($t['base'] ?? '') . '/' . ($t['target'] ?? ''),
            'precio'    => $t['converted_last']['usd'] ?? null,
            'volumen'   => $t['converted_volume']['usd'] ?? null,
            'confianza' => $t['trust_score'] ?? null,
            'spread'    => $t['bid_ask_spread_percentage'] ?? null,
            'url'       => $t['trade_url'] ?? null,
        ], array_slice(array_values($tickers), 0, 12));

        return [
            'categorias'  => array_values(array_filter($d['categories'] ?? [])),
            'descripcion' => $descripciones,
            'web'         => $d['links']['homepage'][0] ?? '',
            'whitepaper'  => $d['links']['whitepaper'] ?? '',
            'explorador'  => $d['links']['blockchain_site'][0] ?? '',
            'github'      => $d['links']['repos_url']['github'][0] ?? '',
            'reddit'      => $d['links']['subreddit_url'] ?? '',
            'twitter'     => $d['links']['twitter_screen_name'] ?? '',
            'telegram'    => $d['links']['telegram_channel_identifier'] ?? '',
            'genesis'     => $d['genesis_date'] ?? null,
            'algoritmo'   => $d['hashing_algorithm'] ?? null,
            'sentimiento' => $d['sentiment_votes_up_percentage'] ?? null,
            'watchlist'   => $d['watchlist_portfolio_users'] ?? null,
            'pais'        => $d['country_origin'] ?? '',
            'rendimiento' => $rendimiento,
            'tvl'         => $md['total_value_locked']['usd'] ?? (is_numeric($md['total_value_locked'] ?? null) ? $md['total_value_locked'] : null),
            'mcap_tvl'    => $md['mcap_to_tvl_ratio'] ?? null,
            'mercados'    => $mercados,
            'n_mercados'  => count($d['tickers'] ?? []),
        ];
    })['datos'];

    // Descripción en el idioma del visitante; si no existe, en inglés.
    $textos = $detalle['descripcion'];
    $detalle['descripcion'] = ($textos[idioma()] ?? '') ?: ($textos['en'] ?? '');
    $detalle['descripcion_traducida'] = ($textos[idioma()] ?? '') !== '';
    return $detalle;
}

/**
 * Categorías del mercado (DeFi, memes, IA…) con su capitalización y variación.
 */
function obtenerCategorias(): array
{
    return conCache('categorias', CACHE_CATEGORIAS_SEG, function () {
        $lista = coingecko('/coins/categories', ['order' => 'market_cap_desc']);
        return array_values(array_map(fn($c) => [
            'id'         => $c['id'],
            'nombre'     => $c['name'],
            'cap'        => $c['market_cap'] ?? null,
            'cambio_24h' => $c['market_cap_change_24h'] ?? null,
            'volumen'    => $c['volume_24h'] ?? null,
            'logos'      => array_slice($c['top_3_coins'] ?? [], 0, 3),
        ], array_filter($lista, fn($c) => ($c['market_cap'] ?? 0) > 0)));
    });
}

/**
 * Las 50 monedas más grandes de una categoría (con análisis).
 */
function obtenerMonedasCategoria(string $categoria): array
{
    $res = conCache('categoria_' . $categoria, CACHE_CATEGORIA_SEG, function () use ($categoria) {
        return coingecko('/coins/markets', [
            'vs_currency'             => MONEDA_BASE,
            'category'                => $categoria,
            'order'                   => 'market_cap_desc',
            'per_page'                => 50,
            'page'                    => 1,
            'sparkline'               => 'false',
            'price_change_percentage' => '24h,7d,30d',
        ]);
    });
    $res['datos'] = array_map(function ($m) {
        $m['analisis'] = analizarMoneda($m);
        return $m;
    }, $res['datos']);
    return $res;
}

/**
 * Principales exchanges por volumen y confianza.
 */
function obtenerExchanges(): array
{
    return conCache('exchanges', CACHE_EXCHANGES_SEG, function () {
        return array_map(fn($x) => [
            'id'         => $x['id'],
            'nombre'     => $x['name'],
            'imagen'     => $x['image'] ?? '',
            'pais'       => $x['country'] ?? '',
            'anio'       => $x['year_established'] ?? null,
            'confianza'  => $x['trust_score'] ?? null,
            'rank'       => $x['trust_score_rank'] ?? null,
            'volumen_btc' => $x['trade_volume_24h_btc'] ?? null,
            'url'        => $x['url'] ?? '',
        ], coingecko('/exchanges', ['per_page' => 50, 'page' => 1]));
    });
}

/**
 * Velas OHLC (apertura, máximo, mínimo, cierre). $dias: 1, 7, 30, 90 o 365.
 */
function obtenerOhlc(string $id, int $dias): array
{
    $actual = buscarEnTop100($id);
    $par = $actual ? parBinance($actual) : null;
    if ($par) {
        [$intervalo, $ttl] = BINANCE_VELAS[$dias];
        try {
            return conCache("ohlc_bn_{$id}_{$dias}", $ttl, function () use ($par, $intervalo, $dias) {
                $velas = velasBinance($par, $intervalo, (time() - $dias * 86400) * 1000);
                return array_map(fn($v) => ['t' => (int) $v[0], 'o' => (float) $v[1], 'h' => (float) $v[2], 'l' => (float) $v[3], 'c' => (float) $v[4]], $velas);
            }, CACHE_VIEJA_VIVO_SEG)['datos'];
        } catch (Throwable $e) {
            // Sigue con CoinGecko.
        }
    }

    return conCache("ohlc_v2_{$id}_{$dias}", CACHE_HISTORIAL[$dias], function () use ($id, $dias) {
        $d = coingecko('/coins/' . rawurlencode($id) . '/ohlc', ['vs_currency' => MONEDA_BASE, 'days' => $dias]);
        // CoinGecko marca cada vela con su hora de CIERRE: una vela de 4 h aparecía 4 h
        // "adelantada". Se mueve a su hora de apertura restando la duración de la vela.
        $duracion = count($d) > 1 ? $d[1][0] - $d[0][0] : 0;
        return array_map(fn($v) => ['t' => (int) $v[0] - $duracion, 'o' => $v[1], 'h' => $v[2], 'l' => $v[3], 'c' => $v[4]], $d);
    })['datos'];
}

/**
 * Serie de precios para el gráfico. $dias: 1, 7, 30, 90 o 365.
 * Al final se añade el precio más reciente del top 100 (actualizado cada minuto).
 *
 * @return array<int, array{t: int, p: float}>
 */
function obtenerHistorial(string $id, int $dias): array
{
    $actual = buscarEnTop100($id);
    $par = $actual ? parBinance($actual) : null;
    if ($par) {
        [$intervalo, $ttl] = BINANCE_HISTORIAL[$dias];
        try {
            return conCache("historial_bn_{$id}_{$dias}", $ttl, function () use ($par, $intervalo, $dias) {
                $ahora = time();
                $velas = velasBinance($par, $intervalo, ($ahora - $dias * 86400) * 1000);
                // Cada punto va a la hora en que termina su vela; la vela en curso, a la hora
                // actual (su cierre es el precio de este momento). Nada queda en el futuro.
                return array_map(fn($v) => ['t' => min($ahora, (int) floor(($v[6] + 1) / 1000)), 'p' => (float) $v[4]], $velas);
            }, CACHE_VIEJA_VIVO_SEG)['datos'];
        } catch (Throwable $e) {
            // Sigue con CoinGecko.
        }
    }

    $remoto = conCache("historial_{$id}_{$dias}", CACHE_HISTORIAL[$dias], function () use ($id, $dias) {
        $d = coingecko('/coins/' . rawurlencode($id) . '/market_chart', [
            'vs_currency' => MONEDA_BASE,
            'days'        => $dias,
        ]);
        return array_map(fn($p) => [(int) round($p[0] / 1000), $p[1]], $d['prices'] ?? []);
    });

    $serie = array_map(fn($p) => ['t' => $p[0], 'p' => $p[1]], $remoto['datos']);

    $actual = buscarEnTop100($id);
    $ultimo = end($serie);
    if ($actual && $actual['current_price'] !== null) {
        $t = min(time(), strtotime($actual['last_updated'] ?? 'now') ?: time());
        if (!$ultimo || $t > $ultimo['t']) {
            $serie[] = ['t' => $t, 'p' => (float) $actual['current_price']];
        }
    }
    return $serie;
}

/**
 * Respuesta JSON estándar para los endpoints de /api.
 */
function responderJson($datos, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
