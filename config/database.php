<?php
// config/database.php
define('BASE_PATH', dirname(__DIR__));
define('DB_DIR',    BASE_PATH . '/database');
define('DB_PATH',   DB_DIR . '/iceanotes.sqlite');

// --- App meta ---
$__appJson = json_decode(file_get_contents(BASE_PATH . '/config/app.json'), true) ?: [];
define('APP_NAME',    $__appJson['name']       ?? 'ICEANOTES');
define('APP_VERSION', $__appJson['version']    ?? 'v0.0');
define('APP_YEAR',    $__appJson['year_start'] ?? 2025);

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        if (!is_dir(DB_DIR)) mkdir(DB_DIR, 0775, true);
        if (file_exists(DB_PATH)) {
            $h = @fopen(DB_PATH, 'rb');
            $header = $h ? fread($h, 16) : '';
            if ($h) fclose($h);
            if ($header !== "SQLite format 3\0") unlink(DB_PATH);
        }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

function passwordAlgo(): string|int
{
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
}
function hashPassword(string $password): string
{
    if (passwordAlgo() === PASSWORD_ARGON2ID) {
        return password_hash($password, PASSWORD_ARGON2ID, ['memory_cost'=>65536,'time_cost'=>4,'threads'=>2]);
    }
    return password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]);
}
function verifyPassword(string $password, string $hash): bool { return password_verify($password, $hash); }
function needsRehash(string $hash): bool { return password_needs_rehash($hash, passwordAlgo()); }

function redirect(string $url): void { header('Location: ' . $url); exit; }
function e(?string $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }

// --- Pagination helpers ---
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
function sortLink(string $column, string $label, string $currentSort, string $currentDir): string
{
    $newDir = ($column === $currentSort && strtoupper($currentDir) === 'ASC') ? 'desc' : 'asc';
    $params = array_merge($_GET, ['sort' => $column, 'dir' => $newDir]);
    $url    = '?' . http_build_query($params);
    $arrow  = ($column === $currentSort) ? (strtoupper($currentDir) === 'ASC' ? ' ▲' : ' ▼') : '';
    return '<a href="' . e($url) . '" class="hover:text-blue-600">' . e($label) . $arrow . '</a>';
}
function renderPagination(int $page, int $perPage, int $total): string
{
    $pages = max(1, (int)ceil($total / $perPage));
    if ($pages <= 1) return '';
    $build = function (int $p, string $label, bool $active = false) {
        $params = array_merge($_GET, ['page' => $p]);
        $url = '?' . http_build_query($params);
        $cls = $active ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-300';
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

// --- i18n ---
function currentLang(): string
{
    static $lang = null;
    if ($lang !== null) return $lang;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $supported = ['fr', 'en', 'es'];
    if (isset($_GET['lang']) && in_array($_GET['lang'], $supported, true)) {
        $_SESSION['lang'] = $_GET['lang'];
    }
    $lang = $_SESSION['lang'] ?? 'fr';
    if (!in_array($lang, $supported, true)) $lang = 'fr';
    return $lang;
}

function loadTranslations(): array
{
    static $dict = null;
    if ($dict !== null) return $dict;
    $file = BASE_PATH . '/lang/' . currentLang() . '.php';
    $dict = file_exists($file) ? require $file : [];
    return $dict;
}

function t(string $key, array $vars = []): string
{
    $dict = loadTranslations();
    $txt  = $dict[$key] ?? $key;
    foreach ($vars as $k => $v) {
        $txt = str_replace(':' . $k, (string)$v, $txt);
    }
    return $txt;
}

/**
 * Génère les 3 icônes d'action (edit / toggle / delete)
 */
function actionButtons(
    string $editUrl,
    string $deleteUrl,
    string $toggleUrl,
    bool $isActive,
    bool $canDelete = true,
    bool $canToggle = true
): string {
    $btn = 'inline-flex items-center justify-center w-8 h-8 rounded-lg hover:bg-gray-100 transition';
    $html = '<div class="flex items-center justify-end gap-1">';

    // --- Edit ---
    $html .= '<a href="' . e($editUrl) . '" class="' . $btn . ' text-blue-600" title="' . e(t('edit')) . '">';
    $html .= '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">';
    $html .= '<path stroke-linecap="round" stroke-linejoin="round" d="M11 5h6m2 4v10a2 2 0 01-2 2H7a2 2 0 01-2-2V7a2 2 0 012-2h6m4-3l3 3-9 9H8v-3l9-9z"/>';
    $html .= '</svg></a>';

    // --- Toggle actif/inactif ---
    if ($canToggle) {
        $color = $isActive ? 'text-yellow-600' : 'text-green-600';
        $label = $isActive ? t('deactivate') : t('activate');
        $html .= '<a href="' . e($toggleUrl) . '" class="' . $btn . ' ' . $color . '" title="' . e($label) . '">';
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">';
        $html .= '<path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8a5 5 0 010 10H8A5 5 0 018 7z"/>';
        $html .= '<circle cx="8" cy="12" r="2" fill="currentColor"/>';
        $html .= '</svg></a>';
    }

    // --- Delete ---
    if ($canDelete) {
        $html .= '<a href="' . e($deleteUrl) . '" class="' . $btn . ' text-red-600"';
        $html .= ' onclick="return confirm(\'' . e(t('confirm_delete')) . '\') && confirm(\'' . e(t('confirm_delete_2')) . '\')"';
        $html .= ' title="' . e(t('delete')) . '">';
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">';
        $html .= '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/>';
        $html .= '</svg></a>';
    }

    $html .= '</div>';
    return $html;
}