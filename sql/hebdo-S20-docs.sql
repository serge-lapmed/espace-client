-- =====================================================================
-- Documents détectés — S20 (2026)
-- Généré le 2026-05-12 14:17
-- 8 document(s)
-- Les INSERT utilisent WHERE NOT EXISTS pour éviter les doublons.
-- =====================================================================

INSERT INTO documents (mission_slug, titre, type, path, visibility)
SELECT 'fl-metal-2026', '2026-04-29_Synthèse — Séance de travail Jacky   Serge.gdoc', 'link', 'https://docs.google.com/document/d/1DJV85iwFahTA8o11YHaYXRCegBW5Iba4vWEdHlEdQ7o/edit', 'dirigeant'
WHERE NOT EXISTS (SELECT 1 FROM documents WHERE mission_slug='fl-metal-2026' AND path='https://docs.google.com/document/d/1DJV85iwFahTA8o11YHaYXRCegBW5Iba4vWEdHlEdQ7o/edit');

INSERT INTO documents (mission_slug, titre, type, path, visibility)
SELECT 'fl-metal-2026', 'Expression-Besoins-FL-Metal.gdoc', 'link', 'https://docs.google.com/document/d/1G38-zD8axjbCsbK4UyNKNZqRjLD6DUvVWiGFSYhjWRI/edit', 'dirigeant'
WHERE NOT EXISTS (SELECT 1 FROM documents WHERE mission_slug='fl-metal-2026' AND path='https://docs.google.com/document/d/1G38-zD8axjbCsbK4UyNKNZqRjLD6DUvVWiGFSYhjWRI/edit');

INSERT INTO documents (mission_slug, titre, type, path, filename, visibility)
SELECT 'fl-metal-2026', '2026-05-11_Expression-Besoins-FL-Metal_v1.1_copie', 'file', '/missions/fl-metal-2026/docs/2026-05-11_Expression-Besoins-FL-Metal_v1.1_copie.docx', '2026-05-11_Expression-Besoins-FL-Metal_v1.1_copie.docx', 'dirigeant'
WHERE NOT EXISTS (SELECT 1 FROM documents WHERE mission_slug='fl-metal-2026' AND filename='2026-05-11_Expression-Besoins-FL-Metal_v1.1_copie.docx');

INSERT INTO documents (mission_slug, titre, type, path, filename, visibility)
SELECT 'fl-metal-2026', '2026-05-11_Processus-Selection-Outil-FL-Metal', 'file', '/missions/fl-metal-2026/docs/2026-05-11_Processus-Selection-Outil-FL-Metal.pdf', '2026-05-11_Processus-Selection-Outil-FL-Metal.pdf', 'dirigeant'
WHERE NOT EXISTS (SELECT 1 FROM documents WHERE mission_slug='fl-metal-2026' AND filename='2026-05-11_Processus-Selection-Outil-FL-Metal.pdf');

INSERT INTO documents (mission_slug, titre, type, path, filename, visibility)
SELECT 'fl-metal-2026', '2026-04-29_RACI-Affaire-Type-FL-Metal', 'file', '/missions/fl-metal-2026/docs/2026-04-29_RACI-Affaire-Type-FL-Metal.xlsx', '2026-04-29_RACI-Affaire-Type-FL-Metal.xlsx', 'dirigeant'
WHERE NOT EXISTS (SELECT 1 FROM documents WHERE mission_slug='fl-metal-2026' AND filename='2026-04-29_RACI-Affaire-Type-FL-Metal.xlsx');

INSERT INTO documents (mission_slug, titre, type, path, filename, visibility)
SELECT 'rs-2026', 'RS-Document-de-travail-Phase1-2026-05-07_copie_-_master_dans_livrables', 'file', '/missions/rs-2026/docs/RS-Document-de-travail-Phase1-2026-05-07_copie_-_master_dans_livrables.docx', 'RS-Document-de-travail-Phase1-2026-05-07_copie_-_master_dans_livrables.docx', 'all'
WHERE NOT EXISTS (SELECT 1 FROM documents WHERE mission_slug='rs-2026' AND filename='RS-Document-de-travail-Phase1-2026-05-07_copie_-_master_dans_livrables.docx');

INSERT INTO documents (mission_slug, titre, type, path, filename, visibility)
SELECT 'rs-2026', 'RS-Document-de-travail-Phase1-2026-05-07_copie_-_master_dans_livrables', 'file', '/missions/rs-2026/docs/RS-Document-de-travail-Phase1-2026-05-07_copie_-_master_dans_livrables.docx', 'RS-Document-de-travail-Phase1-2026-05-07_copie_-_master_dans_livrables.docx', 'dirigeant'
WHERE NOT EXISTS (SELECT 1 FROM documents WHERE mission_slug='rs-2026' AND filename='RS-Document-de-travail-Phase1-2026-05-07_copie_-_master_dans_livrables.docx');

INSERT INTO documents (mission_slug, titre, type, path, filename, visibility)
SELECT 'rs-2026', 'RACI', 'file', '/missions/rs-2026/docs/RACI.png', 'RACI.png', 'dirigeant'
WHERE NOT EXISTS (SELECT 1 FROM documents WHERE mission_slug='rs-2026' AND filename='RACI.png');

-- Vérification :
-- SELECT mission_slug, titre, type, visibility FROM documents ORDER BY id DESC LIMIT 10;
