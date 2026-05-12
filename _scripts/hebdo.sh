#!/bin/bash
# =====================================================================
# hebdo.sh — Mise à jour hebdomadaire du contenu espace client
#
# Ce script gère le CONTENU (pas le code — pour ça, deploy.sh).
#
# Ce qu'il fait :
#   1. Détecte les missions actives (dossiers -ms avec resumes-ec/)
#   2. Upload FTP des résumés Sxx + donuts pour chaque mission
#   3. Re-uploade mission.json si modifié (jours consommés, phase)
#   4. Scanne partage-shared/ pour les nouveaux Google Docs
#      → Lit le doc_id dans les .gdoc/.gsheet → construit le lien Google
#      → Génère le SQL d'insertion dans la table documents
#   5. Uploade les fichiers classiques (PDF, images) de partage-shared/
#      directement sur O2switch (pas de lien Drive nécessaire)
#
# Usage : ./hebdo.sh [SEMAINE]
# Exemple : ./hebdo.sh S20
#
# Automatisation : ajouter en crontab (dimanche soir ou lundi matin)
#   0 7 * * 1 cd /path/to/espace-client/_scripts && ./hebdo.sh >> /tmp/hebdo-ec.log 2>&1
#
# Prérequis :
#   - ~/.netrc avec credentials FTP o2switch
#   - python3 disponible
# =====================================================================
set -euo pipefail

SEMAINE="${1:-S$(date +%V)}"
ANNEE=$(date +%Y)

# --- Config ---
FTP_HOST="ftp.qufi1696.odns.fr"
REMOTE_BASE="/missions"
MISSIONS_DIR="$(cd "$(dirname "$0")/../../.."; pwd)/Missions/missions"
SQL_OUTPUT_DIR="$(cd "$(dirname "$0")/.."; pwd)/sql"

# --- Détecter les missions actives ---
ACTIVE_MISSIONS=()
for d in "$MISSIONS_DIR"/*-ms; do
    [ -d "$d/resumes-ec" ] || continue
    ACTIVE_MISSIONS+=("$d")
done

if [ ${#ACTIVE_MISSIONS[@]} -eq 0 ]; then
    echo "⚠ Aucune mission active trouvée."
    exit 0
fi

echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║  Mise à jour hebdo — ${SEMAINE} (${ANNEE})                       ║"
echo "║  Missions actives : ${#ACTIVE_MISSIONS[@]}                                  ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

TOTAL_UPLOADED=0
TOTAL_NEW_DOCS=0
SQL_INSERTS=""

for MISSION_DIR in "${ACTIVE_MISSIONS[@]}"; do
    MISSION_NAME=$(basename "$MISSION_DIR")

    # --- Extraire le slug ---
    SLUG=""
    if [ -f "$MISSION_DIR/_sharing.yml" ]; then
        SLUG=$(grep "^mission_slug:" "$MISSION_DIR/_sharing.yml" | awk '{print $2}' | tr -d '"' | tr -d "'")
    fi
    if [ -z "$SLUG" ] && [ -f "$MISSION_DIR/resumes-ec/mission.json" ]; then
        SLUG=$(python3 -c "import json; d=json.load(open('$MISSION_DIR/resumes-ec/mission.json')); print(d.get('slug', d.get('code','').lower().replace('_','-')))" 2>/dev/null || true)
    fi
    if [ -z "$SLUG" ]; then
        SLUG=$(echo "$MISSION_NAME" | sed 's/-ms$//' | tr '[:upper:]' '[:lower:]' | tr '_' '-')
    fi

    echo "━━━ $MISSION_NAME (slug: $SLUG) ━━━"

    # ─────────────────────────────────────────────────────
    # 1. Upload résumé + donut de la semaine
    # ─────────────────────────────────────────────────────
    RESUME="$MISSION_DIR/resumes-ec/${ANNEE}-${SEMAINE}.md"
    DONUT="$MISSION_DIR/resumes-ec/donut-${SEMAINE}.png"

    if [ -f "$RESUME" ]; then
        curl -s -k --ftp-ssl --netrc \
            -T "$RESUME" \
            "ftp://${FTP_HOST}${REMOTE_BASE}/${SLUG}/resumes/${ANNEE}-${SEMAINE}.md" \
            && echo "  ✓ Résumé ${ANNEE}-${SEMAINE}.md" \
            || echo "  ✗ Erreur résumé"
        TOTAL_UPLOADED=$((TOTAL_UPLOADED + 1))
    else
        echo "  · Pas de résumé ${ANNEE}-${SEMAINE}.md"
    fi

    if [ -f "$DONUT" ]; then
        curl -s -k --ftp-ssl --netrc \
            -T "$DONUT" \
            "ftp://${FTP_HOST}${REMOTE_BASE}/${SLUG}/resumes/donut-${SEMAINE}.png" \
            && echo "  ✓ Donut donut-${SEMAINE}.png" \
            || echo "  ✗ Erreur donut"
        TOTAL_UPLOADED=$((TOTAL_UPLOADED + 1))
    fi

    # ─────────────────────────────────────────────────────
    # 2. Re-upload mission.json si modifié (< 7 jours)
    # ─────────────────────────────────────────────────────
    MISSION_JSON="$MISSION_DIR/resumes-ec/mission.json"
    if [ -f "$MISSION_JSON" ]; then
        MODIFIED=$(find "$MISSION_JSON" -mtime -7 2>/dev/null)
        if [ -n "$MODIFIED" ]; then
            curl -s -k --ftp-ssl --netrc \
                -T "$MISSION_JSON" \
                "ftp://${FTP_HOST}${REMOTE_BASE}/${SLUG}/mission.json" \
                && echo "  ✓ mission.json (modifié récemment)" \
                || echo "  ✗ Erreur mission.json"
        fi
    fi

    # ─────────────────────────────────────────────────────
    # 3. Scanner partage-shared/ pour Google Docs
    #    Les .gdoc/.gsheet/.gslides contiennent un JSON
    #    avec doc_id → on construit le lien Google
    # ─────────────────────────────────────────────────────
    if [ -d "$MISSION_DIR/partage-shared" ]; then

        # 3a. Google Docs natifs (.gdoc, .gsheet, .gslides)
        while IFS= read -r -d '' gdoc_file; do
            REL_PATH="${gdoc_file#$MISSION_DIR/partage-shared/}"
            LEVEL=$(echo "$REL_PATH" | cut -d'/' -f1)
            FILENAME=$(basename "$gdoc_file")

            # Ignorer brouillons et fichiers cachés
            [[ "$FILENAME" == _* ]] && continue
            [[ "$FILENAME" == .* ]] && continue

            # Extraire doc_id du JSON
            DOC_ID=$(python3 -c "import json; print(json.load(open('$gdoc_file'))['doc_id'])" 2>/dev/null || true)
            [ -z "$DOC_ID" ] && continue

            TITRE=$(echo "$FILENAME" | sed 's/\.\(gdoc\|gsheet\|gslides\)$//')

            # Construire l'URL Google (prévisible, pas besoin d'API)
            if [[ "$FILENAME" == *.gsheet ]]; then
                URL="https://docs.google.com/spreadsheets/d/${DOC_ID}/edit"
            elif [[ "$FILENAME" == *.gslides ]]; then
                URL="https://docs.google.com/presentation/d/${DOC_ID}/edit"
            else
                URL="https://docs.google.com/document/d/${DOC_ID}/edit"
            fi

            # Mapping niveau → visibilité espace client
            VISIBILITY="all"
            [[ "$LEVEL" == "direction" ]] && VISIBILITY="dirigeant"

            SQL_INSERTS+="
INSERT INTO documents (mission_slug, titre, type, path, visibility)
SELECT '${SLUG}', '${TITRE}', 'link', '${URL}', '${VISIBILITY}'
WHERE NOT EXISTS (SELECT 1 FROM documents WHERE mission_slug='${SLUG}' AND path='${URL}');
"
            TOTAL_NEW_DOCS=$((TOTAL_NEW_DOCS + 1))
            echo "  📄 Google Doc : $TITRE → $LEVEL"

        done < <(find "$MISSION_DIR/partage-shared" -type f \( -name "*.gdoc" -o -name "*.gsheet" -o -name "*.gslides" \) -print0 2>/dev/null)

        # 3b. Fichiers classiques (PDF, images) → upload FTP direct
        while IFS= read -r -d '' regular_file; do
            REL_PATH="${regular_file#$MISSION_DIR/partage-shared/}"
            LEVEL=$(echo "$REL_PATH" | cut -d'/' -f1)
            FILENAME=$(basename "$regular_file")
            EXT="${FILENAME##*.}"

            [[ "$FILENAME" == _* ]] && continue
            [[ "$FILENAME" == .* ]] && continue
            [[ "$FILENAME" == *.gdoc ]] && continue
            [[ "$FILENAME" == *.gsheet ]] && continue
            [[ "$FILENAME" == *.gslides ]] && continue

            TITRE=$(echo "$FILENAME" | sed "s/\.${EXT}$//")
            REMOTE_PATH="/missions/${SLUG}/docs/${FILENAME}"

            # Créer le dossier docs/ sur o2switch
            curl -s -k --ftp-ssl --netrc \
                "ftp://${FTP_HOST}${REMOTE_BASE}/${SLUG}/" \
                -Q "MKD docs" 2>/dev/null || true

            # Upload le fichier
            curl -s -k --ftp-ssl --netrc \
                -T "$regular_file" \
                "ftp://${FTP_HOST}${REMOTE_BASE}/${SLUG}/docs/${FILENAME}" \
                && echo "  📎 Fichier : $FILENAME → FTP" \
                || echo "  ✗ Erreur upload $FILENAME"

            VISIBILITY="all"
            [[ "$LEVEL" == "direction" ]] && VISIBILITY="dirigeant"

            SQL_INSERTS+="
INSERT INTO documents (mission_slug, titre, type, path, filename, visibility)
SELECT '${SLUG}', '${TITRE}', 'file', '${REMOTE_PATH}', '${FILENAME}', '${VISIBILITY}'
WHERE NOT EXISTS (SELECT 1 FROM documents WHERE mission_slug='${SLUG}' AND filename='${FILENAME}');
"
            TOTAL_NEW_DOCS=$((TOTAL_NEW_DOCS + 1))
            TOTAL_UPLOADED=$((TOTAL_UPLOADED + 1))

        done < <(find "$MISSION_DIR/partage-shared" -type f \( -name "*.pdf" -o -name "*.png" -o -name "*.jpg" -o -name "*.xlsx" -o -name "*.docx" \) -print0 2>/dev/null)
    fi

    echo ""
done

# ─────────────────────────────────────────────────────
# 4. Écrire le SQL si nouveaux docs
# ─────────────────────────────────────────────────────
if [ -n "$SQL_INSERTS" ]; then
    SQL_FILE="${SQL_OUTPUT_DIR}/hebdo-${SEMAINE}-docs.sql"
    cat > "$SQL_FILE" << EOSQL
-- =====================================================================
-- Documents détectés — ${SEMAINE} (${ANNEE})
-- Généré le $(date '+%Y-%m-%d %H:%M')
-- ${TOTAL_NEW_DOCS} document(s)
-- Les INSERT utilisent WHERE NOT EXISTS pour éviter les doublons.
-- =====================================================================
${SQL_INSERTS}
-- Vérification :
-- SELECT mission_slug, titre, type, visibility FROM documents ORDER BY id DESC LIMIT 10;
EOSQL

    echo "┌─────────────────────────────────────────────────────────┐"
    echo "│  SQL généré : $(basename "$SQL_FILE")"
    echo "│  ${TOTAL_NEW_DOCS} document(s) à insérer"
    echo "│  → Exécuter sur phpMyAdmin"
    echo "└─────────────────────────────────────────────────────────┘"
fi

# ─────────────────────────────────────────────────────
# 5. Résumé
# ─────────────────────────────────────────────────────
echo ""
echo "══════════════════════════════════════════════════════════"
echo "  Hebdo ${SEMAINE} terminé :"
echo "    Fichiers uploadés (FTP) : $TOTAL_UPLOADED"
echo "    Nouveaux docs détectés  : $TOTAL_NEW_DOCS"
echo ""
echo "  → https://mission.lapmedigitale.fr"
echo "══════════════════════════════════════════════════════════"
echo ""
