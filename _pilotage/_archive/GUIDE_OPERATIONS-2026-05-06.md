# Guide opérationnel — Espace client & Missions

> Version : 2026-05-06 | Auteur : Serge + Claude
> Ce document est la référence pour toutes les opérations sur les missions et l'espace client.

---

## 1. Créer une nouvelle mission

### Prérequis
- Le devis est signé ou la convention BPI validée
- Tu connais : nom client, slug, nom dirigeant, type (BPI/direct), budget

### Étapes

**A. Créer la structure locale (H:/)**

```bash
cd "H:/01 - TRAVAIL/LAB IA/projets/espace-client/_scripts"
./init-mission.sh CLIENT_SLUG "Nom Client" "Prénom Nom Dirigeant" [bpi|direct] JOURS TARIF
```

Exemple :
```bash
./init-mission.sh mms-2026 "MMS Rhône-Alpes" "Sébastien Bay" direct 8 1300
```

Ce script :
- Copie `_TEMPLATE_MISSION/` vers `Missions/missions/CLIENT_SLUG/`
- Remplit `_sharing.yml` avec le slug et le dirigeant
- Crée `ETAT_MISSION.md` pré-rempli
- Crée `resumes-ec/mission.json`
- Si BPI : crée `Doc admin BPI/` ; sinon : crée `doc-admin/`

**B. Compléter manuellement**

- `_pilotage/ETAT_MISSION.md` : enrichir le contexte entreprise, périmètre, livrables
- `doc-admin/` : y déposer le devis signé, contrat, etc.
- `partage-shared/direction/` : configurer les droits Drive (clic droit → Partager → email dirigeant, lecteur)

---

## 2. Ouvrir l'espace client

### Étapes

**A. Déployer sur o2switch (FTP)**

```bash
./open-ec.sh CLIENT_SLUG
```

Ce script :
- Uploade `resumes-ec/mission.json` → `missions/{slug}/mission.json` sur o2switch
- Crée le dossier `missions/{slug}/resumes/` sur o2switch

**B. Créer l'utilisateur (SQL)**

Le script `open-ec.sh` génère le SQL à exécuter sur phpMyAdmin :

```sql
INSERT INTO users (email, password_hash, nom, role, mission_slug) VALUES
('email@client.fr', '$2b$12$...hash...', 'Prénom Nom', 'dirigeant', 'slug');
```

**C. Vérifier**

- Aller sur `https://mission.lapmedigitale.fr/CLIENT_SLUG`
- Tester le login avec les credentials temporaires
- Envoyer les credentials au client

---

## 3. Gérer les dossiers partagés

### Convention

```
Mission/
  diagnostics/          ← fichiers de travail internes (MD)
  partage-shared/
    direction/          ← Google Docs visibles par le dirigeant
    equipe/             ← Google Docs visibles par tous (si mission multi-niveaux)
```

### Règles

- **MD source** → reste dans `diagnostics/`
- **Google Doc client** → va dans `partage-shared/{niveau}/`
- **Pas de fichiers `_`** dans partage-shared (le client voit le dossier Drive)
- **Droits Drive** : par héritage sur le dossier (configurer une seule fois)
- **_sharing.yml** : définit les niveaux et le mapping vers les rôles BD

### Ajouter un document à l'espace client

Quand le script de sync n'est pas encore en place :
1. Placer le Google Doc dans `partage-shared/{niveau}/`
2. Ouvrir le `.gdoc` pour récupérer le `doc_id`
3. Exécuter le SQL d'insertion (ou utiliser `./sync-docs.sh SLUG`)

---

## 4. Maintenance hebdomadaire

### Séance type (~30 min pour 3-4 missions actives)

```bash
./hebdo.sh [SEMAINE]
# Exemple : ./hebdo.sh S19
```

### Ce que fait le script

Pour chaque mission active :
1. **Upload résumés** : envoie les `resumes-ec/2026-Sxx.md` + donuts en FTP
2. **Sync documents** : scanne `partage-shared/` pour les nouveaux `.gdoc`, génère le SQL d'insertion
3. **Affiche le diff** : montre ce qui a changé depuis la dernière fois

### Workflow concret

1. **En amont (au fil de la semaine)** :
   - Déposer les Google Docs dans `partage-shared/{niveau}/`
   - Rédiger les résumés dans `resumes-ec/2026-Sxx.md`

2. **Séance hebdo** :
   - Rédiger/finaliser les résumés manquants
   - Lancer `./hebdo.sh`
   - Exécuter le SQL généré sur phpMyAdmin (si nouveaux docs)
   - Vérifier sur `mission.lapmedigitale.fr`

3. **Mettre à jour `mission.json`** si les jours consommés ou la phase changent

---

## 5. Checklist rapide

### Nouvelle mission
- [ ] `init-mission.sh` exécuté
- [ ] `ETAT_MISSION.md` complété
- [ ] Devis/contrat dans `doc-admin/`
- [ ] Droits Drive configurés sur `partage-shared/direction/`
- [ ] `open-ec.sh` exécuté
- [ ] SQL utilisateur exécuté sur phpMyAdmin
- [ ] Test login EC OK
- [ ] Credentials envoyés au client

### Chaque semaine
- [ ] Résumés Sxx rédigés dans `resumes-ec/`
- [ ] Nouveaux docs dans `partage-shared/` si applicable
- [ ] `hebdo.sh` exécuté
- [ ] SQL docs exécuté si nécessaire
- [ ] `mission.json` mis à jour (jours, phase) si changement

---

## 6. Arborescence de référence

```
Missions/missions/{SLUG}/
  _pilotage/
    ETAT_MISSION.md           ← mémoire persistante mission
    contexte-sessions/        ← contextes pour Claude
    rapports/
  _sharing.yml                ← config niveaux de visibilité
  doc-admin/                  ← devis, factures, contrat (hors BPI)
  Doc admin BPI/              ← convention, NDC, etc. (si BPI)
  partage-shared/
    direction/                ← docs partagés dirigeant
    equipe/                   ← docs partagés équipe (optionnel)
  diagnostics/                ← MD de travail internes
  transcriptions/brutes/      ← transcriptions réunions
  entrants/                   ← documents fournis par le client
  livrables/                  ← livrables finaux
  recommandations/            ← recommandations formalisées
  resumes-ec/
    mission.json              ← config espace client
    2026-Sxx.md               ← résumés hebdo (FTP → o2switch)
    donut-Sxx.png             ← graphiques avancement
```
