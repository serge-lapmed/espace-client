-- ============================================================
-- Espace Mission — Schema V2
-- Table mission_state : état dynamique des missions
-- Les champs ici ÉCRASENT ceux du mission.json au chargement
-- Version 1.0 — 2026-05-13
-- ============================================================

CREATE TABLE IF NOT EXISTS mission_state (
    mission_slug        VARCHAR(50) PRIMARY KEY,

    -- Avancement
    jours_consommes     DECIMAL(5,1)    DEFAULT NULL,
    phase_actuelle      VARCHAR(100)    DEFAULT NULL,
    phase_statut        VARCHAR(20)     DEFAULT NULL,       -- a_venir | en_cours | termine
    statut              VARCHAR(20)     DEFAULT NULL,       -- amorçage | en_cours | terminee | archivee

    -- Modules actifs (liste JSON, ex: ["mission","resumes","messages"])
    modules             JSON            DEFAULT NULL,

    -- Intervenants ajoutés via l'IHM (fusionnés avec ceux du JSON)
    intervenants_extra  JSON            DEFAULT NULL,
    -- Format : [{"nom":"X","role":"Y","type":"Z"}, ...]

    updated_at          TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by          VARCHAR(100)    DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
