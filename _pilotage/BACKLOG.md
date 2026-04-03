# Backlog — Espace Mission

## V1 — MVP en ligne (3 avril 2026)

### Fait
- [x] Structure projet PHP/Tailwind/Marked.js
- [x] Moteur MD : résumés hebdomadaires rendus côté client
- [x] Timeline phases ADM-PME
- [x] Fiche mission dépliable (intervenants, objectif, durée)
- [x] Bandeau temps mission (jauge X/Y jours)
- [x] Déploiement O2switch + sous-domaine mission.lapmedigitale.fr
- [x] Workflow : git push (Mac) → git pull (O2switch)
- [x] Contenu fictif FL Métal (S13 + S14)

### À faire — prochaine session
- [ ] **Repasser le repo GitHub en privé** (+ configurer token HTTPS sur O2switch pour le pull)
- [ ] **Auth par mot de passe mission** — empêcher l'accès par URL devinée (mdp unique par mission, PHP sessions, bcrypt)
- [ ] **Intégrer le design du mockup validé** — budget bandeau compact, footer Bpifrance, onglets Suivi/Messages, vue admin vs client
- [ ] **Vrai logo Bpifrance** en SVG dans le footer
- [ ] **Résumé S14 réel** — remplacer le contenu fictif par le vrai résumé post-réunion Jacky

## V1+ (semaine 15-16)
- [ ] Messages / échanges client (zone de tickets ouverte à tous les intervenants — remplacer les mails)
- [ ] **Notif → Serge** quand le client poste un message (n8n + Resend)
- [ ] **Notif → Client** quand Serge publie un résumé ou un doc (n8n + Resend/Twilio)
- [ ] Détail du temps consommé visible uniquement pour Serge et Guillaume (lien "Voir le détail" dans le bandeau budget)

## V2 (Q3)
- [ ] Auth par utilisateur (login/mdp individuel, PHP sessions)
- [ ] Rôles différenciés : PDG (R/W), Équipe client (R), BPI (R), Admin (*)
- [ ] Connexion BDD hub PocketBase (API)
- [ ] Multi-missions (un client = N missions)

## V3 (Q4)
- [ ] Remplacement complet Notion
- [ ] Formulaires custom (remplacer Fillout)
- [ ] Knowledge base / capitalisation accessible depuis l'espace
- [ ] Historique client long terme
