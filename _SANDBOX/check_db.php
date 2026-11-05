<?php
// _SANDBOX/check_db.php
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<script src='https://cdn.tailwindcss.com'></script></head>";
echo "<body class='bg-gray-50 p-8 font-sans'>";
echo "<div class='max-w-3xl mx-auto bg-white rounded-xl shadow p-6'>";
echo "<h1 class='text-2xl font-bold text-blue-600 mb-4'>🔍 Diagnostic ICEANOTES</h1>";

// Filières
echo "<h2 class='text-lg font-semibold mt-4 mb-2'>Filières</h2>";
$rows = $pdo->query("SELECT f.*, s.nom AS section FROM filieres f JOIN sections s ON s.id = f.section_id")->fetchAll();
if (empty($rows)) {
    echo "<p class='text-red-600'>❌ Aucune filière en base</p>";
} else {
    echo "<table class='w-full text-sm border-collapse'>";
    echo "<thead class='bg-gray-100'><tr><th class='text-left p-2'>ID</th><th class='text-left p-2'>Nom</th><th class='text-left p-2'>Section</th></tr></thead><tbody>";
    foreach ($rows as $f) {
        echo "<tr class='border-b'><td class='p-2'>{$f['id']}</td><td class='p-2'>{$f['nom']}</td><td class='p-2'>{$f['section']}</td></tr>";
    }
    echo "</tbody></table>";
}

// Diplômes
echo "<h2 class='text-lg font-semibold mt-6 mb-2'>Diplômes</h2>";
$rows = $pdo->query("SELECT * FROM diplomes ORDER BY ordre")->fetchAll();
if (empty($rows)) {
    echo "<p class='text-red-600'>❌ Aucun diplôme</p>";
} else {
    echo "<table class='w-full text-sm border-collapse'>";
    echo "<thead class='bg-gray-100'><tr><th class='text-left p-2'>ID</th><th class='text-left p-2'>Nom</th><th class='text-left p-2'>Ordre</th></tr></thead><tbody>";
    foreach ($rows as $d) {
        echo "<tr class='border-b'><td class='p-2'>{$d['id']}</td><td class='p-2'>{$d['nom']}</td><td class='p-2'>{$d['ordre']}</td></tr>";
    }
    echo "</tbody></table>";
}

// Promos
echo "<h2 class='text-lg font-semibold mt-6 mb-2'>Promos</h2>";
$rows = $pdo->query("
    SELECT p.*, f.nom AS filiere, d.nom AS diplome
    FROM promos p JOIN filieres f ON f.id = p.filiere_id JOIN diplomes d ON d.id = p.diplome_id
")->fetchAll();
if (empty($rows)) {
    echo "<p class='text-red-600'>❌ Aucune promo</p>";
} else {
    echo "<table class='w-full text-sm border-collapse'>";
    echo "<thead class='bg-gray-100'><tr><th class='text-left p-2'>ID</th><th class='text-left p-2'>Nom</th><th class='text-left p-2'>Année</th><th class='text-left p-2'>Filière</th><th class='text-left p-2'>Diplôme</th></tr></thead><tbody>";
    foreach ($rows as $p) {
        echo "<tr class='border-b'><td class='p-2'>{$p['id']}</td><td class='p-2'>{$p['nom']}</td><td class='p-2'>{$p['annee']}</td><td class='p-2'>{$p['filiere']}</td><td class='p-2'>{$p['diplome']}</td></tr>";
    }
    echo "</tbody></table>";
}

// Utilisateurs
echo "<h2 class='text-lg font-semibold mt-6 mb-2'>Utilisateurs</h2>";
$rows = $pdo->query("SELECT u.*, t.label AS type FROM users u JOIN users_types t ON t.id = u.user_type_id")->fetchAll();
echo "<table class='w-full text-sm border-collapse'>";
echo "<thead class='bg-gray-100'><tr><th class='text-left p-2'>ID</th><th class='text-left p-2'>Email</th><th class='text-left p-2'>Nom</th><th class='text-left p-2'>Type</th></tr></thead><tbody>";
foreach ($rows as $u) {
    echo "<tr class='border-b'><td class='p-2'>{$u['id']}</td><td class='p-2'>{$u['email']}</td><td class='p-2'>{$u['prenom']} {$u['nom']}</td><td class='p-2'>{$u['type']}</td></tr>";
}
echo "</tbody></table>";

// Compteurs
echo "<h2 class='text-lg font-semibold mt-6 mb-2'>Compteurs</h2>";
echo "<ul class='text-sm space-y-1'>";
foreach (['matieres', 'etudiants', 'notes'] as $t) {
    $n = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    echo "<li><strong>" . ucfirst($t) . " :</strong> $n</li>";
}
echo "</ul>";

echo "<p class='mt-6 text-xs text-gray-500'>📄 " . DB_PATH . "</p>";
echo "</div></body></html>";