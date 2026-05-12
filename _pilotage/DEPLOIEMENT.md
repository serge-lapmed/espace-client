# Déploiement — Espace client mission.lapmedigitale.fr

> Référence unique pour le déploiement. Ne pas dupliquer ailleurs.
> Dernière mise à jour : 12 mai 2026

---

## Architecture : 3 flux distincts

```
CODE (PHP, config, CSS)            CONTENU (résumés, donuts, mission.json, fichiers)
         │                                        │
    git push                               FTP (curl)
         │                                        │
    deploy.sh                              hebdo.sh
         │                                        │
         └──────────── O2switch ──────────────────┘

GOOGLE DOCS (.gdoc/.gsheet)
         │
    sync auto (Drive for Desktop)  → Google Drive (liens)
         │
    hebdo.sh extrait doc_id → SQL  → phpMyAdmin (table documents)
```

**Règle d'or : le code passe par git, le contenu passe par FTP.**

Les docs clients (résumés, PDF, images) n'ont rien à faire dans un repo git.

---

## Prérequis (configuré une seule fois)

### SSH vers O2switch

```bash
ssh qufi1696@raquette.o2switch.net    # doit fonctionner sans mot de passe
# Si non : ssh-copy-id qufi1696@raquette.o2switch.net
```

### FTP credentials (~/.netrc)

```
machine ftp.qufi1696.odns.fr
login contenu@mission.lapmedigitale.fr
password [MOT_DE_PASSE_FTP]
```
Puis : `chmod 600 ~/.netrc`

### Python + bcrypt

```bash
pip3 install bcrypt
```

---

## Les 3 scripts

Tous dans `LAB IA/projets/espace-client/_scripts/`.

### deploy.sh — Déployer du CODE

Quand : après une modification PHP, config, template, CSS.

```bash
./_scripts/deploy.sh "Description de la modif"
```

Ce qu'il fait : `git add → commit → push → SSH git pull → copie src/*`.
Ne touche PAS au contenu (résumés, docs). Pour ça → `hebdo.sh`.

### open-ec.sh — Ouvrir une NOUVELLE mission

Quand : nouvelle mission à rendre visible dans l'espace client.

```bash
./_scripts/open-ec.sh SLUG email@client.fr "Nom Complet"
```

Exemples :
```bash
./_scripts/open-ec.sh bonnavion-2025 andre@bonnavion.fr "André Bonnavion"
./_scripts/open-ec.sh mms-2026 sebastien.bay@mms-ra.fr "Sébastien Bay"
```

Ce qu'il fait :
1. Crée `missions/SLUG/` + `resumes/` sur O2switch (FTP)
2. Uploade `mission.json` (depuis `H:/Missions/SLUG-ms/resumes-ec/`)
3. Génère le SQL d'insertion utilisateur (hash bcrypt + mot de passe temporaire)

Après : exécuter le SQL sur phpMyAdmin → tester le login → envoyer les credentials.

### hebdo.sh — Mise à jour HEBDOMADAIRE

Quand : chaque semaine (automatisable via cron).

```bash
./_scripts/hebdo.sh S20
```

Ce qu'il fait :
1. Scanne toutes les missions actives (`H:/Missions/*-ms/resumes-ec/`)
2. Upload FTP : résumés (`2026-S20.md`), donuts (`donut-S20.png`)
3. Re-upload `mission.json` si modifié dans les 7 derniers jours
4. Scanne `partage-shared/` :
   - `.gdoc/.gsheet/.gslides` → extrait doc_id → SQL avec lien Google Drive
   - Fichiers classiques (PDF, images) → upload FTP sur O2switch
5. Génère un fichier SQL avec les INSERT (anti-doublons)

Après : exécuter le SQL sur phpMyAdmin si des documents ont été détectés.

---

## Liens Google Drive : comment ça marche

Les fichiers dans `partage-shared/` sont synchro auto avec Google Drive (Drive for Desktop).

**Fichiers Google natifs** (`.gdoc`, `.gsheet`, `.gslides`) : ce sont des petits fichiers JSON qui contiennent un champ `doc_id`. Exemple de contenu d'un `.gdoc` :
```json
{"url": "https://docs.google.com/...", "doc_id": "1D6M-ZhkfF...", "email": "serge@..."}
```

Le lien est prévisible à partir du `doc_id` :
- Docs → `https://docs.google.com/document/d/{doc_id}/edit`
- Sheets → `https://docs.google.com/spreadsheets/d/{doc_id}/edit`
- Slides → `https://docs.google.com/presentation/d/{doc_id}/edit`

`hebdo.sh` lit automatiquement ce JSON et construit le lien. **Pas besoin d'API Google.**

**Fichiers classiques** (PDF, images, .xlsx, .docx) : pas de doc_id accessible localement. On les uploade en FTP sur O2switch et ils sont servis directement depuis l'espace client (`/missions/SLUG/docs/fichier.pdf`). Pas besoin de lien Drive.

---

## Automatisation (cron)

Pour `hebdo.sh` chaque lundi à 7h :

```bash
crontab -e
# Ajouter :
0 7 * * 1 cd "/Users/serge/Documents (H)/01 - TRAVAIL/LAB IA/projets/espace-client/_scripts" && ./hebdo.sh >> /tmp/hebdo-ec.log 2>&1
```

Le Mac doit être allumé à 7h le lundi. Alternative : lancer manuellement.

---

## Checklist : nouvelle mission

1. `init-mission.sh SLUG "Client" "Dirigeant" bpi|direct JOURS TARIF` → structure H:/ + mission.json
2. Compléter `ETAT_MISSION.md` et `mission.json`
3. Ajouter `mission.json` dans le repo git (`src/missions/SLUG/`)
4. `deploy.sh "Ajout mission SLUG"` → pousse le code
5. `open-ec.sh SLUG email "Nom"` → FTP + SQL utilisateur
6. Exécuter SQL sur phpMyAdmin
7. Tester, envoyer credentials

## Checklist : mise à jour hebdo

1. Rédiger le résumé `H:/Missions/SLUG-ms/resumes-ec/2026-SXX.md`
2. (Optionnel) Donut `donut-SXX.png`
3. Mettre à jour `mission.json` si jours/phase a changé
4. Déposer les nouveaux docs dans `partage-shared/direction/`
5. `hebdo.sh SXX`
6. Exécuter SQL si documents détectés

---

## Accès

| Élément | URL / Chemin |
|---------|-------------|
| Prod | https://mission.lapmedigitale.fr |
| Admin | https://mission.lapmedigitale.fr/admin.php |
| phpMyAdmin | admin O2switch → base `qufi1696_mission` |
| GitHub | https://github.com/serge-lapmed/espace-client |
| FTP | ftp.qufi1696.odns.fr — login contenu@mission.lapmedigitale.fr (credentials ~/.netrc) |
| SSH | qufi1696@raquette.o2switch.net |
| Repo local | H:/01 - TRAVAIL/LAB IA/projets/espace-client |
| Missions H:/ | H:/01 - TRAVAIL/Missions/missions/*-ms |
