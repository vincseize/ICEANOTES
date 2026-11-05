<?php
// _SANDBOX/index.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';

// 🔒 Protection sandbox
if (!isLoggedIn()) {
    redirect('/ICEANOTES/login.php?error=' . urlencode('Accès réservé aux utilisateurs connectés.'));
}
if (!isSuperviseur()) {
    redirect('/ICEANOTES/pages/accueil.php');
}

$user = currentUser();

// --- Fichiers de la sandbox triés par numéro (1-, 2-, 3-...) ---
$files = [];
foreach (glob(__DIR__ . '/*') as $f) {
    $name = basename($f);
    if (!is_file($f)) continue;
    // Ignorer fichiers système
    if (in_array($name, ['index.php', '.htaccess', 'guard.php', 'arbo.txt', 'arbo.php'], true)) continue;
    // Ne garder que les fichiers numérotés (1-xxx.php, 2-xxx.php, etc.)
    if (!preg_match('/^(\d+)-(.+)\.php$/', $name, $matches)) continue;

    $files[] = [
        'num'  => (int)$matches[1],
        'name' => $name,
        'size' => filesize($f),
        'date' => filemtime($f),
    ];
}

// Tri par numéro croissant
usort($files, fn($a, $b) => $a['num'] <=> $b['num']);

// --- Icônes par numéro ---
$icons = [
    1 => '🔑', // reset password
    2 => '🛡️', // backup
    3 => '📊', // check_db
    4 => '📁',
    5 => '⚙️',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Sandbox — ICEANOTES</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen p-6">
<div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-lg p-8">

    <!-- Header avec icône maison -->
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center text-2xl">
                🧪
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Sandbox — ICEANOTES</h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    Connecté : <strong><?= e($user['prenom'] . ' ' . $user['nom']) ?></strong>
                    <span class="inline-block px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-700 ml-1">
                        <?= e($user['type_label']) ?>
                    </span>
                </p>
            </div>
        </div>
        <a href="/ICEANOTES/pages/accueil.php"
           title="Retour à l'application"
           class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
        </a>
    </div>

    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">
        Outils (<?= count($files) ?>)
    </h2>

    <?php if (empty($files)): ?>
        <p class="text-gray-400 text-sm">Aucun outil numéroté dans la sandbox.</p>
    <?php else: ?>
        <div class="space-y-2">
            <?php foreach ($files as $f):
                $icon = $icons[$f['num']] ?? '📄';
            ?>
                <a href="<?= e($f['name']) ?>"
                   class="flex items-center gap-4 p-4 rounded-xl border border-gray-200 hover:border-purple-300 hover:bg-purple-50/50 transition group">
                    <div class="w-10 h-10 rounded-lg bg-gray-100 group-hover:bg-white flex items-center justify-center text-xl shrink-0">
                        <?= $icon ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-gray-800 group-hover:text-purple-700 font-mono text-sm">
                            <?= e($f['name']) ?>
                        </div>
                        <div class="text-xs text-gray-500">
                            <?= round($f['size'] / 1024, 1) ?> Ko — modifié le <?= date('d/m/Y à H:i', $f['date']) ?>
                        </div>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 group-hover:text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Footer : liens secondaires -->
    <div class="mt-8 pt-6 border-t border-gray-200 flex flex-wrap gap-4 text-xs">
        <a href="arbo.php" class="text-gray-500 hover:text-purple-600 hover:underline inline-flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
            </svg>
            arbo.php
        </a>
        <a href="check_db.php" class="text-gray-500 hover:text-purple-600 hover:underline inline-flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
            </svg>
            check_db.php
        </a>
        <span class="text-gray-400 ml-auto">🔒 Accès admin/superviseur uniquement</span>
    </div>
</div>
</body>
</html>