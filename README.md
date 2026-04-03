# Espace Client — Codevelopper / La PME Digitale

> Portail web client pour le suivi des missions de conseil SI

## Objectif

Remplacer l'espace client Notion par un portail web custom, souverain (hébergé O2switch, FR), avec authentification et rôles différenciés.

## Stack technique

- **Frontend** : HTML + Tailwind CSS (CDN)
- **Backend** : PHP 8 (natif O2switch)
- **BDD** : MySQL (natif O2switch)
- **Auth** : PHP sessions + bcrypt
- **Déploiement** : FTP/SSH vers O2switch
- **Domaine** : client.lapmedigitale.fr

## Structure du projet

```
espace-client/
├── README.md                 ← ce fichier
├── _pilotage/
│   ├── ETAT_PROJET.md
│   └── BACKLOG.md
├── src/                      ← code source
│   ├── index.php             ← dashboard (page d'accueil après auth)
│   ├── auth.php              ← login / logout
│   ├── timeline.php          ← composant timeline méthode ADM-PME
│   ├── actions.php           ← plan d'action (CRUD)
│   ├── documents.php         ← gestion docs (upload/download)
│   ├── mission.php           ← fiche mission (périmètre, rôles, gouvernance)
│   ├── messages.php          ← échanges client (V1+)
│   ├── config.php            ← connexion BDD, constantes
│   ├── api.php               ← endpoints JSON (pour synchro future)
│   └── assets/
│       ├── style.css         ← custom CSS (complément Tailwind)
│       └── logo.svg
├── sql/
│   └── schema.sql            ← structure BDD MySQL
└── docs/
    └── SPEC-V1.md            ← spec détaillée (extrait de REVUE-STACK)
```

## Versions prévues

| Version | Contenu | Date cible |
|---------|---------|-----------|
| **V1** | Dashboard, timeline, plan d'action, documents, fiche mission, auth par mdp mission | Mardi 24 mars |
| **V1+** | Messages/échanges client | Semaine 14 |
| **V2** | Auth par utilisateur, rôles (PDG R/W, Équipe R, BPI R, Admin) | Phase 2 (Q3) |
| **V3** | Connexion BDD hub PocketBase, multi-missions, capitalisation | Phase 3 (Q4) |

## Premier client

**FL Métal** — Mission démarrage lundi 23 mars 2026
- Jacky (PDG) : accès R/W
- Guillaume (BPI) : accès R
