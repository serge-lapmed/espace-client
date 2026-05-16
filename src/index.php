<?php
/**
 * Espace Mission — La PME Digitale
 * Point d'entrée unique avec authentification
 *
 * URLs :
 *   /                    → liste des missions (ou redirect si une seule)
 *   /fl-metal-2026       → page mission (onglet résumés par défaut)
 *   /fl-metal-2026/S14   → résumé spécifique
 *   /s/TOKEN             → accès par lien de partage (BPI, externes)
 *   /login.php           → connexion
 *   /logout.php          → déconnexion
 *   /admin.php           → gestion utilisateurs (admin only)
 */

// --- Fichiers statiques (livrables HTML, images des résumés…) ---
// Gestion en PHP car %{DOCUMENT_ROOT} Apache ne résout pas sur O2switch mutualisé
$_static_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_static_types = [
    'html' => 'text/html; charset=utf-8',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'svg'  => 'image/svg+xml',
    'pdf'  => 'application/pdf',
];
$_static_ext = strtolower(pathinfo($_static_uri, PATHINFO_EXTENSION));
if (isset($_static_types[$_static_ext])) {
    $_static_file = __DIR__ . $_static_uri;
    if (file_exists($_static_file)) {
        header('Content-Type: ' . $_static_types[$_static_ext]);
        readfile($_static_file);
        exit;
    }
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

// Routing simple
$request = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts = explode('/', $request);

// ─── LIEN DE PARTAGE : /s/TOKEN ───
if (($parts[0] ?? '') === 's' && !empty($parts[1])) {
    $share = auth_check_share_token($parts[1]);
    if (!$share) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:4rem"><h1>Lien invalide ou expiré</h1><p>Contactez votre consultant pour obtenir un nouveau lien.</p></body></html>';
        exit;
    }
    auth_start();
    $_SESSION['user_id'] = 'share_' . $share['id'];
    $_SESSION['user_email'] = 'share';
    $_SESSION['user_nom'] = $share['label'] ?? 'Invité';
    $_SESSION['user_role'] = $share['role'];
    $_SESSION['user_mission_slug'] = $share['mission_slug'];
    $_SESSION['is_share_link'] = true;
    header('Location: /' . $share['mission_slug']);
    exit;
}

// ─── AUTH CHECK ───
if (!auth_check()) {
    header('Location: /login.php');
    exit;
}

$user = auth_user();

// ─── POST: MESSAGE ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'post_message') {
    $slug = sanitize_slug($_POST['mission_slug'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($slug && $content && auth_can_access_mission($slug)) {
        $db = get_db();
        $stmt = $db->prepare('INSERT INTO messages (mission_slug, author_id, author_name, author_role, content) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$slug, $user['id'], $user['nom'], $user['role'], $content]);
    }
    header('Location: /' . $slug . '?tab=messages');
    exit;
}

// ─── POST: METEO ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'post_meteo') {
    $slug = sanitize_slug($_POST['mission_slug'] ?? '');
    $score = intval($_POST['score'] ?? 0);
    $commentaire = trim(mb_substr($_POST['commentaire'] ?? '', 0, 280));
    if ($slug && $score >= 1 && $score <= 4 && auth_can_access_mission($slug)) {
        $db = get_db();
        $stmt = $db->prepare('INSERT INTO meteo (mission_slug, user_id, score, commentaire) VALUES (?, ?, ?, ?)');
        $stmt->execute([$slug, $user['id'], $score, $commentaire]);
    }
    header('Location: /' . $slug . '?tab=' . ($_POST['redirect_tab'] ?? 'resumes'));
    exit;
}

// ─── POST: ARBITRAGE (créer) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_arbitrage') {
    $slug = sanitize_slug($_POST['mission_slug'] ?? '');
    $titre = trim($_POST['titre'] ?? '');
    $contexte = trim($_POST['contexte'] ?? '');
    $choix = trim($_POST['choix_propose'] ?? '');
    if ($slug && $titre && $user['role'] === 'admin' && auth_can_access_mission($slug)) {
        $db = get_db();
        $stmt = $db->prepare('INSERT INTO arbitrages (mission_slug, titre, contexte, choix_propose, statut, created_by) VALUES (?, ?, ?, ?, "ouvert", ?)');
        $stmt->execute([$slug, $titre, $contexte, $choix, $user['id']]);
    }
    header('Location: /' . $slug . '?tab=arbitrages');
    exit;
}

// ─── POST: ARBITRAGE (voter) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'vote_arbitrage') {
    $slug = sanitize_slug($_POST['mission_slug'] ?? '');
    $arb_id = intval($_POST['arbitrage_id'] ?? 0);
    $vote = $_POST['vote'] ?? '';
    $commentaire = trim($_POST['commentaire'] ?? '');
    if ($slug && $arb_id && in_array($vote, ['ok', 'pas_ok', 'a_discuter']) && auth_can_access_mission($slug)) {
        $db = get_db();
        $stmt = $db->prepare('INSERT INTO arbitrage_votes (arbitrage_id, user_id, vote, commentaire) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE vote = VALUES(vote), commentaire = VALUES(commentaire)');
        $stmt->execute([$arb_id, $user['id'], $vote, $commentaire]);
    }
    header('Location: /' . $slug . '?tab=arbitrages');
    exit;
}

// ─── POST: ARBITRAGE (clore) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'close_arbitrage') {
    $slug = sanitize_slug($_POST['mission_slug'] ?? '');
    $arb_id = intval($_POST['arbitrage_id'] ?? 0);
    if ($slug && $arb_id && $user['role'] === 'admin' && auth_can_access_mission($slug)) {
        $db = get_db();
        $stmt = $db->prepare('UPDATE arbitrages SET statut = "clos", closed_at = NOW() WHERE id = ? AND mission_slug = ?');
        $stmt->execute([$arb_id, $slug]);
    }
    header('Location: /' . $slug . '?tab=arbitrages');
    exit;
}

// ─── POST: DECISION ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_decision') {
    $slug = sanitize_slug($_POST['mission_slug'] ?? '');
    $titre = trim($_POST['titre'] ?? '');
    $decideur = trim($_POST['decideur'] ?? '');
    $contexte = trim($_POST['contexte'] ?? '');
    $date_decision = $_POST['date_decision'] ?? date('Y-m-d');
    if ($slug && $titre && $user['role'] === 'admin' && auth_can_access_mission($slug)) {
        $db = get_db();
        $stmt = $db->prepare('INSERT INTO decisions (mission_slug, titre, decideur, contexte, date_decision, created_by) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$slug, $titre, $decideur, $contexte, $date_decision, $user['id']]);
    }
    header('Location: /' . $slug . '?tab=decisions');
    exit;
}

// ─── POST: POINT HEBDO (créer) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_point') {
    $slug = sanitize_slug($_POST['mission_slug'] ?? '');
    $semaine = trim($_POST['semaine'] ?? '');
    $date_point = $_POST['date_point'] ?? date('Y-m-d');
    $ordre_du_jour = trim($_POST['ordre_du_jour'] ?? '');
    $frequence = trim($_POST['frequence'] ?? 'hebdomadaire');
    if ($slug && $semaine && $user['role'] === 'admin' && auth_can_access_mission($slug)) {
        $db = get_db();
        $stmt = $db->prepare('INSERT INTO points_hebdo (mission_slug, semaine, date_point, frequence, ordre_du_jour, statut, created_by) VALUES (?, ?, ?, ?, ?, "brouillon", ?)');
        $stmt->execute([$slug, $semaine, $date_point, $frequence, $ordre_du_jour, $user['id']]);
        $new_point_id = $db->lastInsertId();
        // Dupliquer les actions non faites du point précédent
        $stmt = $db->prepare('SELECT id FROM points_hebdo WHERE mission_slug = ? AND id != ? ORDER BY date_point DESC, id DESC LIMIT 1');
        $stmt->execute([$slug, $new_point_id]);
        $prev = $stmt->fetch();
        if ($prev) {
            $stmt = $db->prepare('SELECT * FROM point_actions WHERE point_id = ? AND statut != "fait" ORDER BY ordre, id');
            $stmt->execute([$prev['id']]);
            $old_actions = $stmt->fetchAll();
            foreach ($old_actions as $idx => $oa) {
                $stmt2 = $db->prepare('INSERT INTO point_actions (point_id, mission_slug, titre, responsable, echeance, statut, ordre) VALUES (?, ?, ?, ?, ?, "reporte", ?)');
                $stmt2->execute([$new_point_id, $slug, $oa['titre'], $oa['responsable'], $oa['echeance'], $idx]);
            }
        }
        header('Location: /' . $slug . '?tab=points&point_id=' . $new_point_id);
        exit;
    }
    header('Location: /' . $slug . '?tab=points');
    exit;
}

// ─── POST: POINT HEBDO (mettre à jour) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_point') {
    $slug = sanitize_slug($_POST['mission_slug'] ?? '');
    $point_id = intval($_POST['point_id'] ?? 0);
    if ($slug && $point_id && $user['role'] === 'admin' && auth_can_access_mission($slug)) {
        $db = get_db();
        $fields = [];
        $params = [];
        foreach (['ordre_du_jour', 'resume', 'avancement', 'prochaines_etapes'] as $f) {
            if (isset($_POST[$f])) { $fields[] = "$f = ?"; $params[] = trim($_POST[$f]); }
        }
        if (isset($_POST['temps_passe'])) { $fields[] = "temps_passe = ?"; $params[] = floatval($_POST['temps_passe']); }
        if (!empty($fields)) {
            $fields[] = "updated_at = NOW()";
            $params[] = $point_id;
            $params[] = $slug;
            $stmt = $db->prepare('UPDATE points_hebdo SET ' . implode(', ', $fields) . ' WHERE id = ? AND mission_slug = ?');
            $stmt->execute($params);
        }
    }
    header('Location: /' . $slug . '?tab=points&point_id=' . $point_id);
    exit;
}

// ─── POST: POINT HEBDO (publier) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'publish_point') {
    $slug = sanitize_slug($_POST['mission_slug'] ?? '');
    $point_id = intval($_POST['point_id'] ?? 0);
    if ($slug && $point_id && $user['role'] === 'admin' && auth_can_access_mission($slug)) {
        $db = get_db();
        $stmt = $db->prepare('UPDATE points_hebdo SET statut = "publie", published_at = NOW(), updated_at = NOW() WHERE id = ? AND mission_slug = ?');
        $stmt->execute([$point_id, $slug]);
    }
    header('Location: /' . $slug . '?tab=points&point_id=' . $point_id);
    exit;
}

// ─── POST: POINT ACTION (ajouter) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_point_action') {
    $slug = sanitize_slug($_POST['mission_slug'] ?? '');
    $point_id = intval($_POST['point_id'] ?? 0);
    $titre = trim($_POST['titre'] ?? '');
    $responsable = trim($_POST['responsable'] ?? '');
    $echeance = $_POST['echeance'] ?? null;
    if ($slug && $point_id && $titre && $user['role'] === 'admin' && auth_can_access_mission($slug)) {
        $db = get_db();
        $stmt = $db->prepare('SELECT COALESCE(MAX(ordre), -1) + 1 as next_ordre FROM point_actions WHERE point_id = ?');
        $stmt->execute([$point_id]);
        $next = $stmt->fetch();
        $ordre = $next['next_ordre'] ?? 0;
        $stmt = $db->prepare('INSERT INTO point_actions (point_id, mission_slug, titre, responsable, echeance, statut, ordre) VALUES (?, ?, ?, ?, ?, "a_faire", ?)');
        $stmt->execute([$point_id, $slug, $titre, $responsable, $echeance ?: null, $ordre]);
    }
    header('Location: /' . $slug . '?tab=points&point_id=' . $point_id);
    exit;
}

// ─── POST: POINT ACTION (toggle) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_action') {
    $slug = sanitize_slug($_POST['mission_slug'] ?? '');
    $action_id = intval($_POST['action_id'] ?? 0);
    $point_id = intval($_POST['point_id'] ?? 0);
    $new_statut = null;
    if ($slug && $action_id && auth_can_access_mission($slug)) {
        $db = get_db();
        $stmt = $db->prepare('SELECT statut FROM point_actions WHERE id = ? AND mission_slug = ?');
        $stmt->execute([$action_id, $slug]);
        $act = $stmt->fetch();
        if ($act) {
            $new_statut = ($act['statut'] === 'fait') ? 'a_faire' : 'fait';
            $stmt = $db->prepare('UPDATE point_actions SET statut = ?, checked_by = ?, checked_at = NOW() WHERE id = ?');
            $stmt->execute([$new_statut, $user['id'], $action_id]);
        }
    }
    // AJAX: répondre en JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        // Recompter les actions
        $db = get_db();
        $stmt = $db->prepare('SELECT COUNT(*) as total, SUM(statut = "fait") as faites FROM point_actions WHERE point_id = ?');
        $stmt->execute([$point_id]);
        $counts = $stmt->fetch();
        echo json_encode(['ok' => true, 'new_statut' => $new_statut, 'action_id' => $action_id, 'total' => intval($counts['total'] ?? 0), 'faites' => intval($counts['faites'] ?? 0)]);
        exit;
    }
    header('Location: /' . $slug . '?tab=points&point_id=' . $point_id);
    exit;
}

// ─── POST: POINT MÉTÉO ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'post_point_meteo') {
    $slug = sanitize_slug($_POST['mission_slug'] ?? '');
    $point_id = intval($_POST['point_id'] ?? 0);
    $score = intval($_POST['score'] ?? 0);
    $commentaire = trim(mb_substr($_POST['commentaire'] ?? '', 0, 280));
    if ($slug && $point_id && $score >= 1 && $score <= 4 && auth_can_access_mission($slug)) {
        $db = get_db();
        $stmt = $db->prepare('INSERT INTO point_meteo (point_id, user_id, score, commentaire) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE score = VALUES(score), commentaire = VALUES(commentaire), created_at = NOW()');
        $stmt->execute([$point_id, $user['id'], $score, $commentaire]);
    }
    // AJAX: répondre en JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'score' => $score, 'commentaire' => $commentaire, 'user_nom' => $user['nom']]);
        exit;
    }
    header('Location: /' . $slug . '?tab=points&point_id=' . $point_id);
    exit;
}



// ─── ROUTING ───
$mission_slug = sanitize_slug($parts[0] ?? '');
$resume_id = $parts[1] ?? null;
$active_tab = $_GET['tab'] ?? 'resumes';

// Page d'accueil
if (empty($mission_slug)) {
    $missions = list_missions();
    if ($user['role'] !== 'admin' && $user['mission_slug']) {
        $missions = array_filter($missions, fn($m) => $m['slug'] === $user['mission_slug']);
        $missions = array_values($missions);
    }
    if (count($missions) === 1) {
        header('Location: /' . $missions[0]['slug']);
        exit;
    }
    render_home($missions, $user);
    exit;
}

// Vérifier accès
if (!auth_can_access_mission($mission_slug)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:4rem"><h1>Accès refusé</h1><p>Vous n\'avez pas accès à cette mission.</p><p><a href="/">Retour</a></p></body></html>';
    exit;
}

// Charger la mission
$mission = load_mission($mission_slug);
if (!$mission) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body><h1>Mission non trouvée</h1><p><a href="/">Retour</a></p></body></html>';
    exit;
}

// Si on a un resume_id dans l'URL, forcer l'onglet résumés
if ($resume_id) $active_tab = 'resumes';

// Sécurité : si l'onglet demandé n'est pas dans les modules autorisés, rediriger vers le premier
// (empêche un utilisateur externe de forcer ?tab=documents)
$_role_modules = ['externe' => ['mission', 'resumes'], 'equipe' => ['mission', 'resumes', 'messages', 'documents', 'actions', 'meteo', 'arbitrages', 'points'], 'dirigeant' => ['mission', 'resumes', 'messages', 'documents', 'actions', 'meteo', 'arbitrages', 'decisions', 'points']];
$_allowed = $_role_modules[$user['role']] ?? null;
if ($_allowed !== null && !in_array($active_tab, $_allowed)) {
    $active_tab = $_allowed[0] ?? 'resumes';
}

// ─── V2.1: ROUTAGE FICHIERS HTML LIVRABLES ───
// URLs comme /bonnavion-2025/diagnostics/gouvernance_si.html
if ($resume_id && count($parts) >= 2) {
    $sub_path = implode('/', array_slice($parts, 1));
    if (preg_match('/\.html$/i', $sub_path)) {
        $html_file = __DIR__ . '/' . $mission_slug . '/' . $sub_path;
        $html_file = realpath($html_file);
        $base_dir = realpath(__DIR__ . '/' . $mission_slug);
        // Sécurité : vérifier que le fichier est bien dans le dossier mission
        if ($html_file && $base_dir && str_starts_with($html_file, $base_dir) && file_exists($html_file)) {
            // Servir dans une page avec iframe pleine page
            ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(basename($sub_path, '.html')) ?> — <?= htmlspecialchars($mission['client']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        iframe { border: none; width: 100%; height: calc(100vh - 56px); }
    </style>
</head>
<body class="bg-gray-50">
    <div class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="/<?= $mission_slug ?>" class="text-sm text-blue-600 hover:underline flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                <?= htmlspecialchars($mission['client']) ?>
            </a>
            <span class="text-gray-300">/</span>
            <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars(basename($sub_path, '.html')) ?></span>
        </div>
    </div>
    <iframe src="/<?= htmlspecialchars($mission_slug . '/' . $sub_path) ?>?raw=1"></iframe>
</body>
</html>
            <?php
            exit;
        }
    }
}

$resumes = list_resumes($mission_slug);
render_mission($mission, $resumes, $resume_id, $user, $active_tab);

// ─────────────────────────────────────────────
// FONCTIONS DE RENDU
// ─────────────────────────────────────────────

function render_user_bar(array $user): void {
    $is_share = $_SESSION['is_share_link'] ?? false;
    $role_labels = ['admin' => 'Administrateur', 'dirigeant' => 'Dirigeant', 'equipe' => 'Équipe', 'externe' => 'Observateur'];
    $role_colors = ['admin' => 'bg-purple-100 text-purple-700', 'dirigeant' => 'bg-blue-100 text-blue-700', 'equipe' => 'bg-green-100 text-green-700', 'externe' => 'bg-gray-100 text-gray-600'];
?>
    <div class="bg-white border-b border-gray-100 px-6 py-2 flex items-center justify-between text-xs text-gray-500">
        <div class="flex items-center gap-3">
            <span class="font-medium text-gray-700"><?= htmlspecialchars($user['nom']) ?></span>
            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $role_colors[$user['role']] ?? '' ?>">
                <?= $role_labels[$user['role']] ?? $user['role'] ?>
            </span>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($user['role'] === 'admin'): ?>
                <a href="/admin.php" class="text-gray-400 hover:text-gray-700">Gestion</a>
            <?php endif; ?>
            <?php if (!$is_share): ?>
                <a href="/logout.php" class="text-gray-400 hover:text-gray-700">Déconnexion</a>
            <?php endif; ?>
        </div>
    </div>
<?php
}

function render_home(array $missions, array $user): void {
    $type_labels = [
        'diagnostic-si' => 'Diagnostic SI',
        'cadrage-si' => 'Cadrage SI',
        'accompagnement-transfo' => 'Transformation',
        'amoa-gouvernance' => 'AMOA & Gouvernance',
        'dsi-d' => 'DSI externalisée',
    ];
    $statut_labels = [
        'amorçage' => 'Amorçage',
        'en_cours' => 'En cours',
        'terminee' => 'Terminée',
        'archivee' => 'Archivée',
    ];
    $statut_colors = [
        'amorçage' => 'bg-yellow-100 text-yellow-700',
        'en_cours' => 'bg-blue-100 text-blue-700',
        'terminee' => 'bg-green-100 text-green-700',
        'archivee' => 'bg-gray-100 text-gray-500',
    ];

    // Séparer actives et terminées
    $actives = array_filter($missions, fn($m) => in_array($m['statut'] ?? 'en_cours', ['amorçage', 'en_cours']));
    $terminees = array_filter($missions, fn($m) => in_array($m['statut'] ?? '', ['terminee', 'archivee']));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <?php render_user_bar($user); ?>
    <header class="bg-white border-b border-gray-200 px-6 py-4">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-xl font-semibold text-gray-800"><?= SITE_NAME ?></h1>
            <p class="text-sm text-gray-500"><?= CONSULTANT_TITLE ?></p>
        </div>
    </header>
    <main class="max-w-4xl mx-auto px-6 py-10">

        <!-- MISSIONS ACTIVES -->
        <h2 class="text-lg font-medium text-gray-700 mb-4">Missions en cours</h2>
        <?php if (empty($actives)): ?>
            <p class="text-gray-400 text-center py-6 mb-8">Aucune mission active.</p>
        <?php else: ?>
        <div class="space-y-3 mb-10">
        <?php foreach ($actives as $m):
            $statut = $m['statut'] ?? 'en_cours';
            $type = $m['type'] ?? '';
        ?>
            <a href="/<?= $m['slug'] ?>" class="block bg-white rounded-lg border border-gray-200 p-5 hover:border-blue-400 hover:shadow-sm transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <?php if (!empty($m['logo_client'])): ?>
                        <img src="<?= htmlspecialchars($m['logo_client']) ?>" alt="" class="h-8 w-8 rounded object-contain flex-shrink-0">
                        <?php endif; ?>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($m['client']) ?></h3>
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full <?= $statut_colors[$statut] ?? 'bg-gray-100 text-gray-600' ?>">
                                    <?= $statut_labels[$statut] ?? $statut ?>
                                </span>
                            </div>
                            <p class="text-sm text-gray-500 mt-0.5"><?= htmlspecialchars($m['titre']) ?></p>
                            <?php if ($type && isset($type_labels[$type])): ?>
                            <span class="text-xs text-gray-400"><?= $type_labels[$type] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0 ml-4">
                        <span class="inline-block px-3 py-1 text-xs font-medium rounded-full
                            <?= ($m['phase_statut'] ?? '') === 'en_cours' ? 'bg-blue-50 text-blue-600' : 'bg-gray-50 text-gray-500' ?>">
                            <?= htmlspecialchars($m['phase_actuelle'] ?? '—') ?>
                        </span>
                        <?php if (!empty($m['jours_total'])): ?>
                        <p class="text-xs text-gray-400 mt-1"><?= $m['jours_consommes'] ?? 0 ?>/<?= $m['jours_total'] ?>j</p>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- MISSIONS TERMINÉES -->
        <?php if (!empty($terminees)): ?>
        <h2 class="text-sm font-medium text-gray-400 uppercase tracking-wide mb-3">Missions précédentes</h2>
        <div class="space-y-2">
        <?php foreach ($terminees as $m):
            $statut = $m['statut'] ?? 'terminee';
        ?>
            <a href="/<?= $m['slug'] ?>" class="block bg-white rounded-lg border border-gray-100 p-4 opacity-60 hover:opacity-80 transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <?php if (!empty($m['logo_client'])): ?>
                        <img src="<?= htmlspecialchars($m['logo_client']) ?>" alt="" class="h-6 w-6 rounded object-contain flex-shrink-0 grayscale">
                        <?php endif; ?>
                        <div>
                            <h3 class="font-medium text-gray-600 text-sm"><?= htmlspecialchars($m['client']) ?></h3>
                            <p class="text-xs text-gray-400"><?= htmlspecialchars($m['titre']) ?></p>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-50 text-green-600">
                        <?= $statut_labels[$statut] ?? 'Terminée' ?>
                    </span>
                </div>
            </a>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </main>
</body>
</html>
<?php
}

function render_mission(array $mission, array $resumes, ?string $resume_id, array $user, string $active_tab): void {
    $active_resume = null;
    if ($resume_id) {
        foreach ($resumes as $r) {
            if ($r['id'] === $resume_id) { $active_resume = $r; break; }
        }
    }
    if (!$active_resume && !empty($resumes)) {
        $active_resume = $resumes[0];
    }

    $phases = $mission['phases'] ?? [];
    $modules = $mission['modules'] ?? ['resumes'];
    $financeur = $mission['financeur'] ?? null;
    $show_budget = in_array($user['role'], ['admin', 'dirigeant']);

    // Filtrage des modules par rôle
    // externe (BPI) : uniquement Mission + Résumés
    // equipe : tout sauf le budget (déjà géré par $show_budget)
    $role_modules = [
        'externe' => ['mission', 'resumes'],
        'equipe'  => ['mission', 'resumes', 'messages', 'documents', 'actions', 'meteo', 'arbitrages', 'points'],
        'dirigeant' => null, // null = tous les modules
        'admin' => null,
    ];
    $allowed = $role_modules[$user['role']] ?? null;
    if ($allowed !== null) {
        $modules = array_values(array_intersect($modules, $allowed));
    }
    $slug = $mission['slug'];

    // Charger les données selon l'onglet actif
    $db = get_db();
    $messages = [];
    $documents = [];
    $actions = [];

    if (in_array('messages', $modules)) {
        $stmt = $db->prepare('SELECT * FROM messages WHERE mission_slug = ? ORDER BY created_at DESC LIMIT 50');
        $stmt->execute([$slug]);
        $messages = $stmt->fetchAll();
    }
    if (in_array('documents', $modules)) {
        $stmt = $db->prepare('SELECT * FROM documents WHERE mission_slug = ? ORDER BY created_at DESC');
        $stmt->execute([$slug]);
        $all_docs = $stmt->fetchAll();
        // Filtrer par visibilité
        foreach ($all_docs as $doc) {
            if ($doc['visibility'] === 'all') { $documents[] = $doc; continue; }
            if ($doc['visibility'] === 'dirigeant' && in_array($user['role'], ['admin', 'dirigeant'])) { $documents[] = $doc; continue; }
            if ($doc['visibility'] === 'admin' && $user['role'] === 'admin') { $documents[] = $doc; continue; }
        }
    }
    if (in_array('actions', $modules)) {
        $stmt = $db->prepare('SELECT * FROM actions WHERE mission_slug = ? ORDER BY FIELD(statut, "en_cours", "a_faire", "fait", "annule"), priorite = "haute" DESC, echeance ASC');
        $stmt->execute([$slug]);
        $actions = $stmt->fetchAll();
    }

    // V2.1 — Météo
    $meteo_last = null;
    $meteo_history = [];
    if (in_array('meteo', $modules)) {
        // Dernière météo du user
        $stmt = $db->prepare('SELECT * FROM meteo WHERE mission_slug = ? AND user_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([$slug, $user['id']]);
        $meteo_last = $stmt->fetch();
        // Historique (8 dernières, toutes si admin)
        if ($user['role'] === 'admin') {
            $stmt = $db->prepare('SELECT m.*, u.nom as user_nom FROM meteo m LEFT JOIN users u ON m.user_id = u.id WHERE m.mission_slug = ? ORDER BY m.created_at DESC LIMIT 20');
            $stmt->execute([$slug]);
        } else {
            $stmt = $db->prepare('SELECT * FROM meteo WHERE mission_slug = ? AND user_id = ? ORDER BY created_at DESC LIMIT 8');
            $stmt->execute([$slug, $user['id']]);
        }
        $meteo_history = $stmt->fetchAll();
    }

    // V2.1 — Arbitrages
    $arbitrages = [];
    if (in_array('arbitrages', $modules)) {
        $stmt = $db->prepare('SELECT * FROM arbitrages WHERE mission_slug = ? ORDER BY FIELD(statut, "ouvert", "clos"), created_at DESC');
        $stmt->execute([$slug]);
        $arbitrages = $stmt->fetchAll();
        // Charger les votes pour chaque arbitrage
        foreach ($arbitrages as &$arb) {
            $stmt2 = $db->prepare('SELECT av.*, u.nom as user_nom FROM arbitrage_votes av LEFT JOIN users u ON av.user_id = u.id WHERE av.arbitrage_id = ?');
            $stmt2->execute([$arb['id']]);
            $arb['votes'] = $stmt2->fetchAll();
            $arb['user_vote'] = null;
            foreach ($arb['votes'] as $v) {
                if ($v['user_id'] == $user['id']) { $arb['user_vote'] = $v; break; }
            }
        }
        unset($arb);
    }

    // V2.1 — Décisions
    $decisions = [];
    if (in_array('decisions', $modules)) {
        $stmt = $db->prepare('SELECT * FROM decisions WHERE mission_slug = ? ORDER BY date_decision DESC, created_at DESC');
        $stmt->execute([$slug]);
        $decisions = $stmt->fetchAll();
    }


    // V2.1 — Points Hebdo
    $points_hebdo = [];
    $active_point = null;
    $active_point_actions = [];
    $active_point_meteos = [];
    if (in_array('points', $modules)) {
        // Filtrer par statut selon le rôle
        if ($user['role'] === 'admin') {
            $stmt = $db->prepare('SELECT * FROM points_hebdo WHERE mission_slug = ? ORDER BY date_point DESC, id DESC');
            $stmt->execute([$slug]);
        } else {
            $stmt = $db->prepare('SELECT * FROM points_hebdo WHERE mission_slug = ? AND statut = "publie" ORDER BY date_point DESC, id DESC');
            $stmt->execute([$slug]);
        }
        $points_hebdo = $stmt->fetchAll();

        // Charger le point actif
        $req_point_id = intval($_GET['point_id'] ?? 0);
        if ($req_point_id) {
            foreach ($points_hebdo as $ph) {
                if (intval($ph['id']) === $req_point_id) { $active_point = $ph; break; }
            }
        }
        if ($active_point) {
            // Actions du point
            $stmt = $db->prepare('SELECT * FROM point_actions WHERE point_id = ? ORDER BY ordre, id');
            $stmt->execute([$active_point['id']]);
            $active_point_actions = $stmt->fetchAll();
            // Météos du point
            $stmt = $db->prepare('SELECT pm.*, u.nom as user_nom FROM point_meteo pm LEFT JOIN users u ON pm.user_id = u.id WHERE pm.point_id = ? ORDER BY pm.created_at');
            $stmt->execute([$active_point['id']]);
            $active_point_meteos = $stmt->fetchAll();
        }

        // Pour la liste : compter les actions par point
        foreach ($points_hebdo as &$ph) {
            $stmt = $db->prepare('SELECT COUNT(*) as total, SUM(statut = "fait") as faites FROM point_actions WHERE point_id = ?');
            $stmt->execute([$ph['id']]);
            $counts = $stmt->fetch();
            $ph['actions_total'] = intval($counts['total'] ?? 0);
            $ph['actions_faites'] = intval($counts['faites'] ?? 0);
            // Moyenne météo
            $stmt = $db->prepare('SELECT AVG(score) as avg_score, COUNT(*) as nb FROM point_meteo WHERE point_id = ?');
            $stmt->execute([$ph['id']]);
            $meteo_stats = $stmt->fetch();
            $ph['meteo_avg'] = $meteo_stats['avg_score'] ? round(floatval($meteo_stats['avg_score']), 1) : null;
            $ph['meteo_nb'] = intval($meteo_stats['nb'] ?? 0);
        }
        unset($ph);
    }

    // Labels onglets
    $tab_labels = [
        'mission' => 'Mission',
        'resumes' => 'Résumés',
        'messages' => 'Messages',
        'documents' => 'Documents',
        'actions' => 'Plan d\'action',
        'arbitrages' => 'Arbitrages',
        'decisions' => 'Décisions',
        'points' => 'Points',
    ];
    $tab_icons = [
        'mission' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        'resumes' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
        'messages' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>',
        'documents' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>',
        'actions' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>',
        'arbitrages' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l3 9a5.002 5.002 0 01-6.001 0M18 7l-3 9m-6-5l6-2m0 0l6 2"/></svg>',
        'decisions' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
        'points' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
    ];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($mission['client']) ?> — <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        .tab-active { border-bottom: 2px solid #2563eb; color: #1e40af; font-weight: 500; }
        .tab-inactive { border-bottom: 2px solid transparent; color: #6b7280; }
        .tab-inactive:hover { color: #374151; border-color: #d1d5db; }
        .msg-consultant { border-left: 3px solid #2563eb; }
        .msg-client { border-left: 3px solid #10b981; }
        .action-haute { border-left: 3px solid #ef4444; }

        /* Points hebdo — animations & fun */
        .point-action-row { transition: opacity 0.3s ease, transform 0.2s ease; }
        .point-action-row.done { opacity: 0.55; }
        .point-action-row .action-text { transition: all 0.3s ease; position: relative; }
        .point-action-row.done .action-text { text-decoration: line-through; text-decoration-color: #9ca3af; }
        .point-action-check { transition: all 0.2s ease; }
        .point-action-check:active { transform: scale(0.85); }
        .meteo-btn { transition: transform 0.15s ease, box-shadow 0.15s ease; cursor: pointer; }
        .meteo-btn:hover { transform: scale(1.15); }
        .meteo-btn:active { transform: scale(0.95); }
        .meteo-btn.selected { transform: scale(1.2); box-shadow: 0 0 0 3px rgba(59,130,246,0.4); }
        .progress-bar-fill { transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1); }
        .point-stepper-step { transition: all 0.2s ease; }
        .point-stepper-step.active { font-weight: 600; }
        .mini-progress { transition: width 0.4s ease; }
        .md-rendered { line-height: 1.6; }
        .md-rendered p { margin-bottom: 0.5em; }
        .md-rendered ul, .md-rendered ol { margin-left: 1.2em; margin-bottom: 0.5em; }
        .md-rendered li { margin-bottom: 0.15em; }
        .md-rendered strong { font-weight: 600; }
        .md-rendered em { font-style: italic; }
        .md-rendered h1, .md-rendered h2, .md-rendered h3 { font-weight: 600; margin-top: 0.8em; margin-bottom: 0.3em; }
        .md-preview-toggle { cursor: pointer; user-select: none; }
        .action-normale { border-left: 3px solid #f59e0b; }
        .action-basse { border-left: 3px solid #d1d5db; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

    <?php render_user_bar($user); ?>

    <!-- HEADER MISSION -->
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-5xl mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <?php if (!empty($mission['logo_client'])): ?>
                    <img src="<?= htmlspecialchars($mission['logo_client']) ?>" alt="<?= htmlspecialchars($mission['client']) ?>" class="h-10 w-10 rounded-lg object-contain">
                    <?php endif; ?>
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-xl font-semibold text-gray-800"><?= htmlspecialchars($mission['client']) ?></h1>
                            <?php if ($financeur): ?>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-600 font-medium"><?= htmlspecialchars($financeur['nom']) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm text-gray-500 mt-0.5"><?= htmlspecialchars($mission['titre']) ?></p>
                    </div>
                </div>
                <div class="text-right text-sm text-gray-400">
                    <p><?= CONSULTANT_NAME ?></p>
                    <p class="text-xs"><?= CONSULTANT_TITLE ?></p>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-5xl mx-auto px-6 py-6">

        <!-- FICHE MISSION -->
        <div class="bg-white rounded-lg border border-gray-200 p-5 mb-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="text-gray-400 block text-xs uppercase tracking-wide">Client</span>
                    <span class="font-medium text-gray-800"><?= htmlspecialchars($mission['client']) ?></span>
                </div>
                <div>
                    <span class="text-gray-400 block text-xs uppercase tracking-wide">Objectif</span>
                    <span class="font-medium text-gray-800"><?= htmlspecialchars($mission['objectif'] ?? '—') ?></span>
                </div>
                <div>
                    <span class="text-gray-400 block text-xs uppercase tracking-wide">Démarrage</span>
                    <span class="font-medium text-gray-800"><?= htmlspecialchars($mission['date_debut'] ?? '—') ?></span>
                </div>
                <div>
                    <span class="text-gray-400 block text-xs uppercase tracking-wide">Phase</span>
                    <span class="inline-block px-2 py-1 text-xs font-medium rounded-full
                        <?= ($mission['phase_statut'] ?? '') === 'en_cours' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' ?>">
                        <?= htmlspecialchars($mission['phase_actuelle'] ?? '—') ?>
                    </span>
                </div>
            </div>

            <?php if ($show_budget && !empty($mission['jours_total'])): ?>
            <div class="mt-4 pt-4 border-t border-gray-100">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-400">Budget mission</span>
                    <span class="font-medium text-gray-700"><?= $mission['jours_consommes'] ?? 0 ?> / <?= $mission['jours_total'] ?> jours</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                    <?php $pct = min(100, round((($mission['jours_consommes'] ?? 0) / $mission['jours_total']) * 100)); ?>
                    <div class="h-2 rounded-full <?= $pct > 80 ? 'bg-red-500' : ($pct > 50 ? 'bg-yellow-500' : 'bg-blue-500') ?>" style="width: <?= $pct ?>%"></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($mission['intervenants'])): ?>
            <div class="mt-4 pt-4 border-t border-gray-100">
                <span class="text-gray-400 text-xs uppercase tracking-wide block mb-2">Intervenants</span>
                <div class="flex flex-wrap gap-4">
                    <?php foreach ($mission['intervenants'] as $i): ?>
                    <span class="text-sm text-gray-600">
                        <span class="font-medium"><?= htmlspecialchars($i['nom']) ?></span>
                        <span class="text-gray-400">— <?= htmlspecialchars($i['role']) ?></span>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- TIMELINE PHASES -->
        <?php if (!empty($phases)): ?>
        <div class="bg-white rounded-lg border border-gray-200 p-5 mb-6">
            <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-4">Avancement mission</h2>
            <div class="flex items-center gap-1 overflow-x-auto pb-2">
                <?php foreach ($phases as $idx => $phase):
                    $is_current = ($phase['statut'] ?? '') === 'en_cours';
                    $is_done = ($phase['statut'] ?? '') === 'termine';
                    if ($is_done) { $bg = 'bg-green-500 text-white'; $icon = '✓'; }
                    elseif ($is_current) { $bg = 'bg-blue-500 text-white'; $icon = '●'; }
                    else { $bg = 'bg-gray-200 text-gray-500'; $icon = ''; }
                ?>
                <?php if ($idx > 0): ?>
                    <div class="w-6 h-0.5 <?= $is_done ? 'bg-green-400' : ($is_current ? 'bg-blue-300' : 'bg-gray-200') ?> flex-shrink-0"></div>
                <?php endif; ?>
                <div class="flex flex-col items-center flex-shrink-0 min-w-0" style="width: 90px;">
                    <div class="w-8 h-8 rounded-full <?= $bg ?> flex items-center justify-center text-xs font-bold mb-1"><?= $icon ?: ($idx + 1) ?></div>
                    <span class="text-xs text-center leading-tight <?= $is_current ? 'font-semibold text-blue-700' : 'text-gray-500' ?>"><?= htmlspecialchars($phase['nom']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- WIDGET MÉTÉO MISSION -->
        <?php if (in_array('meteo', $modules)): ?>
        <div class="bg-white rounded-lg border border-gray-200 p-5 mb-6">
            <div class="flex items-start justify-between gap-6">
                <div>
                    <h2 class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-3">Ressenti mission</h2>
                    <form method="POST" class="flex items-center gap-2">
                        <input type="hidden" name="action" value="post_meteo">
                        <input type="hidden" name="mission_slug" value="<?= $slug ?>">
                        <input type="hidden" name="redirect_tab" value="<?= htmlspecialchars($active_tab) ?>">
                        <input type="hidden" name="score" id="meteo-score" value="">
                        <?php
                        $meteo_icons = [4 => ['icon' => "☀️", 'label' => 'Soleil'], 3 => ['icon' => "⛅", 'label' => 'Nuageux'], 2 => ['icon' => "⛈️", 'label' => 'Orage'], 1 => ['icon' => "🌪️", 'label' => 'Temp\u00eate']];
                        foreach ($meteo_icons as $score => $info):
                            $is_selected = ($meteo_last && intval($meteo_last['score']) === $score);
                        ?>
                        <button type="submit" onclick="document.getElementById('meteo-score').value='<?= $score ?>'" title="<?= $info['label'] ?>"
                            class="text-2xl px-2 py-1 rounded-lg transition hover:bg-gray-100 <?= $is_selected ? 'bg-blue-50 ring-2 ring-blue-300' : '' ?>">
                            <?= $info['icon'] ?>
                        </button>
                        <?php endforeach; ?>
                        <input type="text" name="commentaire" maxlength="280" placeholder="Commentaire optionnel..."
                            class="ml-3 flex-1 px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" style="min-width:180px;">
                    </form>
                    <?php if ($meteo_last): ?>
                    <p class="text-xs text-gray-400 mt-2">
                        Dernier ressenti : <?= $meteo_icons[intval($meteo_last['score'])]['icon'] ?? '' ?>
                        <?php if ($meteo_last['commentaire']): ?>
                        — <span class="text-gray-500 italic"><?= htmlspecialchars($meteo_last['commentaire']) ?></span>
                        <?php endif; ?>
                        <span class="ml-1">(<?= date('d/m', strtotime($meteo_last['created_at'])) ?>)</span>
                    </p>
                    <?php endif; ?>
                </div>
                <!-- Mini sparkline CSS -->
                <?php if (count($meteo_history) > 1): ?>
                <div class="flex-shrink-0">
                    <span class="text-xs text-gray-400 block mb-1"><?= $user['role'] === 'admin' ? 'Historique global' : 'Historique' ?></span>
                    <div class="flex items-end gap-0.5" style="height:32px;">
                        <?php
                        $display = array_slice(array_reverse($meteo_history), 0, 8);
                        $colors = [1 => 'bg-red-400', 2 => 'bg-orange-400', 3 => 'bg-yellow-400', 4 => 'bg-green-400'];
                        foreach ($display as $mh):
                            $h = intval($mh['score']) * 8;
                        ?>
                        <div class="w-3 rounded-sm <?= $colors[intval($mh['score'])] ?? 'bg-gray-300' ?>" style="height:<?= $h ?>px;"
                            title="<?= ($mh['user_nom'] ?? '') . ' ' . date('d/m', strtotime($mh['created_at'])) . ' — ' . ($meteo_icons[intval($mh['score'])]['label'] ?? '') ?>"></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ONGLETS -->
        <nav class="flex gap-6 border-b border-gray-200 mb-6">
            <?php foreach ($modules as $mod):
                if (!isset($tab_labels[$mod])) continue;
                $is_active = ($active_tab === $mod);
                $count = '';
                if ($mod === 'messages' && count($messages)) $count = ' <span class="text-xs bg-gray-100 text-gray-500 rounded-full px-1.5 py-0.5 ml-1">' . count($messages) . '</span>';
                if ($mod === 'actions') {
                    $pending = count(array_filter($actions, fn($a) => in_array($a['statut'], ['a_faire', 'en_cours'])));
                    if ($pending) $count = ' <span class="text-xs bg-blue-100 text-blue-600 rounded-full px-1.5 py-0.5 ml-1">' . $pending . '</span>';
                }
                if ($mod === 'arbitrages') {
                    $open = count(array_filter($arbitrages, fn($a) => $a['statut'] === 'ouvert'));
                    if ($open) $count = ' <span class="text-xs bg-yellow-100 text-yellow-600 rounded-full px-1.5 py-0.5 ml-1">' . $open . '</span>';
                }
                if ($mod === 'points' && !empty($points_hebdo)) {
                    $count = ' <span class="text-xs bg-blue-100 text-blue-600 rounded-full px-1.5 py-0.5 ml-1">' . count($points_hebdo) . '</span>';
                }
            ?>
            <a href="/<?= $slug ?>?tab=<?= $mod ?>"
               class="flex items-center gap-1.5 pb-3 pt-1 text-sm transition <?= $is_active ? 'tab-active' : 'tab-inactive' ?>">
                <?= $tab_icons[$mod] ?? '' ?>
                <?= $tab_labels[$mod] ?><?= $count ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <!-- CONTENU ONGLET -->

        <?php if ($active_tab === 'mission'): ?>
        <!-- ═══ FICHE MISSION ═══ -->
        <div class="max-w-3xl space-y-6">

            <!-- Objectif & infos clés -->
            <div class="bg-white rounded-lg border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wide mb-3">Objectif de la mission</h3>
                <p class="text-gray-800 font-medium"><?= htmlspecialchars($mission['objectif'] ?? '') ?></p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 pt-4 border-t border-gray-100 text-sm">
                    <div><span class="text-gray-400 block text-xs">Code</span><span class="font-medium"><?= htmlspecialchars($mission['code'] ?? '—') ?></span></div>
                    <div><span class="text-gray-400 block text-xs">Démarrage</span><span class="font-medium"><?= htmlspecialchars($mission['date_debut'] ?? '—') ?></span></div>
                    <div><span class="text-gray-400 block text-xs">Durée</span><span class="font-medium"><?= htmlspecialchars($mission['duree'] ?? '—') ?></span></div>
                    <div><span class="text-gray-400 block text-xs">Consultant</span><span class="font-medium"><?= htmlspecialchars($mission['consultant'] ?? CONSULTANT_NAME) ?></span></div>
                </div>
                <?php if ($financeur && !empty($financeur['ref'])): ?>
                <div class="mt-3 pt-3 border-t border-gray-100 flex items-center gap-2 text-sm">
                    <?php if (!empty($financeur['logo'])): ?>
                    <img src="<?= htmlspecialchars($financeur['logo']) ?>" alt="<?= htmlspecialchars($financeur['nom']) ?>" class="h-5 opacity-60">
                    <?php endif; ?>
                    <span class="text-gray-500"><?= htmlspecialchars($financeur['ref']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Phases détaillées -->
            <?php if (!empty($phases)): ?>
            <div class="bg-white rounded-lg border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wide mb-4">Déroulement de la mission</h3>
                <div class="space-y-4">
                    <?php foreach ($phases as $idx => $phase):
                        $is_current = ($phase['statut'] ?? '') === 'en_cours';
                        $is_done = ($phase['statut'] ?? '') === 'termine';
                        if ($is_done) { $dot = 'bg-green-500'; $text_color = 'text-gray-500'; }
                        elseif ($is_current) { $dot = 'bg-blue-500'; $text_color = 'text-gray-800'; }
                        else { $dot = 'bg-gray-300'; $text_color = 'text-gray-400'; }
                        $statut_label = $is_done ? 'Terminée' : ($is_current ? 'En cours' : 'À venir');
                    ?>
                    <div class="flex gap-3 <?= $text_color ?>">
                        <div class="flex flex-col items-center flex-shrink-0">
                            <div class="w-3 h-3 rounded-full <?= $dot ?> mt-1"></div>
                            <?php if ($idx < count($phases) - 1): ?>
                            <div class="w-0.5 flex-1 <?= $is_done ? 'bg-green-300' : 'bg-gray-200' ?> mt-1"></div>
                            <?php endif; ?>
                        </div>
                        <div class="pb-4 flex-1">
                            <div class="flex items-center gap-2">
                                <h4 class="text-sm font-semibold"><?= htmlspecialchars($phase['nom']) ?></h4>
                                <span class="text-xs px-1.5 py-0.5 rounded <?= $is_done ? 'bg-green-50 text-green-600' : ($is_current ? 'bg-blue-50 text-blue-600' : 'bg-gray-50 text-gray-400') ?>"><?= $statut_label ?></span>
                            </div>
                            <?php if (!empty($phase['description'])): ?>
                            <p class="text-xs mt-1 <?= $is_current ? 'text-gray-600' : 'text-gray-400' ?>"><?= htmlspecialchars($phase['description']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($phase['etapes'])): ?>
                            <ul class="mt-2 space-y-1">
                                <?php foreach ($phase['etapes'] as $etape): ?>
                                <li class="text-xs flex items-start gap-1.5">
                                    <span class="mt-1 w-1 h-1 rounded-full <?= $dot ?> flex-shrink-0"></span>
                                    <?= htmlspecialchars($etape) ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Intervenants & gouvernance -->
            <?php if (!empty($mission['intervenants'])): ?>
            <div class="bg-white rounded-lg border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wide mb-4">Intervenants</h3>
                <div class="space-y-3">
                    <?php
                    $type_labels = ['sponsor' => 'Sponsor client', 'rc' => 'Responsable Conseil', 'consultant' => 'Consultant', 'equipe' => 'Équipe projet'];
                    $type_colors = ['sponsor' => 'bg-blue-50 text-blue-700', 'rc' => 'bg-yellow-50 text-yellow-700', 'consultant' => 'bg-green-50 text-green-700', 'equipe' => 'bg-gray-50 text-gray-600'];
                    foreach ($mission['intervenants'] as $i):
                        $type = $i['type'] ?? '';
                    ?>
                    <div class="flex items-center justify-between text-sm">
                        <div>
                            <span class="font-medium text-gray-800"><?= htmlspecialchars($i['nom']) ?></span>
                            <span class="text-gray-400 ml-1">— <?= htmlspecialchars($i['role']) ?></span>
                        </div>
                        <?php if ($type && isset($type_labels[$type])): ?>
                        <span class="text-xs px-2 py-0.5 rounded-full <?= $type_colors[$type] ?? '' ?>"><?= $type_labels[$type] ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($mission['gouvernance'])): ?>
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <h4 class="text-xs font-semibold text-gray-400 uppercase mb-2">Gouvernance</h4>
                    <?php if (!empty($mission['gouvernance']['copil'])): ?>
                    <p class="text-sm text-gray-600"><span class="font-medium">COPIL :</span> <?= htmlspecialchars(implode(', ', $mission['gouvernance']['copil'])) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($mission['gouvernance']['frequence'])): ?>
                    <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($mission['gouvernance']['frequence']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Livrables -->
            <?php if (!empty($mission['livrables'])): ?>
            <div class="bg-white rounded-lg border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wide mb-3">Livrables attendus</h3>
                <div class="space-y-2">
                    <?php foreach ($mission['livrables'] as $livrable): ?>
                    <div class="flex items-center gap-2 text-sm text-gray-700">
                        <svg class="w-4 h-4 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>
                        <?= htmlspecialchars($livrable) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Bonnes pratiques -->
            <?php if (!empty($mission['bonnes_pratiques'])): ?>
            <div class="bg-blue-50 rounded-lg border border-blue-100 p-5">
                <h3 class="text-sm font-semibold text-blue-800 mb-3">Bonnes pratiques</h3>
                <div class="space-y-2">
                    <?php foreach ($mission['bonnes_pratiques'] as $bp): ?>
                    <p class="text-sm text-blue-700"><?= htmlspecialchars($bp) ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <?php elseif ($active_tab === 'resumes'): ?>
        <!-- ═══ RÉSUMÉS ═══ -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="md:col-span-1">
                <nav class="space-y-1">
                    <?php foreach ($resumes as $r):
                        $is_active = ($active_resume && $r['id'] === $active_resume['id']);
                        $first_line = strtok($r['content'], "\n");
                        $label = preg_replace('/^#+\s*/', '', $first_line);
                    ?>
                    <a href="/<?= $slug ?>/<?= $r['id'] ?>"
                       class="block px-3 py-2 rounded text-sm transition
                              <?= $is_active ? 'bg-blue-50 text-blue-700 font-medium border-l-2 border-blue-500' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <?= htmlspecialchars($label) ?>
                    </a>
                    <?php endforeach; ?>
                    <?php if (empty($resumes)): ?>
                        <p class="text-sm text-gray-400 italic">Aucun résumé pour l'instant.</p>
                    <?php endif; ?>
                </nav>
            </div>
            <div class="md:col-span-3">
                <?php if ($active_resume): ?>
                <div class="bg-white rounded-lg border border-gray-200 p-6">
                    <style>#resume-content img { max-width: 280px; height: auto; }</style>
                    <div id="resume-content" class="prose prose-sm max-w-none"></div>
                </div>
                <script>
                    const md = <?= json_encode($active_resume['content'], JSON_UNESCAPED_UNICODE) ?>;
                    document.getElementById('resume-content').innerHTML = marked.parse(md);
                </script>
                <?php else: ?>
                <div class="bg-white rounded-lg border border-gray-200 p-6 text-center text-gray-400">
                    <p>Le premier résumé de mission sera bientôt disponible.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php elseif ($active_tab === 'messages'): ?>
        <!-- ═══ MESSAGES ═══ -->
        <div class="max-w-3xl">
            <!-- Formulaire -->
            <?php if (!($_SESSION['is_share_link'] ?? false)): ?>
            <form method="POST" class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
                <input type="hidden" name="action" value="post_message">
                <input type="hidden" name="mission_slug" value="<?= $slug ?>">
                <textarea name="content" rows="3" required
                    placeholder="Écrire un message..."
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none resize-none mb-3"></textarea>
                <div class="flex justify-end">
                    <button type="submit"
                        class="bg-blue-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        Envoyer
                    </button>
                </div>
            </form>
            <?php endif; ?>

            <!-- Liste messages -->
            <?php if (empty($messages)): ?>
            <div class="text-center py-12 text-gray-400">
                <p class="text-sm">Aucun message pour l'instant.</p>
                <p class="text-xs mt-1">Les échanges entre vous et votre consultant apparaîtront ici.</p>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($messages as $msg):
                    $is_consultant = in_array($msg['author_role'], ['admin']);
                    $time = date('d/m à H:i', strtotime($msg['created_at']));
                ?>
                <div class="bg-white rounded-lg border border-gray-200 p-4 <?= $is_consultant ? 'msg-consultant' : 'msg-client' ?>">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($msg['author_name']) ?></span>
                        <span class="text-xs text-gray-400"><?= $time ?></span>
                    </div>
                    <p class="text-sm text-gray-600 whitespace-pre-line"><?= htmlspecialchars($msg['content']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php elseif ($active_tab === 'documents'): ?>
        <!-- ═══ DOCUMENTS ═══ -->
        <div class="max-w-3xl">
            <?php if (empty($documents)): ?>
            <div class="text-center py-12 text-gray-400">
                <p class="text-sm">Aucun document pour l'instant.</p>
                <p class="text-xs mt-1">Les livrables et documents de mission apparaîtront ici.</p>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($documents as $doc):
                    $icon = match($doc['type']) {
                        'html' => '<svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>',
                        'link' => '<svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>',
                        default => '<svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                    };
                    $date = date('d/m/Y', strtotime($doc['created_at']));
                ?>
                <div class="bg-white rounded-lg border border-gray-200 p-4 flex items-start gap-3">
                    <div class="mt-0.5"><?= $icon ?></div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-medium text-gray-800"><?= htmlspecialchars($doc['titre']) ?></h3>
                        <?php if ($doc['description']): ?>
                        <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($doc['description']) ?></p>
                        <?php endif; ?>
                        <span class="text-xs text-gray-400"><?= $date ?></span>
                    </div>
                    <div>
                        <?php if ($doc['type'] === 'html'): ?>
                        <a href="<?= htmlspecialchars($doc['path']) ?>" target="_blank" class="text-xs text-blue-600 hover:underline">Voir</a>
                        <?php elseif ($doc['type'] === 'link'): ?>
                        <a href="<?= htmlspecialchars($doc['path']) ?>" target="_blank" class="text-xs text-blue-600 hover:underline">Ouvrir</a>
                        <?php else: ?>
                        <a href="<?= htmlspecialchars($doc['path']) ?>" target="_blank" class="text-xs text-blue-600 hover:underline">Télécharger</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php elseif ($active_tab === 'actions'): ?>
        <!-- ═══ PLAN D'ACTION ═══ -->
        <div class="max-w-3xl">
            <?php if (empty($actions)): ?>
            <div class="text-center py-12 text-gray-400">
                <p class="text-sm">Aucune action planifiée pour l'instant.</p>
                <p class="text-xs mt-1">Le plan d'action de la mission apparaîtra ici.</p>
            </div>
            <?php else: ?>
            <?php
                $statut_labels = ['a_faire' => 'À faire', 'en_cours' => 'En cours', 'fait' => 'Fait', 'annule' => 'Annulé'];
                $statut_colors = ['a_faire' => 'bg-gray-100 text-gray-600', 'en_cours' => 'bg-blue-100 text-blue-700', 'fait' => 'bg-green-100 text-green-700', 'annule' => 'bg-red-50 text-red-400'];
            ?>
            <div class="space-y-2">
                <?php foreach ($actions as $act):
                    $echeance = $act['echeance'] ? date('d/m', strtotime($act['echeance'])) : '';
                    $overdue = $act['echeance'] && $act['statut'] !== 'fait' && $act['statut'] !== 'annule' && strtotime($act['echeance']) < time();
                ?>
                <div class="bg-white rounded-lg border border-gray-200 p-4 action-<?= $act['priorite'] ?> <?= $act['statut'] === 'fait' ? 'opacity-60' : '' ?>">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-medium text-gray-800 <?= $act['statut'] === 'fait' ? 'line-through' : '' ?>">
                                <?= htmlspecialchars($act['titre']) ?>
                            </h3>
                            <?php if ($act['description']): ?>
                            <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($act['description']) ?></p>
                            <?php endif; ?>
                            <div class="flex items-center gap-3 mt-2">
                                <?php if ($act['responsable']): ?>
                                <span class="text-xs text-gray-500"><?= htmlspecialchars($act['responsable']) ?></span>
                                <?php endif; ?>
                                <?php if ($echeance): ?>
                                <span class="text-xs <?= $overdue ? 'text-red-600 font-medium' : 'text-gray-400' ?>"><?= $echeance ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap <?= $statut_colors[$act['statut']] ?? '' ?>">
                            <?= $statut_labels[$act['statut']] ?? $act['statut'] ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php elseif ($active_tab === 'arbitrages'): ?>
        <!-- ═══ ARBITRAGES ═══ -->
        <div class="max-w-3xl">
            <!-- Formulaire création (admin) -->
            <?php if ($user['role'] === 'admin'): ?>
            <form method="POST" class="bg-white rounded-lg border border-gray-200 p-5 mb-6">
                <input type="hidden" name="action" value="create_arbitrage">
                <input type="hidden" name="mission_slug" value="<?= $slug ?>">
                <h3 class="text-sm font-medium text-gray-700 mb-3">Soumettre un arbitrage</h3>
                <div class="space-y-3">
                    <input type="text" name="titre" required placeholder="Titre de l'arbitrage"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    <textarea name="contexte" rows="2" placeholder="Contexte / enjeux"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none resize-none"></textarea>
                    <input type="text" name="choix_propose" placeholder="Choix proposé"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div class="flex justify-end mt-3">
                    <button type="submit" class="bg-blue-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-blue-700 transition">Créer l'arbitrage</button>
                </div>
            </form>
            <?php endif; ?>

            <?php if (empty($arbitrages)): ?>
            <div class="text-center py-12 text-gray-400">
                <p class="text-sm">Aucun arbitrage pour l'instant.</p>
                <p class="text-xs mt-1">Les arbitrages soumis au vote apparaîtront ici.</p>
            </div>
            <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($arbitrages as $arb):
                    $is_open = ($arb['statut'] === 'ouvert');
                    $vote_counts = ['ok' => 0, 'pas_ok' => 0, 'a_discuter' => 0];
                    foreach ($arb['votes'] as $v) { if (isset($vote_counts[$v['vote']])) $vote_counts[$v['vote']]++; }
                    $total_votes = array_sum($vote_counts);
                ?>
                <div class="bg-white rounded-lg border <?= $is_open ? 'border-gray-200' : 'border-gray-100 opacity-60' ?> p-5">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-medium text-gray-800"><?= htmlspecialchars($arb['titre']) ?></h3>
                                <span class="text-xs px-2 py-0.5 rounded-full <?= $is_open ? 'bg-blue-50 text-blue-600' : 'bg-gray-100 text-gray-500' ?>">
                                    <?= $is_open ? 'Ouvert' : 'Clos' ?>
                                </span>
                            </div>
                            <?php if ($arb['contexte']): ?>
                            <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($arb['contexte']) ?></p>
                            <?php endif; ?>
                            <?php if ($arb['choix_propose']): ?>
                            <p class="text-xs text-gray-600 mt-1 italic">Proposition : <?= htmlspecialchars($arb['choix_propose']) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($is_open && $user['role'] === 'admin'): ?>
                        <form method="POST" class="flex-shrink-0">
                            <input type="hidden" name="action" value="close_arbitrage">
                            <input type="hidden" name="mission_slug" value="<?= $slug ?>">
                            <input type="hidden" name="arbitrage_id" value="<?= $arb['id'] ?>">
                            <button type="submit" class="text-xs text-gray-400 hover:text-gray-600 px-2 py-1 rounded hover:bg-gray-100 transition">Clore</button>
                        </form>
                        <?php endif; ?>
                    </div>

                    <!-- Résumé des votes -->
                    <?php if ($total_votes > 0): ?>
                    <div class="flex items-center gap-4 text-xs text-gray-500 mb-3 pb-3 border-b border-gray-100">
                        <span class="text-green-600">&#10003; OK : <?= $vote_counts['ok'] ?></span>
                        <span class="text-red-500">&#10007; Pas OK : <?= $vote_counts['pas_ok'] ?></span>
                        <span class="text-yellow-600">? À discuter : <?= $vote_counts['a_discuter'] ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Votes détaillés (visibles admin) -->
                    <?php if ($user['role'] === 'admin' && !empty($arb['votes'])): ?>
                    <div class="space-y-1 mb-3">
                        <?php foreach ($arb['votes'] as $v):
                            $vote_icons = ['ok' => '&#10003;', 'pas_ok' => '&#10007;', 'a_discuter' => '?'];
                            $vote_colors = ['ok' => 'text-green-600', 'pas_ok' => 'text-red-500', 'a_discuter' => 'text-yellow-600'];
                        ?>
                        <div class="flex items-center gap-2 text-xs">
                            <span class="<?= $vote_colors[$v['vote']] ?? '' ?>"><?= $vote_icons[$v['vote']] ?? '' ?></span>
                            <span class="font-medium text-gray-700"><?= htmlspecialchars($v['user_nom'] ?? 'User') ?></span>
                            <?php if ($v['commentaire']): ?>
                            <span class="text-gray-400 italic">— <?= htmlspecialchars($v['commentaire']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Formulaire de vote (si ouvert et pas encore voté ou pour modifier) -->
                    <?php if ($is_open && $user['role'] !== 'admin'): ?>
                    <form method="POST" class="flex items-center gap-2 pt-2 border-t border-gray-100">
                        <input type="hidden" name="action" value="vote_arbitrage">
                        <input type="hidden" name="mission_slug" value="<?= $slug ?>">
                        <input type="hidden" name="arbitrage_id" value="<?= $arb['id'] ?>">
                        <?php
                        $vote_options = ['ok' => '&#10003; OK', 'pas_ok' => '&#10007; Pas OK', 'a_discuter' => '? À discuter'];
                        foreach ($vote_options as $val => $label):
                            $is_my_vote = ($arb['user_vote'] && $arb['user_vote']['vote'] === $val);
                        ?>
                        <button type="submit" name="vote" value="<?= $val ?>"
                            class="text-xs px-3 py-1.5 rounded-lg transition <?= $is_my_vote ? 'bg-blue-100 text-blue-700 ring-1 ring-blue-300' : 'bg-gray-50 text-gray-600 hover:bg-gray-100' ?>">
                            <?= $label ?>
                        </button>
                        <?php endforeach; ?>
                        <input type="text" name="commentaire" placeholder="Commentaire..." value="<?= htmlspecialchars($arb['user_vote']['commentaire'] ?? '') ?>"
                            class="flex-1 ml-2 px-2 py-1 border border-gray-200 rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                    </form>
                    <?php elseif ($is_open && $user['role'] === 'admin'): ?>
                    <!-- Admin peut aussi voter -->
                    <form method="POST" class="flex items-center gap-2 pt-2 border-t border-gray-100">
                        <input type="hidden" name="action" value="vote_arbitrage">
                        <input type="hidden" name="mission_slug" value="<?= $slug ?>">
                        <input type="hidden" name="arbitrage_id" value="<?= $arb['id'] ?>">
                        <?php
                        $vote_options = ['ok' => '&#10003; OK', 'pas_ok' => '&#10007; Pas OK', 'a_discuter' => '? À discuter'];
                        foreach ($vote_options as $val => $label):
                            $is_my_vote = ($arb['user_vote'] && $arb['user_vote']['vote'] === $val);
                        ?>
                        <button type="submit" name="vote" value="<?= $val ?>"
                            class="text-xs px-3 py-1.5 rounded-lg transition <?= $is_my_vote ? 'bg-blue-100 text-blue-700 ring-1 ring-blue-300' : 'bg-gray-50 text-gray-600 hover:bg-gray-100' ?>">
                            <?= $label ?>
                        </button>
                        <?php endforeach; ?>
                        <input type="text" name="commentaire" placeholder="Commentaire..." value="<?= htmlspecialchars($arb['user_vote']['commentaire'] ?? '') ?>"
                            class="flex-1 ml-2 px-2 py-1 border border-gray-200 rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                    </form>
                    <?php endif; ?>

                    <div class="text-xs text-gray-400 mt-2">
                        Créé le <?= date('d/m/Y', strtotime($arb['created_at'])) ?>
                        <?php if (!$is_open && $arb['closed_at']): ?>
                        — Clos le <?= date('d/m/Y', strtotime($arb['closed_at'])) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php elseif ($active_tab === 'decisions'): ?>
        <!-- ═══ FIL DE DÉCISIONS ═══ -->
        <div class="max-w-3xl">
            <!-- Formulaire (admin) -->
            <?php if ($user['role'] === 'admin'): ?>
            <form method="POST" class="bg-white rounded-lg border border-gray-200 p-5 mb-6">
                <input type="hidden" name="action" value="create_decision">
                <input type="hidden" name="mission_slug" value="<?= $slug ?>">
                <h3 class="text-sm font-medium text-gray-700 mb-3">Enregistrer une décision</h3>
                <div class="space-y-3">
                    <input type="text" name="titre" required placeholder="Intitulé de la décision"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    <div class="grid grid-cols-2 gap-3">
                        <input type="text" name="decideur" placeholder="Décideur"
                            class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        <input type="date" name="date_decision" value="<?= date('Y-m-d') ?>"
                            class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    </div>
                    <textarea name="contexte" rows="2" placeholder="Contexte / raison de la décision"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none resize-none"></textarea>
                </div>
                <div class="flex justify-end mt-3">
                    <button type="submit" class="bg-blue-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-blue-700 transition">Enregistrer</button>
                </div>
            </form>
            <?php endif; ?>

            <?php if (empty($decisions)): ?>
            <div class="text-center py-12 text-gray-400">
                <p class="text-sm">Aucune décision enregistrée pour l'instant.</p>
                <p class="text-xs mt-1">Les décisions prises au cours de la mission apparaîtront ici.</p>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($decisions as $dec):
                    $d_date = date('d/m/Y', strtotime($dec['date_decision']));
                ?>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 mt-0.5">
                            <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center">
                                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-medium text-gray-800"><?= htmlspecialchars($dec['titre']) ?></h3>
                                <span class="text-xs text-gray-400"><?= $d_date ?></span>
                            </div>
                            <?php if ($dec['decideur']): ?>
                            <p class="text-xs text-gray-500 mt-0.5">Décideur : <?= htmlspecialchars($dec['decideur']) ?></p>
                            <?php endif; ?>
                            <?php if ($dec['contexte']): ?>
                            <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($dec['contexte']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>


        <?php elseif ($active_tab === 'points'): ?>
        <!-- ═══ POINTS HEBDO ═══ -->
        <div class="max-w-3xl">

        <?php if ($active_point): ?>
            <!-- ── VUE DÉTAIL ── -->
            <a href="/<?= $slug ?>?tab=points" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline mb-4">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Retour à la liste
            </a>

            <!-- En-tête + Stepper -->
            <div class="bg-white rounded-lg border border-gray-200 p-5 mb-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <h2 class="text-lg font-medium text-gray-800">Point <?= htmlspecialchars($active_point['semaine']) ?></h2>
                        <span class="text-sm text-gray-400"><?= date('d/m/Y', strtotime($active_point['date_point'])) ?></span>
                    </div>
                    <?php if ($active_point['statut'] === 'brouillon' && $user['role'] === 'admin'): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="publish_point">
                        <input type="hidden" name="mission_slug" value="<?= $slug ?>">
                        <input type="hidden" name="point_id" value="<?= $active_point['id'] ?>">
                        <button type="submit" class="bg-green-600 text-white text-xs font-medium px-3 py-1.5 rounded-lg hover:bg-green-700 transition">Publier</button>
                    </form>
                    <?php endif; ?>
                </div>
                <!-- Stepper visuel -->
                <?php
                $stepper_steps = ['brouillon' => 'Brouillon', 'en_cours' => 'En cours', 'publie' => 'Publié'];
                $current_step_idx = ($active_point['statut'] === 'brouillon') ? 0 : (($active_point['statut'] === 'publie') ? 2 : 1);
                ?>
                <div class="flex items-center gap-0 mt-1">
                    <?php $si = 0; foreach ($stepper_steps as $skey => $slabel): ?>
                        <?php if ($si > 0): ?>
                        <div class="flex-1 h-0.5 <?= $si <= $current_step_idx ? 'bg-blue-500' : 'bg-gray-200' ?> mx-1"></div>
                        <?php endif; ?>
                        <div class="flex items-center gap-1.5 point-stepper-step <?= $si <= $current_step_idx ? 'active' : '' ?>">
                            <div class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-medium
                                <?= $si < $current_step_idx ? 'bg-blue-500 text-white' : ($si === $current_step_idx ? 'bg-blue-500 text-white ring-2 ring-blue-200' : 'bg-gray-200 text-gray-400') ?>">
                                <?php if ($si < $current_step_idx): ?>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                <?php else: ?>
                                <?= $si + 1 ?>
                                <?php endif; ?>
                            </div>
                            <span class="text-xs <?= $si <= $current_step_idx ? 'text-blue-700' : 'text-gray-400' ?>"><?= $slabel ?></span>
                        </div>
                    <?php $si++; endforeach; ?>
                </div>
                <?php if ($active_point['frequence']): ?>
                <p class="text-xs text-gray-400 mt-2">Fréquence : <?= htmlspecialchars($active_point['frequence']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Ordre du jour -->
            <div class="bg-white rounded-lg border border-gray-200 p-5 mb-4">
                <h3 class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-3">Ordre du jour</h3>
                <?php if ($active_point['statut'] === 'brouillon' && $user['role'] === 'admin'): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="update_point">
                    <input type="hidden" name="mission_slug" value="<?= $slug ?>">
                    <input type="hidden" name="point_id" value="<?= $active_point['id'] ?>">
                    <div class="flex items-center justify-between mb-2">
                        <span class="md-preview-toggle text-xs text-blue-500 hover:underline" onclick="toggleMdPreview(this, 'odj')">Aperçu</span>
                    </div>
                    <textarea id="md-edit-odj" name="ordre_du_jour" rows="3" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none resize-none mb-2"><?= htmlspecialchars($active_point['ordre_du_jour'] ?? '') ?></textarea>
                    <div id="md-preview-odj" class="hidden text-sm text-gray-700 md-rendered border border-gray-200 rounded-lg px-3 py-2 mb-2 min-h-[3rem] bg-gray-50"></div>
                    <div class="flex justify-end">
                        <button type="submit" class="text-xs text-blue-600 hover:underline">Enregistrer</button>
                    </div>
                </form>
                <?php else: ?>
                <div class="text-sm text-gray-700 md-rendered" data-md="<?= htmlspecialchars($active_point['ordre_du_jour'] ?? '', ENT_QUOTES) ?>"></div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="bg-white rounded-lg border border-gray-200 p-5 mb-4">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-xs font-medium text-gray-400 uppercase tracking-wide">Actions</h3>
                    <?php
                    $total_act = count($active_point_actions);
                    $done_act = count(array_filter($active_point_actions, fn($a) => $a['statut'] === 'fait'));
                    $pct = $total_act > 0 ? round(($done_act / $total_act) * 100) : 0;
                    ?>
                    <span id="actions-counter" class="text-xs text-gray-400"><?= $done_act ?>/<?= $total_act ?> faites</span>
                </div>

                <!-- Barre de progression -->
                <?php if ($total_act > 0): ?>
                <div class="w-full bg-gray-100 rounded-full h-2 mb-4 overflow-hidden">
                    <div id="actions-progress-bar" class="progress-bar-fill h-2 rounded-full <?= $pct === 100 ? 'bg-green-500' : 'bg-blue-500' ?>" style="width: <?= $pct ?>%"></div>
                </div>
                <?php endif; ?>

                <?php if (empty($active_point_actions)): ?>
                <p class="text-sm text-gray-400 italic">Aucune action pour ce point.</p>
                <?php else: ?>
                <div class="space-y-2 mb-4" id="actions-list">
                    <?php foreach ($active_point_actions as $pa):
                        $is_done = ($pa['statut'] === 'fait');
                        $is_reporte = ($pa['statut'] === 'reporte');
                    ?>
                    <div class="point-action-row flex items-start gap-3 py-2 <?= $is_done ? 'done' : '' ?>" data-action-id="<?= $pa['id'] ?>">
                        <button type="button" onclick="toggleAction(<?= $pa['id'] ?>, '<?= $slug ?>', <?= $active_point['id'] ?>)" class="point-action-check flex-shrink-0 mt-0.5 w-5 h-5 rounded border-2 flex items-center justify-center transition <?= $is_done ? 'bg-green-500 border-green-500 text-white' : 'border-gray-300 hover:border-blue-400' ?>">
                            <?php if ($is_done): ?>
                            <svg class="w-3 h-3 check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            <?php endif; ?>
                        </button>
                        <div class="flex-1 min-w-0">
                            <span class="action-text text-sm text-gray-800"><?= htmlspecialchars($pa['titre']) ?></span>
                            <?php if ($is_reporte): ?>
                            <span class="text-xs text-orange-500 ml-1">reporté</span>
                            <?php endif; ?>
                            <div class="flex items-center gap-3 mt-0.5">
                                <?php if ($pa['responsable']): ?>
                                <span class="text-xs px-1.5 py-0.5 rounded bg-blue-50 text-blue-600"><?= htmlspecialchars($pa['responsable']) ?></span>
                                <?php endif; ?>
                                <?php if ($pa['echeance']): ?>
                                <span class="text-xs text-gray-400"><?= date('d/m', strtotime($pa['echeance'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Ajouter une action (admin) -->
                <?php if ($user['role'] === 'admin'): ?>
                <form method="POST" class="border-t border-gray-100 pt-3">
                    <input type="hidden" name="action" value="add_point_action">
                    <input type="hidden" name="mission_slug" value="<?= $slug ?>">
                    <input type="hidden" name="point_id" value="<?= $active_point['id'] ?>">
                    <div class="flex items-center gap-2">
                        <input type="text" name="titre" required placeholder="Nouvelle action..."
                            class="flex-1 px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        <input type="text" name="responsable" placeholder="Responsable"
                            class="w-28 px-2 py-1.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        <input type="date" name="echeance"
                            class="px-2 py-1.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        <button type="submit" class="bg-blue-600 text-white text-xs font-medium px-3 py-1.5 rounded-lg hover:bg-blue-700 transition">+</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>

            <!-- Météo collective -->
            <div class="bg-white rounded-lg border border-gray-200 p-5 mb-4">
                <h3 class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-3">Météo collective</h3>

                <?php
                $meteo_icons_point = [4 => ['icon' => '☀️', 'label' => 'Soleil', 'bg' => 'bg-yellow-50'], 3 => ['icon' => '⛅', 'label' => 'Nuageux', 'bg' => 'bg-gray-50'], 2 => ['icon' => '⛈️', 'label' => 'Orage', 'bg' => 'bg-blue-50'], 1 => ['icon' => '🌪️', 'label' => 'Tempête', 'bg' => 'bg-red-50']];
                $user_meteo = null;
                foreach ($active_point_meteos as $pm) {
                    if ($pm['user_id'] == $user['id']) { $user_meteo = $pm; break; }
                }
                ?>

                <!-- Vote météo (AJAX) -->
                <div class="mb-4" id="meteo-vote-area">
                    <div class="flex items-center gap-2 mb-2">
                        <?php foreach ($meteo_icons_point as $sc => $info):
                            $is_sel = ($user_meteo && intval($user_meteo['score']) === $sc);
                        ?>
                        <button type="button" onclick="postMeteo(<?= $sc ?>, '<?= $slug ?>', <?= $active_point['id'] ?>)" title="<?= $info['label'] ?>"
                            data-meteo-score="<?= $sc ?>"
                            class="meteo-btn text-2xl px-3 py-2 rounded-lg <?= $info['bg'] ?> <?= $is_sel ? 'selected ring-2 ring-blue-400' : '' ?>">
                            <?= $info['icon'] ?>
                        </button>
                        <?php endforeach; ?>
                        <input type="text" id="meteo-commentaire" maxlength="280" placeholder="Commentaire optionnel..." value="<?= htmlspecialchars($user_meteo['commentaire'] ?? '') ?>"
                            class="flex-1 ml-2 px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    </div>
                </div>

                <!-- Météos déjà données -->
                <?php if (!empty($active_point_meteos)): ?>
                <div class="space-y-1 border-t border-gray-100 pt-3">
                    <?php foreach ($active_point_meteos as $pm):
                        $pm_icon = $meteo_icons_point[intval($pm['score'])]['icon'] ?? '?';
                        $pm_bg = $meteo_icons_point[intval($pm['score'])]['bg'] ?? '';
                    ?>
                    <div class="flex items-center gap-2 text-sm <?= $pm_bg ?> px-3 py-1.5 rounded">
                        <span><?= $pm_icon ?></span>
                        <span class="font-medium text-gray-700"><?= htmlspecialchars($pm['user_nom'] ?? 'Utilisateur') ?></span>
                        <?php if ($pm['commentaire']): ?>
                        <span class="text-gray-500 italic text-xs">— <?= htmlspecialchars($pm['commentaire']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Résumé / Avancement / Prochaines étapes -->
            <div class="bg-white rounded-lg border border-gray-200 p-5 mb-4">
                <h3 class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-3">Résumé & suivi</h3>
                <?php if ($active_point['statut'] === 'brouillon' && $user['role'] === 'admin'): ?>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_point">
                    <input type="hidden" name="mission_slug" value="<?= $slug ?>">
                    <input type="hidden" name="point_id" value="<?= $active_point['id'] ?>">

                    <?php foreach (['resume' => 'Résumé', 'avancement' => 'Avancement', 'prochaines_etapes' => 'Prochaines étapes'] as $field_key => $field_label): ?>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs text-gray-500"><?= $field_label ?></label>
                            <span class="md-preview-toggle text-xs text-blue-500 hover:underline" onclick="toggleMdPreview(this, '<?= $field_key ?>')">Aperçu</span>
                        </div>
                        <textarea id="md-edit-<?= $field_key ?>" name="<?= $field_key ?>" rows="<?= $field_key === 'resume' ? 3 : 2 ?>" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none resize-none"><?= htmlspecialchars($active_point[$field_key] ?? '') ?></textarea>
                        <div id="md-preview-<?= $field_key ?>" class="hidden text-sm text-gray-700 md-rendered border border-gray-200 rounded-lg px-3 py-2 min-h-[2rem] bg-gray-50"></div>
                    </div>
                    <?php endforeach; ?>

                    <div class="flex items-center gap-3">
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Temps passé (heures)</label>
                            <input type="number" name="temps_passe" step="0.25" min="0" value="<?= htmlspecialchars($active_point['temps_passe'] ?? '') ?>"
                                class="w-24 px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div class="flex-1"></div>
                        <button type="submit" class="bg-blue-600 text-white text-xs font-medium px-4 py-2 rounded-lg hover:bg-blue-700 transition mt-4">Enregistrer</button>
                    </div>
                </form>
                <?php else: ?>
                <?php foreach (['resume' => 'Résumé', 'avancement' => 'Avancement', 'prochaines_etapes' => 'Prochaines étapes'] as $field_key => $field_label): ?>
                <?php if ($active_point[$field_key]): ?>
                <div class="mb-3">
                    <span class="text-xs text-gray-500 block mb-1"><?= $field_label ?></span>
                    <div class="text-sm text-gray-700 md-rendered" data-md="<?= htmlspecialchars($active_point[$field_key], ENT_QUOTES) ?>"></div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($active_point['temps_passe']): ?>
                <div class="inline-block px-3 py-1.5 bg-gray-50 rounded text-xs text-gray-500 mt-2">
                    Temps passé : <?= htmlspecialchars($active_point['temps_passe']) ?>h
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- ── VUE LISTE ── -->

            <!-- Bouton nouveau point (admin) -->
            <?php if ($user['role'] === 'admin'): ?>
            <form method="POST" class="bg-white rounded-lg border border-gray-200 p-5 mb-6">
                <input type="hidden" name="action" value="create_point">
                <input type="hidden" name="mission_slug" value="<?= $slug ?>">
                <h3 class="text-sm font-medium text-gray-700 mb-3">Nouveau point</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <input type="text" name="semaine" required placeholder="S20" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    <input type="date" name="date_point" value="<?= date('Y-m-d') ?>" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    <input type="text" name="frequence" placeholder="hebdomadaire" value="hebdomadaire" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    <button type="submit" class="bg-blue-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-blue-700 transition">Créer</button>
                </div>
                <textarea name="ordre_du_jour" rows="2" placeholder="Ordre du jour (optionnel)" class="w-full mt-3 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none resize-none"></textarea>
            </form>
            <?php endif; ?>

            <?php if (empty($points_hebdo)): ?>
            <div class="text-center py-12 text-gray-400">
                <p class="text-sm">Aucun point hebdo pour l'instant.</p>
                <p class="text-xs mt-1">Les comptes-rendus de réunion structurés apparaîtront ici.</p>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php
                $meteo_icons_list = [4 => '☀️', 3 => '⛅', 2 => '⛈️', 1 => '🌪️'];
                foreach ($points_hebdo as $ph):
                    $ph_date = date('d/m/Y', strtotime($ph['date_point']));
                    $is_brouillon = ($ph['statut'] === 'brouillon');
                    // Météo moyenne arrondie
                    $avg_icon = '';
                    if ($ph['meteo_avg']) {
                        $rounded = max(1, min(4, round($ph['meteo_avg'])));
                        $avg_icon = $meteo_icons_list[$rounded] ?? '';
                    }
                ?>
                <?php $list_pct = $ph['actions_total'] > 0 ? round(($ph['actions_faites'] / $ph['actions_total']) * 100) : 0; ?>
                <a href="/<?= $slug ?>?tab=points&point_id=<?= $ph['id'] ?>" class="block bg-white rounded-lg border border-gray-200 hover:border-blue-400 hover:shadow-sm transition overflow-hidden">
                    <div class="flex items-center justify-between p-4">
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-medium text-gray-800"><?= htmlspecialchars($ph['semaine']) ?></span>
                            <span class="text-xs text-gray-400"><?= $ph_date ?></span>
                            <?php if ($is_brouillon): ?>
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-yellow-100 text-yellow-700">Brouillon</span>
                            <?php else: ?>
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700">Publié</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-4 text-xs text-gray-400">
                            <?php if ($ph['actions_total'] > 0): ?>
                            <span><?= $ph['actions_faites'] ?>/<?= $ph['actions_total'] ?> actions</span>
                            <?php endif; ?>
                            <?php if ($avg_icon): ?>
                            <span title="Météo moyenne : <?= $ph['meteo_avg'] ?>/4 (<?= $ph['meteo_nb'] ?> votes)"><?= $avg_icon ?></span>
                            <?php endif; ?>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </div>
                    </div>
                    <?php if ($ph['actions_total'] > 0): ?>
                    <div class="h-1 bg-gray-100">
                        <div class="mini-progress h-1 <?= $list_pct === 100 ? 'bg-green-400' : 'bg-blue-400' ?>" style="width: <?= $list_pct ?>%"></div>
                    </div>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>
        </div>

        <!-- JavaScript Points Hebdo -->
        <script>
        // --- AJAX toggle action ---
        function toggleAction(actionId, slug, pointId) {
            var row = document.querySelector('[data-action-id="' + actionId + '"]');
            var btn = row.querySelector('.point-action-check');
            var isDone = row.classList.contains('done');

            // Optimistic UI
            if (isDone) {
                row.classList.remove('done');
                btn.classList.remove('bg-green-500', 'border-green-500', 'text-white');
                btn.classList.add('border-gray-300');
                btn.innerHTML = '';
            } else {
                row.classList.add('done');
                btn.classList.add('bg-green-500', 'border-green-500', 'text-white');
                btn.classList.remove('border-gray-300');
                btn.innerHTML = '<svg class="w-3 h-3 check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>';
            }

            var formData = new FormData();
            formData.append('action', 'toggle_action');
            formData.append('mission_slug', slug);
            formData.append('action_id', actionId);
            formData.append('point_id', pointId);

            fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) {
                    // Update counter
                    var counter = document.getElementById('actions-counter');
                    if (counter) counter.textContent = data.faites + '/' + data.total + ' faites';
                    // Update progress bar
                    var bar = document.getElementById('actions-progress-bar');
                    if (bar && data.total > 0) {
                        var pct = Math.round((data.faites / data.total) * 100);
                        bar.style.width = pct + '%';
                        bar.className = 'progress-bar-fill h-2 rounded-full ' + (pct === 100 ? 'bg-green-500' : 'bg-blue-500');
                    }
                }
            })
            .catch(function() {
                // Revert on error
                window.location.reload();
            });
        }

        // --- AJAX météo ---
        function postMeteo(score, slug, pointId) {
            var commentaire = document.getElementById('meteo-commentaire');
            var comment = commentaire ? commentaire.value : '';

            // Optimistic UI: highlight selected
            document.querySelectorAll('.meteo-btn').forEach(function(b) {
                b.classList.remove('selected', 'ring-2', 'ring-blue-400');
            });
            var selected = document.querySelector('[data-meteo-score="' + score + '"]');
            if (selected) {
                selected.classList.add('selected', 'ring-2', 'ring-blue-400');
            }

            var formData = new FormData();
            formData.append('action', 'post_point_meteo');
            formData.append('mission_slug', slug);
            formData.append('point_id', pointId);
            formData.append('score', score);
            formData.append('commentaire', comment);

            fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.ok) window.location.reload();
            })
            .catch(function() {
                window.location.reload();
            });
        }

        // --- Markdown preview toggle ---
        function toggleMdPreview(toggleEl, fieldId) {
            var editEl = document.getElementById('md-edit-' + fieldId);
            var previewEl = document.getElementById('md-preview-' + fieldId);
            if (!editEl || !previewEl) return;

            if (previewEl.classList.contains('hidden')) {
                // Show preview
                previewEl.innerHTML = marked.parse(editEl.value || '');
                previewEl.classList.remove('hidden');
                editEl.classList.add('hidden');
                toggleEl.textContent = 'Éditer';
            } else {
                // Show editor
                previewEl.classList.add('hidden');
                editEl.classList.remove('hidden');
                toggleEl.textContent = 'Aperçu';
            }
        }

        // --- Render markdown in read-only fields ---
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.md-rendered[data-md]').forEach(function(el) {
                var raw = el.getAttribute('data-md');
                if (raw && raw.trim()) {
                    el.innerHTML = marked.parse(raw);
                } else {
                    el.innerHTML = '<span class="text-gray-400 italic text-sm">—</span>';
                }
            });
        });
        </script>

        <?php endif; ?>

    </div>

    <!-- FOOTER -->
    <footer class="border-t border-gray-100 mt-12 py-6">
        <div class="max-w-5xl mx-auto px-6">
            <?php if ($financeur): ?>
            <div class="flex items-center justify-center gap-3 mb-4">
                <?php if (!empty($financeur['logo'])): ?>
                <img src="<?= htmlspecialchars($financeur['logo']) ?>" alt="<?= htmlspecialchars($financeur['nom']) ?>" class="h-8 opacity-60">
                <?php endif; ?>
                <span class="text-xs text-gray-400">Mission accompagnée avec le soutien de <?= htmlspecialchars($financeur['nom']) ?></span>
            </div>
            <?php endif; ?>
            <p class="text-center text-xs text-gray-400">
                <?= SITE_NAME ?> · <?= CONSULTANT_NAME ?> · <?= date('Y') ?>
                · <a href="/mentions-legales.php" class="hover:text-gray-600">Mentions légales</a>
            </p>
        </div>
    </footer>

</body>
</html>
<?php
}
