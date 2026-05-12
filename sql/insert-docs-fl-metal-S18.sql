-- =====================================================================
-- Documents FL Métal — Semaine 18 (mai 2026)
-- À exécuter sur O2switch → phpMyAdmin → base client_lapmedigitale
-- =====================================================================

-- 1) Expression de besoin (Google Doc — accès dirigeant)
INSERT INTO documents (mission_slug, titre, description, type, path, visibility) VALUES
('fl-metal-2026',
 'Expression de besoin — FL Métal',
 'Document synthétique formalisant les 3 besoins prioritaires, interfaces par métier, besoins périphériques et contraintes. Issu des entretiens + points S13 à S17.',
 'link',
 'https://drive.google.com/file/d/1D6M-ZhkfF_3tMyA0dL2Z1-HAFJIozTPZ/view',
 'dirigeant');

-- 2) RACI détaillé — Cycle de vie d'une affaire type (Google Doc)
INSERT INTO documents (mission_slug, titre, description, type, path, visibility) VALUES
('fl-metal-2026',
 'RACI détaillé — Affaire type',
 'Matrice complète des responsabilités par jalon, de la réception AO à la clôture DGD. ~80 actions, 3 niveaux d''alerte.',
 'link',
 'https://drive.google.com/file/d/18v4ldY_iTj3jRYp1zBBmIeLphFomxlY_/view',
 'dirigeant');

-- 3) Plan d'étapes suivantes (mai → février 2027)
INSERT INTO documents (mission_slug, titre, description, type, path, visibility) VALUES
('fl-metal-2026',
 'Plan d''étapes — Mai à février 2027',
 'Feuille de route des prochaines phases : qualification solutions (mai), choix (juin), implémentation vague 1 (sept-nov), vague 2 (nov-janv). Jalon critique : opérationnel avant rush fév 2027.',
 'file',
 '/missions/fl-metal-2026/docs/2026-05-04_Plan-Etapes-Suivantes.md',
 'dirigeant');

-- =====================================================================
-- Vérification après insertion :
--   SELECT id, titre, type, visibility FROM documents
--   WHERE mission_slug = 'fl-metal-2026' ORDER BY id DESC LIMIT 5;
-- =====================================================================
