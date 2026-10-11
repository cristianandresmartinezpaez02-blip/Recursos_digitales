-- Esquema de cuentas de CriptoAnálisis para SQLite (un solo archivo: data/basedatos.sqlite).
-- includes/db.php lo ejecuta solo la primera vez. Es el mismo modelo que sql/esquema.sql (MySQL).
-- Las fechas por defecto usan NOW(), una función que includes/db.php registra con la hora de config.php.

CREATE TABLE IF NOT EXISTS usuarios (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre            TEXT NOT NULL,
    email             TEXT NOT NULL UNIQUE,
    password_hash     TEXT NOT NULL DEFAULT '',
    rol               TEXT NOT NULL DEFAULT 'usuario',
    activo            INTEGER NOT NULL DEFAULT 1,
    metodo_registro   TEXT NOT NULL DEFAULT 'correo',
    google_sub        TEXT NULL UNIQUE,
    apple_sub         TEXT NULL UNIQUE,
    descriptor_facial TEXT NULL,
    facial_fecha      TEXT NULL,
    creado            TEXT NOT NULL DEFAULT (NOW()),
    ultimo_acceso     TEXT NULL
);

CREATE TABLE IF NOT EXISTS progreso (
    usuario_id  INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    clave       TEXT NOT NULL,
    valor       TEXT NOT NULL,
    actualizado TEXT NOT NULL DEFAULT (NOW()),
    PRIMARY KEY (usuario_id, clave)
);

CREATE TABLE IF NOT EXISTS intentos_acceso (
    id     INTEGER PRIMARY KEY AUTOINCREMENT,
    email  TEXT NOT NULL,
    ip     TEXT NOT NULL,
    tipo   TEXT NOT NULL,
    fecha  TEXT NOT NULL DEFAULT (NOW())
);
CREATE INDEX IF NOT EXISTS idx_intentos_email_fecha ON intentos_acceso (email, fecha);
CREATE INDEX IF NOT EXISTS idx_intentos_ip_fecha ON intentos_acceso (ip, fecha);

CREATE TABLE IF NOT EXISTS ajustes (
    clave  TEXT PRIMARY KEY,
    valor  TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS actividad (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id  INTEGER NULL,
    accion      TEXT NOT NULL,
    detalle     TEXT NOT NULL DEFAULT '',
    ip          TEXT NOT NULL DEFAULT '',
    fecha       TEXT NOT NULL DEFAULT (NOW())
);
CREATE INDEX IF NOT EXISTS idx_actividad_fecha ON actividad (fecha);
CREATE INDEX IF NOT EXISTS idx_actividad_accion_fecha ON actividad (accion, fecha);

-- v2: inicio de sesión con Hotmail / Outlook (cuenta Microsoft).
ALTER TABLE usuarios ADD COLUMN microsoft_sub TEXT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS idx_microsoft_sub ON usuarios (microsoft_sub);
