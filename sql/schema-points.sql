-- ============================================================
-- Espace Mission — Schema Points Hebdo
-- Tables : points_hebdo, point_actions, point_meteo
-- Version 1.0 — 2026-05-13
-- ============================================================

-- Point hebdo : entité centrale du suivi mission
CREATE TABLE IF NOT EXISTS points_hebdo (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    mission_slug    VARCHAR(50)     NOT NULL,
    semaine         VARCHAR(10)     NOT NULL,           -- S20, S21...
    date_point      DATE            NOT NULL,
    frequence       VARCHAR(20)     DEFAULT 'hebdo',    -- hebdo | bi-mensuel | mensuel
    ordre_du_jour   TEXT            DEFAULT NULL,
    resume          TEXT            DEFAULT NULL,
    avancement      TEXT            DEFAULT NULL,
    prochaines_etapes TEXT          DEFAULT NULL,
    temps_passe     VARCHAR(100)    DEFAULT NULL,       -- ex: "1,5 jour (cumul : 8,5)"
    statut          VARCHAR(20)     DEFAULT 'brouillon', -- brouillon | publie | archive
    created_by      INT             NOT NULL,
    published_at    TIMESTAMP       NULL DEFAULT NULL,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_points_mission (mission_slug, date_point DESC),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Actions rattachées à un point (cochables par le responsable)
CREATE TABLE IF NOT EXISTS point_actions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    point_id        INT             NOT NULL,
    mission_slug    VARCHAR(50)     NOT NULL,
    titre           VARCHAR(300)    NOT NULL,
    responsable     VARCHAR(100)    DEFAULT NULL,
    echeance        VARCHAR(20)     DEFAULT NULL,       -- "S21", "15/06", libre
    statut          VARCHAR(20)     DEFAULT 'a_faire',  -- a_faire | fait | reporte
    checked_by      INT             DEFAULT NULL,
    checked_at      TIMESTAMP       NULL DEFAULT NULL,
    ordre           INT             DEFAULT 0,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pa_point (point_id),
    INDEX idx_pa_mission (mission_slug, statut),
    FOREIGN KEY (point_id) REFERENCES points_hebdo(id) ON DELETE CASCADE,
    FOREIGN KEY (checked_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Météo collective rattachée à un point (fin de réunion)
CREATE TABLE IF NOT EXISTS point_meteo (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    point_id        INT             NOT NULL,
    user_id         INT             NOT NULL,
    score           TINYINT         NOT NULL CHECK (score BETWEEN 1 AND 4),
    commentaire     VARCHAR(280)    DEFAULT NULL,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_meteo_point_user (point_id, user_id),
    FOREIGN KEY (point_id) REFERENCES points_hebdo(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
