-- ============================================================
-- Espace Mission — Schema V2.1
-- Tables : meteo, arbitrages, decisions
-- Version 1.0 — 2026-05-13
-- ============================================================

-- Météo mission : ressenti client (1=tempête, 2=orage, 3=nuageux, 4=soleil)
CREATE TABLE IF NOT EXISTS meteo (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    mission_slug    VARCHAR(50)     NOT NULL,
    user_id         INT             NOT NULL,
    score           TINYINT         NOT NULL CHECK (score BETWEEN 1 AND 4),
    commentaire     VARCHAR(280)    DEFAULT NULL,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_meteo_mission (mission_slug, created_at DESC),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Arbitrages / votes : questions de cadrage posées par le consultant
CREATE TABLE IF NOT EXISTS arbitrages (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    mission_slug    VARCHAR(50)     NOT NULL,
    titre           VARCHAR(200)    NOT NULL,
    contexte        TEXT            DEFAULT NULL,
    choix_propose   VARCHAR(500)    DEFAULT NULL,
    statut          VARCHAR(20)     DEFAULT 'ouvert',       -- ouvert | clos
    created_by      INT             NOT NULL,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    closed_at       TIMESTAMP       NULL DEFAULT NULL,
    INDEX idx_arb_mission (mission_slug, statut, created_at DESC),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Votes sur un arbitrage
CREATE TABLE IF NOT EXISTS arbitrage_votes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    arbitrage_id    INT             NOT NULL,
    user_id         INT             NOT NULL,
    vote            VARCHAR(20)     NOT NULL,               -- ok | pas_ok | a_discuter
    commentaire     TEXT            DEFAULT NULL,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vote (arbitrage_id, user_id),
    FOREIGN KEY (arbitrage_id) REFERENCES arbitrages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fil de décisions : log chronologique des décisions prises
CREATE TABLE IF NOT EXISTS decisions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    mission_slug    VARCHAR(50)     NOT NULL,
    titre           VARCHAR(200)    NOT NULL,
    decideur        VARCHAR(100)    NOT NULL,
    contexte        VARCHAR(500)    DEFAULT NULL,
    date_decision   DATE            NOT NULL,
    created_by      INT             NOT NULL,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dec_mission (mission_slug, date_decision DESC),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
