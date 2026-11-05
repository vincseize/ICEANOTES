<?php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';

// 🔒 Protection sandbox
if (!isLoggedIn()) {
    redirect('/ICEANOTES/login.php?error=' . urlencode('Accès réservé aux utilisateurs connectés.'));
}
if (!isSuperviseur()) {
    redirect('/ICEANOTES/pages/accueil.php');
}
// _SANDBOX/index.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';

// 🔒 Protection : uniquement admin/superviseur connectés
if (!isLoggedIn()) {
    redirect('/ICEANOTES/login.php?error=' . urlencode('Accès réservé aux utilisateurs connectés.'));
}
if (!isSuperviseur()) {
    redirect('/ICEANOTES/pages/accueil.php');
}

$user = currentUser();

$files = [];
foreach (glob(__DIR__ . '/*') as $f) {
    $name = basename($f);
    if (in_array($name, ['index.php', '.htaccess', 'guard.php'], true)) continue;
    if (is_file($f)) {
        $files[] = ['name' => $name, 'size' => filesize($f), 'date' => filemtime($f)];
    }
}
usort($files, fn($a, $b) => $b['date'] - $a['date']);
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
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">🧪 Sandbox — ICEANOTES</h1>
            <p class="text-sm text-gray-500 mt-1">
                Connecté : <strong><?= e($user['prenom'] . ' ' . $user['nom']) ?></strong>
                <span class="inline-block px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-700 ml-1">
                    <?= e($user['type_label']) ?>
                </span>
            </p>
        </div>
        <a href="/ICEANOTES/pages/accueil.php" class="text-sm text-blue-600 hover:underline">← Retour</a>
    </div>

    <h2 class="text-lg font-semibold text-gray-800 mb-3">
        Fichiers disponibles (<?= count($files) ?>)
    </h2>

    <?php if (empty($files)): ?>
        <p class="text-gray-400 text-sm">Aucun fichier.</p>
    <?php else: ?>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-left">
                <tr>
                    <th class="px-3 py-2">Fichier</th>
                    <th class="px-3 py-2">Taille</th>
                    <th class="px-3 py-2">Modifié</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($files as $f): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2">
                        <a href="<?= e($f['name']) ?>" class="text-blue-600 hover:underline font-mono text-xs">
                            <?= e($f['name']) ?>
                        </a>
                    </td>
                    <td class="px-3 py-2 text-gray-500"><?= round($f['size'] / 1024, 1) ?> Ko</td>
                    <td class="px-3 py-2 text-gray-500"><?= date('d/m/Y H:i', $f['date']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="mt-8 pt-6 border-t border-gray-200 text-xs text-gray-400">
        🔒 Accès réservé aux administrateurs et superviseurs.
    </div>
</div>
</body>
</html>