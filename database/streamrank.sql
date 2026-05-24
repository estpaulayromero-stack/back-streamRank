-- ============================================================
--  STREAMRANK — BASE DE DATOS COMPLETA MARIA DB (XAMPP)
-- ============================================================
-- Importar en phpMyAdmin
-- o ejecutar:
--
-- mysql -u root -p < streamrank.sql
-- ============================================================


-- ============================================================
-- CREAR BASE DE DATOS
-- ============================================================

CREATE DATABASE IF NOT EXISTS streamrank
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE streamrank;


-- ============================================================
-- TABLA: USERS
-- ============================================================

CREATE TABLE IF NOT EXISTS users (

    id INT UNSIGNED NOT NULL AUTO_INCREMENT,

    email VARCHAR(180) NOT NULL,

    password VARCHAR(255) NOT NULL,

    username VARCHAR(80) DEFAULT NULL,

    foto_url TEXT DEFAULT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uq_users_email (email)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLA: LISTAS
-- ============================================================

CREATE TABLE IF NOT EXISTS listas (

    id INT UNSIGNED NOT NULL AUTO_INCREMENT,

    user_id INT UNSIGNED NOT NULL,

    titulo VARCHAR(255) NOT NULL,

    tipo ENUM('pelicula','serie') NOT NULL,

    genero VARCHAR(100) DEFAULT NULL,

    plataforma VARCHAR(100) DEFAULT NULL,

    imagen_url TEXT DEFAULT NULL,

    rating DECIMAL(3,1) DEFAULT NULL,

    fecha_agregado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uq_user_titulo (user_id, titulo),

    CONSTRAINT fk_listas_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- ÍNDICES LISTAS
-- ============================================================

CREATE INDEX idx_listas_user_id
ON listas(user_id);

CREATE INDEX idx_listas_tipo
ON listas(tipo);

CREATE INDEX idx_listas_plataforma
ON listas(plataforma);


-- ============================================================
-- TABLA: HISTORIAL
-- ============================================================

CREATE TABLE IF NOT EXISTS historial (

    id INT UNSIGNED NOT NULL AUTO_INCREMENT,

    user_id INT UNSIGNED NOT NULL,

    titulo VARCHAR(255) NOT NULL,

    tipo ENUM('pelicula','serie') NOT NULL,

    genero VARCHAR(100) DEFAULT NULL,

    plataforma VARCHAR(100) DEFAULT NULL,

    imagen_url TEXT DEFAULT NULL,

    rating DECIMAL(3,1) DEFAULT NULL,

    mi_calificacion TINYINT DEFAULT NULL,

    reaccion ENUM('encanto','neutro') DEFAULT 'neutro',

    notas TEXT DEFAULT NULL,

    fecha_visto DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    CONSTRAINT fk_historial_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    INDEX idx_historial_user_id (user_id),

    INDEX idx_historial_fecha (fecha_visto)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLA: TOPS PERSONALES
-- ============================================================

CREATE TABLE IF NOT EXISTS tops_personales (

    id INT UNSIGNED NOT NULL AUTO_INCREMENT,

    user_id INT UNSIGNED NOT NULL,

    nombre VARCHAR(255) NOT NULL,

    descripcion TEXT DEFAULT NULL,

    fecha_creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    fecha_modificado TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    CONSTRAINT fk_top_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    INDEX idx_top_user_id (user_id)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLA: TOPS ITEMS
-- ============================================================
-- ============================================================
-- TABLA: TOPS ITEMS
-- ============================================================

CREATE TABLE IF NOT EXISTS tops_items (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    top_id         INT UNSIGNED NOT NULL,  
    titulo         VARCHAR(255) NOT NULL,
    imagen_url     TEXT,
    posicion       INT NOT NULL,
    
    FOREIGN KEY (top_id) REFERENCES tops_personales(id) ON DELETE CASCADE,
    INDEX idx_top_posicion (top_id, posicion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
