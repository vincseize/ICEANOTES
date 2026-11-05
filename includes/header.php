<?php
// includes/header.php
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
$user = currentUser();

$isAdmin = ($user['type_label'] ?? '') === 'admin';

$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
function navClass(string $file, string $current): string {
    return $file === $current
        ? 'text-blue-600 font-semibold border-b-2 border-blue-600 pb-1'
        : 'text-gray-700 hover:text-blue-600 pb-1 border-b-2 border-transparent';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ICEANOTES</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">

<nav class="bg-white shadow-sm border-b border-gray-200 shrink-0">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center gap-6">
                <a href="/ICEANOTES/pages/accueil.php" class="text-xl font-bold text-blue-600">ICEANOTES</a>
                <div class="hidden lg:flex gap-4 text-sm">
                    <a href="/ICEANOTES/pages/accueil.php"    class="<?= navClass('accueil.php', $current) ?>">Accueil</a>
                    <?php if ($isAdmin): ?>
                        <a href="/ICEANOTES/pages/filieres.php"    class="<?= navClass('filieres.php', $current) ?>">Filières</a>
                    <?php endif; ?>
                    <a href="/ICEANOTES/pages/matieres.php"   class="<?= navClass('matieres.php', $current) ?>">Matières</a>
                    <?php if ($isAdmin): ?>
                        <a href="/ICEANOTES/pages/professeurs.php" class="<?= navClass('professeurs.php', $current) ?>">Professeurs</a>
                        <a href="/ICEANOTES/pages/diplomes.php"    class="<?= navClass('diplomes.php', $current) ?>">Diplômes</a>
                        <a href="/ICEANOTES/pages/promos.php"      class="<?= navClass('promos.php', $current) ?>">Promos</a>
                    <?php endif; ?>
                    <a href="/ICEANOTES/pages/etudiants.php"  class="<?= navClass('etudiants.php', $current) ?>">Étudiants</a>
                    <a href="/ICEANOTES/pages/notes.php"      class="<?= navClass('notes.php', $current) ?>">Notes</a>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="hidden sm:block text-sm text-gray-500">
                    <?= e($user['email']) ?>
                    <?php if ($isAdmin): ?>
                        <span class="ml-1 inline-block px-1.5 py-0.5 rounded text-xs bg-purple-100 text-purple-700">admin</span>
                    <?php endif; ?>
                </span>
                <a href="/ICEANOTES/logout.php" class="text-sm bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg">Déconnexion</a>
            </div>
        </div>
        <div class="lg:hidden flex gap-4 pb-3 text-sm overflow-x-auto">
            <a href="/ICEANOTES/pages/accueil.php"    class="<?= navClass('accueil.php', $current) ?>">Accueil</a>
            <?php if ($isAdmin): ?>
                <a href="/ICEANOTES/pages/filieres.php"    class="<?= navClass('filieres.php', $current) ?>">Filières</a>
            <?php endif; ?>
            <a href="/ICEANOTES/pages/matieres.php"   class="<?= navClass('matieres.php', $current) ?>">Matières</a>
            <?php if ($isAdmin): ?>
                <a href="/ICEANOTES/pages/professeurs.php" class="<?= navClass('professeurs.php', $current) ?>">Profs</a>
                <a href="/ICEANOTES/pages/diplomes.php"    class="<?= navClass('diplomes.php', $current) ?>">Diplômes</a>
                <a href="/ICEANOTES/pages/promos.php"      class="<?= navClass('promos.php', $current) ?>">Promos</a>
            <?php endif; ?>
            <a href="/ICEANOTES/pages/etudiants.php"  class="<?= navClass('etudiants.php', $current) ?>">Étudiants</a>
            <a href="/ICEANOTES/pages/notes.php"      class="<?= navClass('notes.php', $current) ?>">Notes</a>
        </div>
    </div>
</nav>

<main class="flex-1 w-full max-w-7xl mx-auto px-4 py-8">