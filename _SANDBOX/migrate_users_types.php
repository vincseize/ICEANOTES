<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();

echo "<h2>Migration users_types</h2>";

// 1. Vérifier l'état actuel
$rows = $pdo->query("SELECT * FROM users_types ORDER BY id")->fetchAll();
echo "<h3>Avant :</h3><ul>";
foreach ($rows as $r) {
    echo "<li>{$r['id']} — {$r['label']}</li>";
}
echo "</ul>";

// 2. Renommer 'professeur' en 'user' (ou ajouter si absent)
$existing = array_column($rows, 'label');

if (in_array('professeur', $existing) && !in_array('user', $existing)) {
    $pdo->exec("UPDATE users_types SET label='user' WHERE label='professeur'");
    echo "<p>✅ 'professeur' renommé en 'user'</p>";
}

// 3. Ajouter 'superviseur' et 'guest' si absents
$existing = $pdo->query("SELECT label FROM users_types")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('superviseur', $existing)) {
    $pdo->exec("INSERT INTO users_types (label) VALUES ('superviseur')");
    echo "<p>✅ 'superviseur' ajouté</p>";
}
if (!in_array('guest', $existing)) {
    $pdo->exec("INSERT INTO users_types (label) VALUES ('guest')");
    echo "<p>✅ 'guest' ajouté</p>";
}

// 4. Changer le rôle de Sylvio en 'superviseur'
$superviseurId = (int)$pdo->query("SELECT id FROM users_types WHERE label='superviseur'")->fetchColumn();
$pdo->prepare("UPDATE users SET user_type_id = ? WHERE email = ?")
    ->execute([$superviseurId, 'sylvio.prudent@icea-edu.fr']);
echo "<p>✅ Sylvio passé en 'superviseur'</p>";

// 5. Ajouter colonnes protégées (si absentes)
$cols = $pdo->query("PRAGMA table_info(users)")->fetchAll();
$colNames = array_column($cols, 'name');

if (!in_array('protected', $colNames)) {
    $pdo->exec("ALTER TABLE users ADD COLUMN protected INTEGER NOT NULL DEFAULT 0");
    echo "<p>✅ Colonne 'protected' ajoutée à users</p>";
}

// 6. Marquer Charles comme protégé (admin)
$pdo->exec("UPDATE users SET protected = 1 WHERE email = 'charles.pottier@icea-edu.fr'");
echo "<p>✅ Charles marqué comme protégé</p>";

// 7. Marquer Sylvio comme protégé aussi (superviseur)
$pdo->exec("UPDATE users SET protected = 1 WHERE email = 'sylvio.prudent@icea-edu.fr'");
echo "<p>✅ Sylvio marqué comme protégé</p>";

// 8. Vérifier qu'il n'y a qu'un seul admin
$adminId = (int)$pdo->query("SELECT id FROM users_types WHERE label='admin'")->fetchColumn();
$adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE user_type_id = $adminId")->fetchColumn();
echo "<p><strong>Admins en base : $adminCount</strong></p>";

// 9. Récap final
echo "<h3>Après :</h3><ul>";
foreach ($pdo->query("
    SELECT u.id, u.email, u.nom, u.prenom, u.actif, u.protected, t.label AS type
    FROM users u JOIN users_types t ON t.id = u.user_type_id
    ORDER BY u.id
") as $u) {
    echo "<li><strong>{$u['email']}</strong> — {$u['prenom']} {$u['nom']} — type: <em>{$u['type']}</em> — actif: {$u['actif']} — protégé: {$u['protected']}</li>";
}
echo "</ul>";

echo "<h3>Types disponibles :</h3><ul>";
foreach ($pdo->query("SELECT * FROM users_types ORDER BY id") as $t) {
    echo "<li>{$t['id']} — <strong>{$t['label']}</strong></li>";
}
echo "</ul>";

echo "<p><a href='/ICEANOTES/pages/professeurs.php'>→ Aller sur /pages/professeurs.php</a></p>";