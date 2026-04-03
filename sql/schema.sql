-- Espace Client Codevelopper — Schéma MySQL V1
-- À déployer sur O2switch
-- Date : 24 mars 2026

CREATE DATABASE IF NOT EXISTS espace_client CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE espace_client;

-- Missions
CREATE TABLE missions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,          -- ex: FLM_2026
    nom VARCHAR(255) NOT NULL,                 -- ex: Mission SI — FL Métal
    client_nom VARCHAR(255) NOT NULL,          -- ex: FL Métal
    objectif TEXT,                              -- objectif principal
    consultant VARCHAR(100) DEFAULT 'Serge Fornier',
    date_debut DATE,
    date_fin_prevue DATE,
    phase_actuelle ENUM('phase_0','phase_a','phase_b','phase_c','phase_d','integration','dsi_d') DEFAULT 'phase_0',
    duree_jours INT,                           -- nb jours prévus
    mot_de_passe_hash VARCHAR(255) NOT NULL,   -- bcrypt hash, auth V1
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Phases de la mission (pour la timeline)
CREATE TABLE phases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mission_id INT NOT NULL,
    code ENUM('phase_0','phase_a','phase_b','phase_c','phase_d','integration','dsi_d') NOT NULL,
    nom VARCHAR(100) NOT NULL,                 -- ex: Amorçage, Audit, Analyse...
    description TEXT,                           -- résumé court pour le client
    livrables_attendus TEXT,                    -- ce qui sera produit
    duree_indicative VARCHAR(50),              -- ex: "2-5 jours"
    statut ENUM('a_venir','en_cours','termine') DEFAULT 'a_venir',
    date_debut DATE,
    date_fin DATE,
    FOREIGN KEY (mission_id) REFERENCES missions(id)
);

-- Plan d'action
CREATE TABLE actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mission_id INT NOT NULL,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    responsable VARCHAR(100),
    statut ENUM('prevu','a_faire','en_cours','en_attente','valide','termine') DEFAULT 'prevu',
    echeance DATE,
    priorite ENUM('haute','moyenne','basse') DEFAULT 'moyenne',
    phase_code VARCHAR(20),                    -- lien vers la phase
    ordre INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mission_id) REFERENCES missions(id)
);

-- Documents partagés
CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mission_id INT NOT NULL,
    nom VARCHAR(255) NOT NULL,
    description VARCHAR(500),
    fichier_path VARCHAR(500),                 -- chemin sur le serveur
    fichier_taille INT,                        -- en octets
    type ENUM('livrable','document_client','cr','autre') DEFAULT 'autre',
    uploaded_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mission_id) REFERENCES missions(id)
);

-- Messages / échanges (V1+)
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mission_id INT NOT NULL,
    auteur VARCHAR(100) NOT NULL,
    contenu TEXT NOT NULL,
    lu TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mission_id) REFERENCES missions(id)
);

-- Données de la fiche mission (gouvernance, rôles, intervenants)
CREATE TABLE mission_intervenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mission_id INT NOT NULL,
    nom VARCHAR(100) NOT NULL,
    role VARCHAR(100) NOT NULL,                -- ex: Sponsor, RC BPI, Consultant, Référent interne
    email VARCHAR(255),
    telephone VARCHAR(20),
    FOREIGN KEY (mission_id) REFERENCES missions(id)
);

-- Insert phases standard ADM-PME (template réutilisable pour chaque mission)
-- À exécuter après création d'une mission avec : INSERT INTO phases ... SELECT ... WHERE mission_id = NEW_ID
