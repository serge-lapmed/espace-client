#!/bin/bash
# =====================================================================
# deploy.sh — Déploiement du CODE sur O2switch
#
# Ce script gère UNIQUEMENT le code PHP/config/templates.
# Le contenu (résumés, docs, mission.json) passe par hebdo.sh (FTP).
#
# Usage : ./deploy.sh [message de commit]
# Exemple : ./deploy.sh "Ajout page profil utilisateur"
#
# Ce qu'il fait :
#   1. Git add + commit + push (depuis le Mac)
#   2. SSH sur O2switch : git pull + copie src/ vers la racine web
#
# Prérequis :
#   - Clé SSH configurée pour O2switch (ssh o2switch fonctionne)
#   - Remote git configuré (origin → GitHub)
# =====================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
SSH_HOST="qufi1696@raquette.o2switch.net"  # SSH = raquette.o2switch.net
REMOTE_DIR="~/mission.lapmedigitale.fr"
COMMIT_MSG="${1:-Mise à jour espace client}"

cd "$REPO_DIR"

echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║  Déploiement CODE — espace client                      ║"
echo "║  $(date '+%Y-%m-%d %H:%M')                                        ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

# --- 1. Git commit + push ---
echo "[1/3] Git commit + push..."
git add -A
if git diff --cached --quiet; then
    echo "      Rien à commiter — code déjà à jour."
else
    git commit -m "$COMMIT_MSG"
    echo "      ✓ Commit effectué"
fi
git push origin main 2>&1
echo "      ✓ Push OK"
echo ""

# --- 2. SSH : git pull ---
echo "[2/3] SSH → git pull sur O2switch..."
ssh "$SSH_HOST" "cd $REMOTE_DIR && git pull origin main" 2>&1
echo "      ✓ Pull OK"
echo ""

# --- 3. SSH : copie src/ vers racine ---
echo "[3/3] SSH → copie src/ vers racine web..."
ssh "$SSH_HOST" "cd $REMOTE_DIR && cp -r src/* . && cp src/.htaccess ." 2>&1
echo "      ✓ Fichiers copiés"

echo ""
echo "══════════════════════════════════════════════════════════"
echo "  ✓ Code déployé : https://mission.lapmedigitale.fr"
echo "══════════════════════════════════════════════════════════"
echo ""
echo "  ⚠ Ce script ne déploie PAS le contenu (résumés, docs)."
echo "    Pour ça → ./hebdo.sh"
echo ""
