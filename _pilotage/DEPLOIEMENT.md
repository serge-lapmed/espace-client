# Note de déploiement — Espace Client

## Infra

| Élément | Valeur |
|---------|--------|
| **Repo** | `serge-lapmed/espace-client` (GitHub, privé) |
| **Hébergement** | O2switch (compte existant La PME Digitale) |
| **Domaine** | `client.lapmedigitale.fr` |
| **Stack** | PHP 8 + MySQL + Tailwind CDN |
| **BDD** | MySQL sur O2switch (phpMyAdmin) |

## Workflow de déploiement

```
[Local / Cowork]  →  git push  →  [GitHub]  →  git pull + cp src/* .  →  [Prod ~/mission.lapmedigitale.fr]
                                                         ↓
                                                SQL sur phpMyAdmin (si nécessaire)
```

### Étapes concrètes

1. **Commit & push** (depuis le Mac ou Cowork quand les fichiers sont modifiés)
   ```bash
   cd ~/Documents\ \(H\)/01\ -\ TRAVAIL/LAB\ IA/projets/espace-client
   git add -A
   git commit -m "description des changements"
   git push origin main
   ```

2. **Pull sur O2switch** (SSH ou terminal O2switch)
   ```bash
   cd ~/mission.lapmedigitale.fr
   git pull origin main
   cp -r src/* .
   cp src/.htaccess .
   ```
   > **Note** : le repo est cloné dans `~/mission.lapmedigitale.fr`. Le `cp` est nécessaire car O2switch sert la racine du domaine, pas le dossier `src/`. On copie le contenu de `src/` à la racine après chaque pull.

3. **SQL** (si le déploiement inclut des données à insérer)
   - Ouvrir phpMyAdmin sur O2switch
   - Sélectionner la base `espace_client` (ou le nom exact)
   - Onglet SQL → coller le contenu du fichier `.sql`
   - Exécuter

### Ce qui est automatique
- Le `.htaccess` redirige tout vers `index.php` (routing)
- Les fichiers `.json` et `.md` sont bloqués par le `.htaccess` (pas d'accès direct)
- Les résumés markdown sont rendus côté serveur (via `marked.js` en JS)

### Ce qui est manuel
- Le `git pull` sur O2switch (pas de CI/CD pour l'instant)
- Les INSERT SQL pour les documents et données
- Les images (donut, logos) à placer manuellement dans le bon dossier

## Déploiement S15 — Ce qu'il faut faire

### Fichiers modifiés
| Fichier | Type de changement |
|---------|--------------------|
| `src/index.php` | Filtre BPI (externe → Mission + Résumés) + contrôle tab URL |
| `src/missions/fl-metal-2026/resumes/2026-S15.md` | Nouveau résumé S15 |
| `sql/insert-docs-S15.sql` | INSERT documents (prez kickoff + carte motivations v3) |

### Commandes

```bash
# 1. Depuis le Mac
cd ~/Documents\ \(H\)/01\ -\ TRAVAIL/LAB\ IA/projets/espace-client
git add src/index.php src/missions/fl-metal-2026/resumes/2026-S15.md sql/insert-docs-S15.sql
git commit -m "S15 — filtre BPI, résumé S15, docs kickoff + carte motivations"
git push origin main

# 2. Sur O2switch (SSH)
cd ~/mission.lapmedigitale.fr
git pull origin main
cp -r src/* .
cp src/.htaccess .

# 3. Sur phpMyAdmin
# Copier-coller le contenu de sql/insert-docs-S15.sql → Exécuter
```

### Vérifications post-déploiement
- [ ] Ouvrir `client.lapmedigitale.fr/fl-metal-2026` → le résumé S15 doit apparaître
- [ ] Se connecter en tant qu'externe (BPI) → seuls Mission et Résumés visibles
- [ ] Onglet Documents → les 2 nouveaux documents apparaissent
- [ ] Tester `?tab=documents` en tant qu'externe → doit rediriger vers Mission

## Notes

- **Pas de CI/CD** pour l'instant. Le pull est manuel. Si besoin, on peut ajouter un webhook GitHub → O2switch.
- **Les fichiers `.md` des résumés** sont lus par PHP côté serveur et rendus en HTML via `marked.js`. Pas besoin de les convertir.
- **Les images** (donut, logos) doivent être dans le bon dossier sur O2switch. Le `git pull` les déploie automatiquement si elles sont committées.
