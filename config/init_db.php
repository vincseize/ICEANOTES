<?php
// config/init_db.php
require_once __DIR__ . '/database.php';

if (!is_dir(DB_DIR)) mkdir(DB_DIR, 0775, true);
if (file_exists(DB_PATH)) @unlink(DB_PATH);

$pdo = getDB();

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
    actif INTEGER NOT NULL DEFAULT 1,
    protected INTEGER NOT NULL DEFAULT 0,
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
    actif INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (section_id) REFERENCES sections(id)
);
CREATE TABLE promotions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    ordre INTEGER NOT NULL DEFAULT 0,
    promo TEXT DEFAULT NULL,
    annee TEXT NOT NULL,
    filiere_id INTEGER NOT NULL,
    actif INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id),
    UNIQUE(nom, annee, filiere_id, promo)
);
CREATE TABLE modules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    niveau TEXT NOT NULL,
    filiere_id INTEGER NOT NULL,
    professeur_id INTEGER NOT NULL,
    actif INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id),
    FOREIGN KEY (professeur_id) REFERENCES users(id)
);
CREATE TABLE etudiants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    prenom TEXT NOT NULL,
    promotion_id INTEGER NOT NULL,
    actif INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (promotion_id) REFERENCES promotions(id)
);
CREATE TABLE note_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL UNIQUE,
    sur REAL NOT NULL DEFAULT 20,
    coef REAL NOT NULL DEFAULT 1,
    ordre INTEGER NOT NULL DEFAULT 0,
    actif INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    etudiant_id INTEGER NOT NULL,
    module_id INTEGER NOT NULL,
    note_type_id INTEGER NOT NULL,
    valeur REAL NOT NULL,
    date_saisie DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id),
    FOREIGN KEY (module_id) REFERENCES modules(id),
    FOREIGN KEY (note_type_id) REFERENCES note_types(id),
    UNIQUE(etudiant_id, module_id, note_type_id)
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
// SEED
// =============================================================================

// --- Types utilisateurs ---
$pdo->exec("INSERT INTO users_types (label) VALUES ('admin'), ('superviseur'), ('user'), ('guest')");
$adminTypeId       = (int)$pdo->query("SELECT id FROM users_types WHERE label='admin'")->fetchColumn();
$superviseurTypeId = (int)$pdo->query("SELECT id FROM users_types WHERE label='superviseur'")->fetchColumn();

// --- Admins par défaut ---
$hash = hashPassword('icea');
$stmt = $pdo->prepare("
    INSERT INTO users (email, password, nom, prenom, user_type_id, protected)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->execute(['charles.pottier@icea-edu.fr', $hash, 'Pottier', 'Charles', $adminTypeId, 1]);
$stmt->execute(['sylvio.prudent@icea-edu.fr',  $hash, 'Prudent', 'Sylvio',  $superviseurTypeId, 1]);

// --- Section ICEA ---
$pdo->exec("INSERT INTO sections (nom) VALUES ('ICEA')");
$sectionId = (int)$pdo->query("SELECT id FROM sections WHERE nom='ICEA'")->fetchColumn();

// --- 4 filières ---
$stmt = $pdo->prepare("INSERT INTO filieres (nom, section_id) VALUES (?, ?)");
foreach (['SDN', 'Sciences Sociales', 'Journalisme', 'Sciences du Numériques'] as $f) {
    $stmt->execute([$f, $sectionId]);
}

// --- Promotions (niveaux + groupes) ---
$sdnId         = (int)$pdo->query("SELECT id FROM filieres WHERE nom='SDN'")->fetchColumn();
$journalismeId = (int)$pdo->query("SELECT id FROM filieres WHERE nom='Journalisme'")->fetchColumn();

$stmt = $pdo->prepare("
    INSERT INTO promotions (nom, ordre, promo, annee, filiere_id)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute(['L1', 1, null,             '2026-2027', $sdnId]);
$stmt->execute(['L1', 1, 'Lilo et Stitch', '2026-2027', $sdnId]);
$stmt->execute(['L3', 3, 'Stitch et Lilo', '2026-2027', $sdnId]);
$stmt->execute(['L1', 1, null,             '2026-2027', $journalismeId]);
$stmt->execute(['L3', 3, 'Nemo',           '2026-2027', $journalismeId]);

// --- Types de notes (avec coef) ---
$pdo->exec("
    INSERT INTO note_types (nom, sur, coef, ordre) VALUES
        ('CC1',    20, 1, 1),
        ('CC2',    20, 1, 2),
        ('Examen', 20, 2, 3)
");

// =============================================================================
// RÉCAP
// =============================================================================

$nbUsers     = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$nbFilieres  = (int)$pdo->query("SELECT COUNT(*) FROM filieres")->fetchColumn();
$nbPromos    = (int)$pdo->query("SELECT COUNT(*) FROM promotions")->fetchColumn();
$nbModules   = (int)$pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
$nbEtudiants = (int)$pdo->query("SELECT COUNT(*) FROM etudiants")->fetchColumn();
$nbNoteTypes = (int)$pdo->query("SELECT COUNT(*) FROM note_types")->fetchColumn();
$nbNotes     = (int)$pdo->query("SELECT COUNT(*) FROM notes")->fetchColumn();

echo "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'>";
echo "<title>ICEANOTES v2 - Init DB</title>";
echo "<script src='https://cdn.tailwindcss.com'></script></head>";
echo "<body class='bg-gray-50 min-h-screen flex items-center justify-center p-6'>";
echo "<div class='bg-white rounded-2xl shadow-lg p-8 max-w-xl w-full'>";
echo "<h1 class='text-2xl font-bold text-green-600 mb-4'>✅ Base ICEANOTES v2 initialisée</h1>";

echo "<div class='space-y-2 text-sm text-gray-700 mb-6'>";
echo "<p><strong>Section :</strong> ICEA</p>";
echo "<p><strong>Filières :</strong> $nbFilieres (SDN, Sciences Sociales, Journalisme, Sciences du Numériques)</p>";
echo "<p><strong>Promotions :</strong> $nbPromos (L1/L3 × SDN/Journalisme × avec/sans promo)</p>";
echo "<p><strong>Types de notes :</strong> $nbNoteTypes (CC1 ×1, CC2 ×1, Examen ×2)</p>";
echo "<p><strong>Utilisateurs :</strong> $nbUsers (Charles=admin, Sylvio=superviseur)</p>";
echo "<p><strong>Modules :</strong> $nbModules</p>";
echo "<p><strong>Étudiants :</strong> $nbEtudiants</p>";
echo "<p><strong>Notes :</strong> $nbNotes</p>";
echo "</div>";

echo "<div class='bg-blue-50 rounded-lg p-4 text-sm mb-6'>";
echo "<p class='font-semibold text-blue-800 mb-2'>Comptes :</p>";
echo "<p>📧 <code>charles.pottier@icea-edu.fr</code> — 🔑 <code>icea</code> (admin)</p>";
echo "<p>📧 <code>sylvio.prudent@icea-edu.fr</code> — 🔑 <code>icea</code> (superviseur)</p>";
echo "</div>";

echo "<a href='/ICEANOTES/login.php' class='block text-center bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 rounded-lg transition'>→ Aller au login</a>";
echo "</div></body></html>";