<?php
// _SANDBOX/backup_db.php
require_once __DIR__ . '/../config/database.php';

$date    = date('Y-m-d_His');
$backupFile = DB_DIR . '/iceanotes_backup_' . $date . '.sqlite';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<script src='https://cdn.tailwindcss.com'></script></head>";
echo "<body class='bg-gray-50 p-8 font-sans'><div class='max-w-2xl mx-auto bg-white rounded-xl shadow p-6'>";
echo "<h1 class='text-2xl font-bold text-blue-600 mb-4'>🛡️ Sauvegarde base</h1>";

// Copie physique
if (copy(DB_PATH, $backupFile)) {
    $size = round(filesize($backupFile) / 1024, 1);
    echo "<div class='bg-green-50 text-green-700 p-3 rounded mb-4'>";
    echo "✅ Sauvegarde créée<br>";
    echo "<code class='text-xs'>" . htmlspecialchars(basename($backupFile)) . "</code> — <strong>$size Ko</strong>";
    echo "</div>";
} else {
    echo "<div class='bg-red-50 text-red-700 p-3 rounded mb-4'>❌ Échec de la copie</div>";
}

// Export SQL (bonus)
$sqlFile = DB_DIR . '/iceanotes_backup_' . $date . '.sql';
try {
    $pdo = getDB();
    $sql = "-- ICEANOTES backup $date\n\n";
    $tables = $pdo->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll();
    foreach ($tables as $t) {
        $sql .= "-- Table {$t['name']}\n";
        $sql .= $t['sql'] . ";\n\n";
        $rows = $pdo->query("SELECT * FROM \"{$t['name']}\"")->fetchAll();
        foreach ($rows as $r) {
            $cols  = array_keys($r);
            $vals  = array_map(fn($v) => $v === null ? 'NULL' : (is_numeric($v) ? $v : $pdo->quote((string)$v)), array_values($r));
            $sql .= "INSERT INTO \"{$t['name']}\" (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
        }
        $sql .= "\n";
    }
    file_put_contents($sqlFile, $sql);
    $sizeSql = round(filesize($sqlFile) / 1024, 1);
    echo "<div class='bg-green-50 text-green-700 p-3 rounded mb-4'>";
    echo "✅ Export SQL créé<br>";
    echo "<code class='text-xs'>" . htmlspecialchars(basename($sqlFile)) . "</code> — <strong>$sizeSql Ko</strong>";
    echo "</div>";
} catch (Exception $e) {
    echo "<div class='bg-red-50 text-red-700 p-3 rounded mb-4'>❌ Erreur SQL : " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Liste des backups existants
echo "<h2 class='text-lg font-semibold mt-6 mb-2'>Backups présents</h2>";
echo "<ul class='text-sm space-y-1'>";
$backups = glob(DB_DIR . '/iceanotes_backup_*');
rsort($backups);
foreach ($backups as $b) {
    $size = round(filesize($b) / 1024, 1);
    echo "<li><code class='text-xs'>" . htmlspecialchars(basename($b)) . "</code> — $size Ko</li>";
}
if (empty($backups)) echo "<li class='text-gray-400'>Aucun backup</li>";
echo "</ul>";

echo "<p class='mt-6'><a href='/ICEANOTES/pages/accueil.php' class='text-blue-600 hover:underline'>→ Retour accueil</a></p>";
echo "</div></body></html>";