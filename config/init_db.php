<?php
// config/init_db.php
require_once __DIR__ . '/database.php';

// ============================================================================
// GARDE-FOU : ne pas écraser une base existante sans confirmation
// ============================================================================
$dbExists = file_exists(DB_PATH) && filesize(DB_PATH) > 0;
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === '1';

if ($dbExists && !$confirmed) {
    // Compter les données pour informer l'utilisateur
    try {
        $pdoCheck = getDB();
        $counts = [
            'users'     => (int)$pdoCheck->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'etudiants' => (int)$pdoCheck->query("SELECT COUNT(*) FROM etudiants")->fetchColumn(),
            'notes'     => (int)$pdoCheck->query("SELECT COUNT(*) FROM notes")->fetchColumn(),
        ];
    } catch (Exception $e) {
        $counts = ['users' => '?', 'etudiants' => '?', 'notes' => '?'];
    }
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>ICEANOTES — Base existante</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50 min-h-screen flex items-center justify-center p-6">
    <div class="bg-white rounded-2xl shadow-lg p-8 max-w-lg w-full">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 rounded-xl bg-yellow-100 flex items-center justify-center text-2xl">⚠️</div>
            <h1 class="text-xl font-bold text-gray-800">Base existante détectée</h1>
        </div>
        <p class="text-sm text-gray-600 mb-4">
            La base <code class="bg-gray-100 px-1.5 py-0.5 rounded"><?= htmlspecialchars(basename(DB_PATH)) ?></code>
            existe déjà et contient des données :
        </p>
        <ul class="text-sm text-gray-700 mb-6 space-y-1 bg-gray-50 p-4 rounded-lg">
            <li>👥 <strong><?= $counts['users'] ?></strong> utilisateur(s)</li>
            <li>🎓 <strong><?= $counts['etudiants'] ?></strong> étudiant(s)</li>
            <li>📝 <strong><?= $counts['notes'] ?></strong> note(s)</li>
        </ul>
        <p class="text-sm text-red-600 font-medium mb-6">
            ⚠️ Continuer va <strong>SUPPRIMER TOUTES CES DONNÉES</strong>.<br>
            <span class="text-xs text-gray-500 font-normal">
                (une sauvegarde automatique sera faite dans <code>database/dump/</code>)
            </span>
        </p>
        <div class="flex gap-3">
            <a href="?confirm=1"
               class="flex-1 text-center bg-red-600 hover:bg-red-700 text-white font-medium py-2.5 rounded-lg transition">
                Oui, réinitialiser
            </a>
            <a href="/ICEANOTES/pages/accueil.php"
               class="flex-1 text-center bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2.5 rounded-lg transition">
                Annuler
            </a>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================================================
// BACKUP automatique avant suppression
// ============================================================================
$backupMsg = '';
if ($dbExists) {
    $backupDir = BASE_PATH . '/database/dump';
    if (!is_dir($backupDir)) @mkdir($backupDir, 0775, true);
    $backupFile = $backupDir . '/iceanotes_avant_reset_' . date('Y-m-d_His') . '.sqlite';
    if (copy(DB_PATH, $backupFile)) {
        $backupMsg = "Sauvegarde créée : " . basename($backupFile) . " (" . round(filesize($backupFile) / 1024, 1) . " Ko)";
    } else {
        die("❌ Impossible de créer la sauvegarde automatique. Abandon pour sécurité.");
    }
}

// ============================================================================
// SUPPRESSION + recréation
// ============================================================================
if (!is_dir(DB_DIR)) mkdir(DB_DIR, 0775, true);
if (file_exists(DB_PATH)) @unlink(DB_PATH);

$pdo = getDB();

// ============================================================================
// SCHÉMA (v2 avec ecoles, coef, description, ordre)
// ============================================================================
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

CREATE TABLE ecoles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    color TEXT NOT NULL DEFAULT '#3b82f6'
);

CREATE TABLE filieres (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL UNIQUE,
    ordre INTEGER NOT NULL DEFAULT 0,
    ecole_id INTEGER NOT NULL,
    actif INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (ecole_id) REFERENCES ecoles(id)
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
    ordre INTEGER NOT NULL DEFAULT 0,
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
    description TEXT DEFAULT NULL,
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

// ============================================================================
// SEED
// ============================================================================

// --- Types utilisateurs ---
$pdo->exec("INSERT INTO users_types (label) VALUES ('admin'), ('superviseur'), ('user'), ('guest')");
$adminTypeId       = (int)$pdo->query("SELECT id FROM users_types WHERE label='admin'")->fetchColumn();
$superviseurTypeId = (int)$pdo->query("SELECT id FROM users_types WHERE label='superviseur'")->fetchColumn();

// --- Utilisateurs par défaut ---
$hash = hashPassword('icea');
$stmt = $pdo->prepare("
    INSERT INTO users (email, password, nom, prenom, user_type_id, protected)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->execute(['charles.pottier@icea-edu.fr', $hash, 'Pottier', 'Charles', $adminTypeId, 1]);
$stmt->execute(['sylvio.prudent@icea-edu.fr',  $hash, 'Prudent', 'Sylvio',  $superviseurTypeId, 1]);

// --- École ICEA ---
$pdo->exec("INSERT INTO ecoles (nom, description, color) VALUES ('ICEA', 'Institut Catholique Européen des Amériques', '#3b82f6')");
$ecoleId = (int)$pdo->query("SELECT id FROM ecoles WHERE nom='ICEA'")->fetchColumn();

// --- 4 filières rattachées à ICEA (avec ordre) ---
$stmt = $pdo->prepare("INSERT INTO filieres (nom, ordre, ecole_id) VALUES (?, ?, ?)");
$stmt->execute(['SDN', 10, $ecoleId]);
$stmt->execute(['Sciences Sociales', 20, $ecoleId]);
$stmt->execute(['Journalisme', 30, $ecoleId]);
$stmt->execute(['Sciences du Numériques', 40, $ecoleId]);

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

// --- Types de notes (avec description + coef) ---
$stmt = $pdo->prepare("
    INSERT INTO note_types (nom, description, sur, coef, ordre)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute(['CC1',    'Contrôle continu 1',                20, 1, 1]);
$stmt->execute(['CC2',    'Contrôle continu 2',                20, 1, 2]);
$stmt->execute(['Examen', 'Examen final (coefficient double)', 20, 2, 3]);

// ============================================================================
// RÉCAP
// ============================================================================

$nbUsers     = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$nbEcoles    = (int)$pdo->query("SELECT COUNT(*) FROM ecoles")->fetchColumn();
$nbFilieres  = (int)$pdo->query("SELECT COUNT(*) FROM filieres")->fetchColumn();
$nbPromos    = (int)$pdo->query("SELECT COUNT(*) FROM promotions")->fetchColumn();
$nbModules   = (int)$pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
$nbEtudiants = (int)$pdo->query("SELECT COUNT(*) FROM etudiants")->fetchColumn();
$nbNoteTypes = (int)$pdo->query("SELECT COUNT(*) FROM note_types")->fetchColumn();
$nbNotes     = (int)$pdo->query("SELECT COUNT(*) FROM notes")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>ICEANOTES v2 - Init DB</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-6">
<div class="bg-white rounded-2xl shadow-lg p-8 max-w-2xl w-full">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center text-2xl">✅</div>
        <div>
            <h1 class="text-2xl font-bold text-green-600">Base ICEANOTES v2 initialisée</h1>
            <p class="text-sm text-gray-500 mt-0.5">Schéma complet avec <code>ecoles</code>, <code>coef</code>, <code>description</code>, <code>ordre</code></p>
        </div>
    </div>

    <?php if ($backupMsg): ?>
        <div class="mb-4 p-3 rounded-lg bg-blue-50 text-blue-800 border border-blue-200 text-sm">
            💾 <?= htmlspecialchars($backupMsg) ?>
        </div>
    <?php endif; ?>

    <div class="space-y-2 text-sm text-gray-700 mb-6 bg-gray-50 p-4 rounded-lg">
        <p><strong>Écoles :</strong> <?= $nbEcoles ?> (ICEA)</p>
        <p><strong>Filières :</strong> <?= $nbFilieres ?> (SDN, Sciences Sociales, Journalisme, Sciences du Numériques)</p>
        <p><strong>Promotions :</strong> <?= $nbPromos ?> (L1/L3 × SDN/Journalisme × avec/sans promo)</p>
        <p><strong>Types de notes :</strong> <?= $nbNoteTypes ?> (CC1, CC2, Examen)</p>
        <p><strong>Utilisateurs :</strong> <?= $nbUsers ?> (Charles=admin, Sylvio=superviseur)</p>
        <p><strong>Modules :</strong> <?= $nbModules ?></p>
        <p><strong>Étudiants :</strong> <?= $nbEtudiants ?></p>
        <p><strong>Notes :</strong> <?= $nbNotes ?></p>
    </div>

    <div class="bg-blue-50 rounded-lg p-4 text-sm mb-6">
        <p class="font-semibold text-blue-800 mb-2">Comptes par défaut :</p>
        <p>📧 <code>charles.pottier@icea-edu.fr</code> — 🔑 <code>icea</code> (admin)</p>
        <p>📧 <code>sylvio.prudent@icea-edu.fr</code> — 🔑 <code>icea</code> (superviseur)</p>
    </div>

    <?php if (!empty($backupMsg)): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm mb-6">
            <p class="font-semibold text-amber-800 mb-2">💡 Récupérer tes anciennes données ?</p>
            <p class="text-amber-700 mb-3">
                Le dump <code>iceanotes_backup_2026-11-06_192259.sql</code> contient 6 étudiants et 3 notes.
                Utilise le script d'import pour les réinjecter dans cette base v2.
            </p>
            <a href="/ICEANOTES/_SANDBOX/3-import_from_dump.php"
               class="inline-flex items-center gap-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                📥 Importer depuis le dump
            </a>
        </div>
    <?php endif; ?>

    <div class="flex gap-2">
        <a href="/ICEANOTES/login.php"
           class="flex-1 text-center bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg transition">
            → Aller au login
        </a>
        <a href="/ICEANOTES/_SANDBOX/index.php"
           class="flex-1 text-center bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2.5 rounded-lg transition">
            🧪 Sandbox
        </a>
    </div>
</div>
</body>
</html>