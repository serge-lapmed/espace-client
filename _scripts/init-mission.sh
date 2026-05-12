#!/bin/bash
# =====================================================================
# init-mission.sh — Initialise une nouvelle mission client
# Usage : ./init-mission.sh SLUG "Nom Client" "Nom Dirigeant" [bpi|direct] JOURS TARIF
# Exemple : ./init-mission.sh mms-2026 "MMS Rhône-Alpes" "Sébastien Bay" direct 8 1300
# =====================================================================
set -euo pipefail

if [ $# -lt 6 ]; then
    echo "Usage: $0 SLUG \"Nom Client\" \"Nom Dirigeant\" [bpi|direct] JOURS TARIF"
    echo "Exemple: $0 mms-2026 \"MMS Rhône-Alpes\" \"Sébastien Bay\" direct 8 1300"
    exit 1
fi

SLUG="$1"
CLIENT="$2"
DIRIGEANT="$3"
TYPE="$4"       # bpi ou direct
JOURS="$5"
TARIF="$6"
BUDGET=$((JOURS * TARIF))
ANNEE=$(date +%Y)
DATE=$(date +%d/%m/%Y)
SLUG_UPPER=$(echo "$SLUG" | tr '[:lower:]' '[:upper:]' | tr '-' '_')

# Chemins
MISSIONS_DIR="$(cd "$(dirname "$0")/../../.."; pwd)/Missions/missions"
TEMPLATE="$MISSIONS_DIR/_TEMPLATE_MISSION"
DEST="$MISSIONS_DIR/${SLUG_UPPER}-ms"

if [ -d "$DEST" ]; then
    echo "❌ Le dossier $DEST existe déjà."
    exit 1
fi

echo "=== Création mission : $CLIENT ($SLUG) ==="
echo "  Type : $TYPE | Budget : ${BUDGET}€ HT (${JOURS}j × ${TARIF}€)"
echo "  Dirigeant : $DIRIGEANT"
echo "  Dossier : $DEST"
echo ""

# 1. Copier le template
cp -r "$TEMPLATE" "$DEST"
echo "✓ Template copié"

# 2. Dossier admin
if [ "$TYPE" = "bpi" ]; then
    mv "$DEST/doc-admin" "$DEST/Doc admin BPI" 2>/dev/null || mkdir -p "$DEST/Doc admin BPI"
    echo "✓ Dossier Doc admin BPI créé"
else
    echo "✓ Dossier doc-admin conservé (mission directe)"
fi

# 3. _sharing.yml
cat > "$DEST/_sharing.yml" << EOF
# $CLIENT — Configuration de partage documentaire
mission_slug: $SLUG

levels:
  - id: direction
    label: "Direction"
    description: "$DIRIGEANT"
    local_path: "partage-shared/direction"

default_level: direction

role_mapping:
  direction:
    - dirigeant
EOF
echo "✓ _sharing.yml créé"

# 4. ETAT_MISSION.md
TYPE_LABEL="Direct (hors BPI)"
[ "$TYPE" = "bpi" ] && TYPE_LABEL="BPI"

cat > "$DEST/_pilotage/ETAT_MISSION.md" << EOF
# ETAT_MISSION — $CLIENT $ANNEE
*Méthode Codevelopper / ADM-PME — La PME Digitale*
*Dernière mise à jour : $DATE*

> Ce fichier est la mémoire persistante de la mission. Il est lu par toutes les sessions Claude en premier.
> Il DOIT être mis à jour après chaque session ou échange significatif.

---

## 1. Identité de la mission

| | |
|---|---|
| **Client** | $CLIENT |
| **Code mission** | ${SLUG_UPPER}_SI |
| **Type** | $TYPE_LABEL |
| **Consultant** | Serge Fornier — La PME Digitale |
| **Date de démarrage** | À confirmer |
| **Durée prévue** | ${JOURS} jours |
| **Budget** | ${BUDGET} € HT (${JOURS}j × ${TARIF} €) |
| **Phase actuelle** | Phase 0 — Amorçage |

---

## 2. Contexte entreprise

**$CLIENT** est une [description à compléter].

**Dirigeant** : $DIRIGEANT

**Contexte stratégique** :
- À compléter lors du premier entretien

---

## 3. Problématique exprimée

[À compléter]

---

## 4. Périmètre de la mission

| Domaine | Inclus |
|---|---|
| [Domaine 1] | ✅ / ❌ |

---

## 5. Intervenants mission

| Rôle | Identité | Disponibilité |
|---|---|---|
| Dirigeant | $DIRIGEANT | À confirmer |
| Consultant | Serge Fornier | Full mission |

---

## 6. Éléments terrain collectés

| Date | Source | Type | Résumé |
|---|---|---|---|

---

## 7. Livrables prévus

- [À compléter selon le devis]

---

## 8. Décisions prises

*(Aucune pour l'instant)*

---

## 9. Questions ouvertes

- [ ] Date de démarrage à confirmer
- [ ] Premier entretien à planifier

---

## 10. Notes et observations libres

*(Notes terrain de Serge)*
EOF
echo "✓ ETAT_MISSION.md créé"

# 5. mission.json pour l'espace client
mkdir -p "$DEST/resumes-ec"
cat > "$DEST/resumes-ec/mission.json" << EOF
{
    "client": "$CLIENT",
    "titre": "Cadrage SI",
    "code": "${SLUG_UPPER}_SI",
    "type": "cadrage-si",
    "objectif": "[À compléter]",
    "date_debut": "À confirmer",
    "duree": "À définir",
    "consultant": "Serge Fornier",
    "jours_total": $JOURS,
    "jours_consommes": 0,
    "phase_actuelle": "Phase 0 — Amorçage",
    "phase_statut": "a_venir",
    "modules": ["mission", "resumes", "messages", "documents", "actions"],
    "intervenants": [
        { "nom": "$DIRIGEANT", "role": "Dirigeant", "type": "sponsor" },
        { "nom": "Serge Fornier", "role": "Consultant SI — La PME Digitale", "type": "consultant" }
    ],
    "gouvernance": {
        "copil": ["$DIRIGEANT", "Serge Fornier"],
        "frequence": "Point hebdomadaire ou à la demande"
    },
    "phases": [
        { "nom": "Amorçage", "code": "phase_0", "statut": "a_venir", "description": "Validation périmètre, planification" },
        { "nom": "Audit terrain", "code": "phase_a", "statut": "a_venir", "description": "Entretiens, cartographie SI" },
        { "nom": "Analyse & Diagnostic", "code": "phase_b", "statut": "a_venir", "description": "Diagnostic, benchmark, gains" },
        { "nom": "Recommandations", "code": "phase_c", "statut": "a_venir", "description": "Feuille de route, restitution" }
    ],
    "livrables": ["À compléter selon le devis"],
    "bonnes_pratiques": [
        "Les échanges passent par cet espace",
        "Le plan d'action est mis à jour chaque semaine",
        "Les documents sont suivis par le consultant"
    ]
}
EOF
echo "✓ mission.json créé"

# 6. _LISEZMOI dans partage-shared/direction
cat > "$DEST/partage-shared/direction/_LISEZMOI.md" << EOF
# Direction — Documents partagés

Documents partagés avec $DIRIGEANT.
Droits Google Drive gérés par héritage sur ce dossier.

**Niveau espace-client** : \`direction\`
EOF
echo "✓ partage-shared/direction/_LISEZMOI.md créé"

echo ""
echo "=== Mission $CLIENT créée avec succès ==="
echo ""
echo "Prochaines étapes :"
echo "  1. Compléter _pilotage/ETAT_MISSION.md (contexte, périmètre, livrables)"
echo "  2. Compléter resumes-ec/mission.json (objectif, titre, livrables, phases)"
echo "  3. Déposer le devis/contrat dans doc-admin/"
echo "  4. Configurer les droits Drive sur partage-shared/direction/"
echo "  5. Lancer ./open-ec.sh $SLUG pour ouvrir l'espace client"
