<?php
// includes/header.php
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
$user = currentUser();
$isAdmin = isAdmin();
$isSuperviseur = isSuperviseur();

$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
function navClass(string $file, string $current): string {
    return $file === $current
        ? 'text-blue-600 font-semibold border-b-2 border-blue-600 pb-1'
        : 'text-gray-700 hover:text-blue-600 pb-1 border-b-2 border-transparent';
}
$lang = currentLang();

$flags = [
    'fr' => ['🇫🇷', 'Français', 'FR'],
    'en' => ['🇬🇧', 'English',  'EN'],
    'es' => ['🇪🇸', 'Español',  'ES'],
];
$currentFlag = $flags[$lang] ?? $flags['fr'];

$initiales = strtoupper(
    mb_substr($user['prenom'] ?? '', 0, 1) .
    mb_substr($user['nom']    ?? '', 0, 1)
);
if ($initiales === '') $initiales = '?';

$typeLabel = $user['type_label'] ?? 'user';
$avatarColor = match($typeLabel) {
    'admin'       => 'bg-purple-600 text-white',
    'superviseur' => 'bg-blue-600 text-white',
    'user', 'professeur' => 'bg-green-600 text-white',
    default       => 'bg-gray-500 text-white',
};
$typeBadgeColor = match($typeLabel) {
    'admin'       => 'bg-purple-100 text-purple-700',
    'superviseur' => 'bg-blue-100 text-blue-700',
    'user', 'professeur' => 'bg-green-100 text-green-700',
    default       => 'bg-gray-100 text-gray-600',
};
$typeBadgeLabel = t('type_' . ($typeLabel === 'professeur' ? 'user' : $typeLabel));
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> — <?= e(APP_VERSION) ?></title>
    <meta name="theme-color" content="#2563eb">
    <link rel="manifest" href="/ICEANOTES/manifest.json">
    <link rel="icon" type="image/svg+xml" href="/ICEANOTES/assets/icons/favicon.svg">
    <link rel="apple-touch-icon" href="/ICEANOTES/assets/icons/icon-192.svg">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">

<nav class="bg-white shadow-sm border-b border-gray-200 shrink-0">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center gap-6">
                <a href="/ICEANOTES/pages/accueil.php" class="flex items-center gap-2">
                    <img src="/ICEANOTES/assets/icons/icon.svg" alt="ICEANOTES" class="h-8 w-8">
                    <span class="text-xl font-bold text-blue-600">
                        <?= e(APP_NAME) ?>
                        <span class="text-xs text-gray-400 font-normal"><?= e(APP_VERSION) ?></span>
                    </span>
                </a>

                <div class="hidden xl:flex gap-3 text-sm items-center">
                    <a href="/ICEANOTES/pages/accueil.php" class="<?= navClass('accueil.php', $current) ?>"><?= t('nav_home') ?></a>

                    <?php if ($isSuperviseur): ?>
                        <a href="/ICEANOTES/pages/ecoles.php"      class="<?= navClass('ecoles.php', $current) ?>">Ecole</a>
                        <a href="/ICEANOTES/pages/filieres.php"    class="<?= navClass('filieres.php', $current) ?>"><?= t('nav_filieres') ?></a>
                        <a href="/ICEANOTES/pages/promotions.php"  class="<?= navClass('promotions.php', $current) ?>"><?= t('nav_promotions') ?></a>
                    <?php endif; ?>

                    <a href="/ICEANOTES/pages/modules.php" class="<?= navClass('modules.php', $current) ?>"><?= t('nav_modules') ?></a>

                    <?php if ($isSuperviseur): ?>
                        <a href="/ICEANOTES/pages/professeurs.php" class="<?= navClass('professeurs.php', $current) ?>"><?= t('nav_professeurs') ?></a>
                    <?php endif; ?>

                    <a href="/ICEANOTES/pages/etudiants.php" class="<?= navClass('etudiants.php', $current) ?>"><?= t('nav_etudiants') ?></a>
                    <a href="/ICEANOTES/pages/notes.php"     class="<?= navClass('notes.php', $current) ?>"><?= t('nav_notes') ?></a>

                    <?php if ($isSuperviseur): ?>
                        <a href="/ICEANOTES/pages/note_types.php" class="<?= navClass('note_types.php', $current) ?>"><?= t('nav_note_types') ?></a>

                        <span class="text-gray-300 select-none">|</span>

                        <a href="/ICEANOTES/_SANDBOX/"
                           class="text-gray-500 hover:text-purple-600 transition text-xs font-mono uppercase tracking-wider">
                            SANDBOX
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2" title="<?= e($user['prenom'] . ' ' . $user['nom'] . ' — ' . $user['email']) ?>">
                    <span class="hidden sm:inline-block px-1.5 py-0.5 rounded text-xs font-medium <?= $typeBadgeColor ?>">
                        <?= e($typeBadgeLabel) ?>
                    </span>
                    <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm shadow-sm <?= $avatarColor ?>">
                        <?= e($initiales) ?>
                    </div>
                </div>

                <a href="/ICEANOTES/logout.php" title="<?= e(t('logout')) ?>"
                   class="bg-red-500 hover:bg-red-600 text-white px-2 py-1.5 rounded-lg transition flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </a>

                <div class="relative" id="langDropdownWrapper">
                    <button type="button" id="langDropdownBtn" onclick="toggleLangDropdown()"
                            class="inline-flex items-center gap-1.5 px-2 sm:px-2.5 py-1.5 rounded-lg border border-gray-200 hover:border-gray-300 hover:bg-gray-50 transition">
                        <span class="text-lg leading-none"><?= $currentFlag[0] ?></span>
                        <span class="hidden sm:inline text-xs font-semibold text-gray-700"><?= $currentFlag[2] ?></span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="hidden sm:block h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div id="langDropdownMenu" class="hidden absolute right-0 mt-2 w-40 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-50">
                        <?php foreach ($flags as $code => [$emoji, $label, $short]): ?>
                            <?php
                            $url = '?' . http_build_query(array_merge($_GET, ['lang' => $code]));
                            $isActive = ($lang === $code);
                            ?>
                            <a href="<?= e($url) ?>" class="flex items-center gap-3 px-3 py-2 text-sm transition
                                      <?= $isActive ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' ?>">
                                <span class="text-lg leading-none"><?= $emoji ?></span>
                                <span class="flex-1"><?= e($label) ?></span>
                                <?php if ($isActive): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="xl:hidden flex gap-4 pb-3 text-sm overflow-x-auto">
            <a href="/ICEANOTES/pages/accueil.php" class="<?= navClass('accueil.php', $current) ?>"><?= t('nav_home') ?></a>

            <?php if ($isSuperviseur): ?>
                <a href="/ICEANOTES/pages/ecoles.php"      class="<?= navClass('ecoles.php', $current) ?>">Ecole</a>
                <a href="/ICEANOTES/pages/filieres.php"    class="<?= navClass('filieres.php', $current) ?>"><?= t('nav_filieres') ?></a>
                <a href="/ICEANOTES/pages/promotions.php"  class="<?= navClass('promotions.php', $current) ?>"><?= t('nav_promotions') ?></a>
            <?php endif; ?>

            <a href="/ICEANOTES/pages/modules.php" class="<?= navClass('modules.php', $current) ?>"><?= t('nav_modules') ?></a>

            <?php if ($isSuperviseur): ?>
                <a href="/ICEANOTES/pages/professeurs.php" class="<?= navClass('professeurs.php', $current) ?>"><?= t('nav_professeurs') ?></a>
            <?php endif; ?>

            <a href="/ICEANOTES/pages/etudiants.php" class="<?= navClass('etudiants.php', $current) ?>"><?= t('nav_etudiants') ?></a>
            <a href="/ICEANOTES/pages/notes.php"     class="<?= navClass('notes.php', $current) ?>"><?= t('nav_notes') ?></a>

            <?php if ($isSuperviseur): ?>
                <a href="/ICEANOTES/pages/note_types.php" class="<?= navClass('note_types.php', $current) ?>"><?= t('nav_note_types') ?></a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main class="flex-1 w-full max-w-7xl mx-auto px-4 py-8">

<script>
function toggleLangDropdown() {
    document.getElementById('langDropdownMenu').classList.toggle('hidden');
}
document.addEventListener('click', function (e) {
    const wrapper = document.getElementById('langDropdownWrapper');
    if (!wrapper.contains(e.target)) {
        document.getElementById('langDropdownMenu').classList.add('hidden');
    }
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.getElementById('langDropdownMenu').classList.add('hidden');
    }
});
</script>