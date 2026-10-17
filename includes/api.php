<?php
/**
 * Acceso a Binance (precio al segundo y velas) y CoinGecko (datos de mercado, respaldo)
 * con caché en archivos JSON (sin base de datos).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/analisis.php';
require_once __DIR__ . '/db.php'; // copia duradera de la caché (solo con PostgreSQL)

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
 * Copia de la caché en la base de datos (solo con PostgreSQL, es decir, en Render con Supabase).
 * Se actualiza como mucho cada 10 minutos por dato para no cargar la base.
 */
function copiaDuradera(string $clave, string $json): void
{
    if (!esPostgres()) {
        return;
    }
    $marca = DIR_CACHE . '/.copia_' . md5($clave);
    if (is_file($marca) && time() - filemtime($marca) < 600) {
        return;
    }
    try {
        db()->prepare('INSERT INTO cache_datos (clave, contenido) VALUES (?, ?)
                ON CONFLICT (clave) DO UPDATE SET contenido = excluded.contenido')->execute([$clave, $json]);
        @touch($marca);
    } catch (Throwable $e) {
        error_log('copia de la caché en la base: ' . $e->getMessage());
    }
}

function leerCopiaDuradera(string $clave): ?string
{
    if (!esPostgres()) {
        return null;
    }
    try {
        $st = db()->prepare('SELECT contenido FROM cache_datos WHERE clave = ?');
        $st->execute([$clave]);
        $copia = $st->fetchColumn();
        return is_string($copia) ? $copia : null;
    } catch (Throwable $e) {
        error_log('leer copia de la caché: ' . $e->getMessage());
        return null;
    }
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
    $guardar = function ($datos) use ($archivo, $clave) {
        $fecha = time();
        $json = json_encode(['fecha' => $fecha, 'datos' => $datos]);
        file_put_contents($archivo, $json, LOCK_EX);
        copiaDuradera($clave, $json);
        return $fecha;
    };

    // Servidor recién arrancado (Render borra los archivos al reiniciar): se parte de la última
    // copia guardada en la base, así la página tiene datos aunque CoinGecko esté limitando.
    $cache = $leer();
    if (!$cache && ($copia = leerCopiaDuradera($clave)) !== null) {
        @file_put_contents($archivo, $copia, LOCK_EX);
        $cache = json_decode($copia, true);
    }
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
        try {
            return coingecko('/coins/markets', [
                'vs_currency'             => MONEDA_BASE,
                'order'                   => 'market_cap_desc',
                'per_page'                => 100,
                'page'                    => 1,
                'sparkline'               => 'true',
                'price_change_percentage' => '1h,24h,7d,30d,1y',
            ]);
        } catch (RuntimeException $e) {
            // CoinGecko suele limitar a los servidores en la nube (comparten IP con muchas páginas).
            error_log('CoinGecko falló (' . $e->getMessage() . '): se usa CoinPaprika + Binance');
            return mercadoRespaldo();
        }
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

/** Ids de CoinPaprika distintos de los de CoinGecko (los enlaces, favoritas y partidas usan los de CoinGecko). */
const IDS_PAPRIKA_A_COINGECKO = [
    'xrp-xrp' => 'ripple', 'bnb-binance-coin' => 'binancecoin', 'avax-avalanche' => 'avalanche-2',
    'toncoin-the-open-network' => 'the-open-network', 'near-near-protocol' => 'near', 'steth-lido-staked-ether' => 'staked-ether',
    'wsteth-wrapped-liquid-staked-ether-20' => 'wrapped-steth', 'leo-leo-token' => 'leo-token', 'hype-hyperliquid' => 'hyperliquid',
    'cbbtc-coinbase-wrapped-btc' => 'coinbase-wrapped-btc', 'weeth-wrapped-eeth' => 'wrapped-eeth', 'usde-ethena-usde' => 'ethena-usde',
    'cc-canton-network' => 'canton-network', 'matic-polygon' => 'matic-network', 'pol-polygon-ecosystem-token' => 'polygon-ecosystem-token',
    'wbt-whitebit' => 'whitebit', 'hbar-hedera-hashgraph' => 'hedera-hashgraph', 'icp-internet-computer' => 'internet-computer',
    'okb-okb' => 'okb', 'cro-cryptocom-chain' => 'crypto-com-chain', 'atom-cosmos' => 'cosmos', 'etc-ethereum-classic' => 'ethereum-classic',
];

/**
 * Top 100 de respaldo cuando CoinGecko no responde: la lista y los datos de mercado vienen de
 * CoinPaprika (gratis, sin clave) y el gráfico de 7 días de las velas de Binance. Devuelve el
 * mismo formato que /coins/markets de CoinGecko, así el resto de la página no nota la diferencia.
 */
function mercadoRespaldo(): array
{
    // CoinPaprika renueva sus datos cada pocos minutos: 5 min de caché ahorran su cupo gratuito.
    $tickers = conCache('respaldo_paprika', 300, function () {
        [$codigo, $cuerpo] = peticionHttp('https://api.coinpaprika.com/v1/tickers?quotes=USD', [], 25);
        $todas = $codigo === 200 ? json_decode($cuerpo, true) : null;
        if (!is_array($todas)) {
            throw new RuntimeException("CoinPaprika respondió HTTP $codigo");
        }
        $todas = array_values(array_filter($todas, fn($t) => !empty($t['rank']) && isset($t['quotes']['USD']['price'])));
        usort($todas, fn($a, $b) => $a['rank'] <=> $b['rank']);
        return array_slice($todas, 0, 100);
    })['datos'];

    // Velas de 1 h de los últimos 7 días (168), pedidas todas a la vez; se guardan 30 min.
    try {
        $velas = conCache('respaldo_sparklines', 1800, function () use ($tickers) {
            $binance = preciosBinance()['precios'];
            $multi = curl_multi_init();
            $handles = [];
            foreach ($tickers as $t) {
                $s = strtoupper($t['symbol']);
                $p = $binance[$s] ?? null;
                $ref = (float) $t['quotes']['USD']['price'];
                if ($s === 'USDT' || !$p || !$ref || abs($p / $ref - 1) > 0.05) {
                    continue; // no cotiza en Binance (o es otra moneda con el mismo símbolo)
                }
                $ch = curl_init(BINANCE_URL . '/klines?' . http_build_query(['symbol' => $s . 'USDT', 'interval' => '1h', 'limit' => 168]));
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_ENCODING => '', CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]);
                curl_multi_add_handle($multi, $ch);
                $handles[$s] = $ch;
            }
            do {
                $estado = curl_multi_exec($multi, $activos);
                if ($activos) {
                    curl_multi_select($multi, 1);
                }
            } while ($activos && $estado === CURLM_OK);
            $series = [];
            foreach ($handles as $s => $ch) {
                $datos = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200 ? json_decode((string) curl_multi_getcontent($ch), true) : null;
                if (is_array($datos) && $datos) {
                    $series[$s] = array_map(fn($v) => (float) $v[4], $datos); // precio de cierre de cada hora
                }
                curl_multi_remove_handle($multi, $ch);
                curl_close($ch);
            }
            curl_multi_close($multi);
            if (!$series) {
                throw new RuntimeException('Binance no devolvió velas');
            }
            return $series;
        })['datos'];
    } catch (Throwable $e) {
        $velas = []; // sin gráfico de 7 días, pero con precios
    }

    $porcentaje = fn($v) => is_numeric($v) && (float) $v != 0.0 ? (float) $v : null; // CoinPaprika usa 0 cuando no sabe
    return array_map(function (array $t) use ($velas, $porcentaje) {
        $q = $t['quotes']['USD'];
        $precio = (float) $q['price'];
        $simbolo = strtolower($t['symbol']);
        $serie = $velas[strtoupper($t['symbol'])] ?? [];
        $dia = array_slice($serie, -24);
        $cambio24 = (float) ($q['percent_change_24h'] ?? 0);
        return [
            'id'                               => IDS_PAPRIKA_A_COINGECKO[$t['id']] ?? substr($t['id'], strpos($t['id'], '-') + 1),
            'symbol'                           => $simbolo,
            'name'                             => $t['name'],
            'image'                            => 'https://static.coinpaprika.com/coin/' . rawurlencode($t['id']) . '/logo.png',
            'current_price'                    => $precio,
            'market_cap'                       => (float) $q['market_cap'],
            'market_cap_rank'                  => (int) $t['rank'],
            'fully_diluted_valuation'          => !empty($t['max_supply']) ? $t['max_supply'] * $precio : null,
            'total_volume'                     => (float) $q['volume_24h'],
            'high_24h'                         => $dia ? max(max($dia), $precio) : null,
            'low_24h'                          => $dia ? min(min($dia), $precio) : null,
            'price_change_24h'                 => $precio - $precio / (1 + $cambio24 / 100),
            'price_change_percentage_24h'      => $cambio24,
            'market_cap_change_24h'            => null,
            'market_cap_change_percentage_24h' => $porcentaje($q['market_cap_change_24h'] ?? null),
            'circulating_supply'               => $precio > 0 ? $q['market_cap'] / $precio : null,
            'total_supply'                     => $t['total_supply'] ?: null,
            'max_supply'                       => $t['max_supply'] ?: null,
            'ath'                              => $q['ath_price'] ?? null,
            'ath_change_percentage'            => $q['percent_from_price_ath'] ?? null,
            'ath_date'                         => $q['ath_date'] ?? null,
            'atl'                              => null,
            'atl_change_percentage'            => null,
            'atl_date'                         => null,
            'last_updated'                     => $t['last_updated'],
            'sparkline_in_7d'                  => ['price' => $serie],
            'price_change_percentage_1h_in_currency'  => $porcentaje($q['percent_change_1h'] ?? null),
            'price_change_percentage_24h_in_currency' => $cambio24,
            'price_change_percentage_7d_in_currency'  => $porcentaje($q['percent_change_7d'] ?? null),
            'price_change_percentage_30d_in_currency' => $porcentaje($q['percent_change_30d'] ?? null),
            'price_change_percentage_1y_in_currency'  => $porcentaje($q['percent_change_1y'] ?? null),
            'fuente_lista'                     => 'coinpaprika',
        ];
    }, $tickers);
}

/**
 * ¿El id es una moneda del top 100? Los endpoints solo piden a CoinGecko/Binance datos de esas
 * monedas: con ids inventados cada petición se saltaba la caché y gastaba el cupo de la API
 * (30/min, 10.000/mes), y bastaba un script para dejar a todo el sitio sin datos.
 */
function monedaPermitida(string $id): bool
{
    if (strlen($id) > 100 || !preg_match('/^[a-z0-9\-]+$/', $id)) {
        return false;
    }
    try {
        return buscarEnTop100($id) !== null;
    } catch (Throwable $e) {
        return false;
    }
}

function categoriaPermitida(string $id): bool
{
    if (strlen($id) > 100 || !preg_match('/^[a-z0-9\-]+$/', $id)) {
        return false;
    }
    try {
        return in_array($id, array_column(obtenerCategorias()['datos'], 'id'), true);
    } catch (Throwable $e) {
        return false;
    }
}

/** Error de un endpoint: el detalle va al log del servidor, nunca al visitante. */
function fallarApi(Throwable $e, string $mensaje = 'Datos no disponibles por ahora. Intenta en un minuto.'): void
{
    error_log(basename($_SERVER['SCRIPT_NAME'] ?? 'api') . ': ' . $e->getMessage());
    responderJson(['error' => $mensaje], 503);
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
