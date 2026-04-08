#!/bin/bash
# deploy.sh — Déploiement espace client sur O2switch
# Usage : ssh o2switch "cd ~/mission.lapmedigitale.fr && bash deploy.sh"
#   ou directement sur le serveur : ./deploy.sh
#
# Ce script consolide les étapes manuelles :
#   1. git pull
#   2. copie src/ vers la racine (O2switch sert la racine, pas src/)
#   3. détection des fichiers SQL à exécuter

set -euo pipefail

# --- Config ---
REPO_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
BRANCH="main"
SQL_DIR="$REPO_DIR/sql"
SQL_DONE_DIR="$REPO_DIR/sql/.done"

# --- Couleurs ---
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo ""
echo "=========================================="
echo "  Déploiement espace client"
echo "  $(date '+%Y-%m-%d %H:%M')"
echo "=========================================="
echo ""

# --- 1. Git pull ---
echo -e "${GREEN}[1/3]${NC} Git pull origin $BRANCH..."
cd "$REPO_DIR"
git pull origin "$BRANCH" 2>&1
echo ""

# --- 2. Copie src/ vers racine ---
echo -e "${GREEN}[2/3]${NC} Copie src/ vers la racine..."
cp -r src/* .
cp src/.htaccess .
echo "      ✓ Fichiers copiés"
echo ""

# --- 3. Détection SQL ---
echo -e "${GREEN}[3/3]${NC} Vérification fichiers SQL..."

# Créer le dossier .done s'il n'existe pas
mkdir -p "$SQL_DONE_DIR"

SQL_PENDING=()
if [ -d "$SQL_DIR" ]; then
    for f in "$SQL_DIR"/insert-*.sql; do
        [ -f "$f" ] || continue
        fname=$(basename "$f")
        if [ ! -f "$SQL_DONE_DIR/$fname" ]; then
            SQL_PENDING+=("$f")
        fi
    done
fi

if [ ${#SQL_PENDING[@]} -eq 0 ]; then
    echo "      Aucun SQL en attente."
else
    echo -e "      ${YELLOW}⚠  ${#SQL_PENDING[@]} fichier(s) SQL à exécuter sur phpMyAdmin :${NC}"
    echo ""
    for f in "${SQL_PENDING[@]}"; do
        fname=$(basename "$f")
        echo "      → $fname"
        echo "        Contenu :"
        echo "        --------"
        sed 's/^/        /' "$f"
        echo ""
    done
    echo -e "      ${YELLOW}Après exécution sur phpMyAdmin, marquer comme fait :${NC}"
    for f in "${SQL_PENDING[@]}"; do
        fname=$(basename "$f")
        echo "        touch $SQL_DONE_DIR/$fname"
    done
fi

echo ""
echo "=========================================="
echo -e "  ${GREEN}✓ Déploiement terminé${NC}"
echo "=========================================="
echo ""

# --- Rappel FTP si fichiers mission HTML modifiés ---
echo -e "${YELLOW}Rappel :${NC} les fichiers HTML mission (prez, diagnostics...)"
echo "sont hors repo. Si modifiés, les uploader en FTP dans"
echo "~/mission.lapmedigitale.fr/FL_METAL_2026/"
echo ""
