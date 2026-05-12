#!/bin/bash
# =====================================================================
# open-ec.sh — Ouvre l'espace client pour une nouvelle mission
#
# Ce script :
#   1. Crée le dossier mission + resumes/ sur O2switch (FTP)
#   2. Uploade mission.json sur O2switch (FTP)
#   3. Génère le SQL d'insertion utilisateur (bcrypt)
#   4. Affiche les instructions pour finaliser
#
# Source du mission.json :
#   H:/Missions/missions/SLUG_UPPER-ms/resumes-ec/mission.json
#
# Usage : ./open-ec.sh SLUG EMAIL "Nom Complet"
# Exemple : ./open-ec.sh mms-2026 sebastien.bay@mms-ra.fr "Sébastien Bay"
#
# Prérequis :
#   - ~/.netrc avec credentials FTP o2switch
#   - python3 + bcrypt (pip3 install bcrypt)
#   - Le dossier mission existe sur H:/ avec resumes-ec/mission.json
# =====================================================================
set -euo pipefail

if [ $# -lt 3 ]; then
    echo "Usage: $0 SLUG EMAIL \"Nom Complet\""
    echo ""
    echo "Exemples :"
    echo "  $0 mms-2026 sebastien.bay@mms-ra.fr \"Sébastien Bay\""
    echo "  $0 bonnavion-2025 andre@bonnavion.fr \"André Bonnavion\""
    exit 1
fi

SLUG="$1"
EMAIL="$2"
NOM="$3"

# --- Config ---
FTP_HOST="ftp.qufi1696.odns.fr"
REMOTE_BASE="/missions"

# --- Trouver le dossier mission sur H:/ ---
SLUG_UPPER=$(echo "$SLUG" | tr '[:lower:]' '[:upper:]' | tr '-' '_')
MISSIONS_DIR="$(cd "$(dirname "$0")/../../.."; pwd)/Missions/missions"

MISSION_DIR=""
for d in "$MISSIONS_DIR/"*; do
    dirname=$(basename "$d")
    # Chercher SLUG_UPPER-ms ou SLUG_UPPER_YYYY-ms
    if [[ "$dirname" == "${SLUG_UPPER}-ms" ]] || [[ "$dirname" == "${SLUG_UPPER}_"*"-ms" ]]; then
        MISSION_DIR="$d"
        break
    fi
done

if [ -z "$MISSION_DIR" ]; then
    echo "❌ Dossier mission introuvable pour '$SLUG'"
    echo "   Cherché : ${SLUG_UPPER}-ms dans $MISSIONS_DIR"
    echo "   Dossiers existants :"
    ls -d "$MISSIONS_DIR/"*-ms 2>/dev/null | while read d; do echo "     $(basename "$d")"; done
    exit 1
fi

MISSION_JSON="$MISSION_DIR/resumes-ec/mission.json"
if [ ! -f "$MISSION_JSON" ]; then
    echo "❌ mission.json introuvable : $MISSION_JSON"
    echo "   Crée-le d'abord avec init-mission.sh ou manuellement."
    exit 1
fi

CLIENT=$(python3 -c "import json; print(json.load(open('$MISSION_JSON'))['client'])" 2>/dev/null || echo "$SLUG")

echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║  Ouverture espace client : $CLIENT"
echo "║  Slug : $SLUG"
echo "║  $(date '+%Y-%m-%d %H:%M')                                        ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

# --- 1. Créer les dossiers sur O2switch (FTP) ---
echo "[1/3] Création dossiers FTP..."
curl -s -k --ftp-ssl --netrc \
    "ftp://${FTP_HOST}${REMOTE_BASE}/" \
    -Q "MKD ${SLUG}" 2>/dev/null || true
curl -s -k --ftp-ssl --netrc \
    "ftp://${FTP_HOST}${REMOTE_BASE}/${SLUG}/" \
    -Q "MKD resumes" 2>/dev/null || true
echo "      ✓ missions/${SLUG}/ et missions/${SLUG}/resumes/ créés"

# --- 2. Upload mission.json ---
echo "[2/3] Upload mission.json..."
curl -s -k --ftp-ssl --netrc \
    -T "$MISSION_JSON" \
    "ftp://${FTP_HOST}${REMOTE_BASE}/${SLUG}/mission.json"
echo "      ✓ mission.json uploadé"

# --- 3. Générer SQL utilisateur ---
echo "[3/3] Génération SQL utilisateur..."

TEMP_PASS=$(python3 -c "import secrets; print(secrets.token_urlsafe(8))")
HASH=$(python3 -c "
import bcrypt
hashed = bcrypt.hashpw('${TEMP_PASS}'.encode(), bcrypt.gensalt(rounds=12))
print(hashed.decode())
" 2>/dev/null)

if [ -z "$HASH" ]; then
    echo "      ⚠ bcrypt non disponible — pip3 install bcrypt"
    exit 1
fi

SQL_FILE="$(cd "$(dirname "$0")/.."; pwd)/sql/insert-user-${SLUG}.sql"
cat > "$SQL_FILE" << EOSQL
-- Compte utilisateur : $NOM ($CLIENT)
-- Généré le $(date '+%Y-%m-%d %H:%M')
-- Mot de passe temporaire : $TEMP_PASS

INSERT INTO users (email, password_hash, nom, role, mission_slug)
VALUES (
    '${EMAIL}',
    '${HASH}',
    '${NOM}',
    'dirigeant',
    '${SLUG}'
);

-- Vérification :
-- SELECT id, nom, email, role, mission_slug FROM users WHERE email = '${EMAIL}';
EOSQL

echo ""
echo "┌─────────────────────────────────────────────────────────┐"
echo "│  Compte créé                                           │"
echo "├─────────────────────────────────────────────────────────┤"
echo "│  Nom    : $NOM"
echo "│  Email  : $EMAIL"
echo "│  Rôle   : dirigeant"
echo "│  Mission: $SLUG"
echo "│  MDP    : $TEMP_PASS"
echo "│                                                         │"
echo "│  SQL    : $SQL_FILE"
echo "└─────────────────────────────────────────────────────────┘"
echo ""
echo "Prochaines étapes :"
echo "  1. Exécuter le SQL sur phpMyAdmin (ou via /admin.php)"
echo "  2. Tester : https://mission.lapmedigitale.fr/${SLUG}"
echo "  3. Envoyer les credentials au client"
echo ""
