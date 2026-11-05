<?php
// _SANDBOX/check_db.php
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<script src='https://cdn.tailwindcss.com'></script></head>";
echo "<body class='bg-gray-50 p-8 font-sans'>";
echo "<div class='max-w-5xl mx-auto bg-white rounded-xl shadow p-6'>";
echo "<h1 class='text-2xl font-bold text-blue-600 mb-4'>🔍 Diagnostic ICEANOTES v2</h1>";

// --- Sections ---
echo "<h2 class='text-lg font-semibold mt-6 mb-2'>Sections</h2>";
$rows = $pdo->query("SELECT * FROM sections")->fetchAll();
echo "<table class='w-full text-sm border-collapse'>";
echo "<thead class='bg-gray-100'><tr><th class='text-left p-2'>ID</th><th class='text-left p-2'>Nom</th></tr></thead><tbody>";
foreach ($rows as $r) {
    echo "<tr class='border-b'><td class='p-2'>{$r['id']}</td><td class='p-2'>{$r['nom']}</td></tr>";
}
echo "</tbody></table>";

// --- Filières ---
echo "<h2 class='text-lg font-semibold mt-6 mb-2'>Filières</h2>";
$rows = $pdo->query("SELECT f.*, s.nom AS section FROM filieres f JOIN sections s ON s.id=f.section_id ORDER BY f.id")->fetchAll();
echo "<table class='w-full text-sm border-collapse'>";
echo "<thead class='bg-gray-100'><tr><th class='text-left p-2'>ID</th><th class='text-left p-2'>Nom</th><th class='text-left p-2'>Section</th><th class='text-center p-2'>Actif</th></tr></thead><tbody>";
foreach ($rows as $r) {
    $actif = $r['actif'] ? '✅' : '❌';
    echo "<tr class='border-b'><td class='p-2'>{$r['id']}</td><td class='p-2'>{$r['nom']}</td><td class='p-2'>{$r['section']}</td><td class='p-2 text-center'>$actif</td></tr>";
}
echo "</tbody></table>";

// --- Promotions ---
echo "<h2 class='text-lg font-semibold mt-6 mb-2'>Promotions</h2>";
$rows = $pdo->query("
    SELECT p.*, f.nom AS filiere
    FROM promotions p
    JOIN filieres f ON f.id = p.filiere_id
    ORDER BY p.ordre, f.nom, p.annee
")->fetchAll();
if (empty($rows)) {
    echo "<p class='text-red-600'>❌ Aucune promotion</p>";
} else {
    echo "<table class='w-full text-sm border-collapse'>";
    echo "<thead class='bg-gray-100'><tr>
        <th class='text-left p-2'>ID</th>
        <th class='text-left p-2'>Nom (niveau)</th>
        <th class='text-center p-2'>Ordre</th>
        <th class='text-left p-2'>Promo</th>
        <th class='text-left p-2'>Année</th>
        <th class='text-left p-2'>Filière</th>
        <th class='text-center p-2'>Actif</th>
    </tr></thead><tbody>";
    foreach ($rows as $r) {
        $promo = $r['promo'] ? htmlspecialchars($r['promo']) : '<em class="text-gray-400">(sans nom)</em>';
        $actif = $r['actif'] ? '✅' : '❌';
        echo "<tr class='border-b'>
            <td class='p-2'>{$r['id']}</td>
            <td class='p-2 font-semibold'>{$r['nom']}</td>
            <td class='p-2 text-center'>{$r['ordre']}</td>
            <td class='p-2'>$promo</td>
            <td class='p-2'>{$r['annee']}</td>
            <td class='p-2'>{$r['filiere']}</td>
            <td class='p-2 text-center'>$actif</td>
        </tr>";
    }
    echo "</tbody></table>";
}

// --- Matières ---
echo "<h2 class='text-lg font-semibold mt-6 mb-2'>Matières</h2>";
$rows = $pdo->query("
    SELECT m.*, f.nom AS filiere, u.nom AS prof_nom, u.prenom AS prof_prenom
    FROM matieres m
    JOIN filieres f ON f.id = m.filiere_id
    JOIN users u ON u.id = m.professeur_id
    ORDER BY m.id
")->fetchAll();
if (empty($rows)) {
    echo "<p class='text-gray-400'>Aucune matière</p>";
} else {
    echo "<table class='w-full text-sm border-collapse'>";
    echo "<thead class='bg-gray-100'><tr>
        <th class='text-left p-2'>ID</th>
        <th class='text-left p-2'>Nom</th>
        <th class='text-left p-2'>Niveau</th>
        <th class='text-left p-2'>Filière</th>
        <th class='text-left p-2'>Professeur</th>
        <th class='text-center p-2'>Actif</th>
    </tr></thead><tbody>";
    foreach ($rows as $r) {
        $actif = $r['actif'] ? '✅' : '❌';
        echo "<tr class='border-b'>
            <td class='p-2'>{$r['id']}</td>
            <td class='p-2 font-semibold'>{$r['nom']}</td>
            <td class='p-2'>{$r['niveau']}</td>
            <td class='p-2'>{$r['filiere']}</td>
            <td class='p-2'>{$r['prof_prenom']} {$r['prof_nom']}</td>
            <td class='p-2 text-center'>$actif</td>
        </tr>";
    }
    echo "</tbody></table>";
}

// --- Étudiants ---
echo "<h2 class='text-lg font-semibold mt-6 mb-2'>Étudiants</h2>";
$rows = $pdo->query("
    SELECT e.*, p.nom AS niveau, p.promo, p.annee, f.nom AS filiere
    FROM etudiants e
    JOIN promotions p ON p.id = e.promotion_id
    JOIN filieres f ON f.id = p.filiere_id
    ORDER BY e.id
")->fetchAll();
if (empty($rows)) {
    echo "<p class='text-gray-400'>Aucun étudiant</p>";
} else {
    echo "<table class='w-full text-sm border-collapse'>";
    echo "<thead class='bg-gray-100'><tr>
        <th class='text-left p-2'>ID</th>
        <th class='text-left p-2'>Nom</th>
        <th class='text-left p-2'>Prénom</th>
        <th class='text-left p-2'>Niveau</th>
        <th class='text-left p-2'>Promo</th>
        <th class='text-left p-2'>Année</th>
        <th class='text-left p-2'>Filière</th>
        <th class='text-center p-2'>Actif</th>
    </tr></thead><tbody>";
    foreach ($rows as $r) {
        $promo = $r['promo'] ? htmlspecialchars($r['promo']) : '<em class="text-gray-400">(sans nom)</em>';
        $actif = $r['actif'] ? '✅' : '❌';
        echo "<tr class='border-b'>
            <td class='p-2'>{$r['id']}</td>
            <td class='p-2'>{$r['nom']}</td>
            <td class='p-2'>{$r['prenom']}</td>
            <td class='p-2 font-semibold'>{$r['niveau']}</td>
            <td class='p-2'>$promo</td>
            <td class='p-2'>{$r['annee']}</td>
            <td class='p-2'>{$r['filiere']}</td>
            <td class='p-2 text-center'>$actif</td>
        </tr>";
    }
    echo "</tbody></table>";
}

// --- Notes ---
echo "<h2 class='text-lg font-semibold mt-6 mb-2'>Notes</h2>";
$rows = $pdo->query("
    SELECT n.*, e.nom AS etu_nom, e.prenom AS etu_prenom,
           m.nom AS matiere, m.niveau, f.nom AS filiere
    FROM notes n
    JOIN etudiants e ON e.id = n.etudiant_id
    JOIN matieres m ON m.id = n.matiere_id
    JOIN filieres f ON f.id = m.filiere_id
    ORDER BY n.id DESC
    LIMIT 20
")->fetchAll();
if (empty($rows)) {
    echo "<p class='text-gray-400'>Aucune note</p>";
} else {
    echo "<table class='w-full text-sm border-collapse'>";
    echo "<thead class='bg-gray-100'><tr>
        <th class='text-left p-2'>ID</th>
        <th class='text-left p-2'>Étudiant</th>
        <th class='text-left p-2'>Matière</th>
        <th class='text-left p-2'>Niveau</th>
        <th class='text-left p-2'>Filière</th>
        <th class='text-center p-2'>Type</th>
        <th class='text-center p-2'>Note</th>
    </tr></thead><tbody>";
    foreach ($rows as $r) {
        echo "<tr class='border-b'>
            <td class='p-2'>{$r['id']}</td>
            <td class='p-2'>{$r['etu_prenom']} {$r['etu_nom']}</td>
            <td class='p-2'>{$r['matiere']}</td>
            <td class='p-2'>{$r['niveau']}</td>
            <td class='p-2'>{$r['filiere']}</td>
            <td class='p-2 text-center'>{$r['type_note']}</td>
            <td class='p-2 text-center font-bold'>" . number_format((float)$r['valeur'], 1) . "/20</td>
        </tr>";
    }
    echo "</tbody></table>";
}

// --- Utilisateurs ---
echo "<h2 class='text-lg font-semibold mt-6 mb-2'>Utilisateurs</h2>";
$rows = $pdo->query("
    SELECT u.*, t.label AS type
    FROM users u
    JOIN users_types t ON t.id = u.user_type_id
    ORDER BY u.id
")->fetchAll();
echo "<table class='w-full text-sm border-collapse'>";
echo "<thead class='bg-gray-100'><tr>
    <th class='text-left p-2'>ID</th>
    <th class='text-left p-2'>Email</th>
    <th class='text-left p-2'>Nom</th>
    <th class='text-left p-2'>Type</th>
    <th class='text-center p-2'>Actif</th>
    <th class='text-center p-2'>Protégé</th>
</tr></thead><tbody>";
foreach ($rows as $r) {
    $actif   = $r['actif'] ? '✅' : '❌';
    $prot    = $r['protected'] ? '🔒' : '—';
    echo "<tr class='border-b'>
        <td class='p-2'>{$r['id']}</td>
        <td class='p-2'>{$r['email']}</td>
        <td class='p-2'>{$r['prenom']} {$r['nom']}</td>
        <td class='p-2 font-semibold'>{$r['type']}</td>
        <td class='p-2 text-center'>$actif</td>
        <td class='p-2 text-center'>$prot</td>
    </tr>";
}
echo "</tbody></table>";

// --- Compteurs ---
echo "<h2 class='text-lg font-semibold mt-6 mb-2'>Compteurs</h2>";
echo "<ul class='text-sm space-y-1'>";
foreach (['promotions', 'matieres', 'etudiants', 'notes'] as $t) {
    $n = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    echo "<li><strong>" . ucfirst($t) . " :</strong> $n</li>";
}
echo "</ul>";

// --- Fichier base ---
echo "<p class='mt-6 text-xs text-gray-500'>📄 " . DB_PATH . "</p>";
$size = file_exists(DB_PATH) ? round(filesize(DB_PATH) / 1024, 1) : 0;
echo "<p class='text-xs text-gray-500'>Taille : $size Ko</p>";

echo "</div></body></html>";