<?php
// _SANDBOX/4-add-ecoles-actif.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';

if (!isLoggedIn()) {
    redirect('/ICEANOTES/login.php?error=' . urlencode('Accès réservé aux utilisateurs connectés.'));
}
if (!isSuperviseur()) {
    redirect('/ICEANOTES/pages/accueil.php');
}

$pdo = getDB();
$log = [];
$ok = true;

// Vérifier si la colonne existe déjà
$cols = $pdo->query("PRAGMA table_info(ecoles)")->fetchAll();
$hasActif = false;
foreach ($cols as $c) {
    if ($c['name'] === 'actif') { $hasActif = true; break; }
}

if ($hasActif) {
    $log[] = ['info', "La colonne <code>ecoles.actif</code> existe déjà. Rien à faire."];
} else {
    try {
        $pdo->exec("ALTER TABLE ecoles ADD COLUMN actif INTEGER NOT NULL DEFAULT 1");
        $log[] = ['ok', "Colonne <code>ecoles.actif</code> ajoutée (défaut : 1 = actif)."];
        $log[] = ['info', "Toutes les écoles existantes ont été marquées comme actives."];
    } catch (Exception $e) {
        $log[] = ['err', "Erreur : " . htmlspecialchars($e->getMessage())];
        $ok = false;
    }
}

$stats = [];
if ($ok) {
    foreach (['ecoles', 'filieres'] as $t) {
        $stats[$t] = (int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Migration ecoles.actif</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen p-6">
<div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-lg p-8">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl <?= $ok ? 'bg-green-100' : 'bg-red-100' ?> flex items-center justify-center text-2xl">
                <?= $ok ? '✅' : '❌' ?>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Migration <code>ecoles.actif</code></h1>
        </div>
        <a href="index.php" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </a>
    </div>

    <div class="space-y-2 mb-6">
        <?php foreach ($log as [$type, $msg]):
            $cls = match($type) {
                'ok'   => 'bg-green-50 text-green-800 border-green-200',
                'err'  => 'bg-red-50 text-red-800 border-red-200',
                default => 'bg-blue-50 text-blue-800 border-blue-200',
            };
        ?>
            <div class="p-3 rounded-lg border text-sm <?= $cls ?>"><?= $msg ?></div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($stats)): ?>
        <div class="grid grid-cols-2 gap-3 mb-6">
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-xs text-gray-500">Écoles</div>
                <div class="text-xl font-bold text-gray-800"><?= $stats['ecoles'] ?></div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-xs text-gray-500">Filières</div>
                <div class="text-xl font-bold text-gray-800"><?= $stats['filieres'] ?></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="flex gap-2">
        <a href="/ICEANOTES/pages/accueil.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">→ Accueil</a>
        <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">← Sandbox</a>
    </div>
</div>
</body>
</html>