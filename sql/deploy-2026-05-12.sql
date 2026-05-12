-- =====================================================================
-- Déploiement 12 mai 2026
-- Modifications : ajout missions Bonnavion + MMS, statut dans JSON
-- Aucune modification de schéma SQL nécessaire (le statut est dans
-- mission.json, pas en base)
-- =====================================================================

-- Vérifier que les tables V1.2 existent bien
SELECT 'messages' AS table_check, COUNT(*) AS rows FROM messages
UNION ALL
SELECT 'documents', COUNT(*) FROM documents
UNION ALL
SELECT 'actions', COUNT(*) FROM actions;

-- Vérifier les utilisateurs existants
SELECT id, nom, email, role, mission_slug, actif, last_login
FROM users ORDER BY id;

-- Vérifier les missions FL Metal docs déjà insérés
SELECT id, titre, type, visibility FROM documents
WHERE mission_slug = 'fl-metal-2026' ORDER BY id;

-- =====================================================================
-- ACTIONS MANUELLES après déploiement :
--
-- 1. git pull sur O2switch (ou ./deploy.sh)
-- 2. Créer les comptes via /admin.php :
--    → André Bonnavion | andre@bonnavion.fr | dirigeant | bonnavion-2025
--    → Sébastien Bay  | [email à confirmer] | dirigeant | mms-2026
-- 3. Tester : se connecter en tant que chaque utilisateur
-- 4. Vérifier que les 4 missions apparaissent sur la page d'accueil admin
-- =====================================================================
