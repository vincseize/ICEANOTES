<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();

echo "<h2>Migration matieres.filiere_id</h2>";

// 1. Vérifier si la colonne existe déjà
$cols = $pdo->query("PRAGMA table_info(matieres)")->fetchAll();
$hasFiliere = false;
foreach ($cols as $c) {
    if ($c['name'] === 'filiere_id') {
        $hasFiliere = true;
        break;
    }
}

if ($hasFiliere) {
    echo "<p style='color:green'>✅ Colonne 'filiere_id' existe déjà — rien à faire.</p>";
} else {
    echo "<p>Ajout de la colonne…</p>";
    // 2. Ajouter la colonne avec une valeur par défaut
    $pdo->exec("ALTER TABLE matieres ADD COLUMN filiere_id INTEGER NOT NULL DEFAULT 1");
    echo "<p style='color:green'>✅ Colonne 'filiere_id' ajoutée (default = 1).</p>";
}

// 3. Vérifier s'il y a des matières orphelines (filiere_id = 1 par défaut)
$count = (int)$pdo->query("SELECT COUNT(*) FROM matieres WHERE filiere_id = 1")->fetchColumn();
if ($count > 0) {
    echo "<p style='color:orange'>⚠️ $count matière(s) avec filiere_id = 1 (valeur par défaut).</p>";
    echo "<p>Va sur <a href='/ICEANOTES/pages/matieres.php'>/pages/matieres.php</a> et édite chaque matière pour lui assigner la bonne filière.</p>";
}

// 4. Afficher le schéma final
echo "<h3>Colonnes de matieres :</h3><ul>";
foreach ($pdo->query("PRAGMA table_info(matieres)") as $c) {
    echo "<li><strong>{$c['name']}</strong> ({$c['type']})" . ($c['dflt_value'] !== null ? " — défaut: {$c['dflt_value']}" : '') . "</li>";
}
echo "</ul>";

echo "<hr><p><a href='/ICEANOTES/pages/matieres.php'>→ Aller sur /pages/matieres.php</a></p>";