-- =====================================================================
-- Comptes utilisateurs — Bonnavion + MMS
-- À exécuter sur O2switch → phpMyAdmin → base qufi1696_mission
-- Date : 12 mai 2026
-- =====================================================================

-- ─── BONNAVION ───
-- André Bonnavion — Dirigeant, accès mission bonnavion-2025
-- Mot de passe temporaire : bonnavion2026 (à changer à la première connexion)
INSERT INTO users (email, nom, password_hash, role, mission_slug, actif)
VALUES (
    'andre@bonnavion.fr',
    'André Bonnavion',
    '$2y$10$PLACEHOLDER_HASH_BONNAVION',
    'dirigeant',
    'bonnavion-2025',
    1
);

-- ─── MMS ───
-- Sébastien Bay — Dirigeant, accès mission mms-2026
-- Mot de passe temporaire : mms2026 (à changer à la première connexion)
INSERT INTO users (email, nom, password_hash, role, mission_slug, actif)
VALUES (
    'sebastien.bay@mms-ra.fr',
    'Sébastien Bay',
    '$2y$10$PLACEHOLDER_HASH_MMS',
    'dirigeant',
    'mms-2026',
    1
);

-- =====================================================================
-- ⚠ IMPORTANT : Les PLACEHOLDER_HASH doivent être remplacés par de
-- vrais hash bcrypt. Utiliser l'interface admin (/admin.php) pour
-- créer les comptes est plus simple — les hash sont générés auto.
--
-- Alternative : créer via admin.php directement :
--   → André Bonnavion | andre@bonnavion.fr | dirigeant | bonnavion-2025
--   → Sébastien Bay  | sebastien.bay@mms-ra.fr | dirigeant | mms-2026
--
-- Vérification :
--   SELECT id, nom, email, role, mission_slug, actif
--   FROM users ORDER BY id DESC LIMIT 5;
-- =====================================================================
