<?php
// actions/login_action.php
require_once __DIR__ . '/../config/database.php';

const SESSION_LIFETIME_IDLE = 3600;      // 1h d'inactivité
const REMEMBER_COOKIE_DAYS  = 30;        // 30 jours

$remember = !empty($_POST['remember']);

// --- Session PHP ---
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,          // cookie navigateur
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// --- Récupération ---
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    header('Location: /ICEANOTES/login.php?error=' . urlencode('Champs manquants'));
    exit;
}

$pdo  = getDB();
$stmt = $pdo->prepare("
    SELECT u.*, t.label AS type_label
    FROM users u
    JOIN users_types t ON t.id = u.user_type_id
    WHERE u.email = ?
");
$stmt->execute([$email]);
$user = $stmt->fetch();

// --- Vérification ---
if (!$user || !verifyPassword($password, $user['password'])) {
    header('Location: /ICEANOTES/login.php?error=' . urlencode('Identifiants invalides'));
    exit;
}

// --- Restriction aux admins / professeurs ---
if (!in_array($user['type_label'], ['admin', 'professeur'], true)) {
    header('Location: /ICEANOTES/login.php?error=' . urlencode('Accès non autorisé'));
    exit;
}

// --- Re-hash auto si algo évolue ---
if (needsRehash($user['password'])) {
    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
        ->execute([hashPassword($password), $user['id']]);
}

// --- Session ---
$_SESSION['user_id']       = (int)$user['id'];
$_SESSION['user_email']    = $user['email'];
$_SESSION['user_type']     = $user['type_label'];
$_SESSION['last_activity'] = time();
$_SESSION['remember']      = $remember;

// --- Remember me : token persistant 30 jours ---
if ($remember) {
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);
    $expiresTs = time() + REMEMBER_COOKIE_DAYS * 24 * 3600;

    // Nettoyage des anciens tokens expirés + de cet utilisateur
    $pdo->exec("DELETE FROM remember_tokens WHERE expires_at < datetime('now')");
    $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$user['id']]);

    $pdo->prepare("
        INSERT INTO remember_tokens (user_id, token_hash, expires_at)
        VALUES (?, ?, datetime('now', '+' || ? || ' days'))
    ")->execute([$user['id'], $hash, REMEMBER_COOKIE_DAYS]);

    setcookie('remember_token', $token, [
        'expires'  => $expiresTs,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    setcookie('remember_email', $email, [
        'expires'  => $expiresTs,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
} else {
    // Nettoie un éventuel token existant
    if (!empty($_COOKIE['remember_token'])) {
        $oldHash = hash('sha256', $_COOKIE['remember_token']);
        $pdo->prepare("DELETE FROM remember_tokens WHERE token_hash = ?")->execute([$oldHash]);
    }
    setcookie('remember_email', '', ['expires' => time() - 3600, 'path' => '/']);
    setcookie('remember_token', '', ['expires' => time() - 3600, 'path' => '/']);
}

header('Location: /ICEANOTES/pages/accueil.php');
exit;