<?php
// _SANDBOX/migrate_sections_to_ecoles.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';

// 🔒 Protection sandbox
if (!isLoggedIn()) {
    redirect('/ICEANOTES/login.php?error=' . urlencode('Accès réservé aux utilisateurs connectés.'));
}
if (!isSuperviseur()) {
    redirect('/ICEANOTES/pages/accueil.php');
}

$pdo = getDB();
$log = [];
$ok = true;

/**
 * Vérifie si une colonne existe dans une table SQLite
 */
function columnExists(PDO $pdo, string $table, string $column): bool
{
    $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll();
    foreach ($cols as $c) {
        if ($c['name'] === $column) return true;
    }
    return false;
}

/**
 * Vérifie si une table existe
 */
function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

// ============================================================================
// Étape 1 : sauvegarde automatique AVANT toute modif
// ============================================================================
$backupFile = DB_DIR . '/iceanotes_avant_migration_' . date('Y-m-d_His') . '.sqlite';
if (copy(DB_PATH, $backupFile)) {
    $log[] = ['ok', "Sauvegarde créée : <code>" . basename($backupFile) . "</code>"];
} else {
    $log[] = ['err', "Impossible de créer la sauvegarde — ARRÊT."];
    $ok = false;
}

// ============================================================================
// Étape 2 : si `sections` existe mais `ecoles` non → cas complexe à gérer
//           si `ecoles` existe déjà mais vide → on transfère sections → ecoles
//           si `ecoles` existe et non vide → on suppose migration déjà faite
// ============================================================================

if ($ok && !tableExists($pdo, 'ecoles')) {
    // Cas 1 : base très ancienne sans `ecoles` → à éviter, l'init_db v2 doit tourner avant
    $log[] = ['err', "La table <code>ecoles</code> n'existe pas. Lance d'abord <code>config/init_db.php</code> sur une base vide, puis ré-importe les données via ce script en mode manuel."];
    $ok = false;
}

// ============================================================================
// Étape 3 : vérifier le mapping ancien → nouveau
// ============================================================================
$needMigration = $ok
    && tableExists($pdo, 'sections')
    && columnExists($pdo, 'filieres', 'section_id')
    && !columnExists($pdo, 'filieres', 'ecole_id');

if ($ok && !$needMigration) {
    if (tableExists($pdo, 'sections') && columnExists($pdo, 'filieres', 'ecole_id')) {
        $log[] = ['info', "La migration semble déjà effectuée (<code>filieres.ecole_id</code> existe). Rien à faire."];
    } elseif (!tableExists($pdo, 'sections')) {
        $log[] = ['info', "Pas de table <code>sections</code>. Base déjà au nouveau schéma."];
    } else {
        $log[] = ['info', "État inattendu. Vérifie manuellement le schéma."];
    }
}

// ============================================================================
// Étape 4 : la migration
// ============================================================================
if ($ok && $needMigration) {
    try {
        $pdo->beginTransaction();

        // --- 4a. Copier `sections` → `ecoles` (en évitant les doublons par nom) ---
        $sections = $pdo->query("SELECT id, nom FROM sections")->fetchAll();
        $ecoleCount = 0;
        $stmtInsertEcole = $pdo->prepare("
            INSERT INTO ecoles (nom, description, color)
            VALUES (?, NULL, ?)
        ");
        $defaultColor = '#3b82f6';

        // Mapping section_id → ecole_id (nouveau)
        $mapping = [];

        foreach ($sections as $s) {
            // Existe déjà dans ecoles ?
            $check = $pdo->prepare("SELECT id FROM ecoles WHERE nom = ?");
            $check->execute([$s['nom']]);
            $existing = $check->fetchColumn();

            if ($existing) {
                $mapping[(int)$s['id']] = (int)$existing;
                $log[] = ['info', "Section <code>" . htmlspecialchars($s['nom']) . "</code> déjà présente dans <code>ecoles</code> (id=" . (int)$existing . ")."];
            } else {
                $stmtInsertEcole->execute([$s['nom'], $defaultColor]);
                $newId = (int)$pdo->lastInsertId();
                $mapping[(int)$s['id']] = $newId;
                $ecoleCount++;
                $log[] = ['ok', "Section <code>" . htmlspecialchars($s['nom']) . "</code> → <code>ecoles</code> id=" . $newId];
            }
        }

        // --- 4b. Ajouter les colonnes manquantes à `filieres`, `modules`, `note_types` ---
        $colsToAdd = [
            ['filieres',   'ecole_id', 'INTEGER'],
            ['filieres',   'ordre',    'INTEGER NOT NULL DEFAULT 0'],
            ['modules',    'ordre',    'INTEGER NOT NULL DEFAULT 0'],
            ['note_types', 'coef',     'REAL NOT NULL DEFAULT 1'],
            ['note_types', 'description', 'TEXT DEFAULT NULL'],
        ];
        foreach ($colsToAdd as [$table, $col, $def]) {
            if (!columnExists($pdo, $table, $col)) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $col $def");
                $log[] = ['ok', "Colonne <code>$table.$col</code> ajoutée."];
            }
        }

        // --- 4c. Remplir `filieres.ecole_id` depuis `section_id` ---
        $filieres = $pdo->query("SELECT id, section_id FROM filieres")->fetchAll();
        $stmtUpdateFiliere = $pdo->prepare("UPDATE filieres SET ecole_id = ? WHERE id = ?");
        foreach ($filieres as $f) {
            $newEcoleId = $mapping[(int)$f['section_id']] ?? null;
            if ($newEcoleId) {
                $stmtUpdateFiliere->execute([$newEcoleId, $f['id']]);
            } else {
                $log[] = ['warn', "Filière id=" . (int)$f['id'] . " : section_id=" . (int)$f['section_id'] . " non mappée."];
            }
        }
        $log[] = ['ok', count($filieres) . " filière(s) mise(s) à jour (<code>ecole_id</code>)."];

        $pdo->commit();

        // --- 4d. Post-migration : garder `sections` intact pour sécurité ---
        //     (on ne DROP pas → réversible en cas de souci)
        $log[] = ['info', "Table <code>sections</code> conservée (réversible). Tu pourras la supprimer manuellement une fois validé."];
        $log[] = ['info', "Colonne <code>filieres.section_id</code> conservée aussi."];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $log[] = ['err', "Erreur pendant la migration : " . htmlspecialchars($e->getMessage())];
        $ok = false;
    }
}

// ============================================================================
// Résumé final
// ============================================================================
$totalEcoles    = $pdo->query("SELECT COUNT(*) FROM ecoles")->fetchColumn();
$totalFilieres  = $pdo->query("SELECT COUNT(*) FROM filieres")->fetchColumn();
$totalPromos    = $pdo->query("SELECT COUNT(*) FROM promotions")->fetchColumn();
$totalModules   = $pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
$totalEtudiants = $pdo->query("SELECT COUNT(*) FROM etudiants")->fetchColumn();
$totalNotes     = $pdo->query("SELECT COUNT(*) FROM notes")->fetchColumn();
$totalNoteTypes = $pdo->query("SELECT COUNT(*) FROM note_types")->fetchColumn();
$totalUsers     = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Migration sections → ecoles</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen p-6">
<div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-lg p-8">

    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl <?= $ok ? 'bg-green-100' : 'bg-red-100' ?> flex items-center justify-center text-2xl">
                <?= $ok ? '✅' : '❌' ?>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Migration <code>sections</code> → <code>ecoles</code></h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    <?= $needMigration ? 'Migration effectuée' : 'Aucune migration nécessaire' ?>
                </p>
            </div>
        </div>
        <a href="index.php" title="Retour sandbox"
           class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </a>
    </div>

    <div class="space-y-2 mb-6">
        <?php foreach ($log as [$type, $msg]): ?>
            <?php
                $cls = match($type) {
                    'ok'   => 'bg-green-50 text-green-800 border-green-200',
                    'warn' => 'bg-yellow-50 text-yellow-800 border-yellow-200',
                    'err'  => 'bg-red-50 text-red-800 border-red-200',
                    default => 'bg-blue-50 text-blue-800 border-blue-200',
                };
            ?>
            <div class="p-3 rounded-lg border text-sm <?= $cls ?>">
                <?= $msg ?>
            </div>
        <?php endforeach; ?>
    </div>

    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">État actuel de la base</h2>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <?php foreach ([
            ['Écoles',     $totalEcoles],
            ['Filières',   $totalFilieres],
            ['Promotions', $totalPromos],
            ['Modules',    $totalModules],
            ['Étudiants',  $totalEtudiants],
            ['Notes',      $totalNotes],
            ['Types notes',$totalNoteTypes],
            ['Utilisateurs',$totalUsers],
        ] as [$label, $value]): ?>
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-xs text-gray-500"><?= e($label) ?></div>
                <div class="text-xl font-bold text-gray-800"><?= (int)$value ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-8 flex gap-2">
        <a href="/ICEANOTES/pages/accueil.php"
           class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
            → Voir l'accueil
        </a>
        <a href="index.php"
           class="inline-flex items-center gap-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg transition">
            ← Sandbox
        </a>
    </div>

</div>
</body>
</html>