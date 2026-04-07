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

### À déployer (S15)
- [ ] Exécuter schema-v12.sql dans phpMyAdmin
- [ ] git commit + push + déployer (git pull + cp src/*)
- [ ] Créer le compte de Jacky (dirigeant, fl-metal-2026)
- [ ] Créer le lien de partage BPI pour Guillaume Rambaud
- [ ] Tester le flow complet : onglets, messages, affichage rôles
- [ ] Ajouter le logo Bpifrance en SVG dans src/assets/bpi.svg
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

## V2 — Multi-clients en production (Q3)
- [ ] Ajouter mission Bonnavion
- [ ] Ajouter mission RS
- [ ] Dashboard admin multi-missions (vue d'ensemble)
- [ ] Détail du temps consommé (sous-entrées par semaine)
- [ ] Export PDF du récap mission (pour le client)

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
