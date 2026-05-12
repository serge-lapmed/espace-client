# Backlog — Espace Mission

## V1.1 — Auth + multi-missions (7 avril 2026) ✅

- [x] Structure projet PHP/Tailwind/Marked.js
- [x] Moteur MD : résumés hebdomadaires rendus côté client
- [x] Timeline phases ADM-PME
- [x] Fiche mission dépliable (intervenants, objectif, durée)
- [x] Bandeau temps mission (jauge X/Y jours)
- [x] Déploiement O2switch + sous-domaine mission.lapmedigitale.fr
- [x] Workflow : git push (Mac) → git pull (O2switch)
- [x] Contenu FL Métal (S13 + S14 réels)
- [x] Auth MySQL : login/sessions/rôles (admin, dirigeant, équipe, externe)
- [x] 3 niveaux d'accès : dirigeant voit tout, équipe = opérationnel, externe = synthèse
- [x] Budget mission visible uniquement admin + dirigeant
- [x] Page admin : gestion utilisateurs + liens de partage
- [x] Liens de partage BPI/externes (token, sans compte)
- [x] Recovery mot de passe par email (token 30 min)
- [x] Multi-missions natif (un user peut être lié à une ou toutes les missions)

## V1.2 — Contenu & polish (S15-S16)

### Fait (7 avril)
- [x] Navigation par onglets (Résumés, Messages, Documents, Actions)
- [x] Schéma mission enrichi : type, financeur, modules configurables
- [x] Footer BPI conditionnel (logo + mention si financeur défini)
- [x] Zone Messages : fil de discussion client ↔ consultant
- [x] Zone Documents : livrables avec types file/html/link, visibilité par rôle
- [x] Zone Plan d'action : actions avec statut, priorité, échéance, responsable
- [x] Tables MySQL : messages, documents, actions (schema-v12.sql)

### À déployer (S15) — ✅ Déployé
- [x] Exécuter schema-v12.sql dans phpMyAdmin
- [x] git commit + push + déployer (git pull + cp src/*)
- [x] Créer le compte de Jacky (dirigeant, fl-metal-2026)
- [x] Créer le lien de partage BPI pour Guillaume Rambaud
- [x] Tester le flow complet : onglets, messages, affichage rôles
- [x] Logo BPI : géré via CDN dans mission.json (pas besoin de SVG local)
- [ ] Ajouter la carte des motivations (doc HTML) dans documents FL Metal

### À faire (S16)
- [ ] Page profil utilisateur (changer son mot de passe)
- [ ] Upload de documents via l'interface admin
- [ ] Gestion des actions via l'interface (créer, modifier statut)
- [ ] Amélioration mobile (responsive onglets, messages)
- [ ] Repo GitHub en privé + token HTTPS

## V1.3 — Notifications (S17-18)
- [ ] Notif → Serge quand le client poste un message (n8n + mail)
- [ ] Notif → Client quand Serge publie un résumé ou un doc (n8n + mail)
- [ ] Notif → Serge quand un client se connecte pour la première fois

## V1.5 — Multi-missions + statut (S20, 12 mai 2026)

- [x] Ajout mission Bonnavion (bonnavion-2025/mission.json)
- [x] Ajout mission MMS (mms-2026/mission.json)
- [x] RS déjà en place (rs-2026)
- [x] Champ `statut` ajouté aux 4 mission.json (amorçage/en_cours/terminee/archivee)
- [x] Page d'accueil refontée : missions actives + missions terminées (grisées)
- [x] Types de mission affichés (diagnostic-si, accompagnement-transfo, amoa-gouvernance, cadrage-si)
- [x] Tri par statut (actives d'abord)
- [ ] **À déployer** : git push + deploy.sh sur O2switch
- [ ] **À faire** : créer comptes André Bonnavion + Sébastien Bay via /admin.php

## V2 — Multi-clients en production (Q3)
- [x] Ajouter mission Bonnavion *(fait en V1.5)*
- [x] Ajouter mission RS *(fait en V1.2)*
- [ ] Dashboard admin multi-missions (vue d'ensemble)
- [ ] Détail du temps consommé (sous-entrées par semaine)
- [ ] Export PDF du récap mission (pour le client)

## V2.1 — Interactions & engagement client (à planifier)

> Brief complet : `_pilotage/BRIEF-EC-V2-INTERACTIONS.md`
> Origine : discussion 07/05/2026, inspiré par le format Kevin/HIC sur RS

- [ ] Météo mission : widget ressenti client (soleil/nuage/orage + commentaire)
- [ ] Arbitrages / votes : questions de cadrage avec vote OK/Pas OK/À discuter
- [ ] Vue dashboard enrichie : donut dynamique + prochain jalon + météo + arbitrages en attente
- [ ] Fil de décisions : log des décisions prises en point hebdo (quoi, qui, quand)
- [ ] Vue référent BPI : dashboard simplifié pour le RC régional

## V3 — Plateforme (Q4)
- [ ] **BDD d'entrants** : centraliser les contacts/prospects entrants (formulaires, LinkedIn, Malt, BPI, réseau) dans une table MySQL. Remplacer Grist pour le pipeline.
- [ ] Formulaires custom (remplacer Fillout)
- [ ] Knowledge base / capitalisation par client
- [ ] Historique client long terme (au-delà d'une mission)
- [ ] Connexion avec n8n pour automations (Gmail → entrants, etc.)
- [ ] **Bifurcation DSI-D** : l'espace client mission devient espace de pilotage SI permanent

---

## Idées / réflexions (ne pas planifier sans validation)

- Associé commercial : l'espace client comme argument de vente (crédibilité, transparence)
- Template d'espace pré-rempli pour chaque type de mission (diagnostic, cadrage, DSI-D)
- Mode "démo" sans auth pour montrer aux prospects
- API pour que n8n puisse publier des résumés automatiquement (Plaud → transcription → résumé → publication)
