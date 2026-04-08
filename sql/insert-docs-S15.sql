-- Documents à insérer — Semaine 15
-- À exécuter sur O2switch (phpMyAdmin)

-- Présentation Kick-off
INSERT INTO documents (mission_slug, titre, description, type, path, visibility) VALUES
('fl-metal-2026', 'Présentation Kick-off Mission SI', 'Support de la réunion de lancement du 23 mars 2026', 'html', '/FL_METAL_2026/PREZ-KICKOFF-FLM.html', 'all');

-- Carte des Motivations v3 (lecture seule — visibility 'all' mais type 'html' pour affichage inline sans téléchargement)
INSERT INTO documents (mission_slug, titre, description, type, path, visibility) VALUES
('fl-metal-2026', 'Carte des Motivations v3', 'Carte collaborative des parties prenantes, moteurs, constats, objectifs et axes d''amélioration', 'html', '/FL_METAL_2026/diagnostics/FL-Metal_Carte-des-Motivations_v3.html', 'all');
