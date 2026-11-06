<?php
// _SANDBOX/3-import_from_dump.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';

if (!isLoggedIn()) {
    redirect('/ICEANOTES/login.php?error=' . urlencode('Accès réservé aux utilisateurs connectés.'));
}
if (!isSuperviseur()) {
    redirect('/ICEANOTES/pages/accueil.php');
}

$pdo = getDB();
$log = [];
$ok = true;

// Fichier source (adapte le nom si besoin)
$dumpFile = BASE_PATH . '/database/dump/iceanotes_backup_2026-11-06_192259.sql';

if (!file_exists($dumpFile)) {
    $log[] = ['err', "Dump introuvable : <code>" . htmlspecialchars($dumpFile) . "</code>"];
    $ok = false;
}

// Vérifier qu'on est sur le nouveau schéma
if ($ok && !$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='ecoles'")->fetchColumn()) {
    $log[] = ['err', "Table <code>ecoles</code> absente. Lance d'abord <code>config/init_db.php</code>."];
    $ok = false;
}

// ============================================================================
// Parser les INSERT du dump
// ============================================================================
function parseInserts(string $sql): array {
    $result = [];
    preg_match_all(
        '/INSERT\s+INTO\s+"?(\w+)"?\s*\(([^)]+)\)\s*VALUES\s*\((.*?)\);/s',
        $sql,
        $matches,
        PREG_SET_ORDER
    );
    foreach ($matches as $m) {
        $table = $m[1];
        $cols  = array_map(fn($c) => trim($c, ' "'), explode(',', $m[2]));
        $valuesRaw = $m[3];
        $values = [];
        $buf = '';
        $inQuote = false;
        $len = strlen($valuesRaw);
        for ($i = 0; $i < $len; $i++) {
            $ch = $valuesRaw[$i];
            if ($ch === "'" && !$inQuote) { $inQuote = true; $buf .= $ch; }
            elseif ($ch === "'" && $inQuote) {
                if ($i + 1 < $len && $valuesRaw[$i + 1] === "'") { $buf .= "''"; $i++; }
                else { $inQuote = false; $buf .= $ch; }
            }
            elseif ($ch === ',' && !$inQuote) { $values[] = trim($buf); $buf = ''; }
            else { $buf .= $ch; }
        }
        if (trim($buf) !== '') $values[] = trim($buf);

        foreach ($values as &$v) {
            $v = trim($v);
            if ($v === 'NULL') { $v = null; }
            elseif ($v !== '' && $v[0] === "'") { $v = str_replace("''", "'", substr($v, 1, -1)); }
            elseif (is_numeric($v)) { $v = $v + 0; }
        }
        unset($v);
        $result[$table][] = ['cols' => $cols, 'values' => $values];
    }
    return $result;
}

$data = $ok ? parseInserts(file_get_contents($dumpFile)) : [];

// ============================================================================
// Vider les tables (ordre inverse des FK)
// ============================================================================
if ($ok) {
    try {
        $pdo->beginTransaction();

        $tablesToClear = ['notes', 'remember_tokens', 'etudiants', 'modules', 'promotions', 'filieres', 'ecoles', 'note_types', 'users'];
        foreach ($tablesToClear as $t) {
            $pdo->exec("DELETE FROM $t");
            $pdo->exec("DELETE FROM sqlite_sequence WHERE name = '$t'");
        }
        $log[] = ['ok', "Tables vidées (sauf <code>users_types</code>)."];

        // --- users (utilisateurs du dump) ---
        if (!empty($data['users'])) {
            $stmt = $pdo->prepare("INSERT INTO users (id, email, password, nom, prenom, user_type_id, actif, protected) VALUES (?,?,?,?,?,?,?,?)");
            foreach ($data['users'] as $r) $stmt->execute($r['values']);
            $log[] = ['ok', count($data['users']) . " utilisateur(s) importé(s)."];
        }

        // --- ecoles (depuis sections) ---
        $sectionToEcole = [];
        $defaultColor = '#3b82f6';
        if (!empty($data['sections'])) {
            $stmtE = $pdo->prepare("INSERT INTO ecoles (id, nom, description, color) VALUES (?, ?, NULL, ?)");
            foreach ($data['sections'] as $r) {
                $id  = (int)$r['values'][0];
                $nom = $r['values'][1];
                $stmtE->execute([$id, $nom, $defaultColor]);
                $sectionToEcole[$id] = $id;
            }
            $log[] = ['ok', count($data['sections']) . " section(s) → ecoles."];
        }

        // --- note_types ---
        if (!empty($data['note_types'])) {
            $stmt = $pdo->prepare("INSERT INTO note_types (id, nom, description, sur, coef, ordre, actif) VALUES (?,?,NULL,?,?,?,?)");
            foreach ($data['note_types'] as $r) {
                $v = $r['values'];
                $id = (int)$v[0]; $nom = $v[1]; $sur = (float)$v[2]; $ordre = (int)$v[3]; $actif = (int)$v[4];
                $coef = isset($v[5]) ? (float)$v[5] : 1;
                $stmt->execute([$id, $nom, $sur, $coef, $ordre, $actif]);
            }
            $log[] = ['ok', count($data['note_types']) . " type(s) de note importé(s)."];
        }

        // --- filieres (mapper section_id → ecole_id) ---
        if (!empty($data['filieres'])) {
            $stmt = $pdo->prepare("INSERT INTO filieres (id, nom, ordre, ecole_id, actif) VALUES (?,?,?,?,?)");
            $i = 0;
            foreach ($data['filieres'] as $r) {
                $v = $r['values'];
                $id = (int)$v[0]; $nom = $v[1]; $sectionId = (int)$v[2]; $actif = (int)$v[3];
                $ecoleId = $sectionToEcole[$sectionId] ?? null;
                if (!$ecoleId) throw new Exception("Section $sectionId inconnue pour filière $nom");
                $stmt->execute([$id, $nom, ($i += 10), $ecoleId, $actif]);
            }
            $log[] = ['ok', count($data['filieres']) . " filière(s) importée(s)."];
        }

        // --- promotions ---
        if (!empty($data['promotions'])) {
            $stmt = $pdo->prepare("INSERT INTO promotions (id, nom, ordre, promo, annee, filiere_id, actif) VALUES (?,?,?,?,?,?,?)");
            foreach ($data['promotions'] as $r) $stmt->execute($r['values']);
            $log[] = ['ok', count($data['promotions']) . " promotion(s) importée(s)."];
        }

        // --- modules (ordre=0) ---
        if (!empty($data['modules'])) {
            $stmt = $pdo->prepare("INSERT INTO modules (id, nom, niveau, filiere_id, professeur_id, ordre, actif) VALUES (?,?,?,?,?,?,?)");
            foreach ($data['modules'] as $r) {
                $v = $r['values'];
                $stmt->execute([$v[0], $v[1], $v[2], $v[3], $v[4], 0, $v[5]]);
            }
            $log[] = ['ok', count($data['modules']) . " module(s) importé(s)."];
        }

        // --- etudiants ⭐ ---
        if (!empty($data['etudiants'])) {
            $stmt = $pdo->prepare("INSERT INTO etudiants (id, nom, prenom, promotion_id, actif) VALUES (?,?,?,?,?)");
            foreach ($data['etudiants'] as $r) $stmt->execute($r['values']);
            $log[] = ['ok', "⭐ " . count($data['etudiants']) . " étudiant(s) importé(s)."];
        }

        // --- notes ⭐ ---
        if (!empty($data['notes'])) {
            $stmt = $pdo->prepare("INSERT INTO notes (id, etudiant_id, module_id, note_type_id, valeur, date_saisie) VALUES (?,?,?,?,?,?)");
            foreach ($data['notes'] as $r) $stmt->execute($r['values']);
            $log[] = ['ok', "⭐ " . count($data['notes']) . " note(s) importée(s)."];
        } else {
            $log[] = ['warn', "Aucune note dans le dump."];
        }

        // --- remember_tokens (optionnel, pour ne pas casser les sessions) ---
        if (!empty($data['remember_tokens'])) {
            $stmt = $pdo->prepare("INSERT INTO remember_tokens (id, user_id, token_hash, expires_at) VALUES (?,?,?,?)");
            foreach ($data['remember_tokens'] as $r) $stmt->execute($r['values']);
            $log[] = ['info', count($data['remember_tokens']) . " token(s) de session importé(s)."];
        }

        $pdo->commit();
        $log[] = ['ok', "✅ Import terminé avec succès."];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $log[] = ['err', "Erreur : " . htmlspecialchars($e->getMessage())];
        $ok = false;
    }
}

// ============================================================================
// Stats finales
// ============================================================================
$stats = [];
if ($ok) {
    foreach (['ecoles','filieres','promotions','modules','etudiants','notes','note_types','users'] as $t) {
        $stats[$t] = (int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Import depuis dump</title>
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
                <h1 class="text-2xl font-bold text-gray-800">Import depuis dump SQL</h1>
                <p class="text-sm text-gray-500 mt-0.5">Sauvegarde 2026-11-06_192259</p>
            </div>
        </div>
        <a href="index.php" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </a>
    </div>

    <div class="space-y-2 mb-6">
        <?php foreach ($log as [$type, $msg]):
            $cls = match($type) {
                'ok'   => 'bg-green-50 text-green-800 border-green-200',
                'warn' => 'bg-yellow-50 text-yellow-800 border-yellow-200',
                'err'  => 'bg-red-50 text-red-800 border-red-200',
                default => 'bg-blue-50 text-blue-800 border-blue-200',
            };
        ?>
            <div class="p-3 rounded-lg border text-sm <?= $cls ?>"><?= $msg ?></div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($stats)): ?>
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">État final</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <?php
            $labels = [
                'ecoles' => 'Écoles', 'filieres' => 'Filières', 'promotions' => 'Promotions',
                'modules' => 'Modules', 'etudiants' => 'Étudiants', 'notes' => 'Notes',
                'note_types' => 'Types notes', 'users' => 'Utilisateurs',
            ];
            foreach ($labels as $key => $label):
                if (!isset($stats[$key])) continue;
            ?>
                <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-xs text-gray-500"><?= e($label) ?></div>
                    <div class="text-xl font-bold text-gray-800"><?= $stats[$key] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="mt-6 flex gap-2">
        <a href="/ICEANOTES/pages/accueil.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">→ Voir l'accueil</a>
        <a href="/ICEANOTES/pages/etudiants.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">Voir les étudiants</a>
        <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">← Sandbox</a>
    </div>
</div>
</body>
</html>