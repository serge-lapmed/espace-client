<?php
/**
 * Espace Mission — Admin Mission
 * Édition de l'état dynamique d'une mission (V2)
 * Accessible uniquement par l'admin (Serge)
 *
 * URL : /admin-mission.php?slug=fl-metal-2026
 *
 * Version 1.0 — 2026-05-13
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

if (!auth_check() || !auth_has_role('admin')) {
    header('Location: /');
    exit;
}

$user = auth_user();
$slug = sanitize_slug($_GET['slug'] ?? '');
$mission = $slug ? load_mission($slug) : null;

if (!$mission) {
    header('Location: /admin.php');
    exit;
}

$message = '';
$error = '';

// ─── ACTIONS POST ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Mise à jour de l'état mission
    if ($action === 'update_state') {
        $fields = [];

        // Jours consommés
        if (isset($_POST['jours_consommes']) && $_POST['jours_consommes'] !== '') {
            $fields['jours_consommes'] = (float) $_POST['jours_consommes'];
        }

        // Phase actuelle
        if (!empty($_POST['phase_actuelle'])) {
            $fields['phase_actuelle'] = trim($_POST['phase_actuelle']);
        }

        // Statut phase
        if (!empty($_POST['phase_statut'])) {
            $fields['phase_statut'] = $_POST['phase_statut'];
        }

        // Statut mission
        if (!empty($_POST['statut'])) {
            $fields['statut'] = $_POST['statut'];
        }

        // Modules
        if (isset($_POST['modules']) && is_array($_POST['modules'])) {
            $fields['modules'] = array_values($_POST['modules']);
        }

        if (!empty($fields)) {
            try {
                save_mission_state($slug, $fields, $user['nom']);
                $message = 'État de la mission mis à jour.';
                // Recharger la mission pour voir les changements
                $mission = load_mission($slug);
            } catch (Exception $e) {
                $error = 'Erreur : ' . $e->getMessage();
            }
        }
    }

    // Ajouter un intervenant
    if ($action === 'add_intervenant') {
        $nom = trim($_POST['int_nom'] ?? '');
        $role = trim($_POST['int_role'] ?? '');
        $type = trim($_POST['int_type'] ?? 'equipe');

        if ($nom && $role) {
            // Charger les extra existants
            $db = get_db();
            $stmt = $db->prepare('SELECT intervenants_extra FROM mission_state WHERE mission_slug = ?');
            $stmt->execute([$slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $extras = $row ? json_decode($row['intervenants_extra'] ?? '[]', true) : [];
            if (!is_array($extras)) $extras = [];

            $extras[] = ['nom' => $nom, 'role' => $role, 'type' => $type];

            save_mission_state($slug, ['intervenants_extra' => $extras], $user['nom']);
            $message = "Intervenant ajouté : $nom";
            $mission = load_mission($slug);
        } else {
            $error = 'Nom et rôle sont obligatoires.';
        }
    }

    // Supprimer un intervenant extra
    if ($action === 'remove_intervenant') {
        $index = (int) ($_POST['int_index'] ?? -1);
        $db = get_db();
        $stmt = $db->prepare('SELECT intervenants_extra FROM mission_state WHERE mission_slug = ?');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $extras = $row ? json_decode($row['intervenants_extra'] ?? '[]', true) : [];

        if (is_array($extras) && isset($extras[$index])) {
            array_splice($extras, $index, 1);
            save_mission_state($slug, ['intervenants_extra' => $extras], $user['nom']);
            $message = 'Intervenant supprimé.';
            $mission = load_mission($slug);
        }
    }
}

// ─── DONNÉES ───
$all_modules = [
    'mission'   => 'Fiche mission',
    'resumes'   => 'Résumés hebdo',
    'messages'  => 'Messages',
    'documents' => 'Documents',
    'actions'   => "Plan d'action",
    'meteo'     => 'Météo mission',
    'arbitrages'=> 'Arbitrages / Votes',
    'decisions' => 'Fil de décisions',
    'points'   => 'Points hebdo',
];
$current_modules = $mission['modules'] ?? ['mission', 'resumes'];

$phases_list = array_map(fn($p) => $p['nom'], $mission['phases'] ?? []);
$current_phase = $mission['phase_actuelle'] ?? '';

// Séparer intervenants JSON vs extra BDD
$db = get_db();
$stmt = $db->prepare('SELECT intervenants_extra FROM mission_state WHERE mission_slug = ?');
$stmt->execute([$slug]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$extras_raw = $row ? json_decode($row['intervenants_extra'] ?? '[]', true) : [];
if (!is_array($extras_raw)) $extras_raw = [];

// Intervenants du JSON (non supprimables ici)
$json_path = MISSIONS_DIR . '/' . $slug . '/mission.json';
$json_data = json_decode(file_get_contents($json_path), true);
$json_intervenants = $json_data['intervenants'] ?? [];

$type_labels = [
    'sponsor' => 'Sponsor',
    'rc' => 'Référent',
    'consultant' => 'Consultant',
    'equipe' => 'Équipe',
    'externe' => 'Externe',
    'expert' => 'Expert',
    'direction' => 'Direction',
];

$statut_options = [
    'amorçage' => 'Amorçage',
    'en_cours' => 'En cours',
    'terminee' => 'Terminée',
    'archivee' => 'Archivée',
];

$phase_statut_options = [
    'a_venir' => 'À venir',
    'en_cours' => 'En cours',
    'termine' => 'Terminé',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — <?= htmlspecialchars($mission['client']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- Barre de navigation -->
    <div class="bg-white border-b border-gray-100 px-6 py-2 flex items-center justify-between text-xs text-gray-500">
        <div class="flex items-center gap-3">
            <a href="/admin.php" class="text-gray-400 hover:text-gray-700">&larr; Administration</a>
            <span class="text-gray-300">|</span>
            <a href="/<?= $slug ?>" class="text-gray-400 hover:text-gray-700">Voir la mission</a>
            <span class="font-medium text-gray-700"><?= htmlspecialchars($mission['client']) ?></span>
        </div>
        <a href="/logout.php" class="text-gray-400 hover:text-gray-700">Déconnexion</a>
    </div>

    <main class="max-w-3xl mx-auto px-6 py-8">

        <h1 class="text-xl font-semibold text-gray-800 mb-1"><?= htmlspecialchars($mission['client']) ?></h1>
        <p class="text-sm text-gray-500 mb-6"><?= htmlspecialchars($mission['titre']) ?></p>

        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg p-3 mb-6"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3 mb-6"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- ═══════════════════════════════════ -->
        <!-- ÉTAT DE LA MISSION                  -->
        <!-- ═══════════════════════════════════ -->
        <form method="POST" class="bg-white border border-gray-200 rounded-lg p-6 mb-8">
            <input type="hidden" name="action" value="update_state">
            <h2 class="text-base font-semibold text-gray-700 mb-4">État de la mission</h2>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <!-- Statut mission -->
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Statut mission</label>
                    <select name="statut" class="w-full px-3 py-2 border border-gray-300 rounded text-sm">
                        <?php foreach ($statut_options as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($mission['statut'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Jours consommés -->
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Jours consommés / <?= $mission['jours_total'] ?? '?' ?></label>
                    <input type="number" name="jours_consommes" step="0.5" min="0"
                           value="<?= htmlspecialchars($mission['jours_consommes'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <!-- Phase actuelle -->
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Phase actuelle</label>
                    <select name="phase_actuelle" class="w-full px-3 py-2 border border-gray-300 rounded text-sm">
                        <?php foreach ($phases_list as $phase_nom): ?>
                        <option value="<?= htmlspecialchars($phase_nom) ?>"
                            <?= $current_phase === $phase_nom ? 'selected' : '' ?>>
                            <?= htmlspecialchars($phase_nom) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Statut phase -->
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Statut de la phase</label>
                    <select name="phase_statut" class="w-full px-3 py-2 border border-gray-300 rounded text-sm">
                        <?php foreach ($phase_statut_options as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($mission['phase_statut'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Modules actifs -->
            <div class="mb-4">
                <label class="block text-sm text-gray-600 mb-2">Modules actifs</label>
                <div class="flex flex-wrap gap-3">
                    <?php foreach ($all_modules as $mod_key => $mod_label): ?>
                    <label class="flex items-center gap-1.5 text-sm text-gray-700">
                        <input type="checkbox" name="modules[]" value="<?= $mod_key ?>"
                            <?= in_array($mod_key, $current_modules) ? 'checked' : '' ?>
                            class="rounded border-gray-300">
                        <?= $mod_label ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($mission['_state_updated_at'] ?? null): ?>
            <p class="text-xs text-gray-400 mb-3">Dernière modification : <?= date('d/m/Y H:i', strtotime($mission['_state_updated_at'])) ?></p>
            <?php endif; ?>

            <button type="submit" class="bg-blue-600 text-white rounded px-4 py-2 text-sm font-medium hover:bg-blue-700">
                Enregistrer
            </button>
        </form>

        <!-- ═══════════════════════════════════ -->
        <!-- INTERVENANTS                        -->
        <!-- ═══════════════════════════════════ -->
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8">
            <h2 class="text-base font-semibold text-gray-700 mb-4">Intervenants</h2>

            <!-- Liste existante (JSON = non modifiable) -->
            <table class="w-full text-sm mb-4">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-2 font-medium text-gray-500">Nom</th>
                        <th class="text-left py-2 font-medium text-gray-500">Rôle</th>
                        <th class="text-left py-2 font-medium text-gray-500">Type</th>
                        <th class="text-left py-2 font-medium text-gray-500">Source</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($json_intervenants as $int): ?>
                    <tr class="border-b border-gray-100">
                        <td class="py-2 font-medium"><?= htmlspecialchars($int['nom']) ?></td>
                        <td class="py-2 text-gray-500"><?= htmlspecialchars($int['role']) ?></td>
                        <td class="py-2">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                <?= $type_labels[$int['type'] ?? ''] ?? $int['type'] ?? '—' ?>
                            </span>
                        </td>
                        <td class="py-2 text-xs text-gray-400">JSON</td>
                        <td class="py-2"></td>
                    </tr>
                    <?php endforeach; ?>

                    <?php foreach ($extras_raw as $i => $int): ?>
                    <tr class="border-b border-gray-100">
                        <td class="py-2 font-medium"><?= htmlspecialchars($int['nom']) ?></td>
                        <td class="py-2 text-gray-500"><?= htmlspecialchars($int['role']) ?></td>
                        <td class="py-2">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-600">
                                <?= $type_labels[$int['type'] ?? ''] ?? $int['type'] ?? '—' ?>
                            </span>
                        </td>
                        <td class="py-2 text-xs text-blue-500">IHM</td>
                        <td class="py-2">
                            <form method="POST" class="inline" onsubmit="return confirm('Supprimer cet intervenant ?');">
                                <input type="hidden" name="action" value="remove_intervenant">
                                <input type="hidden" name="int_index" value="<?= $i ?>">
                                <button type="submit" class="text-xs text-gray-400 hover:text-red-600">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Ajouter un intervenant -->
            <details>
                <summary class="text-sm text-blue-600 cursor-pointer hover:underline">+ Ajouter un intervenant</summary>
                <form method="POST" class="grid grid-cols-4 gap-3 mt-3">
                    <input type="hidden" name="action" value="add_intervenant">
                    <input type="text" name="int_nom" placeholder="Nom complet" required
                           class="px-3 py-2 border border-gray-300 rounded text-sm">
                    <input type="text" name="int_role" placeholder="Rôle (ex: Resp. Production)"  required
                           class="px-3 py-2 border border-gray-300 rounded text-sm">
                    <select name="int_type" class="px-3 py-2 border border-gray-300 rounded text-sm">
                        <?php foreach ($type_labels as $val => $label): ?>
                        <option value="<?= $val ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="bg-blue-600 text-white rounded py-2 text-sm font-medium hover:bg-blue-700">
                        Ajouter
                    </button>
                </form>
            </details>
        </div>

        <!-- Info source -->
        <div class="text-xs text-gray-400 mt-4">
            <p>Les données structurelles (client, titre, phases, livrables) restent dans <code>mission.json</code>.</p>
            <p>Les modifications ici sont stockées en base de données et s'appliquent immédiatement.</p>
        </div>

    </main>
</body>
</html>
