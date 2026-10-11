<?php
/**
 * Acciones del panel de administración (formularios POST).
 * Cada acción exige token CSRF, queda registrada en "actividad" y termina con una
 * redirección (patrón POST → redirect → GET) para no repetirla al recargar.
 */

function volverAlPanel(string $mensaje, string $pestana = 'resumen', string $tipo = 'exito'): void
{
    $_SESSION['flash_admin'] = [$mensaje, $tipo];
    $consulta = array_intersect_key($_GET, array_flip(['q', 'rol', 'estado', 'p']));
    header('Location: index.php' . ($consulta ? '?' . http_build_query($consulta) : '') . '#' . $pestana);
    exit;
}

function usuarioPorId(int $id): ?array
{
    $st = db()->prepare('SELECT id, nombre, email, rol, activo FROM usuarios WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Primer administrador: un usuario con sesión lo reclama con ADMIN_PASSWORD (solo si aún no hay ninguno). */
function reclamarAdministracion(array $yo): void
{
    $hay = (int) db()->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")->fetchColumn();
    if ($hay > 0) {
        volverAlPanel('Ya existe un administrador. Pídele que te dé permisos.', 'resumen', 'alerta');
    }
    if (!hash_equals(ADMIN_PASSWORD, (string) ($_POST['password'] ?? ''))) {
        registrarActividad('admin_reclamo_fallido', '', (int) $yo['id']);
        volverAlPanel('Contraseña de instalación incorrecta.', 'resumen', 'alerta');
    }
    db()->prepare("UPDATE usuarios SET rol = 'admin' WHERE id = ?")->execute([$yo['id']]);
    registrarActividad('admin_rol', 'primer administrador', (int) $yo['id']);
    volverAlPanel('¡Listo! Ahora eres el administrador de la página.');
}

function ejecutarAccionAdmin(array $yo): void
{
    $accion = $_POST['accion'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $objetivo = $id ? usuarioPorId($id) : null;
    $esYo = $objetivo && (int) $objetivo['id'] === (int) $yo['id'];
    $nombre = $objetivo ? "{$objetivo['nombre']} <{$objetivo['email']}>" : '';
    $log = fn(string $detalle) => registrarActividad('admin_' . $accion, $detalle, (int) $yo['id']);

    if (in_array($accion, ['rol', 'estado', 'quitar_rostro', 'reiniciar_progreso', 'eliminar'], true) && !$objetivo) {
        volverAlPanel('El usuario ya no existe.', 'usuarios', 'alerta');
    }

    switch ($accion) {
        case 'rol':
            if ($esYo) volverAlPanel('No puedes quitarte tus propios permisos.', 'usuarios', 'alerta');
            $rol = $_POST['rol'] === 'admin' ? 'admin' : 'usuario';
            db()->prepare('UPDATE usuarios SET rol = ? WHERE id = ?')->execute([$rol, $id]);
            $log("$nombre → $rol");
            volverAlPanel($rol === 'admin' ? "$nombre ahora es administrador." : "$nombre ya no es administrador.", 'usuarios');

        case 'estado':
            if ($esYo) volverAlPanel('No puedes suspender tu propia cuenta.', 'usuarios', 'alerta');
            $activo = (int) ($_POST['activo'] ?? 0) === 1 ? 1 : 0;
            db()->prepare('UPDATE usuarios SET activo = ? WHERE id = ?')->execute([$activo, $id]);
            $log("$nombre → " . ($activo ? 'activo' : 'suspendido'));
            volverAlPanel($activo ? "Cuenta de $nombre reactivada." : "Cuenta de $nombre suspendida: ya no puede iniciar sesión.", 'usuarios');

        case 'quitar_rostro':
            db()->prepare('UPDATE usuarios SET descriptor_facial = NULL, facial_fecha = NULL WHERE id = ?')->execute([$id]);
            $log($nombre);
            volverAlPanel("Se borró el rostro de $nombre.", 'usuarios');

        case 'reiniciar_progreso':
            db()->prepare('DELETE FROM progreso WHERE usuario_id = ?')->execute([$id]);
            $log($nombre);
            volverAlPanel("Se reinició el progreso de los juegos de $nombre.", 'usuarios');

        case 'eliminar':
            if ($esYo) volverAlPanel('No puedes eliminar tu propia cuenta desde aquí.', 'usuarios', 'alerta');
            db()->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
            $log($nombre);
            volverAlPanel("Cuenta de $nombre eliminada.", 'usuarios');

        case 'desbloquear':
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            db()->prepare('DELETE FROM intentos_acceso WHERE email = ?')->execute([$email]);
            $log($email);
            volverAlPanel("Se desbloqueó $email.", 'seguridad');

        case 'limpiar_intentos':
            db()->exec('DELETE FROM intentos_acceso');
            $log('todos');
            volverAlPanel('Se borraron todos los intentos fallidos.', 'seguridad');

        case 'ajustes':
            $validas = [];
            foreach (menuPrincipal() as $grupo) {
                $validas = array_merge($validas, array_column($grupo['items'], 0));
            }
            $secciones = array_values(array_intersect($validas, (array) ($_POST['secciones'] ?? [])));
            $tipo = in_array($_POST['anuncio_tipo'] ?? '', ['info', 'exito', 'alerta'], true) ? $_POST['anuncio_tipo'] : 'info';
            guardarAjuste('secciones_protegidas', $secciones);
            guardarAjuste('anuncio', mb_substr(trim((string) ($_POST['anuncio'] ?? '')), 0, 300));
            guardarAjuste('anuncio_tipo', $tipo);
            guardarAjuste('registro_abierto', !empty($_POST['registro_abierto']));
            guardarAjuste('facial_activo', !empty($_POST['facial_activo']));
            foreach (['google_client_id', 'microsoft_client_id', 'apple_client_id'] as $campo) {
                if (isset($_POST[$campo])) { // un campo deshabilitado (definido en config.local.php) no llega
                    guardarAjuste($campo, preg_replace('/[^A-Za-z0-9._-]/', '', (string) $_POST[$campo]));
                }
            }
            $log(count($secciones) . ' secciones protegidas');
            volverAlPanel('Ajustes guardados.', 'ajustes');

        case 'limpiar_cache':
            $n = 0;
            foreach (glob(DIR_CACHE . '/*.json') as $f) {
                $n += unlink($f) ? 1 : 0;
            }
            $log("$n archivos");
            volverAlPanel("Caché borrada ($n archivos). La próxima visita pedirá datos nuevos a CoinGecko.", 'sistema');

        case 'limpiar_actividad':
            db()->prepare('DELETE FROM actividad WHERE fecha < ?')->execute([fechaHace(30 * 86400)]);
            $log('más de 30 días');
            volverAlPanel('Se borró la actividad de hace más de 30 días.', 'actividad');
    }
    volverAlPanel('Acción desconocida.', 'resumen', 'alerta');
}

/** Descarga la lista de usuarios en CSV (se abre con Excel). */
function exportarUsuarios(): void
{
    $filas = db()->query('SELECT id, nombre, email, rol, activo, descriptor_facial IS NOT NULL AS rostro, creado, ultimo_acceso FROM usuarios ORDER BY id')->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="usuarios_' . date('Y-m-d') . '.csv"');
    $salida = fopen('php://output', 'w');
    fwrite($salida, "\xEF\xBB\xBF"); // BOM para que Excel lea bien las tildes
    fputcsv($salida, ['id', 'nombre', 'email', 'rol', 'activo', 'rostro', 'creado', 'ultimo_acceso'], ';');
    foreach ($filas as $f) {
        // Un nombre como "=HIPERVINCULO(...)" se ejecutaría como fórmula en Excel: se neutraliza.
        fputcsv($salida, array_map(fn($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : $v, $f), ';');
    }
    registrarActividad('admin_exportar', count($filas) . ' usuarios', (int) usuarioActual()['id']);
    exit;
}
