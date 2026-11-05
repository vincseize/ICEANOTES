<?php
// config/init_db.php
// -----------------------------------------------------------------------------
// Réinitialisation complète de la base ICEANOTES.
// Hiérarchie : ICEA → Filières → Diplômes → Promos → Étudiants / Matières → Notes
// ⚠️ Ce script DÉTRUIT et RECRÉE tout. À lancer manuellement.
// -----------------------------------------------------------------------------

require_once __DIR__ . '/database.php';

if (!is_dir(DB_DIR)) {
    mkdir(DB_DIR, 0775, true);
}

if (file_exists(DB_PATH)) {
    @unlink(DB_PATH);
}

$pdo = getDB();

// =============================================================================
// 1) CRÉATION DES TABLES
// =============================================================================

$pdo->exec("
CREATE TABLE users_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    label TEXT NOT NULL UNIQUE
);

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    nom TEXT,
    prenom TEXT,
    user_type_id INTEGER NOT NULL,
    FOREIGN KEY (user_type_id) REFERENCES users_types(id)
);

CREATE TABLE sections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL UNIQUE
);

CREATE TABLE filieres (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL UNIQUE,
    section_id INTEGER NOT NULL,
    FOREIGN KEY (section_id) REFERENCES sections(id)
);

CREATE TABLE diplomes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL UNIQUE,
    ordre INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE promos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    annee TEXT NOT NULL,
    filiere_id INTEGER NOT NULL,
    diplome_id INTEGER NOT NULL,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id),
    FOREIGN KEY (diplome_id) REFERENCES diplomes(id),
    UNIQUE(nom, annee, filiere_id, diplome_id)
);

CREATE TABLE matieres (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    diplome_id INTEGER NOT NULL,
    professeur_id INTEGER NOT NULL,
    FOREIGN KEY (diplome_id) REFERENCES diplomes(id),
    FOREIGN KEY (professeur_id) REFERENCES users(id)
);

CREATE TABLE etudiants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    prenom TEXT NOT NULL,
    promo_id INTEGER NOT NULL,
    FOREIGN KEY (promo_id) REFERENCES promos(id)
);

CREATE TABLE notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    etudiant_id INTEGER NOT NULL,
    matiere_id INTEGER NOT NULL,
    type_note TEXT NOT NULL CHECK(type_note IN ('CC1','CC2')),
    valeur REAL NOT NULL CHECK(valeur >= 0 AND valeur <= 20),
    date_saisie DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id),
    FOREIGN KEY (matiere_id) REFERENCES matieres(id),
    UNIQUE(etudiant_id, matiere_id, type_note)
);

CREATE TABLE remember_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token_hash TEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
");

// =============================================================================
// 2) SEED
// =============================================================================

// Types utilisateurs
$pdo->exec("INSERT INTO users_types (label) VALUES ('admin'), ('professeur')");
$adminTypeId = (int)$pdo->query("SELECT id FROM users_types WHERE label='admin'")->fetchColumn();

// Admins par défaut
$hash = hashPassword('icea');
$stmt = $pdo->prepare("
    INSERT INTO users (email, password, nom, prenom, user_type_id)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute(['charles.pottier@icea-edu.fr', $hash, 'Pottier', 'Charles', $adminTypeId]);
$stmt->execute(['sylvio.prudent@icea-edu.fr',  $hash, 'Prudent', 'Sylvio',  $adminTypeId]);

// Section unique ICEA
$pdo->exec("INSERT INTO sections (nom) VALUES ('ICEA')");
$sectionId = (int)$pdo->query("SELECT id FROM sections WHERE nom='ICEA'")->fetchColumn();

// 3 filières
$stmt = $pdo->prepare("INSERT INTO filieres (nom, section_id) VALUES (?, ?)");
foreach (['SDN', 'Sciences Sociales', 'Journalisme'] as $f) {
    $stmt->execute([$f, $sectionId]);
}

// 4 diplômes
$stmt = $pdo->prepare("INSERT INTO diplomes (nom, ordre) VALUES (?, ?)");
$diplomes = [['L1', 1], ['L2', 2], ['L3', 3], ['Master', 4]];
foreach ($diplomes as [$nom, $ordre]) {
    $stmt->execute([$nom, $ordre]);
}

// Promo de démo : Lilo et Stitch 2026-2027, SDN, L3
$l3Id  = (int)$pdo->query("SELECT id FROM diplomes WHERE nom='L3'")->fetchColumn();
$sdnId = (int)$pdo->query("SELECT id FROM filieres WHERE nom='SDN'")->fetchColumn();

$pdo->prepare("
    INSERT INTO promos (nom, annee, filiere_id, diplome_id)
    VALUES (?, ?, ?, ?)
")->execute(['Lilo et Stitch', '2026-2027', $sdnId, $l3Id]);

// =============================================================================
// 3) RÉCAPITULATIF
// =============================================================================

$nbUsers     = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$nbFilieres  = (int)$pdo->query("SELECT COUNT(*) FROM filieres")->fetchColumn();
$nbDiplomes  = (int)$pdo->query("SELECT COUNT(*) FROM diplomes")->fetchColumn();
$nbPromos    = (int)$pdo->query("SELECT COUNT(*) FROM promos")->fetchColumn();
$nbMatieres  = (int)$pdo->query("SELECT COUNT(*) FROM matieres")->fetchColumn();
$nbEtudiants = (int)$pdo->query("SELECT COUNT(*) FROM etudiants")->fetchColumn();

echo "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'>";
echo "<title>ICEANOTES - Init DB</title>";
echo "<script src='https://cdn.tailwindcss.com'></script></head>";
echo "<body class='bg-gray-50 min-h-screen flex items-center justify-center p-6'>";
echo "<div class='bg-white rounded-2xl shadow-lg p-8 max-w-xl w-full'>";
echo "<h1 class='text-2xl font-bold text-green-600 mb-4'>✅ Base ICEANOTES initialisée</h1>";

echo "<div class='space-y-2 text-sm text-gray-700 mb-6'>";
echo "<p><strong>Section :</strong> ICEA</p>";
echo "<p><strong>Filières :</strong> $nbFilieres (SDN, Sciences Sociales, Journalisme)</p>";
echo "<p><strong>Diplômes :</strong> $nbDiplomes (L1, L2, L3, Master)</p>";
echo "<p><strong>Promos :</strong> $nbPromos (Lilo et Stitch 2026-2027, SDN, L3)</p>";
echo "<p><strong>Utilisateurs :</strong> $nbUsers</p>";
echo "<p><strong>Matières :</strong> $nbMatieres</p>";
echo "<p><strong>Étudiants :</strong> $nbEtudiants</p>";
echo "</div>";

echo "<div class='bg-blue-50 rounded-lg p-4 text-sm mb-6'>";
echo "<p class='font-semibold text-blue-800 mb-2'>Comptes admin par défaut :</p>";
echo "<p>📧 <code>charles.pottier@icea-edu.fr</code> — 🔑 <code>icea</code></p>";
echo "<p>📧 <code>sylvio.prudent@icea-edu.fr</code> — 🔑 <code>icea</code></p>";
echo "</div>";

echo "<a href='/ICEANOTES/login.php' class='block text-center bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 rounded-lg transition'>→ Aller au login</a>";
echo "</div></body></html>";