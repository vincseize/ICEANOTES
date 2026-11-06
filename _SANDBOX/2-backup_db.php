<?php
// _SANDBOX/2-backup_db.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';

if (!isLoggedIn()) {
    redirect('/ICEANOTES/login.php?error=' . urlencode('Accès réservé aux utilisateurs connectés.'));
}
if (!isSuperviseur()) {
    redirect('/ICEANOTES/pages/accueil.php');
}

$pdo  = getDB();
$date = date('Y-m-d_His');
$dumpDir = BASE_PATH . '/database/dump';
if (!is_dir($dumpDir)) mkdir($dumpDir, 0775, true);

$sqlFile    = $dumpDir . '/iceanotes_backup_' . $date . '.sql';
$sqliteFile = $dumpDir . '/iceanotes_backup_' . $date . '.sqlite';

// -----------------------------------------------------------------------------
// ACTION AJAX : création de backup (retourne JSON)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'backup') {
    header('Content-Type: application/json');
    $result = ['ok' => false, 'html' => '', 'error' => ''];

    try {
        // SQLite
        if (!copy(DB_PATH, $sqliteFile)) {
            throw new Exception('Échec de la copie SQLite');
        }

        // SQL
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

        // Compter les backups pour le retour
        $allFiles = glob($dumpDir . '/iceanotes_backup_*.{sql,sqlite}', GLOB_BRACE);
        $pairs = [];
        foreach ($allFiles as $f) {
            if (preg_match('/^(iceanotes_backup_[\d\-_]+)\.(sql|sqlite)$/', basename($f), $m)) {
                $pairs[$m[1]] = true;
            }
        }

        $result = [
            'ok'         => true,
            'count'      => count($pairs),
            'sqlSize'    => round(filesize($sqlFile) / 1024, 1),
            'sqliteSize' => round(filesize($sqliteFile) / 1024, 1),
            'key'        => 'iceanotes_backup_' . $date,
            'dateLabel'  => date('d/m/Y à H:i:s'),
        ];
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }

    echo json_encode($result);
    exit;
}

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
// SUPPRESSION AJAX
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    header('Content-Type: application/json');
    $base = basename($_POST['key'] ?? '');
    $deleted = 0;
    foreach ([$base . '.sql', $base . '.sqlite'] as $f) {
        $path = $dumpDir . '/' . $f;
        if (file_exists($path)) { unlink($path); $deleted++; }
    }
    echo json_encode(['ok' => true, 'deleted' => $deleted]);
    exit;
}

// -----------------------------------------------------------------------------
// REGROUPEMENT PAR PAIRES
// -----------------------------------------------------------------------------
$groups = [];
$allFiles = glob($dumpDir . '/*.{sql,sqlite}', GLOB_BRACE);

foreach ($allFiles as $f) {
    $name = basename($f);
    if (preg_match('/^(iceanotes_backup_[\d\-_]+)\.(sql|sqlite)$/', $name, $m)) {
        $key = $m[1];
        $ext = $m[2];
        if (!isset($groups[$key])) {
            $groups[$key] = ['key' => $key, 'date' => filemtime($f), 'sql' => null, 'sqlite' => null];
        }
        $info = ['name' => $name, 'size' => filesize($f), 'date' => filemtime($f)];
        if ($ext === 'sql') $groups[$key]['sql'] = $info;
        else $groups[$key]['sqlite'] = $info;
    }
}

usort($groups, fn($a, $b) => $b['date'] - $a['date']);

$lastBackupDate = null;
$lastBackupAgo  = null;
$lastBackupOld  = false;

if (!empty($groups)) {
    $lastBackupDate = $groups[0]['date'];
    $diff = time() - $lastBackupDate;
    $lastBackupOld = $diff > 7 * 86400;
    if ($diff < 60)         $lastBackupAgo = "à l'instant";
    elseif ($diff < 3600)   $lastBackupAgo = "il y a " . floor($diff / 60) . " min";
    elseif ($diff < 86400)  $lastBackupAgo = "il y a " . floor($diff / 3600) . " h";
    else                    $lastBackupAgo = "il y a " . floor($diff / 86400) . " jour(s)";
}

$dbSize = file_exists(DB_PATH) ? round(filesize(DB_PATH) / 1024, 1) : 0;
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
            <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center text-2xl">🛡️</div>
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

    <!-- Toast (caché par défaut) -->
    <div id="toast" class="hidden mb-6 p-4 rounded-xl text-sm"></div>

    <!-- ==================================================================== -->
    <!-- 1. LISTE DES SAUVEGARDES                                             -->
    <!-- ==================================================================== -->
    <div class="border-t border-gray-200 pt-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div>
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Sauvegardes disponibles</h2>
                <p class="text-xs text-gray-400 mt-0.5" id="summary">
                    <?php if (empty($groups)): ?>
                        Aucune sauvegarde pour le moment.
                    <?php else: ?>
                        <span id="countLabel"><?= count($groups) ?></span> paire<?= count($groups) > 1 ? 's' : '' ?>
                        — dernière : <strong><?= e($lastBackupAgo) ?></strong>
                        (<?= date('d/m/Y H:i', $lastBackupDate) ?>)
                    <?php endif; ?>
                </p>
            </div>
            <?php if ($lastBackupOld): ?>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 text-xs font-medium">
                    ⚠️ Dernière sauvegarde &gt; 7 jours
                </span>
            <?php elseif (!empty($groups)): ?>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-50 text-green-700 border border-green-200 text-xs font-medium">
                    ✅ À jour
                </span>
            <?php endif; ?>
        </div>

        <div class="mb-4 p-3 rounded-lg bg-gray-50 border border-gray-200 text-xs text-gray-600 flex items-center gap-4">
            <span>📊 Base actuelle : <strong><?= $dbSize ?> Ko</strong></span>
            <span class="text-gray-300">|</span>
            <span>📁 <code><span id="fileCount"><?= count($allFiles) ?></span> fichier(s)</code> dans <code>database/dump/</code></span>
        </div>

        <div id="backupList" class="space-y-3">
            <?php if (empty($groups)): ?>
                <div id="emptyState" class="text-center py-12 border-2 border-dashed border-gray-200 rounded-xl bg-gray-50">
                    <div class="text-5xl mb-3">📭</div>
                    <p class="text-gray-500 text-sm mb-1">Aucune sauvegarde existante</p>
                    <p class="text-gray-400 text-xs">Utilisez le bouton ci-dessous pour en créer une.</p>
                </div>
            <?php else: ?>
                <?php foreach ($groups as $idx => $g):
                    $labelDate = date('d/m/Y à H:i:s', $g['date']);
                    $key = $g['key'];
                    $isLatest = ($idx === 0);
                ?>
                    <div class="backup-card border <?= $isLatest ? 'border-blue-200 bg-blue-50/30' : 'border-gray-200' ?> rounded-xl p-4 hover:bg-gray-50 transition" data-key="<?= e($key) ?>">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <?= e($labelDate) ?>
                                <?php if ($isLatest): ?>
                                    <span class="badge-latest px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase bg-blue-100 text-blue-700">Dernière</span>
                                <?php endif; ?>
                            </div>
                            <button type="button" onclick="deleteBackup('<?= e($key) ?>')"
                                    title="Supprimer cette sauvegarde"
                                    class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-red-500 hover:bg-red-50 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <?php if ($g['sql']): ?>
                                <div class="flex items-center gap-2 p-2 rounded-lg bg-blue-50 border border-blue-100">
                                    <div class="text-lg">📄</div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-mono text-xs text-gray-800 truncate"><?= e($g['sql']['name']) ?></div>
                                        <div class="text-xs text-gray-500"><?= round($g['sql']['size'] / 1024, 1) ?> Ko</div>
                                    </div>
                                    <a href="?download=<?= urlencode($g['sql']['name']) ?>"
                                       class="inline-flex items-center justify-center w-7 h-7 rounded bg-blue-100 hover:bg-blue-200 text-blue-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <?php if ($g['sqlite']): ?>
                                <div class="flex items-center gap-2 p-2 rounded-lg bg-purple-50 border border-purple-100">
                                    <div class="text-lg">💾</div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-mono text-xs text-gray-800 truncate"><?= e($g['sqlite']['name']) ?></div>
                                        <div class="text-xs text-gray-500"><?= round($g['sqlite']['size'] / 1024, 1) ?> Ko</div>
                                    </div>
                                    <a href="?download=<?= urlencode($g['sqlite']['name']) ?>"
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
            <?php endif; ?>
        </div>
    </div>

    <!-- ==================================================================== -->
    <!-- 2. BOUTON GÉNÉRER (AJAX)                                             -->
    <!-- ==================================================================== -->
    <div class="mt-8 pt-6 border-t border-gray-200">
        <button type="button" id="generateBtn" onclick="generateBackup()"
                class="w-full bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white font-medium py-3 rounded-lg transition flex items-center justify-center gap-2">
            <svg id="btnIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
            </svg>
            <span id="btnLabel">Créer une sauvegarde maintenant</span>
        </button>
        <p class="text-xs text-gray-400 mt-2 text-center">
            Génère <code>.sql</code> + <code>.sqlite</code> dans <code>database/dump/</code> — sans recharger la page
        </p>
    </div>

</div>

<script>
const toast = document.getElementById('toast');
const btn = document.getElementById('generateBtn');
const btnLabel = document.getElementById('btnLabel');
const btnIcon = document.getElementById('btnIcon');

function showToast(msg, type = 'success') {
    toast.className = 'mb-6 p-4 rounded-xl text-sm border ' + (
        type === 'success'
            ? 'bg-green-50 text-green-700 border-green-200'
            : 'bg-red-50 text-red-700 border-red-200'
    );
    toast.innerHTML = msg;
    toast.classList.remove('hidden');
    setTimeout(() => toast.classList.add('hidden'), 4000);
}

async function generateBackup() {
    btn.disabled = true;
    btnLabel.textContent = 'Génération en cours…';
    btnIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>';
    btnIcon.classList.add('animate-spin');

    try {
        const res = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=backup'
        });
        const json = await res.json();

        if (json.ok) {
            showToast(`✅ Sauvegarde créée : <code>${json.key}</code> (${json.sqlSize} Ko SQL + ${json.sqliteSize} Ko SQLite)`);

            // Retirer l'état vide si présent
            const empty = document.getElementById('emptyState');
            if (empty) empty.remove();

            // Retirer le badge "Dernière" des anciennes cartes
            document.querySelectorAll('.badge-latest').forEach(el => el.remove());
            document.querySelectorAll('.backup-card').forEach(el => {
                el.classList.remove('border-blue-200', 'bg-blue-50/30');
                el.classList.add('border-gray-200');
            });

            // Ajouter la nouvelle carte en tête
            const newCard = document.createElement('div');
            newCard.className = 'backup-card border border-blue-200 bg-blue-50/30 rounded-xl p-4 hover:bg-gray-50 transition';
            newCard.dataset.key = json.key;
            newCard.innerHTML = `
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2 text-sm font-medium text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        ${json.dateLabel}
                        <span class="badge-latest px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase bg-blue-100 text-blue-700">Dernière</span>
                    </div>
                    <button type="button" onclick="deleteBackup('${json.key}')"
                            class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-red-500 hover:bg-red-50 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div class="flex items-center gap-2 p-2 rounded-lg bg-blue-50 border border-blue-100">
                        <div class="text-lg">📄</div>
                        <div class="flex-1 min-w-0">
                            <div class="font-mono text-xs text-gray-800 truncate">${json.key}.sql</div>
                            <div class="text-xs text-gray-500">${json.sqlSize} Ko</div>
                        </div>
                        <a href="?download=${encodeURIComponent(json.key + '.sql')}"
                           class="inline-flex items-center justify-center w-7 h-7 rounded bg-blue-100 hover:bg-blue-200 text-blue-700 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                        </a>
                    </div>
                    <div class="flex items-center gap-2 p-2 rounded-lg bg-purple-50 border border-purple-100">
                        <div class="text-lg">💾</div>
                        <div class="flex-1 min-w-0">
                            <div class="font-mono text-xs text-gray-800 truncate">${json.key}.sqlite</div>
                            <div class="text-xs text-gray-500">${json.sqliteSize} Ko</div>
                        </div>
                        <a href="?download=${encodeURIComponent(json.key + '.sqlite')}"
                           class="inline-flex items-center justify-center w-7 h-7 rounded bg-purple-100 hover:bg-purple-200 text-purple-700 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                        </a>
                    </div>
                </div>
            `;
            document.getElementById('backupList').prepend(newCard);

            // Mettre à jour le compteur
            const countLabel = document.getElementById('countLabel');
            if (countLabel) countLabel.textContent = json.count;
            document.getElementById('fileCount').textContent = json.count * 2;
        } else {
            showToast('❌ ' + json.error, 'error');
        }
    } catch (err) {
        showToast('❌ Erreur réseau : ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btnLabel.textContent = 'Créer une sauvegarde maintenant';
        btnIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>';
        btnIcon.classList.remove('animate-spin');
    }
}

async function deleteBackup(key) {
    if (!confirm('Supprimer cette sauvegarde ?\n\nLes 2 fichiers (.sql + .sqlite) seront supprimés.')) return;

    try {
        const res = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=delete&key=' + encodeURIComponent(key)
        });
        const json = await res.json();

        if (json.ok) {
            // Retirer la carte
            const card = document.querySelector(`.backup-card[data-key="${key}"]`);
            if (card) card.remove();

            // Si plus de cartes → afficher l'état vide
            if (document.querySelectorAll('.backup-card').length === 0) {
                document.getElementById('backupList').innerHTML = `
                    <div id="emptyState" class="text-center py-12 border-2 border-dashed border-gray-200 rounded-xl bg-gray-50">
                        <div class="text-5xl mb-3">📭</div>
                        <p class="text-gray-500 text-sm mb-1">Aucune sauvegarde existante</p>
                        <p class="text-gray-400 text-xs">Utilisez le bouton ci-dessous pour en créer une.</p>
                    </div>`;
            } else {
                // Le badge "Dernière" doit passer à la carte suivante
                document.querySelectorAll('.badge-latest').forEach(el => el.remove());
                const first = document.querySelector('.backup-card');
                if (first) {
                    first.classList.remove('border-gray-200');
                    first.classList.add('border-blue-200', 'bg-blue-50/30');
                    const header = first.querySelector('.flex.items-center.gap-2');
                    if (header) {
                        const badge = document.createElement('span');
                        badge.className = 'badge-latest px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase bg-blue-100 text-blue-700';
                        badge.textContent = 'Dernière';
                        header.appendChild(badge);
                    }
                }
            }

            showToast(`🗑️ ${json.deleted} fichier(s) supprimé(s).`, 'success');
        }
    } catch (err) {
        showToast('❌ Erreur réseau : ' + err.message, 'error');
    }
}
</script>

</body>
</html>