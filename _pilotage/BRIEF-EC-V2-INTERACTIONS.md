---
titre: Brief — Espace client v2 · Interactions & engagement
créé le: 2026-05-07
modifié le: 2026-05-07
version: 1.0
auteur: Serge Fornier (via Cowork)
statut: brouillon
portée: Espace client
---

# Brief — Espace client v2 · Interactions & engagement

> Origine : discussion du 07/05/2026 après analyse du site de présentation projet produit par Kevin (resosign-suivi-projet.vercel.app). Le format interactif (votes, commentaires, arbitrages) crée de l'engagement client que l'espace actuel (lecture seule) ne génère pas.

## Constat

L'espace client actuel (mission.lapmedigitale.fr) est un bon outil de transparence :
résumés hebdo, timeline, documents, plan d'action. Mais il est passif — le client
consomme, il n'interagit pas. Il n'a pas de raison forte d'y revenir entre deux points hebdo.

Kevin a trouvé un bon équilibre sur son site projet RS : des arbitrages à voter,
des commentaires à poser, un glossaire commun. Le client a un rôle actif.

## Objectif

Transformer l'espace client en un lieu où le dirigeant a envie de revenir, sans
créer de charge supplémentaire pour Serge ni figer les options de conseil.

## Principe directeur

Détailler les décisions prises, pas les options futures. Le client voit de la
rigueur (traçabilité, votes, météo) sans que le consultant se soit engagé sur
un chemin. C'est du conseil, pas de la spec.

## Fonctionnalités envisagées

### 1. Arbitrages / validations légères

Inspiré du système de Kevin. Le consultant pose 2-5 questions de cadrage entre
les points hebdo. Le client vote (OK / Pas OK / À discuter) et peut commenter.

Bénéfices :
- Structure les points hebdo (on arrive avec des votes, pas des discussions ouvertes)
- Trace les décisions (qui a validé quoi, quand)
- Protège le consultant (les choix sont actés)

Implémentation : table MySQL `arbitrages` (mission_slug, titre, contexte,
choix_propose, statut, votes JSON). Page PHP `arbitrages.php` avec rendu
Tailwind. Pas besoin de back-end complexe.

### 2. Météo mission (ressenti client)

Bonnavion aimait ça. À chaque connexion (ou 1 fois par semaine), le client
clique sur une icône météo (soleil / nuageux / orage / tempête) avec un
commentaire optionnel d'une ligne.

Bénéfices :
- Signal faible : détecte les frustrations avant qu'elles explosent
- Le client se sent écouté sans avoir à rédiger un mail
- Historique de météo visible = courbe de satisfaction

Implémentation : table MySQL `meteo` (mission_slug, user_id, date, score 1-4,
commentaire). Widget en haut du dashboard. Historique en mini-graphe (sparkline).
Notif Serge si score <= 2 (n8n).

### 3. Référent RC BPI

Pour les missions BPI, le référent régional (ex : Guillaume Rambaud pour FL Metal)
doit avoir un accès à l'espace. Aujourd'hui c'est géré par lien de partage
(token sans compte). On peut aller plus loin :

- Vue dédiée "référent BPI" : avancement global, jalons, météo, documents partagés
- Pas d'accès aux arbitrages internes ni aux messages client/consultant
- Tableau de bord simplifié : timeline + donut + derniers résumés
- Le référent peut laisser un commentaire (visible consultant uniquement)

Implémentation : rôle `referent_bpi` dans la table users. Page `bpi.php` avec
vue filtrée. Accès par lien avec token OU par compte (au choix du référent).

### 4. Vue avancement en première page

Le dashboard actuel montre les résumés. Le dirigeant veut savoir où on en est
en 10 secondes. Mettre en avant :
- Le donut d'avancement (déjà existant en image, à rendre dynamique)
- La prochaine étape clé (jalon)
- La dernière météo
- Le nombre d'arbitrages en attente de son vote

### 5. Fil de décisions (pas de discussion)

Chaque décision prise en point hebdo est logguée : quoi, qui a décidé, quand,
pourquoi (1 ligne). Remplace le CR de réunion que personne ne lit.

Implémentation : table MySQL `decisions` (mission_slug, date, titre, decideur,
contexte). Page ou section dans le dashboard. Chronologique, pas de réponse.

## Ce que ça ne doit PAS devenir

- Un outil de gestion de projet (pas de Gantt, pas de tickets)
- Un engagement de détail fonctionnel (on trace les décisions, pas les specs)
- Une charge pour le consultant (les arbitrages sont posés en 2 min, la météo
  est passive, le fil de décisions est alimenté par Cowork post-réunion)
- Un chat / messagerie (la zone messages existante suffit)

## Priorités

| Fonctionnalité | Effort | Impact client | Priorité |
|---|---|---|---|
| Météo mission | Faible (1 table, 1 widget) | Fort (signal faible) | 1 |
| Arbitrages / votes | Moyen (1 table, 1 page) | Fort (engagement) | 2 |
| Vue dashboard enrichie | Faible (refonte page existante) | Moyen (1ère impression) | 3 |
| Fil de décisions | Faible (1 table, 1 section) | Moyen (traçabilité) | 4 |
| Vue référent BPI | Moyen (1 rôle, 1 page) | Moyen (crédibilité BPI) | 5 |

## Stack

Même stack que l'existant : PHP 8 + MySQL + Tailwind sur O2switch. Pas de
framework JS, pas de Vercel. Souverain.

## Planning

Pas planifié. Ce brief est la matière de cadrage. À instruire dans une session
dédiée quand les V1.2 et V1.3 du backlog seront déployées.

---

*Ce fichier est un brief de cadrage, pas un engagement. Les fonctionnalités seront
affinées et priorisées au moment de l'implémentation.*
