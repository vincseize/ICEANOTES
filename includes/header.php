<?php
// includes/header.php
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
$user = currentUser();
$isAdmin = isAdmin();

$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
function navClass(string $file, string $current): string {
    return $file === $current
        ? 'text-blue-600 font-semibold border-b-2 border-blue-600 pb-1'
        : 'text-gray-700 hover:text-blue-600 pb-1 border-b-2 border-transparent';
}
$lang = currentLang();
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> — <?= e(APP_VERSION) ?></title>

    <!-- PWA -->
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
                <!-- Logo + nom -->
                <a href="/ICEANOTES/pages/accueil.php" class="flex items-center gap-2">
                    <img src="/ICEANOTES/assets/icons/icon.svg" alt="ICEANOTES" class="h-8 w-8">
                    <span class="text-xl font-bold text-blue-600">
                        <?= e(APP_NAME) ?>
                        <span class="text-xs text-gray-400 font-normal"><?= e(APP_VERSION) ?></span>
                    </span>
                </a>

                <!-- Nav desktop -->
                <div class="hidden lg:flex gap-4 text-sm">
                    <a href="/ICEANOTES/pages/accueil.php"    class="<?= navClass('accueil.php', $current) ?>"><?= t('nav_home') ?></a>
                    <?php if ($isAdmin): ?>
                        <a href="/ICEANOTES/pages/filieres.php"    class="<?= navClass('filieres.php', $current) ?>"><?= t('nav_filieres') ?></a>
                        <a href="/ICEANOTES/pages/diplomes.php"    class="<?= navClass('diplomes.php', $current) ?>"><?= t('nav_diplomes') ?></a>
                    <?php endif; ?>
                    <a href="/ICEANOTES/pages/matieres.php"   class="<?= navClass('matieres.php', $current) ?>"><?= t('nav_matieres') ?></a>
                    <?php if ($isAdmin): ?>
                        <a href="/ICEANOTES/pages/professeurs.php" class="<?= navClass('professeurs.php', $current) ?>"><?= t('nav_professeurs') ?></a>
                        <a href="/ICEANOTES/pages/promos.php"      class="<?= navClass('promos.php', $current) ?>"><?= t('nav_promos') ?></a>
                    <?php endif; ?>
                    <a href="/ICEANOTES/pages/etudiants.php"  class="<?= navClass('etudiants.php', $current) ?>"><?= t('nav_etudiants') ?></a>
                    <a href="/ICEANOTES/pages/notes.php"      class="<?= navClass('notes.php', $current) ?>"><?= t('nav_notes') ?></a>
                </div>
            </div>

            <!-- Actions à droite -->
            <div class="flex items-center gap-3">
                <!-- Sélecteur langue -->
                <form method="GET" class="flex items-center gap-1 text-sm">
                    <select name="lang" onchange="this.form.submit()" class="px-2 py-1 border border-gray-300 rounded text-sm">
                        <?php foreach (['fr'=>'FR','en'=>'EN','es'=>'ES'] as $code => $lbl): ?>
                            <option value="<?= $code ?>" <?= $lang === $code ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <span class="hidden sm:block text-sm text-gray-500">
                    <?= e($user['email']) ?>
                    <?php if ($isAdmin): ?>
                        <span class="ml-1 inline-block px-1.5 py-0.5 rounded text-xs bg-purple-100 text-purple-700"><?= t('admin_badge') ?></span>
                    <?php endif; ?>
                </span>

                <a href="/ICEANOTES/logout.php" class="text-sm bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg"><?= t('logout') ?></a>
            </div>
        </div>

        <!-- Nav mobile -->
        <div class="lg:hidden flex gap-4 pb-3 text-sm overflow-x-auto">
            <a href="/ICEANOTES/pages/accueil.php"    class="<?= navClass('accueil.php', $current) ?>"><?= t('nav_home') ?></a>
            <?php if ($isAdmin): ?>
                <a href="/ICEANOTES/pages/filieres.php"    class="<?= navClass('filieres.php', $current) ?>"><?= t('nav_filieres') ?></a>
                <a href="/ICEANOTES/pages/diplomes.php"    class="<?= navClass('diplomes.php', $current) ?>"><?= t('nav_diplomes') ?></a>
            <?php endif; ?>
            <a href="/ICEANOTES/pages/matieres.php"   class="<?= navClass('matieres.php', $current) ?>"><?= t('nav_matieres') ?></a>
            <?php if ($isAdmin): ?>
                <a href="/ICEANOTES/pages/professeurs.php" class="<?= navClass('professeurs.php', $current) ?>"><?= t('nav_professeurs') ?></a>
                <a href="/ICEANOTES/pages/promos.php"      class="<?= navClass('promos.php', $current) ?>"><?= t('nav_promos') ?></a>
            <?php endif; ?>
            <a href="/ICEANOTES/pages/etudiants.php"  class="<?= navClass('etudiants.php', $current) ?>"><?= t('nav_etudiants') ?></a>
            <a href="/ICEANOTES/pages/notes.php"      class="<?= navClass('notes.php', $current) ?>"><?= t('nav_notes') ?></a>
        </div>
    </div>
</nav>

<main class="flex-1 w-full max-w-7xl mx-auto px-4 py-8">