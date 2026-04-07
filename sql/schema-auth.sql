-- Espace Mission — Auth MySQL
-- À exécuter sur O2switch (phpMyAdmin ou SSH)
-- Date : 7 avril 2026

-- Table utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    role ENUM('admin', 'dirigeant', 'equipe', 'externe') NOT NULL DEFAULT 'equipe',
    mission_slug VARCHAR(100),                    -- slug de la mission associée (NULL = toutes)
    reset_token VARCHAR(64) DEFAULT NULL,
    reset_expires DATETIME DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table liens de partage (BPI/externes sans compte)
CREATE TABLE IF NOT EXISTS share_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) NOT NULL UNIQUE,
    mission_slug VARCHAR(100) NOT NULL,
    label VARCHAR(100),                           -- ex: "Guillaume Rambaud — BPI"
    role ENUM('dirigeant', 'equipe', 'externe') NOT NULL DEFAULT 'externe',
    expires_at DATETIME DEFAULT NULL,             -- NULL = pas d'expiration
    views INT DEFAULT 0,
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed : compte admin Serge (mot de passe à changer au premier login)
-- Le hash ci-dessous correspond à 'changeme' — À REMPLACER après premier login
INSERT INTO users (email, password_hash, nom, role, mission_slug) VALUES
('serge@lapmedigitale.fr', '$2y$10$placeholder_hash_replace_me', 'Serge Fornier', 'admin', NULL);
