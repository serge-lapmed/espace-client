<?php
/**
 * Espace Mission — Authentification
 *
 * Rôles :
 *   admin     → Serge : voit tout, gère les comptes
 *   dirigeant → Jacky : voit tout sur sa mission (budget, stratégie, docs)
 *   equipe    → Équipe client : voit l'opérationnel (actions, planning, résumés)
 *   externe   → BPI/RC : vue synthèse (avancement, jalons, livrables)
 */

require_once __DIR__ . '/db.php';

// Démarrer la session si pas déjà fait
function auth_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_lifetime' => 86400 * 30, // 30 jours
            'cookie_httponly' => true,
            'cookie_secure' => true,
            'cookie_samesite' => 'Lax',
        ]);
    }
}

// Vérifier si l'utilisateur est connecté
function auth_check(): bool {
    auth_start();
    return !empty($_SESSION['user_id']);
}

// Récupérer l'utilisateur courant
function auth_user(): ?array {
    auth_start();
    if (empty($_SESSION['user_id'])) return null;
    return [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['user_email'],
        'nom' => $_SESSION['user_nom'],
        'role' => $_SESSION['user_role'],
        'mission_slug' => $_SESSION['user_mission_slug'],
    ];
}

// Vérifier si l'utilisateur a accès à une mission
function auth_can_access_mission(string $slug): bool {
    $user = auth_user();
    if (!$user) return false;
    if ($user['role'] === 'admin') return true;
    if ($user['mission_slug'] === null) return true; // Accès à toutes les missions
    return $user['mission_slug'] === $slug;
}

// Vérifier le rôle minimum
function auth_has_role(string $min_role): bool {
    $user = auth_user();
    if (!$user) return false;
    $hierarchy = ['admin' => 4, 'dirigeant' => 3, 'equipe' => 2, 'externe' => 1];
    return ($hierarchy[$user['role']] ?? 0) >= ($hierarchy[$min_role] ?? 99);
}

// Tenter un login
function auth_login(string $email, string $password): array {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND actif = 1 LIMIT 1');
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['ok' => false, 'error' => 'Email ou mot de passe incorrect.'];
    }

    // Mettre à jour last_login
    $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

    // Stocker en session
    auth_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_nom'] = $user['nom'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_mission_slug'] = $user['mission_slug'];

    return ['ok' => true];
}

// Déconnexion
function auth_logout(): void {
    auth_start();
    session_destroy();
}

// Générer un token de reset
function auth_create_reset_token(string $email): ?string {
    $db = get_db();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND actif = 1 LIMIT 1');
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();

    if (!$user) return null;

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 1800); // 30 min

    $db->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?')
       ->execute([$token, $expires, $user['id']]);

    return $token;
}

// Vérifier un token de reset
function auth_verify_reset_token(string $token): ?array {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW() AND actif = 1 LIMIT 1');
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

// Changer le mot de passe via token
function auth_reset_password(string $token, string $new_password): bool {
    $user = auth_verify_reset_token($token);
    if (!$user) return false;

    $hash = password_hash($new_password, PASSWORD_BCRYPT);
    $db = get_db();
    $db->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?')
       ->execute([$hash, $user['id']]);

    return true;
}

// Vérifier un lien de partage (accès sans compte)
function auth_check_share_token(string $token): ?array {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM share_links WHERE token = ? AND actif = 1 AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1');
    $stmt->execute([$token]);
    $link = $stmt->fetch();

    if (!$link) return null;

    // Incrémenter les vues
    $db->prepare('UPDATE share_links SET views = views + 1 WHERE id = ?')->execute([$link['id']]);

    return $link;
}

// Créer un utilisateur (admin only)
function auth_create_user(string $email, string $nom, string $role, ?string $mission_slug, string $temp_password): int {
    $db = get_db();
    $hash = password_hash($temp_password, PASSWORD_BCRYPT);

    $stmt = $db->prepare('INSERT INTO users (email, password_hash, nom, role, mission_slug) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([strtolower(trim($email)), $hash, $nom, $role, $mission_slug]);

    return (int) $db->lastInsertId();
}

// Créer un lien de partage (admin only)
function auth_create_share_link(string $mission_slug, string $label, string $role = 'externe', ?string $expires_at = null): string {
    $db = get_db();
    $token = bin2hex(random_bytes(16));

    $stmt = $db->prepare('INSERT INTO share_links (token, mission_slug, label, role, expires_at) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$token, $mission_slug, $label, $role, $expires_at]);

    return $token;
}

// Lister les utilisateurs (admin only)
function auth_list_users(): array {
    $db = get_db();
    return $db->query('SELECT id, email, nom, role, mission_slug, last_login, actif, created_at FROM users ORDER BY created_at DESC')->fetchAll();
}
