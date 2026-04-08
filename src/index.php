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
$_role_modules = ['externe' => ['mission', 'resumes'], 'equipe' => ['mission', 'resumes', 'messages', 'documents', 'actions']];
$_allowed = $_role_modules[$user['role']] ?? null;
if ($_allowed !== null && !in_array($active_tab, $_allowed)) {
    $active_tab = $_allowed[0] ?? 'resumes';
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
        <h2 class="text-lg font-medium text-gray-700 mb-6">Missions en cours</h2>
        <div class="space-y-4">
        <?php foreach ($missions as $m): ?>
            <a href="/<?= $m['slug'] ?>" class="block bg-white rounded-lg border border-gray-200 p-5 hover:border-blue-400 hover:shadow-sm transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <?php if (!empty($m['logo_client'])): ?>
                        <img src="<?= htmlspecialchars($m['logo_client']) ?>" alt="" class="h-8 w-8 rounded object-contain flex-shrink-0">
                        <?php endif; ?>
                        <div>
                            <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($m['client']) ?></h3>
                            <p class="text-sm text-gray-500"><?= htmlspecialchars($m['titre']) ?></p>
                        </div>
                    </div>
                    <span class="inline-block px-3 py-1 text-xs font-medium rounded-full
                        <?= $m['phase_statut'] === 'en_cours' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' ?>">
                        <?= htmlspecialchars($m['phase_actuelle']) ?>
                    </span>
                </div>
            </a>
        <?php endforeach; ?>
        <?php if (empty($missions)): ?>
            <p class="text-gray-400 text-center py-8">Aucune mission accessible.</p>
        <?php endif; ?>
        </div>
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
        'equipe'  => ['mission', 'resumes', 'messages', 'documents', 'actions'],
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

    // Labels onglets
    $tab_labels = [
        'mission' => 'Mission',
        'resumes' => 'Résumés',
        'messages' => 'Messages',
        'documents' => 'Documents',
        'actions' => 'Plan d\'action',
    ];
    $tab_icons = [
        'mission' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        'resumes' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
        'messages' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>',
        'documents' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>',
        'actions' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>',
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
                        <a href="/documents/<?= $slug ?>/<?= htmlspecialchars($doc['filename']) ?>" class="text-xs text-blue-600 hover:underline">Télécharger</a>
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
