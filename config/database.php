<?php
// config/database.php
// -----------------------------------------------------------------------------
// Configuration centrale : chemins, connexion PDO SQLite, helpers de hash.
// À inclure en TOUTE PREMIÈRE ligne de chaque fichier qui utilise BASE_PATH.
// -----------------------------------------------------------------------------

// --- Chemins absolus ---------------------------------------------------------
// BASE_PATH = racine du projet (ex: C:\xampp\htdocs\ICEANOTES)
define('BASE_PATH', dirname(__DIR__));
define('DB_DIR',    BASE_PATH . '/database');
define('DB_PATH',   DB_DIR . '/iceanotes.sqlite');

// --- Connexion PDO (singleton) ----------------------------------------------
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        // Crée le dossier database/ s'il n'existe pas
        if (!is_dir(DB_DIR)) {
            mkdir(DB_DIR, 0775, true);
        }

        // Vérifie que le fichier est un vrai SQLite (sinon on le recrée vide)
        if (file_exists(DB_PATH)) {
            $h = @fopen(DB_PATH, 'rb');
            $header = $h ? fread($h, 16) : '';
            if ($h) fclose($h);
            if ($header !== "SQLite format 3\0") {
                unlink(DB_PATH);
            }
        }

        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    return $pdo;
}

// --- Hash de mots de passe (Argon2id si dispo, sinon bcrypt cost 12) --------
function passwordAlgo(): string|int
{
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
}

function hashPassword(string $password): string
{
    if (passwordAlgo() === PASSWORD_ARGON2ID) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 Mo
            'time_cost'   => 4,
            'threads'     => 2,
        ]);
    }
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

function needsRehash(string $hash): bool
{
    return password_needs_rehash($hash, passwordAlgo());
}

// --- Helpers génériques ------------------------------------------------------
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// --- Helpers pagination / recherche / tri -----------------------------------

/**
 * Récupère les paramètres de liste depuis $_GET
 * Retourne [page, perPage, offset, search, sort, dir]
 */
function listParams(int $defaultPerPage = 20): array
{
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(5, min(100, (int)($_GET['per_page'] ?? $defaultPerPage)));
    $offset  = ($page - 1) * $perPage;
    $search  = trim($_GET['q'] ?? '');
    $sort    = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['sort'] ?? '');
    $dir     = strtolower($_GET['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
    return compact('page', 'perPage', 'offset', 'search', 'sort', 'dir');
}

/**
 * Construit le lien de tri (toggle asc/desc si déjà trié)
 */
function sortLink(string $column, string $label, string $currentSort, string $currentDir): string
{
    $newDir = ($column === $currentSort && strtoupper($currentDir) === 'ASC') ? 'desc' : 'asc';
    $params = array_merge($_GET, ['sort' => $column, 'dir' => $newDir]);
    $url    = '?' . http_build_query($params);
    $arrow  = '';
    if ($column === $currentSort) {
        $arrow = strtoupper($currentDir) === 'ASC' ? ' ▲' : ' ▼';
    }
    return '<a href="' . e($url) . '" class="hover:text-blue-600">' . e($label) . $arrow . '</a>';
}

/**
 * Rend la barre de pagination
 */
function renderPagination(int $page, int $perPage, int $total): string
{
    $pages = max(1, (int)ceil($total / $perPage));
    if ($pages <= 1) return '';

    $build = function (int $p, string $label, bool $active = false) {
        $params = array_merge($_GET, ['page' => $p]);
        $url = '?' . http_build_query($params);
        $cls = $active
            ? 'bg-blue-600 text-white'
            : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-300';
        return '<a href="' . e($url) . '" class="px-3 py-1.5 rounded-lg text-sm ' . $cls . '">' . $label . '</a>';
    };

    $html = '<div class="flex flex-wrap items-center justify-center gap-1 mt-4">';
    if ($page > 1) $html .= $build($page - 1, '‹');
    for ($i = 1; $i <= $pages; $i++) {
        if ($i === 1 || $i === $pages || abs($i - $page) <= 2) {
            $html .= $build($i, (string)$i, $i === $page);
        } elseif (abs($i - $page) === 3) {
            $html .= '<span class="px-2 text-gray-400">…</span>';
        }
    }
    if ($page < $pages) $html .= $build($page + 1, '›');
    $html .= '</div>';
    return $html;
}

function resetLink(string $page, int $perPage): string
{
    return '/ICEANOTES/pages/' . $page . '?per_page=' . $perPage;
}