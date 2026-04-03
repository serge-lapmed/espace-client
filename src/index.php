<?php
/**
 * Espace Mission — La PME Digitale
 * Point d'entrée unique
 *
 * URLs :
 *   /                    → liste des missions (ou redirect si une seule)
 *   /fl-metal-2026       → page mission avec résumés
 *   /fl-metal-2026/S14   → résumé spécifique
 */

require_once __DIR__ . '/includes/config.php';

// Routing simple
$request = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts = explode('/', $request);

$mission_slug = sanitize_slug($parts[0] ?? '');
$resume_id = $parts[1] ?? null;

// Page d'accueil : si pas de slug, on redirige vers la première mission
if (empty($mission_slug)) {
    $missions = list_missions();
    if (count($missions) === 1) {
        header('Location: /' . $missions[0]['slug']);
        exit;
    }
    // Si plusieurs missions, afficher la liste
    render_home($missions);
    exit;
}

// Charger la mission
$mission = load_mission($mission_slug);
if (!$mission) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body><h1>Mission non trouvée</h1><p><a href="/">Retour</a></p></body></html>';
    exit;
}

// Charger les résumés
$resumes = list_resumes($mission_slug);

// Rendu de la page mission
render_mission($mission, $resumes, $resume_id);

// ─────────────────────────────────────────────
// FONCTIONS DE RENDU
// ─────────────────────────────────────────────

function render_home(array $missions): void {
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
                    <div>
                        <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($m['client']) ?></h3>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($m['titre']) ?></p>
                    </div>
                    <span class="inline-block px-3 py-1 text-xs font-medium rounded-full
                        <?= $m['phase_statut'] === 'en_cours' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' ?>">
                        <?= htmlspecialchars($m['phase_actuelle']) ?>
                    </span>
                </div>
            </a>
        <?php endforeach; ?>
        </div>
    </main>
</body>
</html>
<?php
}

function render_mission(array $mission, array $resumes, ?string $resume_id): void {
    // Si un résumé spécifique est demandé, le mettre en premier
    $active_resume = null;
    if ($resume_id) {
        foreach ($resumes as $r) {
            if ($r['id'] === $resume_id) {
                $active_resume = $r;
                break;
            }
        }
    }
    // Par défaut : le plus récent
    if (!$active_resume && !empty($resumes)) {
        $active_resume = $resumes[0];
    }

    $phases = $mission['phases'] ?? [];
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
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- HEADER -->
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-5xl mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-800"><?= htmlspecialchars($mission['client']) ?></h1>
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($mission['titre']) ?></p>
                </div>
                <div class="text-right text-sm text-gray-400">
                    <p><?= CONSULTANT_NAME ?></p>
                    <p><?= CONSULTANT_TITLE ?></p>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-5xl mx-auto px-6 py-8">

        <!-- FICHE MISSION COMPACTE -->
        <div class="bg-white rounded-lg border border-gray-200 p-5 mb-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="text-gray-400 block">Client</span>
                    <span class="font-medium text-gray-800"><?= htmlspecialchars($mission['client']) ?></span>
                </div>
                <div>
                    <span class="text-gray-400 block">Objectif</span>
                    <span class="font-medium text-gray-800"><?= htmlspecialchars($mission['objectif'] ?? '—') ?></span>
                </div>
                <div>
                    <span class="text-gray-400 block">Démarrage</span>
                    <span class="font-medium text-gray-800"><?= htmlspecialchars($mission['date_debut'] ?? '—') ?></span>
                </div>
                <div>
                    <span class="text-gray-400 block">Phase</span>
                    <span class="inline-block px-2 py-1 text-xs font-medium rounded-full
                        <?= ($mission['phase_statut'] ?? '') === 'en_cours' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' ?>">
                        <?= htmlspecialchars($mission['phase_actuelle'] ?? '—') ?>
                    </span>
                </div>
            </div>

            <?php if (!empty($mission['intervenants'])): ?>
            <div class="mt-4 pt-4 border-t border-gray-100">
                <span class="text-gray-400 text-sm block mb-2">Intervenants</span>
                <div class="flex flex-wrap gap-3">
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
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Avancement mission</h2>
            <div class="flex items-center gap-1 overflow-x-auto pb-2">
                <?php foreach ($phases as $idx => $phase):
                    $is_current = ($phase['statut'] ?? '') === 'en_cours';
                    $is_done = ($phase['statut'] ?? '') === 'termine';

                    if ($is_done) {
                        $bg = 'bg-green-500 text-white';
                        $icon = '✓';
                    } elseif ($is_current) {
                        $bg = 'bg-blue-500 text-white';
                        $icon = '●';
                    } else {
                        $bg = 'bg-gray-200 text-gray-500';
                        $icon = '';
                    }
                ?>
                <?php if ($idx > 0): ?>
                    <div class="w-6 h-0.5 <?= $is_done ? 'bg-green-400' : ($is_current ? 'bg-blue-300' : 'bg-gray-200') ?> flex-shrink-0"></div>
                <?php endif; ?>
                <div class="flex flex-col items-center flex-shrink-0 min-w-0" style="width: 90px;">
                    <div class="w-8 h-8 rounded-full <?= $bg ?> flex items-center justify-center text-xs font-bold mb-1">
                        <?= $icon ?: ($idx + 1) ?>
                    </div>
                    <span class="text-xs text-center leading-tight <?= $is_current ? 'font-semibold text-blue-700' : 'text-gray-500' ?>">
                        <?= htmlspecialchars($phase['nom']) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- CONTENU : RÉSUMÉ DE LA SEMAINE -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">

            <!-- SIDEBAR : LISTE DES RÉSUMÉS -->
            <div class="md:col-span-1">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Résumés</h2>
                <nav class="space-y-1">
                    <?php foreach ($resumes as $r):
                        $is_active = ($active_resume && $r['id'] === $active_resume['id']);
                        // Extraire le titre depuis la première ligne du MD
                        $first_line = strtok($r['content'], "\n");
                        $label = preg_replace('/^#+\s*/', '', $first_line);
                    ?>
                    <a href="/<?= $mission['slug'] ?>/<?= $r['id'] ?>"
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

            <!-- CONTENU PRINCIPAL -->
            <div class="md:col-span-3">
                <?php if ($active_resume): ?>
                <div class="bg-white rounded-lg border border-gray-200 p-6">
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

    </div>

    <!-- FOOTER -->
    <footer class="border-t border-gray-100 mt-12 py-6 text-center text-xs text-gray-400">
        <?= SITE_NAME ?> · <?= date('Y') ?>
    </footer>

</body>
</html>
<?php
}
