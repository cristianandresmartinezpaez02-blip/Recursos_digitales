-- Esquema de cuentas de CriptoAnálisis para PostgreSQL (p. ej. Supabase gratis).
-- includes/db.php lo ejecuta solo cuando existe DATABASE_URL. Es el mismo modelo que
-- sql/esquema_sqlite.sql; todo lleva IF NOT EXISTS porque puede ejecutarse más de una vez.

CREATE TABLE IF NOT EXISTS usuarios (
    id                SERIAL PRIMARY KEY,
    nombre            TEXT NOT NULL,
    email             TEXT NOT NULL UNIQUE,
    password_hash     TEXT NOT NULL DEFAULT '',
    rol               TEXT NOT NULL DEFAULT 'usuario',
    activo            INTEGER NOT NULL DEFAULT 1,
    metodo_registro   TEXT NOT NULL DEFAULT 'correo',
    google_sub        TEXT NULL UNIQUE,
    apple_sub         TEXT NULL UNIQUE,
    microsoft_sub     TEXT NULL UNIQUE,
    descriptor_facial TEXT NULL,
    facial_fecha      TIMESTAMP(0) NULL,
    creado            TIMESTAMP(0) NOT NULL DEFAULT LOCALTIMESTAMP(0),
    ultimo_acceso     TIMESTAMP(0) NULL
);

CREATE TABLE IF NOT EXISTS progreso (
    usuario_id  INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    clave       TEXT NOT NULL,
    valor       TEXT NOT NULL,
    actualizado TIMESTAMP(0) NOT NULL DEFAULT LOCALTIMESTAMP(0),
    PRIMARY KEY (usuario_id, clave)
);

CREATE TABLE IF NOT EXISTS intentos_acceso (
    id     SERIAL PRIMARY KEY,
    email  TEXT NOT NULL,
    ip     TEXT NOT NULL,
    tipo   TEXT NOT NULL,
    fecha  TIMESTAMP(0) NOT NULL DEFAULT LOCALTIMESTAMP(0)
);
CREATE INDEX IF NOT EXISTS idx_intentos_email_fecha ON intentos_acceso (email, fecha);
CREATE INDEX IF NOT EXISTS idx_intentos_ip_fecha ON intentos_acceso (ip, fecha);

CREATE TABLE IF NOT EXISTS ajustes (
    clave  TEXT PRIMARY KEY,
    valor  TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS actividad (
    id          SERIAL PRIMARY KEY,
    usuario_id  INTEGER NULL,
    accion      TEXT NOT NULL,
    detalle     TEXT NOT NULL DEFAULT '',
    ip          TEXT NOT NULL DEFAULT '',
    fecha       TIMESTAMP(0) NOT NULL DEFAULT LOCALTIMESTAMP(0)
);
CREATE INDEX IF NOT EXISTS idx_actividad_fecha ON actividad (fecha);
CREATE INDEX IF NOT EXISTS idx_actividad_accion_fecha ON actividad (accion, fecha);

-- v2: sesiones de usuario en la base (en Render el disco se borra al reiniciar el servidor).
CREATE TABLE IF NOT EXISTS sesiones (
    id      TEXT PRIMARY KEY,
    datos   TEXT NOT NULL DEFAULT '',
    expira  BIGINT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_sesiones_expira ON sesiones (expira);

-- v3: última copia buena de los datos de mercado (Render borra la caché en archivos al reiniciar).
CREATE TABLE IF NOT EXISTS cache_datos (
    clave      TEXT PRIMARY KEY,
    contenido  TEXT NOT NULL
);
