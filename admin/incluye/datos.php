<?php
/**
 * Consultas del panel de administración.
 */

const POR_PAGINA_ADMIN = 20;

function estadisticasAdmin(): array
{
    $pdo = db();
    $uno = fn(string $sql) => (int) $pdo->query($sql)->fetchColumn();
    $hace = fn(int $dias) => $pdo->quote(fechaHace($dias * 86400)); // fecha de hace N días (SQLite y MySQL)
    return [
        'total' => $uno('SELECT COUNT(*) FROM usuarios'),
        'nuevos7' => $uno('SELECT COUNT(*) FROM usuarios WHERE creado > ' . $hace(7)),
        'rostro' => $uno('SELECT COUNT(*) FROM usuarios WHERE descriptor_facial IS NOT NULL'),
        'activos24' => $uno('SELECT COUNT(*) FROM usuarios WHERE ultimo_acceso > ' . $hace(1)),
        'suspendidos' => $uno('SELECT COUNT(*) FROM usuarios WHERE activo = 0'),
        'admins' => $uno("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'"),
        'fallos24' => $uno('SELECT COUNT(*) FROM intentos_acceso WHERE fecha > ' . $hace(1)),
        'login_clave' => $uno("SELECT COUNT(*) FROM actividad WHERE accion = 'login' AND detalle = 'clave' AND fecha > " . $hace(30)),
        'login_facial' => $uno("SELECT COUNT(*) FROM actividad WHERE accion = 'login' AND detalle = 'facial' AND fecha > " . $hace(30)),
    ];
}

/** [fecha Y-m-d => cantidad] de los últimos $dias días (incluye días en cero). */
function seriePorDia(string $sql, int $dias): array
{
    $serie = [];
    for ($i = $dias - 1; $i >= 0; $i--) {
        $serie[date('Y-m-d', strtotime("-$i day"))] = 0;
    }
    foreach (db()->query($sql)->fetchAll() as $f) {
        if (isset($serie[$f['dia']])) {
            $serie[$f['dia']] = (int) $f['n'];
        }
    }
    return $serie;
}

function registrosPorDia(): array
{
    return seriePorDia('SELECT DATE(creado) AS dia, COUNT(*) AS n FROM usuarios WHERE creado > ' . db()->quote(fechaHace(30 * 86400)) . ' GROUP BY dia', 30);
}

function accesosPorDia(): array
{
    return seriePorDia("SELECT DATE(fecha) AS dia, COUNT(*) AS n FROM actividad WHERE accion = 'login' AND fecha > " . db()->quote(fechaHace(30 * 86400)) . ' GROUP BY dia', 30);
}

/** Usuarios filtrados y paginados: [filas, total]. */
function listaUsuarios(string $q, string $rol, string $estado, int $pagina): array
{
    $donde = [];
    $params = [];
    if ($q !== '') {
        $donde[] = '(nombre ' . comoTexto() . ' ? OR email ' . comoTexto() . ' ?)';
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    if (in_array($rol, ['admin', 'usuario'], true)) {
        $donde[] = 'rol = ?';
        $params[] = $rol;
    }
    if ($estado === 'activos') $donde[] = 'activo = 1';
    if ($estado === 'suspendidos') $donde[] = 'activo = 0';
    if ($estado === 'rostro') $donde[] = 'descriptor_facial IS NOT NULL';
    $where = $donde ? 'WHERE ' . implode(' AND ', $donde) : '';

    $st = db()->prepare("SELECT COUNT(*) FROM usuarios $where");
    $st->execute($params);
    $total = (int) $st->fetchColumn();

    $desde = max(0, ($pagina - 1) * POR_PAGINA_ADMIN);
    $st = db()->prepare("SELECT id, nombre, email, rol, activo, metodo_registro, descriptor_facial IS NOT NULL AS rostro, creado, ultimo_acceso,
            (SELECT COUNT(*) FROM progreso p WHERE p.usuario_id = usuarios.id) AS datos_guardados
        FROM usuarios $where ORDER BY creado DESC LIMIT " . POR_PAGINA_ADMIN . " OFFSET $desde");
    $st->execute($params);
    return [$st->fetchAll(), $total];
}

/** Correos con intentos fallidos recientes (y si están bloqueados ahora). */
function intentosRecientes(): array
{
    $st = db()->prepare('SELECT email, COUNT(*) AS intentos, MAX(fecha) AS ultimo, ' . unirDistintos('tipo') . ' AS tipos, ' . unirDistintos('ip') . ' AS ips,
            SUM(CASE WHEN fecha > ? THEN 1 ELSE 0 END) AS recientes
        FROM intentos_acceso WHERE fecha > ? GROUP BY email ORDER BY ultimo DESC LIMIT 50');
    $st->execute([fechaHace(LOGIN_VENTANA_MIN * 60), fechaHace(86400)]);
    return $st->fetchAll();
}

function actividadReciente(int $limite = 80): array
{
    return db()->query("SELECT a.*, u.nombre, u.email FROM actividad a LEFT JOIN usuarios u ON u.id = a.usuario_id
        ORDER BY a.id DESC LIMIT $limite")->fetchAll();
}

/** Mejores jugadores según el progreso guardado en las cuentas. */
function clasificacion(): array
{
    $filas = db()->query("SELECT p.clave, p.valor, u.nombre FROM progreso p JOIN usuarios u ON u.id = p.usuario_id
        WHERE p.clave IN ('reto_records', 'subebaja_record', 'subebaja_stats', 'minitrading_v1') AND u.activo = 1")->fetchAll();
    $reto = $subebaja = $trading = [];
    foreach ($filas as $f) {
        $v = json_decode($f['valor'], true);
        if ($f['clave'] === 'reto_records' && !empty($v[0]['ganancia'])) {
            $reto[] = ['nombre' => $f['nombre'], 'valor' => (float) $v[0]['ganancia'], 'extra' => str_repeat('⭐', (int) ($v[0]['estrellas'] ?? 0))];
        } elseif ($f['clave'] === 'subebaja_record' && (int) $v > 0) {
            $subebaja[] = ['nombre' => $f['nombre'], 'valor' => (int) $v, 'extra' => ''];
        } elseif ($f['clave'] === 'minitrading_v1' && is_array($v)) {
            $historial = $v['patrimonio'] ?? [];
            $ultimo = $historial ? end($historial) : null;
            if ($ultimo) {
                $trading[] = ['nombre' => $f['nombre'], 'valor' => ((float) $ultimo['v'] / JUEGO_SALDO_INICIAL - 1) * 100,
                    'extra' => count($v['operaciones'] ?? []) . ' ops'];
            }
        }
    }
    $ordenar = function (array $lista): array {
        usort($lista, fn($a, $b) => $b['valor'] <=> $a['valor']);
        return array_slice($lista, 0, 10);
    };
    return ['reto' => $ordenar($reto), 'subebaja' => $ordenar($subebaja), 'trading' => $ordenar($trading)];
}

function hace(?string $fecha): string
{
    if (!$fecha) return 'nunca';
    $s = time() - strtotime($fecha);
    if ($s < 60) return 'hace un momento';
    if ($s < 3600) return 'hace ' . floor($s / 60) . ' min';
    if ($s < 86400) return 'hace ' . floor($s / 3600) . ' h';
    if ($s < 86400 * 30) return 'hace ' . floor($s / 86400) . ' d';
    return date('d/m/Y', strtotime($fecha));
}

/** Nombre legible de cada tipo de actividad. */
const NOMBRES_ACTIVIDAD = [
    'registro' => ['✨', 'Nueva cuenta'], 'login' => ['🔑', 'Inicio de sesión'], 'logout' => ['🚪', 'Cierre de sesión'],
    'login_suspendida' => ['⛔', 'Intento de cuenta suspendida'], 'rostro' => ['🙂', 'Rostro'],
    'admin_rol' => ['🛡️', 'Cambio de rol'], 'admin_estado' => ['⏯️', 'Cambio de estado'], 'admin_quitar_rostro' => ['🙂', 'Rostro borrado (admin)'],
    'admin_reiniciar_progreso' => ['↺', 'Progreso reiniciado'], 'admin_eliminar' => ['🗑', 'Cuenta eliminada'],
    'admin_desbloquear' => ['🔓', 'Correo desbloqueado'], 'admin_limpiar_intentos' => ['🧹', 'Intentos borrados'],
    'admin_ajustes' => ['⚙️', 'Ajustes cambiados'], 'admin_limpiar_cache' => ['🗂️', 'Caché borrada'],
    'admin_limpiar_actividad' => ['🧹', 'Actividad antigua borrada'], 'admin_exportar' => ['📥', 'Exportación CSV'],
    'admin_reclamo_fallido' => ['⚠️', 'Intento fallido de reclamar admin'],
];
