# Cadrage technique — Workflow hebdo espace-client

> Version : 2026-05-04 | Auteur : Serge + Claude  
> Statut : cadrage — à valider avant implémentation

---

## 1. Contexte et objectif

Aujourd'hui, la mise à jour de l'espace-client est trop chronophage :
- Les résumés MD sont déployés via Git (mélange code/contenu)
- Les documents sont insérés manuellement en BD (requêtes SQL)
- Pas de lien automatisé entre les dossiers Drive partagés et l'espace-client

**Objectif :** une séance hebdo unique (~30 min) pour mettre à jour les 4 missions actives, avec un maximum d'automatisation et zéro modification structurelle côté client.

---

## 2. Architecture cible

```
Séance hebdo Serge
       │
       ├── 1. Rédiger les points semaine (MD)
       │        → un fichier par mission active
       │
       ├── 2. Lancer le script `hebdo.sh`
       │        │
       │        ├── Upload FTP des résumés MD + images
       │        │     → mission.lapmedigitale.fr/missions/{slug}/resumes/
       │        │
       │        └── Scan Drive → MAJ BD documents
       │              → Détecte les nouveaux fichiers
       │              → Affiche le diff
       │              → Insère en BD après validation
       │
       └── 3. Vérifier sur l'espace-client
```

**Principe clé :** Git = code applicatif. FTP = contenu mission. Drive = documents partagés.

---

## 3. Brique 1 — FTP dédié contenu

### 3.1 Compte FTP sur O2switch

Créer un compte FTP dans cPanel O2switch avec les paramètres suivants :

| Paramètre | Valeur |
|-----------|--------|
| Utilisateur | `contenu@mission.lapmedigitale.fr` |
| Répertoire | `/home/qufi1696/mission.lapmedigitale.fr/missions` |
| Protocole | FTPS (FTP over TLS) |
| Port | 21 (ou 990 pour FTPS implicite) |

Le compte est limité au dossier `missions/` — pas d'accès au code PHP.

### 3.2 Script local d'upload

Fichier : `_scripts/upload-resumes.sh`

```bash
#!/bin/bash
# Upload des résumés MD et images vers O2switch via FTPS
# Usage : ./upload-resumes.sh [semaine]
# Exemple : ./upload-resumes.sh S19

SEMAINE=${1:-$(date +S%V)}
ANNEE=$(date +%Y)
FTP_HOST="mission.lapmedigitale.fr"
FTP_USER="contenu@mission.lapmedigitale.fr"
FTP_PASS=""  # → à stocker dans ~/.netrc ou variable d'env
REMOTE_BASE="/missions"

# Missions actives (slugs correspondant aux dossiers)
MISSIONS=("fl-metal-2026" "rs-2026" "bonnavion-2026" "mms-2026")

LOCAL_BASE="$(dirname "$0")/../src/missions"

for slug in "${MISSIONS[@]}"; do
    RESUME_FILE="${LOCAL_BASE}/${slug}/resumes/${ANNEE}-${SEMAINE}.md"
    DONUT_FILE="${LOCAL_BASE}/${slug}/resumes/donut-${SEMAINE}.png"
    
    if [ -f "$RESUME_FILE" ]; then
        echo "→ Upload ${slug} : ${ANNEE}-${SEMAINE}.md"
        curl -T "$RESUME_FILE" \
             --ftp-ssl \
             --user "${FTP_USER}:${FTP_PASS}" \
             "ftp://${FTP_HOST}${REMOTE_BASE}/${slug}/resumes/${ANNEE}-${SEMAINE}.md"
    fi
    
    if [ -f "$DONUT_FILE" ]; then
        echo "→ Upload ${slug} : donut-${SEMAINE}.png"
        curl -T "$DONUT_FILE" \
             --ftp-ssl \
             --user "${FTP_USER}:${FTP_PASS}" \
             "ftp://${FTP_HOST}${REMOTE_BASE}/${slug}/resumes/donut-${SEMAINE}.png"
    fi
done

echo "✓ Upload terminé pour ${SEMAINE}"
```

### 3.3 Sécurité

- Le mot de passe FTP est stocké dans `~/.netrc` (jamais dans le script ni dans Git)
- Format `.netrc` :
  ```
  machine mission.lapmedigitale.fr
  login contenu@mission.lapmedigitale.fr
  password MOT_DE_PASSE
  ```
- Permissions : `chmod 600 ~/.netrc`

---

## 4. Brique 2 — Google Drive → BD documents

### 4.1 Convention de nommage Drive

Structure des dossiers Google Drive :

```
📁 Espace-Client/
  ├── 📁 FL-Metal-2026/          → mission_slug = "fl-metal-2026"
  │     ├── Kickoff - Présentation.pdf
  │     ├── Cartographie SI v1.pdf
  │     └── Synthèse Phase A.pdf
  │
  ├── 📁 RS-2026/                → mission_slug = "rs-2026"
  │     └── ...
  │
  ├── 📁 Bonnavion-2026/         → mission_slug = "bonnavion-2026"
  │     └── ...
  │
  └── 📁 MMS-2026/               → mission_slug = "mms-2026"
        └── ...
```

**Règles :**
- Le nom du dossier Drive correspond au `mission_slug` (mapping configurable)
- Chaque fichier ajouté dans un dossier devient un document `type = 'link'` dans la BD
- Les fichiers préfixés par `_` sont ignorés (brouillons)
- La visibilité par défaut est `all` (modifiable manuellement en BD si besoin)

### 4.2 Compte de service Google

1. Créer un projet dans Google Cloud Console
2. Activer l'API Google Drive
3. Créer un compte de service → télécharger le JSON de credentials
4. Partager chaque dossier Drive avec l'email du compte de service (lecture seule)
5. Stocker le fichier credentials sur O2switch : `~/private/google-service-account.json`

### 4.3 Script PHP de synchronisation

Fichier : `_scripts/sync-drive.php`

Ce script sera exécutable en local ou sur O2switch. Il fait :

1. Se connecte à l'API Google Drive via le compte de service
2. Liste les fichiers de chaque dossier configuré
3. Compare avec les entrées existantes en BD (`documents` WHERE `type = 'link'`)
4. Affiche les nouveaux fichiers détectés
5. Demande confirmation (mode interactif) ou insère directement (mode `--auto`)
6. Pour chaque nouveau fichier, insère en BD :
   - `mission_slug` : déduit du dossier parent
   - `titre` : nom du fichier sans extension
   - `type` : `link`
   - `path` : lien de partage Google Drive (webViewLink)
   - `filename` : nom complet du fichier
   - `mime_type` : type MIME du fichier
   - `visibility` : `all` par défaut
   - `uploaded_by` : ID admin (Serge)

### 4.4 Configuration du mapping

Fichier : `_scripts/config-drive.json`

```json
{
  "google_credentials": "~/private/google-service-account.json",
  "db": {
    "host": "localhost",
    "name": "qufi1696_mission",
    "user": "serge"
  },
  "folders": [
    {
      "drive_folder_id": "1abc...xyz",
      "mission_slug": "fl-metal-2026",
      "visibility_default": "all"
    },
    {
      "drive_folder_id": "1def...uvw",
      "mission_slug": "rs-2026",
      "visibility_default": "dirigeant"
    }
  ]
}
```

### 4.5 Dépendances

- PHP 8 + extension `curl`
- Bibliothèque Google API PHP Client : `composer require google/apiclient`
- Alternative légère : appels REST directs à l'API Drive v3 (pas besoin du SDK complet)

---

## 5. Brique 3 — Script orchestrateur hebdo

Fichier : `_scripts/hebdo.sh`

```bash
#!/bin/bash
# Orchestrateur séance hebdo espace-client
# Usage : ./hebdo.sh [semaine]

SEMAINE=${1:-$(date +S%V)}
echo "=== Séance hebdo ${SEMAINE} ==="

# Étape 1 : Upload résumés
echo ""
echo "--- Étape 1 : Upload résumés MD ---"
bash "$(dirname "$0")/upload-resumes.sh" "$SEMAINE"

# Étape 2 : Sync Drive → BD
echo ""
echo "--- Étape 2 : Sync Google Drive → BD ---"
php "$(dirname "$0")/sync-drive.php"

echo ""
echo "=== Séance terminée ==="
echo "→ Vérifier sur https://mission.lapmedigitale.fr"
```

---

## 6. Workflow hebdo cible (pas-à-pas)

### Préparation (en amont, au fil de la semaine)

- Déposer les livrables/documents dans le bon dossier Google Drive
- Les clients ont déjà accès au dossier Drive en lecture/commentaire

### Séance hebdo (~30 min)

1. **Rédiger les points semaine** : un fichier `{ANNEE}-S{XX}.md` par mission active, dans `src/missions/{slug}/resumes/`
2. **Générer les donuts** (si applicable) : images `donut-S{XX}.png`
3. **Lancer `./hebdo.sh S{XX}`** :
   - Les résumés sont uploadés en FTP
   - Les nouveaux documents Drive sont détectés et insérés en BD
4. **Vérifier** sur `mission.lapmedigitale.fr` que tout est visible
5. **Notifier les clients** (futur V1.3 : notification automatique)

### Temps estimé par mission

| Tâche | Durée |
|-------|-------|
| Rédaction point semaine MD | 5-10 min |
| Vérification résultat | 2 min |
| **Total par mission** | **7-12 min** |
| **Total 4 missions** | **~30-45 min** |

---

## 7. Plan d'implémentation

| # | Tâche | Prérequis | Effort |
|---|-------|-----------|--------|
| 1 | Créer le compte FTP sur O2switch (cPanel) | Accès cPanel | 10 min |
| 2 | Écrire et tester `upload-resumes.sh` | Compte FTP | 30 min |
| 3 | Créer le projet Google Cloud + compte de service | Compte Google | 20 min |
| 4 | Partager les dossiers Drive avec le compte de service | Dossiers Drive existants | 10 min |
| 5 | Écrire `sync-drive.php` (scan + insert BD) | Credentials Google | 2h |
| 6 | Écrire `config-drive.json` + mapping des dossiers | Folder IDs Drive | 15 min |
| 7 | Écrire `hebdo.sh` (orchestrateur) | Étapes 2 + 5 | 15 min |
| 8 | Tester le workflow complet sur FL Metal | Tout | 30 min |
| 9 | Documenter dans `_pilotage/` | Tout | 15 min |

**Effort total estimé : ~4h**

---

## 8. Points d'attention

- **Pas de modification du code client** : on ajoute uniquement des entrées en BD et des fichiers dans les dossiers existants
- **Rétrocompatibilité** : les résumés restent des `.md` lus par `list_resumes()`, le format ne change pas
- **Sécurité Drive** : le compte de service a uniquement accès en lecture aux dossiers partagés explicitement
- **Sécurité FTP** : compte limité au dossier `missions/`, credentials en `.netrc`
- **Idempotence** : le script Drive vérifie les doublons avant insertion (matching sur `path` ou `filename` + `mission_slug`)
- **Missions futures** : ajouter une mission = ajouter une entrée dans `config-drive.json` + créer le dossier Drive + ajouter le slug dans `upload-resumes.sh`
