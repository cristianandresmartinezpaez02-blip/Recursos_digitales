-- Esquema de cuentas de CriptoAnálisis (MySQL / MariaDB).
-- includes/db.php lo ejecuta solo la primera vez dentro de la base DB_NOMBRE.
-- Para importarlo a mano en phpMyAdmin (XAMPP o el hosting): abre tu base y ejecútalo dentro.

CREATE TABLE IF NOT EXISTS usuarios (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(60)  NOT NULL,
    email           VARCHAR(190) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    -- 128 números (JSON) que genera face-api.js; NULL si no registró su rostro.
    descriptor_facial TEXT NULL,
    facial_fecha    DATETIME NULL,
    creado          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso   DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Progreso de los juegos, favoritas y alertas (lo que antes solo vivía en localStorage).
CREATE TABLE IF NOT EXISTS progreso (
    usuario_id  INT UNSIGNED NOT NULL,
    clave       VARCHAR(40)  NOT NULL,
    valor       MEDIUMTEXT   NOT NULL,
    actualizado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, clave),
    CONSTRAINT fk_progreso_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Intentos fallidos de inicio de sesión (para bloquear ataques de fuerza bruta).
CREATE TABLE IF NOT EXISTS intentos_acceso (
    id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email  VARCHAR(190) NOT NULL,
    ip     VARCHAR(45)  NOT NULL,
    tipo   VARCHAR(10)  NOT NULL,
    fecha  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_fecha (email, fecha),
    INDEX idx_ip_fecha (ip, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- v2: roles, cuentas suspendidas, ajustes editables desde el panel y registro de actividad.
ALTER TABLE usuarios ADD COLUMN rol VARCHAR(10) NOT NULL DEFAULT 'usuario' AFTER password_hash;
ALTER TABLE usuarios ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER rol;

CREATE TABLE IF NOT EXISTS ajustes (
    clave  VARCHAR(40) PRIMARY KEY,
    valor  TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS actividad (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT UNSIGNED NULL,
    accion      VARCHAR(40)  NOT NULL,
    detalle     VARCHAR(255) NOT NULL DEFAULT '',
    ip          VARCHAR(45)  NOT NULL DEFAULT '',
    fecha       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fecha (fecha),
    INDEX idx_accion_fecha (accion, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- v3: inicio de sesión con Google y Apple, y cómo se registró cada usuario.
-- (Sin "IF NOT EXISTS" en ALTER: así también funciona en MySQL 8; includes/db.php
-- ignora el error "la columna ya existe" si se ejecuta dos veces.)
ALTER TABLE usuarios MODIFY password_hash VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE usuarios ADD COLUMN metodo_registro VARCHAR(10) NOT NULL DEFAULT 'correo' AFTER activo;
ALTER TABLE usuarios ADD COLUMN google_sub VARCHAR(64) NULL AFTER metodo_registro;
ALTER TABLE usuarios ADD COLUMN apple_sub VARCHAR(64) NULL AFTER google_sub;
ALTER TABLE usuarios ADD UNIQUE INDEX idx_google_sub (google_sub);
ALTER TABLE usuarios ADD UNIQUE INDEX idx_apple_sub (apple_sub);
UPDATE usuarios SET metodo_registro = 'facial' WHERE descriptor_facial IS NOT NULL AND metodo_registro = 'correo';

-- v4: inicio de sesión con Hotmail / Outlook (cuenta Microsoft).
ALTER TABLE usuarios ADD COLUMN microsoft_sub VARCHAR(64) NULL AFTER apple_sub;
ALTER TABLE usuarios ADD UNIQUE INDEX idx_microsoft_sub (microsoft_sub);

-- v5: notificaciones push al celular (includes/push.php y api/avisos.php).
CREATE TABLE IF NOT EXISTS push_suscripciones (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT UNSIGNED NOT NULL,
    endpoint    VARCHAR(700) NOT NULL,
    p256dh      VARCHAR(120) NOT NULL,
    auth        VARCHAR(40)  NOT NULL,
    idioma      VARCHAR(5)   NOT NULL DEFAULT 'es',
    umbral      INT NOT NULL DEFAULT 5,
    creado      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_endpoint (endpoint),
    INDEX idx_push_usuario (usuario_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_avisos (
    usuario_id  INT UNSIGNED NOT NULL,
    clave       VARCHAR(80) NOT NULL,
    precio_ref  DOUBLE NOT NULL,
    fecha       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, clave),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- v6: "Cerrar sesión en los demás dispositivos" (includes/sesion.php).
ALTER TABLE usuarios ADD COLUMN sesion_version INT UNSIGNED NOT NULL DEFAULT 0;
