-- ============================================================
--  schema.sql — Estructura de Base de Datos
--  Sistema de Adopción de Perros
--
--  Ejecutar en MySQL/MariaDB antes de iniciar la aplicación.
--  Compatible con Laragon y XAMPP.
-- ============================================================

-- Crear base de datos si no existe
CREATE DATABASE IF NOT EXISTS adopcion_perros
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE adopcion_perros;

-- ─── Tabla: usuarios ──────────────────────────────────────────────────────────
-- Almacena las cuentas de los usuarios del sistema.
-- En producción añadir: verificación de email, 2FA, etc.
CREATE TABLE IF NOT EXISTS usuarios (
    id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    nombre     VARCHAR(100)     NOT NULL,
    email      VARCHAR(150)     NOT NULL UNIQUE,
    password   VARCHAR(255)     NOT NULL,  -- bcrypt hash
    rol        ENUM('usuario','admin') NOT NULL DEFAULT 'usuario',
    activo     TINYINT(1)       NOT NULL DEFAULT 1,
    creado_en  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Tabla: perros ────────────────────────────────────────────────────────────
-- Catálogo de perros disponibles para adopción.
CREATE TABLE IF NOT EXISTS perros (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(100)     NOT NULL,
    raza        VARCHAR(100)     NOT NULL DEFAULT 'Mestizo',
    edad_meses  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    descripcion TEXT             NOT NULL,
    imagen_url  VARCHAR(500)     NOT NULL DEFAULT '',
    estado      ENUM('disponible','en_proceso','adoptado') NOT NULL DEFAULT 'disponible',
    creado_en   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Tabla: solicitudes ───────────────────────────────────────────────────────
-- Registra cada solicitud de adopción.
-- Una solicitud activa bloquea el perro para otros usuarios.
CREATE TABLE IF NOT EXISTS solicitudes (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    usuario_id  INT UNSIGNED     NOT NULL,
    perro_id    INT UNSIGNED     NOT NULL,
    estado      ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
    mensaje     TEXT,
    creado_en   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- Un usuario no puede tener dos solicitudes activas para el mismo perro
    UNIQUE KEY uq_solicitud_activa (usuario_id, perro_id, estado),
    FOREIGN KEY fk_solicitud_usuario (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY fk_solicitud_perro (perro_id)
        REFERENCES perros(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_perro_estado (perro_id, estado),
    INDEX idx_usuario_estado (usuario_id, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Datos de ejemplo ─────────────────────────────────────────────────────────

-- Usuario de prueba (password: 'test1234' — hash bcrypt)
INSERT INTO usuarios (nombre, email, password, rol) VALUES
('Admin Sistema',  'admin@adopcion.com',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uHFE05/O2', 'admin'),
('María González', 'maria@ejemplo.com',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uHFE05/O2', 'usuario'),
('Carlos Ruiz',    'carlos@ejemplo.com',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uHFE05/O2', 'usuario');

-- Catálogo de perros de ejemplo
INSERT INTO perros (nombre, raza, edad_meses, descripcion, imagen_url, estado) VALUES
('Buddy',   'Labrador Dorado',    18, 'Buddy es un perro juguetón y cariñoso que ama correr al aire libre. Es ideal para familias con niños y muy fácil de entrenar.', 'https://images.unsplash.com/photo-1552053831-71594a27632d?w=400&q=80', 'disponible'),
('Luna',    'Border Collie',       9, 'Luna es inteligente y activa. Necesita ejercicio diario y estimulación mental. Perfect para personas activas.', 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=400&q=80', 'disponible'),
('Max',     'Bulldog Francés',    36, 'Max es tranquilo y adaptable. Ideal para apartamentos. Le encanta dormir y recibir mimos. Muy bueno con niños.', 'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?w=400&q=80', 'disponible'),
('Coco',    'Mestizo',            24, 'Coco llegó de la calle y ahora busca un hogar permanente. Es leal, agradecido y aprende rápido. Ya está vacunado.', 'https://images.unsplash.com/photo-1543466835-00a7907e9de1?w=400&q=80', 'disponible'),
('Thor',    'Husky Siberiano',    14, 'Thor es enérgico y necesita espacio. Impresionante pelaje y ojos azules. Ideal para casas con jardín.', 'https://images.unsplash.com/photo-1605568427561-40dd23c2acea?w=400&q=80', 'disponible'),
('Mia',     'Poodle Miniatura',   60, 'Mia es una perrita elegante, hipoalergénica y muy inteligente. Perfecta para personas con alergias.', 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=400&q=80', 'disponible'),
('Rocky',   'Pastor Alemán',      30, 'Rocky ya fue adoptado por una familia maravillosa.', 'https://images.unsplash.com/photo-1568572933382-74d440642117?w=400&q=80', 'adoptado'),
('Nala',    'Golden Retriever',   12, 'Nala tiene una solicitud en proceso de revisión.', 'https://images.unsplash.com/photo-1601979031925-424e53b6caaa?w=400&q=80', 'en_proceso');
