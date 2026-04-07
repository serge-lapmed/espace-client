# Backlog — Espace Mission

## V1.1 — Auth + multi-missions (7 avril 2026)

### Fait
- [x] Structure projet PHP/Tailwind/Marked.js
- [x] Moteur MD : résumés hebdomadaires rendus côté client
- [x] Timeline phases ADM-PME
- [x] Fiche mission dépliable (intervenants, objectif, durée)
- [x] Bandeau temps mission (jauge X/Y jours)
- [x] Déploiement O2switch + sous-domaine mission.lapmedigitale.fr
- [x] Workflow : git push (Mac) → git pull (O2switch)
- [x] Contenu FL Métal (S13 + S14 réels)
- [x] **Auth MySQL : login/sessions/rôles (admin, dirigeant, équipe, externe)**
- [x] **3 niveaux d'accès** : dirigeant voit tout, équipe = opérationnel, externe = synthèse
- [x] **Budget mission visible uniquement admin + dirigeant**
- [x] **Page admin : gestion utilisateurs + liens de partage**
- [x] **Liens de partage BPI/externes (token, sans compte)**
- [x] **Recovery mot de passe par email (token 30 min)**
- [x] **Multi-missions natif** (un user peut être lié à une ou toutes les missions)

### À déployer
- [ ] Créer la base MySQL sur O2switch (phpMyAdmin)
- [ ] Exécuter schema-auth.sql
- [ ] Configurer config.php avec les credentials DB
- [ ] Créer le compte de Jacky (dirigeant, fl-metal-2026)
- [ ] Créer le lien de partage BPI pour Guillaume
- [ ] Repasser le repo GitHub en privé + configurer token HTTPS
- [ ] Tester le flow complet : login → mission → résumés → logout → recovery

## V1.2 — Contenu & polish (S16)
- [ ] **Intégrer le design du mockup validé** (footer Bpifrance, onglets)
- [ ] Vrai logo Bpifrance en SVG
- [ ] Onglet "Messages" (échanges client, zone tickets)
- [ ] Onglet "Documents" (livrables téléchargeables)
- [ ] Onglet "Plan d'action" (tableau des actions + statut)
- [ ] Page profil utilisateur (changer son mot de passe)

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
- [ ] BDD d'entrants : centraliser les contacts/prospects entrants (formulaires, LinkedIn, Malt, BPI, réseau) dans une table MySQL. Remplacer Grist pour le pipeline.
- [ ] Formulaires custom (remplacer Fillout)
- [ ] Knowledge base / capitalisation par client
- [ ] Historique client long terme (au-delà d'une mission)
- [ ] Connexion avec n8n pour automations (Gmail → entrants, etc.)
- [ ] Bifurcation DSI-D : l'espace client mission devient espace de pilotage SI permanent

---

## Idées / réflexions (ne pas planifier sans validation)

- Associé commercial : l'espace client comme argument de vente (crédibilité, transparence)
- Template d'espace pré-rempli pour chaque type de mission (diagnostic, cadrage, DSI-D)
- Mode "démo" sans auth pour montrer aux prospects
- API pour que n8n puisse publier des résumés automatiquement (Plaud → transcription → résumé → publication)
