<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();

echo "<h2>Migration : note_types.coef</h2><pre>";

$cols = $pdo->query("PRAGMA table_info(note_types)")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($cols, 'name');

if (!in_array('coef', $colNames)) {
    $pdo->exec("ALTER TABLE note_types ADD COLUMN coef REAL NOT NULL DEFAULT 1");
    echo "✅ Colonne 'coef' ajoutée (default 1)\n";
} else {
    echo "⚠️ coef existe déjà\n";
}

echo "\n--- note_types final ---\n";
foreach ($pdo->query("PRAGMA table_info(note_types)") as $c) {
    echo "  - {$c['name']} ({$c['type']})\n";
}
echo "</pre>";