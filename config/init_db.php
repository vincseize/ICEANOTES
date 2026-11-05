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
CREATE TABLE diplomes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL UNIQUE,
    ordre INTEGER NOT NULL DEFAULT 0,
    actif INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE promos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    annee TEXT NOT NULL,
    filiere_id INTEGER NOT NULL,
    diplome_id INTEGER NOT NULL,
    actif INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id),
    FOREIGN KEY (diplome_id) REFERENCES diplomes(id),
    UNIQUE(nom, annee, filiere_id, diplome_id)
);
CREATE TABLE matieres (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    diplome_id INTEGER NOT NULL,
    filiere_id INTEGER NOT NULL,
    professeur_id INTEGER NOT NULL,
    actif INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (diplome_id) REFERENCES diplomes(id),
    FOREIGN KEY (filiere_id) REFERENCES filieres(id),
    FOREIGN KEY (professeur_id) REFERENCES users(id)
);
CREATE TABLE etudiants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    prenom TEXT NOT NULL,
    promo_id INTEGER NOT NULL,
    actif INTEGER NOT NULL DEFAULT 1,
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

// Seed
$pdo->exec("INSERT INTO users_types (label) VALUES ('admin'), ('professeur')");
$adminTypeId = (int)$pdo->query("SELECT id FROM users_types WHERE label='admin'")->fetchColumn();

$hash = hashPassword('icea');
$stmt = $pdo->prepare("INSERT INTO users (email, password, nom, prenom, user_type_id) VALUES (?, ?, ?, ?, ?)");
$stmt->execute(['charles.pottier@icea-edu.fr', $hash, 'Pottier', 'Charles', $adminTypeId]);
$stmt->execute(['sylvio.prudent@icea-edu.fr',  $hash, 'Prudent', 'Sylvio',  $adminTypeId]);

$pdo->exec("INSERT INTO sections (nom) VALUES ('ICEA')");
$sectionId = (int)$pdo->query("SELECT id FROM sections WHERE nom='ICEA'")->fetchColumn();

$stmt = $pdo->prepare("INSERT INTO filieres (nom, section_id) VALUES (?, ?)");
foreach (['SDN', 'Sciences Sociales', 'Journalisme', 'Sciences du Numériques'] as $f) {
    $stmt->execute([$f, $sectionId]);
}

$stmt = $pdo->prepare("INSERT INTO diplomes (nom, ordre) VALUES (?, ?)");
foreach ([['L1', 1], ['L2', 2], ['L3', 3], ['Master', 4]] as [$nom, $ordre]) {
    $stmt->execute([$nom, $ordre]);
}

$l3Id  = (int)$pdo->query("SELECT id FROM diplomes WHERE nom='L3'")->fetchColumn();
$sdnId = (int)$pdo->query("SELECT id FROM filieres WHERE nom='SDN'")->fetchColumn();

$pdo->prepare("INSERT INTO promos (nom, annee, filiere_id, diplome_id) VALUES (?, ?, ?, ?)")
    ->execute(['Lilo et Stitch', '2026-2027', $sdnId, $l3Id]);

echo "<h1 style='color:green'>✅ Base ICEANOTES réinitialisée</h1>";
echo "<p>2 admins + 4 filières + 4 diplômes + 1 promo</p>";
echo "<a href='/ICEANOTES/login.php'>→ Login</a>";