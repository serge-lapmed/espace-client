# Modèle de partage documentaire — Espace client

> Version : 2026-05-06 | Statut : cadrage — à valider

---

## 1. Le problème

Chaque mission a des niveaux de visibilité différents. FL Metal est simple (2 niveaux), mais un client comme Wichard pourrait avoir 5 niveaux ou plus. Le script de sync Drive → BD doit savoir quel document va dans quelle visibilité, sans coder en dur les règles.

---

## 2. Principe

Chaque mission contient un fichier `_sharing.yml` qui définit :
- Les **niveaux de visibilité** propres à cette mission
- Le **mapping** entre dossiers Drive et niveaux
- Les **rôles** (qui correspond à quel niveau)

Le script de sync lit ce fichier, scanne les dossiers Drive correspondants, et insère les documents avec le bon niveau de visibilité dans la BD.

---

## 3. Structure du fichier `_sharing.yml`

### Exemple FL Metal (simple — 2 niveaux)

```yaml
# FL Metal — Configuration de partage
# Fichier : Missions/missions/FL_METAL_2026/_sharing.yml

mission_slug: fl-metal-2026

# Niveaux de visibilité (du plus restreint au plus large)
# Chaque niveau "hérite" de ce qui est au-dessus
levels:
  - id: direction
    label: "Direction"
    description: "Jacky Cabailh uniquement"
    drive_folder_id: "1abc...xyz"    # Dossier Drive partagé avec Jacky
    
  - id: equipe
    label: "Équipe"
    description: "Tous les intervenants interviewés"
    drive_folder_id: "1def...uvw"    # Dossier Drive partagé avec l'équipe

# Niveau par défaut si un doc n'est pas dans un dossier mappé
default_level: direction

# Qui a accès à quoi (pour référence — les droits réels sont sur Drive)
access:
  direction:
    - "Jacky Cabailh (PDG)"
    - "Guillaume Rambaud (BPI)"
  equipe:
    - "Tout le monde dans 'direction'"
    - "François Cantin"
    - "Julien Nevers"
    - "Quentin Comby"
    - "Loïc Lecoq"
    - "Maryline Matray"
    - "Marion Girard"
    - "Rachid Ait Tahabbassat"
```

### Exemple Wichard (complexe — N niveaux)

```yaml
# Wichard — Configuration de partage
mission_slug: wichard-2026

levels:
  - id: comite_pilotage
    label: "Comité de pilotage"
    description: "Direction + sponsors projet"
    drive_folder_id: "1ghi...rst"
    
  - id: direction
    label: "Direction"
    description: "CFO + DG + DSI"
    drive_folder_id: "1jkl...uvw"
    
  - id: entite_production
    label: "Entité Production"
    description: "Équipe projet production"
    drive_folder_id: "1mno...xyz"
    
  - id: entite_commercial
    label: "Entité Commercial"
    description: "Équipe projet commercial"
    drive_folder_id: "1pqr...abc"
    
  - id: parties_prenantes
    label: "Parties prenantes"
    description: "Tous les intervenants projet"
    drive_folder_id: "1stu...def"

default_level: direction

access:
  comite_pilotage:
    - "DG"
    - "CFO"
    - "Responsable projet"
  direction:
    - "Tout le monde dans 'comite_pilotage'"
    - "DSI"
  entite_production:
    - "Responsable production"
    - "Chefs d'atelier"
  entite_commercial:
    - "Directeur commercial"
    - "Responsables grands comptes"
  parties_prenantes:
    - "Tout le monde"
```

---

## 4. Côté BD — Adapter le champ `visibility`

Aujourd'hui la table `documents` a : `visibility ENUM('all', 'dirigeant', 'admin')`.

**Option A — Garder l'ENUM et mapper** : on mappe chaque level vers une des 3 valeurs existantes. Simple mais rigide. Suffisant pour FL Metal, insuffisant pour Wichard.

**Option B — Passer en VARCHAR** : remplacer l'ENUM par un VARCHAR(50) qui stocke directement le `level.id` du `_sharing.yml`. Le code PHP côté espace-client compare le `visibility` du document avec les niveaux autorisés pour l'utilisateur connecté.

```sql
-- Migration (une seule fois)
ALTER TABLE documents MODIFY visibility VARCHAR(50) NOT NULL DEFAULT 'all';
```

**Recommandation : Option B.** C'est un changement mineur (une colonne), et ça rend le système extensible à N niveaux sans toucher au schéma à chaque nouveau client.

---

## 5. Côté espace-client PHP — Filtrage par niveau

Le fichier `mission.json` (déjà existant par mission) reçoit un champ `levels` qui liste les niveaux et le mapping utilisateur → niveaux autorisés.

```json
{
  "slug": "fl-metal-2026",
  "titre": "Mission SI — FL Métal",
  "client": "FL Métal",
  "levels": ["direction", "equipe"],
  "user_levels": {
    "jacky": ["direction", "equipe"],
    "guillaume_bpi": ["direction"]
  }
}
```

Le code PHP filtre : `WHERE visibility IN (niveaux autorisés pour l'utilisateur)`.

---

## 6. Côté Drive — Convention de dossiers

```
📁 [Dossier racine mission sur Drive]
  ├── 📁 Direction/           → visibility = "direction"
  │     ├── Vision dirigeant.docx
  │     └── Grille ROI.xlsx
  │
  ├── 📁 Équipe/              → visibility = "equipe"
  │     ├── Carte des motivations.html
  │     └── BPMN Macro.html
  │
  └── 📁 _brouillons/         → ignoré par le script (préfixe _)
        └── notes.md
```

Le nom du dossier Drive n'a pas besoin de correspondre exactement au level — c'est le `drive_folder_id` dans le `_sharing.yml` qui fait le mapping.

---

## 7. Script de sync — Algorithme

```
Pour chaque mission dans _sharing.yml :
  Pour chaque level :
    1. Lister les fichiers du drive_folder_id (API Drive v3)
    2. Filtrer : ignorer fichiers préfixés par _
    3. Pour chaque fichier :
       a. Vérifier s'il existe déjà en BD (match sur path = webViewLink)
       b. Si nouveau → INSERT avec visibility = level.id
       c. Si modifié (modifiedTime > stored) → UPDATE titre si renommé
    4. Afficher le diff (mode interactif) ou insérer directement (mode --auto)
```

---

## 8. Mise en place — Étapes concrètes

### Étape 1 : Organiser les dossiers Drive (15 min par mission)

Pour FL Metal :
1. Créer un dossier Drive `FL Metal — Direction` → partager avec Jacky (lecture)
2. Créer un dossier Drive `FL Metal — Équipe` → partager avec tous (lecture)
3. Y copier/déplacer les Google Docs existants (Vision dirigeant, Expression de besoin, RACI…)
4. Noter les `folder_id` (visible dans l'URL du dossier Drive)

### Étape 2 : Créer le `_sharing.yml` (5 min par mission)

Dans `Missions/missions/FL_METAL_2026/_sharing.yml`, remplir avec les folder_id.

### Étape 3 : Migrer la BD (5 min, une seule fois)

```sql
ALTER TABLE documents MODIFY visibility VARCHAR(50) NOT NULL DEFAULT 'all';
```

### Étape 4 : Adapter le code PHP (1h)

- Lire les levels depuis `mission.json`
- Filtrer les documents par niveau autorisé pour l'utilisateur
- Adapter l'admin pour afficher/éditer le champ visibility en texte libre

### Étape 5 : Écrire le script de sync (2h)

- Compte de service Google (cf. CADRAGE_WORKFLOW_HEBDO.md)
- Script PHP qui lit `_sharing.yml`, scanne Drive, insère en BD
- Mode interactif : affiche les nouveaux docs détectés, demande confirmation

### Étape 6 : Tester sur FL Metal (30 min)

Premier run complet : scan → insertion → vérification sur l'espace client.

---

## 9. Ce qui change pour toi au quotidien

**Avant** : tu écris un MD → tu le convertis en Google Doc → tu le mets dans un dossier Drive → tu écris un INSERT SQL → tu l'exécutes sur phpMyAdmin.

**Après** : tu écris un MD → tu le convertis en Google Doc → tu le mets dans le bon dossier Drive (Direction ou Équipe) → tu lances le script de sync → c'est dans l'espace client.

L'INSERT SQL disparaît. Le script le fait pour toi en lisant la structure Drive.
