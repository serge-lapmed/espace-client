<?php
/**
 * Espace Mission — Page de connexion
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Déjà connecté ? Rediriger
if (auth_check()) {
    header('Location: /');
    exit;
}

$error = '';
$success = '';
$page = $_GET['page'] ?? 'login'; // login | forgot | reset

// ─── LOGIN ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'login') {
    $result = auth_login($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($result['ok']) {
        header('Location: /');
        exit;
    }
    $error = $result['error'];
}

// ─── FORGOT PASSWORD ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'forgot') {
    $email = $_POST['email'] ?? '';
    $token = auth_create_reset_token($email);
    if ($token) {
        // Envoyer l'email de reset
        $reset_url = SITE_URL . '/login.php?page=reset&token=' . $token;
        $subject = 'Réinitialisation de votre mot de passe — Espace Mission';
        $body = "Bonjour,\n\nVous avez demandé la réinitialisation de votre mot de passe.\n\nCliquez sur ce lien (valable 30 minutes) :\n$reset_url\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez cet email.\n\nCordialement,\nSerge Fornier — La PME Digitale";
        $headers = "From: noreply@lapmedigitale.fr\r\nReply-To: serge@lapmedigitale.fr\r\nContent-Type: text/plain; charset=UTF-8";
        mail($email, $subject, $body, $headers);
    }
    // Toujours afficher le même message (sécurité)
    $success = 'Si cette adresse est associée à un compte, un email de réinitialisation a été envoyé.';
    $page = 'forgot';
}

// ─── RESET PASSWORD ───
if ($page === 'reset') {
    $token = $_GET['token'] ?? '';
    $token_user = auth_verify_reset_token($token);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_pass = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        if (strlen($new_pass) < 8) {
            $error = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif ($new_pass !== $confirm) {
            $error = 'Les mots de passe ne correspondent pas.';
        } elseif (auth_reset_password($token, $new_pass)) {
            $success = 'Mot de passe modifié. Vous pouvez vous connecter.';
            $page = 'login';
        } else {
            $error = 'Lien expiré ou invalide. Demandez un nouveau lien.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; }
        .login-card { max-width: 400px; }
        .brand-bar { height: 4px; background: linear-gradient(90deg, #2563eb 0%, #7c3aed 100%); border-radius: 4px 4px 0 0; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4">

<div class="login-card w-full">
    <div class="brand-bar"></div>
    <div class="bg-white rounded-b-lg shadow-sm border border-gray-200 p-8">

        <div class="text-center mb-6">
            <h1 class="text-xl font-semibold text-gray-800">Espace Mission</h1>
            <p class="text-sm text-gray-500 mt-1">La PME Digitale</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3 mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg p-3 mb-4"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($page === 'login'): ?>
        <!-- FORMULAIRE LOGIN -->
        <form method="POST" action="/login.php?page=login" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" required autofocus
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                <input type="password" name="password" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>
            <button type="submit"
                    class="w-full bg-blue-600 text-white font-medium py-2.5 rounded-lg text-sm hover:bg-blue-700 transition">
                Se connecter
            </button>
        </form>
        <div class="text-center mt-4">
            <a href="/login.php?page=forgot" class="text-sm text-blue-600 hover:underline">Mot de passe oublié ?</a>
        </div>

        <?php elseif ($page === 'forgot'): ?>
        <!-- FORMULAIRE MOT DE PASSE OUBLIÉ -->
        <p class="text-sm text-gray-600 mb-4">Entrez votre adresse email. Si un compte existe, vous recevrez un lien de réinitialisation.</p>
        <form method="POST" action="/login.php?page=forgot" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" required autofocus
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>
            <button type="submit"
                    class="w-full bg-blue-600 text-white font-medium py-2.5 rounded-lg text-sm hover:bg-blue-700 transition">
                Envoyer le lien
            </button>
        </form>
        <div class="text-center mt-4">
            <a href="/login.php?page=login" class="text-sm text-blue-600 hover:underline">&larr; Retour à la connexion</a>
        </div>

        <?php elseif ($page === 'reset' && $token_user): ?>
        <!-- FORMULAIRE NOUVEAU MOT DE PASSE -->
        <p class="text-sm text-gray-600 mb-4">Choisissez un nouveau mot de passe pour <strong><?= htmlspecialchars($token_user['email']) ?></strong>.</p>
        <form method="POST" action="/login.php?page=reset&token=<?= htmlspecialchars($token) ?>" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nouveau mot de passe</label>
                <input type="password" name="password" required minlength="8"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirmer</label>
                <input type="password" name="password_confirm" required minlength="8"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>
            <button type="submit"
                    class="w-full bg-blue-600 text-white font-medium py-2.5 rounded-lg text-sm hover:bg-blue-700 transition">
                Changer le mot de passe
            </button>
        </form>

        <?php else: ?>
        <!-- TOKEN INVALIDE -->
        <div class="text-center">
            <p class="text-sm text-gray-600 mb-4">Ce lien a expiré ou est invalide.</p>
            <a href="/login.php?page=forgot" class="text-sm text-blue-600 hover:underline">Demander un nouveau lien</a>
        </div>
        <?php endif; ?>

    </div>

    <p class="text-center text-xs text-gray-400 mt-4">
        <?= CONSULTANT_NAME ?> · <?= CONSULTANT_TITLE ?>
    </p>
</div>

</body>
</html>
