<?php
/**
 * Espace Mission — Admin (Serge only)
 * Gestion des utilisateurs et liens de partage
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

if (!auth_check() || !auth_has_role('admin')) {
    header('Location: /');
    exit;
}

$user = auth_user();
$message = '';
$error = '';

// ─── ACTIONS ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Créer un utilisateur
    if ($action === 'create_user') {
        $email = $_POST['email'] ?? '';
        $nom = $_POST['nom'] ?? '';
        $role = $_POST['role'] ?? 'equipe';
        $mission = $_POST['mission_slug'] ?? null;
        $temp_pass = $_POST['temp_password'] ?? bin2hex(random_bytes(4));

        if ($email && $nom) {
            try {
                auth_create_user($email, $nom, $role, $mission ?: null, $temp_pass);
                $message = "Utilisateur créé : $nom ($email) — Mot de passe temporaire : $temp_pass";
            } catch (Exception $e) {
                $error = "Erreur : " . $e->getMessage();
            }
        }
    }

    // Créer un lien de partage
    if ($action === 'create_link') {
        $mission = $_POST['mission_slug'] ?? '';
        $label = $_POST['label'] ?? '';
        $role = $_POST['role'] ?? 'externe';

        if ($mission && $label) {
            $token = auth_create_share_link($mission, $label, $role);
            $link = SITE_URL . '/s/' . $token;
            $message = "Lien créé pour $label : <a href='$link' class='text-blue-600 underline' target='_blank'>$link</a>";
        }
    }

    // Désactiver un utilisateur
    if ($action === 'toggle_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid) {
            $db = get_db();
            $db->prepare('UPDATE users SET actif = NOT actif WHERE id = ? AND id != ?')->execute([$uid, $user['id']]);
            $message = "Utilisateur mis à jour.";
        }
    }
}

// ─── DONNÉES ───
$users = auth_list_users();
$missions = list_missions();
$db = get_db();
$share_links = $db->query('SELECT * FROM share_links ORDER BY created_at DESC')->fetchAll();

$role_labels = ['admin' => 'Admin', 'dirigeant' => 'Dirigeant', 'equipe' => 'Équipe', 'externe' => 'Externe'];
$role_colors = ['admin' => 'bg-purple-100 text-purple-700', 'dirigeant' => 'bg-blue-100 text-blue-700', 'equipe' => 'bg-green-100 text-green-700', 'externe' => 'bg-gray-100 text-gray-600'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration — <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">

    <div class="bg-white border-b border-gray-100 px-6 py-2 flex items-center justify-between text-xs text-gray-500">
        <div class="flex items-center gap-3">
            <a href="/" class="text-gray-400 hover:text-gray-700">&larr; Espace Mission</a>
            <span class="font-medium text-gray-700">Administration</span>
        </div>
        <a href="/logout.php" class="text-gray-400 hover:text-gray-700">Déconnexion</a>
    </div>

    <main class="max-w-4xl mx-auto px-6 py-8">

        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg p-3 mb-6"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3 mb-6"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- UTILISATEURS -->
        <section class="mb-10">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Utilisateurs</h2>

            <table class="w-full bg-white rounded-lg border border-gray-200 text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Nom</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Email</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Rôle</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Mission</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Dernier accès</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr class="border-b border-gray-100 <?= $u['actif'] ? '' : 'opacity-40' ?>">
                        <td class="px-4 py-3 font-medium"><?= htmlspecialchars($u['nom']) ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($u['email']) ?></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $role_colors[$u['role']] ?? '' ?>">
                                <?= $role_labels[$u['role']] ?? $u['role'] ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500"><?= $u['mission_slug'] ?? '<em>toutes</em>' ?></td>
                        <td class="px-4 py-3 text-gray-400"><?= $u['last_login'] ? date('d/m H:i', strtotime($u['last_login'])) : '—' ?></td>
                        <td class="px-4 py-3">
                            <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="toggle_user">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="text-xs text-gray-400 hover:text-red-600">
                                    <?= $u['actif'] ? 'Désactiver' : 'Réactiver' ?>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <!-- AJOUTER UN UTILISATEUR -->
            <details class="mt-4">
                <summary class="text-sm text-blue-600 cursor-pointer hover:underline">+ Ajouter un utilisateur</summary>
                <form method="POST" class="bg-white border border-gray-200 rounded-lg p-4 mt-2 grid grid-cols-2 gap-3">
                    <input type="hidden" name="action" value="create_user">
                    <input type="text" name="nom" placeholder="Nom complet" required class="px-3 py-2 border border-gray-300 rounded text-sm">
                    <input type="email" name="email" placeholder="Email" required class="px-3 py-2 border border-gray-300 rounded text-sm">
                    <select name="role" class="px-3 py-2 border border-gray-300 rounded text-sm">
                        <option value="dirigeant">Dirigeant</option>
                        <option value="equipe">Équipe</option>
                        <option value="externe">Externe</option>
                        <option value="admin">Admin</option>
                    </select>
                    <select name="mission_slug" class="px-3 py-2 border border-gray-300 rounded text-sm">
                        <option value="">Toutes les missions</option>
                        <?php foreach ($missions as $m): ?>
                        <option value="<?= $m['slug'] ?>"><?= htmlspecialchars($m['client']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="temp_password" placeholder="Mot de passe temporaire" class="px-3 py-2 border border-gray-300 rounded text-sm" value="<?= bin2hex(random_bytes(4)) ?>">
                    <button type="submit" class="bg-blue-600 text-white rounded py-2 text-sm font-medium hover:bg-blue-700">Créer</button>
                </form>
            </details>
        </section>

        <!-- LIENS DE PARTAGE -->
        <section>
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Liens de partage</h2>
            <p class="text-sm text-gray-500 mb-4">Accès sans compte — idéal pour BPI, référents, invités ponctuels.</p>

            <?php if ($share_links): ?>
            <table class="w-full bg-white rounded-lg border border-gray-200 text-sm mb-4">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Label</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Mission</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Rôle</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Vues</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500">Lien</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($share_links as $sl): ?>
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-3"><?= htmlspecialchars($sl['label']) ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($sl['mission_slug']) ?></td>
                        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $role_colors[$sl['role']] ?? '' ?>"><?= $role_labels[$sl['role']] ?? $sl['role'] ?></span></td>
                        <td class="px-4 py-3 text-gray-400"><?= $sl['views'] ?></td>
                        <td class="px-4 py-3"><code class="text-xs bg-gray-100 px-2 py-1 rounded">/s/<?= $sl['token'] ?></code></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <details>
                <summary class="text-sm text-blue-600 cursor-pointer hover:underline">+ Créer un lien de partage</summary>
                <form method="POST" class="bg-white border border-gray-200 rounded-lg p-4 mt-2 grid grid-cols-3 gap-3">
                    <input type="hidden" name="action" value="create_link">
                    <input type="text" name="label" placeholder="Label (ex: Guillaume BPI)" required class="px-3 py-2 border border-gray-300 rounded text-sm">
                    <select name="mission_slug" required class="px-3 py-2 border border-gray-300 rounded text-sm">
                        <?php foreach ($missions as $m): ?>
                        <option value="<?= $m['slug'] ?>"><?= htmlspecialchars($m['client']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="role" class="px-3 py-2 border border-gray-300 rounded text-sm">
                        <option value="externe">Externe (synthèse)</option>
                        <option value="equipe">Équipe (opérationnel)</option>
                        <option value="dirigeant">Dirigeant (tout)</option>
                    </select>
                    <button type="submit" class="bg-blue-600 text-white rounded py-2 text-sm font-medium hover:bg-blue-700 col-span-3">Créer le lien</button>
                </form>
            </details>
        </section>

    </main>
</body>
</html>
