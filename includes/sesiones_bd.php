<?php
/**
 * Sesiones guardadas en la base de datos (solo con PostgreSQL / Supabase, ver includes/db.php).
 * En Render el disco se borra cada vez que el servidor se duerme o se publica una versión
 * nueva: con las sesiones en archivos, todos quedaban desconectados. En la base sobreviven.
 */
require_once __DIR__ . '/db.php';

final class SesionesBd implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    /** Una sesión sin actividad durante 7 días se cierra. */
    private const DURACION = 7 * 86400;

    /** Vencimiento de cada sesión leída (para no reescribirla en cada página). */
    private array $vence = [];

    public function open(string $ruta, string $nombre): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $st = db()->prepare('SELECT datos, expira FROM sesiones WHERE id = ? AND expira > ?');
        $st->execute([$id, time()]);
        $fila = $st->fetch();
        $this->vence[$id] = $fila ? (int) $fila['expira'] : 0;
        return $fila ? (string) $fila['datos'] : '';
    }

    public function write(string $id, string $datos): bool
    {
        db()->prepare('INSERT INTO sesiones (id, datos, expira) VALUES (?, ?, ?)
                ON CONFLICT (id) DO UPDATE SET datos = excluded.datos, expira = excluded.expira')
            ->execute([$id, $datos, time() + self::DURACION]);
        return true;
    }

    /** Sesión sin cambios: solo se alarga su vencimiento, y como mucho una vez al día. */
    public function updateTimestamp(string $id, string $datos): bool
    {
        if (($this->vence[$id] ?? 0) - time() < self::DURACION - 86400) {
            db()->prepare('UPDATE sesiones SET expira = ? WHERE id = ?')->execute([time() + self::DURACION, $id]);
        }
        return true;
    }

    public function validateId(string $id): bool
    {
        $st = db()->prepare('SELECT 1 FROM sesiones WHERE id = ? AND expira > ?');
        $st->execute([$id, time()]);
        return (bool) $st->fetchColumn();
    }

    public function destroy(string $id): bool
    {
        db()->prepare('DELETE FROM sesiones WHERE id = ?')->execute([$id]);
        return true;
    }

    public function gc(int $max): int|false
    {
        $st = db()->prepare('DELETE FROM sesiones WHERE expira < ?');
        $st->execute([time()]);
        return $st->rowCount();
    }
}
