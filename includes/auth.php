<?php
// includes/auth.php
require_once __DIR__ . '/../config/database.php';  // ✅ corrige le crash BASE_PATH

const SESSION_LIFETIME_IDLE = 3600;

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function tryRememberLogin(): void
{
    if (isset($_SESSION['user_id'])) return;
    if (empty($_COOKIE['remember_token'])) return;

    $pdo  = getDB();
    $hash = hash('sha256', $_COOKIE['remember_token']);
    $stmt = $pdo->prepare("
        SELECT u.*, t.label AS type_label
        FROM remember_tokens rt
        JOIN users u ON u.id = rt.user_id
        JOIN users_types t ON t.id = u.user_type_id
        WHERE rt.token_hash = ? AND rt.expires_at > datetime('now')
    ");
    $stmt->execute([$hash]);
    $user = $stmt->fetch();
    if (!$user) return;

    $_SESSION['user_id']       = (int)$user['id'];
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['user_type']     = $user['type_label'];
    $_SESSION['last_activity'] = time();
    $_SESSION['remember']      = true;
}
tryRememberLogin();

function isLoggedIn(): bool
{
    if (!isset($_SESSION['user_id'])) return false;
    if (!empty($_SESSION['remember'])) {
        $_SESSION['last_activity'] = time();
        return true;
    }
    $last = $_SESSION['last_activity'] ?? 0;
    if (time() - $last > SESSION_LIFETIME_IDLE) {
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /ICEANOTES/login.php?error=' . urlencode('Session expirée, merci de vous reconnecter.'));
        exit;
    }
}

function currentUser(): ?array
{
    if (!isLoggedIn()) return null;
    static $cache = null;
    if ($cache !== null) return $cache;

    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT u.*, t.label AS type_label
        FROM users u
        JOIN users_types t ON t.id = u.user_type_id
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $cache = $stmt->fetch() ?: null;
    return $cache;
}

function isAdmin(): bool
{
    return isLoggedIn() && (($_SESSION['user_type'] ?? '') === 'admin');
}

function requireAdmin(): void
{
    if (!isAdmin()) {
        header('Location: /ICEANOTES/pages/accueil.php');
        exit;
    }
}

/**
 * Vérifie si l'utilisateur est superviseur ou admin
 */
function isSuperviseur(): bool
{
    return isLoggedIn() && in_array(($_SESSION['user_type'] ?? ''), ['admin', 'superviseur'], true);
}

/**
 * Bloque l'accès aux superviseurs + admins
 */
function requireSuperviseur(): void
{
    if (!isSuperviseur()) {
        header('Location: /ICEANOTES/pages/accueil.php');
        exit;
    }
}

/**
 * Vérifie si l'utilisateur peut modifier un user donné
 * Règles :
 *  - Admin : peut tout (sauf se supprimer/désactiver lui-même)
 *  - Superviseur : peut tout sur les 'user'/'guest', mais pas sur admin/superviseur
 *  - User : ne peut rien sur les users
 */
function canEditUser(array $target): bool
{
    if (!isLoggedIn()) return false;

    $me = currentUser();
    if (!$me) return false;

    // Admin : peut tout sauf se toucher lui-même (suppression/désactivation)
    if ($me['type_label'] === 'admin') {
        return (int)$target['id'] !== (int)$me['id'];
    }

    // Superviseur : peut uniquement gérer les 'user' et 'guest'
    if ($me['type_label'] === 'superviseur') {
        return in_array($target['type_label'], ['user', 'guest'], true);
    }

    // User : aucun droit sur les autres users
    return false;
}

/**
 * Peut-on supprimer cet utilisateur ?
 */
function canDeleteUser(array $target): bool
{
    if (!isLoggedIn()) return false;

    $me = currentUser();
    if (!$me) return false;

    // Protégé (admin ou superviseur protégé) : jamais supprimable
    if (!empty($target['protected'])) return false;

    // Admin ne peut pas se supprimer
    if ($me['type_label'] === 'admin' && (int)$target['id'] === (int)$me['id']) {
        return false;
    }

    // Superviseur : ne peut supprimer que les 'user'/'guest' non protégés
    if ($me['type_label'] === 'superviseur') {
        return in_array($target['type_label'], ['user', 'guest'], true) && empty($target['protected']);
    }

    return false;
}

/**
 * Peut-on désactiver cet utilisateur ?
 */
function canToggleUser(array $target): bool
{
    if (!isLoggedIn()) return false;

    $me = currentUser();
    if (!$me) return false;

    // Admin : jamais désactivable
    if ($target['type_label'] === 'admin') return false;

    // Superviseur protégé : désactivable par admin seulement
    if ($target['type_label'] === 'superviseur') {
        return $me['type_label'] === 'admin';
    }

    // User/guest : désactivable par admin ou superviseur
    if (in_array($me['type_label'], ['admin', 'superviseur'], true)) {
        return true;
    }

    return false;
}