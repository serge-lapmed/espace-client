# État du projet — Espace Client

## Infos

- **Projet** : Espace Client Codevelopper
- **Responsable** : Serge
- **Démarrage** : 24 mars 2026
- **Hébergement cible** : O2switch (compte existant)
- **Domaine cible** : client.lapmedigitale.fr
- **Premier client** : FL Métal (Jacky PDG + Guillaume BPI)

## Phase actuelle : Préparation

### Prêt
- [x] Spec V1 rédigée (dans REVUE-STACK-2026-03-20.md, section 12)
- [x] Structure projet créée dans LAB IA
- [x] Stack technique validée (PHP/MySQL/O2switch)

### À faire mardi 24
- [ ] Schéma MySQL (tables missions, actions, documents, utilisateurs)
- [ ] Structure PHP (config, auth, pages)
- [ ] Composant timeline méthode ADM-PME
- [ ] Dashboard + fiche mission
- [ ] Plan d'action (tableau)
- [ ] Page documents
- [ ] Déploiement O2switch
- [ ] Test + envoi URL à Jacky et Guillaume

## Décisions

| Date | Décision | Contexte |
|------|----------|----------|
| 20 mars | Pas de Notion pour FL — espace custom direct | Pas habituer un nouveau client à un outil qu'on va quitter |
| 20 mars | PHP/MySQL sur O2switch (pas Next.js) | O2switch existant, pas de Node.js nécessaire pour V1 |
| 20 mars | Auth V1 = mdp unique par mission | MVP suffisant, rôles en V2 |
| 20 mars | Séparation Lab (VPS OVH) / Prod (O2switch) | Données client jamais sur le lab |

## Dépendances

- Kick-off FL Métal lundi 23 → données pour alimenter l'espace
- Accès O2switch (Serge a le compte)
- Sous-domaine client.lapmedigitale.fr à configurer
