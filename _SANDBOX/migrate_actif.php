<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();
$tables = ['users','filieres','diplomes','promos','matieres','etudiants'];
foreach ($tables as $t) {
    try {
        $pdo->exec("ALTER TABLE $t ADD COLUMN actif INTEGER NOT NULL DEFAULT 1");
        echo "✅ $t.actif ajouté<br>";
    } catch (PDOException $e) {
        echo "⚠️ $t : " . $e->getMessage() . "<br>";
    }
}
echo "<br>Done. <a href='/ICEANOTES/pages/accueil.php'>Retour</a>";