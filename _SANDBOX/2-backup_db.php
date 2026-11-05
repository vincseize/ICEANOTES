<?php
// _SANDBOX/2-backup_db.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';

// 🔒 Protection sandbox
if (!isLoggedIn()) {
    redirect('/ICEANOTES/login.php?error=' . urlencode('Accès réservé aux utilisateurs connectés.'));
}
if (!isSuperviseur()) {
    redirect('/ICEANOTES/pages/accueil.php');
}

$pdo  = getDB();
$date = date('Y-m-d_His');

$dumpDir = BASE_PATH . '/database/dump';
if (!is_dir($dumpDir)) {
    mkdir($dumpDir, 0775, true);
}

$sqlFile    = $dumpDir . '/iceanotes_backup_' . $date . '.sql';
$sqliteFile = $dumpDir . '/iceanotes_backup_' . $date . '.sqlite';

$msg = '';
$msgType = '';

// -----------------------------------------------------------------------------
// TÉLÉCHARGEMENT
// -----------------------------------------------------------------------------
if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $path = $dumpDir . '/' . $file;
    if (file_exists($path) && (str_ends_with($file, '.sql') || str_ends_with($file, '.sqlite'))) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
    http_response_code(404);
    die('Fichier introuvable');
}

// -----------------------------------------------------------------------------
// SUPPRESSION (individuelle ou par paire)
// -----------------------------------------------------------------------------
if (isset($_GET['delete'])) {
    $base = basename($_GET['delete']); // ex: iceanotes_backup_2026-11-05_143022
    $deleted = 0;

    foreach ([$base . '.sql', $base . '.sqlite'] as $f) {
        $path = $dumpDir . '/' . $f;
        if (file_exists($path)) {
            unlink($path);
            $deleted++;
        }
    }

    header('Location: 2-backup_db.php?deleted=' . $deleted);
    exit;
}

// -----------------------------------------------------------------------------
// CRÉATION D'UNE SAUVEGARDE
// -----------------------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'backup') {
    // 1) SQLite
    if (copy(DB_PATH, $sqliteFile)) {
        $msg .= "✅ SQLite : " . basename($sqliteFile) . " (" . round(filesize($sqliteFile) / 1024, 1) . " Ko)<br>";
        $msgType = 'success';
    } else {
        $msg .= "❌ Échec de la copie SQLite<br>";
        $msgType = 'error';
    }

    // 2) SQL
    try {
        $sql = "-- ICEANOTES backup $date\n\n";
        $tables = $pdo->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll();
        foreach ($tables as $t) {
            $sql .= "-- Table {$t['name']}\n";
            $sql .= $t['sql'] . ";\n\n";
            $rows = $pdo->query("SELECT * FROM \"{$t['name']}\"")->fetchAll();
            foreach ($rows as $r) {
                $cols = array_keys($r);
                $vals = array_map(fn($v) => $v === null ? 'NULL' : (is_numeric($v) ? $v : $pdo->quote((string)$v)), array_values($r));
                $sql .= "INSERT INTO \"{$t['name']}\" (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
            }
            $sql .= "\n";
        }
        file_put_contents($sqlFile, $sql);
        $msg .= "✅ SQL : " . basename($sqlFile) . " (" . round(filesize($sqlFile) / 1024, 1) . " Ko)";
    } catch (Exception $e) {
        $msg .= "❌ Erreur SQL : " . htmlspecialchars($e->getMessage());
        $msgType = 'error';
    }
}

// -----------------------------------------------------------------------------
// REGROUPEMENT PAR PAIRES (.sql + .sqlite même timestamp)
// -----------------------------------------------------------------------------
$groups = [];
$allFiles = glob($dumpDir . '/*.{sql,sqlite}', GLOB_BRACE);

foreach ($allFiles as $f) {
    $name = basename($f);
    // Format : iceanotes_backup_YYYY-MM-DD_HHMMSS.{sql|sqlite}
    if (preg_match('/^(iceanotes_backup_[\d\-_]+)\.(sql|sqlite)$/', $name, $m)) {
        $key = $m[1]; // iceanotes_backup_2026-11-05_143022
        $ext = $m[2];

        if (!isset($groups[$key])) {
            $groups[$key] = [
                'key'   => $key,
                'date'  => filemtime($f),
                'sql'   => null,
                'sqlite'=> null,
            ];
        }

        $info = [
            'name' => $name,
            'size' => filesize($f),
            'date' => filemtime($f),
        ];

        if ($ext === 'sql') {
            $groups[$key]['sql'] = $info;
        } else {
            $groups[$key]['sqlite'] = $info;
        }
    }
}

// Trier par date décroissante
usort($groups, fn($a, $b) => $b['date'] - $a['date']);

$deletedMsg = (int)($_GET['deleted'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Backup DB — Sandbox ICEANOTES</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen p-6">
<div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-lg p-8">

    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center text-2xl">
                🛡️
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Sauvegarde base</h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    SQL + SQLite dans <code class="bg-gray-100 px-1.5 py-0.5 rounded text-xs">database/dump/</code>
                </p>
            </div>
        </div>
        <a href="index.php" title="Retour sandbox"
           class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </a>
    </div>

    <?php if ($deletedMsg > 0): ?>
        <div class="mb-6 p-4 rounded-xl text-sm bg-yellow-50 text-yellow-800 border border-yellow-200">
            🗑️ <?= $deletedMsg ?> fichier(s) supprimé(s).
        </div>
    <?php endif; ?>

    <?php if ($msg): ?>
        <div class="mb-6 p-4 rounded-xl text-sm
            <?= $msgType === 'success'
                ? 'bg-green-50 text-green-700 border border-green-200'
                : 'bg-red-50 text-red-700 border border-red-200' ?>">
            <?= $msg ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="mb-8">
        <input type="hidden" name="action" value="backup">
        <button type="submit"
                class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-3 rounded-lg transition flex items-center justify-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
            </svg>
            Créer une sauvegarde maintenant
        </button>
        <p class="text-xs text-gray-400 mt-2 text-center">
            Génère <code>.sql</code> + <code>.sqlite</code> dans <code>database/dump/</code>
        </p>
    </form>

    <div class="border-t border-gray-200 pt-6">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">
            Sauvegardes disponibles (<?= count($groups) ?> paire<?= count($groups) > 1 ? 's' : '' ?>)
        </h2>

        <?php if (empty($groups)): ?>
            <p class="text-gray-400 text-sm">Aucune sauvegarde.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($groups as $g):
                    $labelDate = date('d/m/Y à H:i:s', $g['date']);
                    $key = $g['key'];
                ?>
                    <div class="border border-gray-200 rounded-xl p-4 hover:bg-gray-50 transition">
                        <!-- En-tête : date + suppression -->
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <?= e($labelDate) ?>
                            </div>
                            <a href="?delete=<?= urlencode($key) ?>"
                               onclick="return confirm('Supprimer cette sauvegarde ?\n\nLes 2 fichiers (.sql + .sqlite) seront supprimés.')"
                               title="Supprimer cette sauvegarde"
                               class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-red-500 hover:bg-red-50 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </a>
                        </div>

                        <!-- Paire SQL / SQLite -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <!-- Fichier SQL -->
                            <?php if ($g['sql']): ?>
                                <div class="flex items-center gap-2 p-2 rounded-lg bg-blue-50 border border-blue-100">
                                    <div class="text-lg">📄</div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-mono text-xs text-gray-800 truncate" title="<?= e($g['sql']['name']) ?>">
                                            <?= e($g['sql']['name']) ?>
                                        </div>
                                        <div class="text-xs text-gray-500"><?= round($g['sql']['size'] / 1024, 1) ?> Ko</div>
                                    </div>
                                    <a href="?download=<?= urlencode($g['sql']['name']) ?>"
                                       title="Télécharger SQL"
                                       class="inline-flex items-center justify-center w-7 h-7 rounded bg-blue-100 hover:bg-blue-200 text-blue-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <!-- Fichier SQLite -->
                            <?php if ($g['sqlite']): ?>
                                <div class="flex items-center gap-2 p-2 rounded-lg bg-purple-50 border border-purple-100">
                                    <div class="text-lg">💾</div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-mono text-xs text-gray-800 truncate" title="<?= e($g['sqlite']['name']) ?>">
                                            <?= e($g['sqlite']['name']) ?>
                                        </div>
                                        <div class="text-xs text-gray-500"><?= round($g['sqlite']['size'] / 1024, 1) ?> Ko</div>
                                    </div>
                                    <a href="?download=<?= urlencode($g['sqlite']['name']) ?>"
                                       title="Télécharger SQLite"
                                       class="inline-flex items-center justify-center w-7 h-7 rounded bg-purple-100 hover:bg-purple-200 text-purple-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>