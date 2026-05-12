-- =====================================================================
-- Documents FL Métal — Semaine 15 (avril 2026)
-- À exécuter sur O2switch → phpMyAdmin → base client_lapmedigitale
-- =====================================================================

-- 1) Vision dirigeant — Document de travail (Google Doc partagé Jacky)
INSERT INTO documents (mission_slug, titre, description, type, path, visibility) VALUES
('fl-metal-2026',
 'Vision dirigeant — Document de travail',
 'Synthèse phase A : diagnostic, 3 leviers, grille ROI, 6 chantiers, risques. Base de discussion pour arbitrages phase B. Non-diffusable hors FL Métal.',
 'link',
 'https://docs.google.com/document/d/1HbcYOagSU0OGW4uQkarE7ZgpizXIC2y5/edit',
 'dirigeant');

-- 2) Processus Macro BPMN (HTML brouillon, auto-hébergé)
-- Pré-requis : uploader le fichier HTML via FTP/cPanel dans
--   client.lapmedigitale.fr/FL_METAL_2026/diagnostics/FL-Metal_Processus-BPMN-Macro.html
INSERT INTO documents (mission_slug, titre, description, type, path, visibility) VALUES
('fl-metal-2026',
 'Cartographie processus macro (BPMN) — brouillon',
 'Vue BROUILLON des 3 processus cœur : Avant-vente, Études & Exécution, Production & Pose. Base de discussion pour l''atelier.',
 'html',
 '/FL_METAL_2026/diagnostics/FL-Metal_Processus-BPMN-Macro.html',
 'dirigeant');

-- =====================================================================
-- Vérification après insertion :
--   SELECT id, titre, type, visibility FROM documents
--   WHERE mission_slug = 'fl-metal-2026' ORDER BY id DESC;
-- =====================================================================
