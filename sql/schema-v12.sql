-- Espace Mission — V1.2 : Messages, Documents, Actions
-- À exécuter sur O2switch (phpMyAdmin) après schema-auth.sql
-- Date : 7 avril 2026

-- ─── MESSAGES ───
-- Fil de discussion simple par mission (client ↔ consultant)
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mission_slug VARCHAR(100) NOT NULL,
    author_id INT NOT NULL,              -- FK users.id (ou share_link simulé)
    author_name VARCHAR(100) NOT NULL,   -- dénormalisé pour affichage rapide
    author_role ENUM('admin', 'dirigeant', 'equipe', 'externe') NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mission (mission_slug),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── DOCUMENTS ───
-- Livrables, fichiers HTML inline, pièces jointes par mission
-- type : 'file' (téléchargement), 'html' (affichage inline), 'link' (URL externe)
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mission_slug VARCHAR(100) NOT NULL,
    titre VARCHAR(200) NOT NULL,
    description TEXT,
    type ENUM('file', 'html', 'link') NOT NULL DEFAULT 'file',
    path VARCHAR(500) NOT NULL,          -- chemin fichier relatif, URL, ou chemin HTML
    filename VARCHAR(200),               -- nom original du fichier (pour download)
    mime_type VARCHAR(100),
    visibility ENUM('all', 'dirigeant', 'admin') NOT NULL DEFAULT 'all',
    uploaded_by INT,                     -- FK users.id
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mission (mission_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── ACTIONS ───
-- Plan d'action par mission : tâches, responsable, statut, échéance
CREATE TABLE IF NOT EXISTS actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mission_slug VARCHAR(100) NOT NULL,
    titre VARCHAR(300) NOT NULL,
    description TEXT,
    responsable VARCHAR(100),
    statut ENUM('a_faire', 'en_cours', 'fait', 'annule') NOT NULL DEFAULT 'a_faire',
    priorite ENUM('haute', 'normale', 'basse') NOT NULL DEFAULT 'normale',
    echeance DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_mission (mission_slug),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
