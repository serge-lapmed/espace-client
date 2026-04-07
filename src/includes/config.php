<?php
/**
 * Espace Mission — Configuration
 * mission.lapmedigitale.fr
 */

// ─── Site ───
define('SITE_NAME', 'Espace Mission — La PME Digitale');
define('SITE_URL', 'https://mission.lapmedigitale.fr');
define('MISSIONS_DIR', __DIR__ . '/../missions');
define('CONSULTANT_NAME', 'Serge Fornier');
define('CONSULTANT_TITLE', 'Consultant SI — La PME Digitale');

// ─── Base de données (O2switch) ───
// À CONFIGURER : voir phpMyAdmin dans l'admin O2switch
define('DB_HOST', 'localhost');
define('DB_NAME', 'qufi1696_mission');     // Adapter au nom réel
define('DB_USER', 'serge');     // Adapter
define('DB_PASS', '4318ocKamese!');                      // ← METTRE LE MOT DE PASSE ICI

// ─── Contenu : fonctions de chargement depuis fichiers ───

function load_mission(string $slug): ?array {
    $path = MISSIONS_DIR . '/' . $slug . '/mission.json';
    if (!file_exists($path)) return null;
    $data = json_decode(file_get_contents($path), true);
    if (!$data) return null;
    $data['slug'] = $slug;
    return $data;
}

function list_resumes(string $slug): array {
    $dir = MISSIONS_DIR . '/' . $slug . '/resumes';
    if (!is_dir($dir)) return [];

    $files = glob($dir . '/*.md');
    $resumes = [];

    foreach ($files as $file) {
        $filename = basename($file, '.md');
        $content = file_get_contents($file);
        $resumes[] = [
            'id' => $filename,
            'content' => $content,
            'file' => $file,
        ];
    }

    usort($resumes, fn($a, $b) => strcmp($b['id'], $a['id']));
    return $resumes;
}

function list_missions(): array {
    $dirs = glob(MISSIONS_DIR . '/*', GLOB_ONLYDIR);
    $missions = [];
    foreach ($dirs as $dir) {
        $slug = basename($dir);
        $mission = load_mission($slug);
        if ($mission) $missions[] = $mission;
    }
    return $missions;
}

function sanitize_slug(string $input): string {
    return preg_replace('/[^a-z0-9\-]/', '', strtolower($input));
}
