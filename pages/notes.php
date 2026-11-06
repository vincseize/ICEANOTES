<?php
// pages/notes.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
$me = currentUser();
$isSuperviseur = isSuperviseur();
$myId = (int)$me['id'];

$pdo = getDB();
$msg = '';

/**
 * Calcule la moyenne pondérée d'un étudiant sur un module
 */
function computeMoyenne(PDO $pdo, int $etudiantId, int $moduleId): ?float
{
    $stmt = $pdo->prepare("
        SELECT n.valeur, nt.sur, nt.coef
        FROM notes n
        JOIN note_types nt ON nt.id = n.note_type_id
        WHERE n.etudiant_id = ? AND n.module_id = ? AND nt.actif = 1
    ");
    $stmt->execute([$etudiantId, $moduleId]);
    $rows = $stmt->fetchAll();

    if (empty($rows)) return null;

    $sumNotes = 0;
    $sumCoefs = 0;
    foreach ($rows as $r) {
        $sumNotes += ((float)$r['valeur'] / (float)$r['sur']) * 20 * (float)$r['coef'];
        $sumCoefs += (float)$r['coef'];
    }
    return $sumCoefs > 0 ? round($sumNotes / $sumCoefs, 2) : null;
}

// --- Filtres ---
$filterPromo  = (int)($_GET['promo'] ?? 0);
$filterModule = (int)($_GET['module'] ?? 0);
$filterType   = (int)($_GET['type'] ?? 0);       // ← NOUVEAU
$search       = trim($_GET['q'] ?? '');

/**
 * Construit la grille (étudiant × module) selon les filtres
 */
function buildGrid(PDO $pdo, bool $isSuperviseur, int $myId, int $filterPromo, int $filterModule, int $filterType, string $search): array
{
    $typesAll = $pdo->query("SELECT * FROM note_types WHERE actif = 1 ORDER BY ordre, nom")->fetchAll();

    // Si un type est filtré → on ne garde que ce type pour l'affichage des colonnes
    if ($filterType > 0) {
        $types = array_values(array_filter($typesAll, fn($t) => (int)$t['id'] === $filterType));
    } else {
        $types = $typesAll;
    }

    // Étudiants
    $etWhere = ['e.actif = 1'];
    $etParams = [];
    if ($search !== '') {
        $etWhere[] = "(e.nom LIKE ? OR e.prenom LIKE ?)";
        $like = "%$search%";
        array_push($etParams, $like, $like);
    }
    if ($filterPromo) {
        $etWhere[] = "e.promotion_id = ?";
        $etParams[] = $filterPromo;
    }
    $etWhereSql = implode(' AND ', $etWhere);

    $stmt = $pdo->prepare("
        SELECT e.*, p.nom AS niveau, p.promo, p.annee, p.filiere_id, f.nom AS filiere
        FROM etudiants e
        JOIN promotions p ON p.id = e.promotion_id
        JOIN filieres f ON f.id = p.filiere_id
        WHERE $etWhereSql
        ORDER BY p.ordre, e.nom, e.prenom
    ");
    $stmt->execute($etParams);
    $etudiants = $stmt->fetchAll();

    // Modules
    $modWhere = ['m.actif = 1'];
    $modParams = [];
    if (!$isSuperviseur) {
        $modWhere[] = "m.professeur_id = ?";
        $modParams[] = $myId;
    }
    if ($filterModule) {
        $modWhere[] = "m.id = ?";
        $modParams[] = $filterModule;
    }
    $modWhereSql = implode(' AND ', $modWhere);

    $stmt = $pdo->prepare("
        SELECT m.*, f.nom AS filiere, f.id AS filiere_id
        FROM modules m
        JOIN filieres f ON f.id = m.filiere_id
        WHERE $modWhereSql
        ORDER BY m.niveau, f.nom, m.nom
    ");
    $stmt->execute($modParams);
    $modules = $stmt->fetchAll();

    // Notes
    $notesMap = [];
    if (!empty($etudiants) && !empty($modules)) {
        $etudiantIds = array_column($etudiants, 'id');
        $moduleIds   = array_column($modules, 'id');
        $inE = implode(',', array_fill(0, count($etudiantIds), '?'));
        $inM = implode(',', array_fill(0, count($moduleIds), '?'));

        $sqlNotes = "SELECT * FROM notes WHERE etudiant_id IN ($inE) AND module_id IN ($inM)";
        $paramsNotes = array_merge($etudiantIds, $moduleIds);

        // Si un type est filtré → on ne charge que les notes de ce type
        if ($filterType > 0) {
            $sqlNotes .= " AND note_type_id = ?";
            $paramsNotes[] = $filterType;
        }

        $stmt = $pdo->prepare($sqlNotes);
        $stmt->execute($paramsNotes);
        foreach ($stmt->fetchAll() as $n) {
            $notesMap[$n['etudiant_id']][$n['module_id']][$n['note_type_id']] = $n;
        }
    }

    // Grille
    $grid = [];
    foreach ($etudiants as $et) {
        $modulesForEt = [];
        foreach ($modules as $mod) {
            if ($et['filiere_id'] != $mod['filiere_id']) continue;
            if ($et['niveau'] !== $mod['niveau']) continue;
            $modulesForEt[] = $mod;
        }

        if (empty($modulesForEt)) {
            $grid[] = [
                'etudiant' => $et,
                'module'   => null,
                'notes'    => [],
                'moyenne'  => null,
                'empty'    => true,
            ];
        } else {
            foreach ($modulesForEt as $mod) {
                $notes = $notesMap[$et['id']][$mod['id']] ?? [];
                $moy = computeMoyenne($pdo, (int)$et['id'], (int)$mod['id']);
                $grid[] = [
                    'etudiant' => $et,
                    'module'   => $mod,
                    'notes'    => $notes,
                    'moyenne'  => $moy,
                    'empty'    => false,
                ];
            }
        }
    }

    return ['grid' => $grid, 'types' => $types];
}

// ============================================================================
// EXPORT CSV / JSON — colonnes dynamiques
// ============================================================================
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $data = buildGrid($pdo, $isSuperviseur, $myId, $filterPromo, $filterModule, $filterType, $search);
    $grid = $data['grid'];
    $types = $data['types'];

    $filename = 'notes_' . date('Y-m-d_His');

    $rows = [];
    foreach ($grid as $row) {
        $et  = $row['etudiant'];
        $mod = $row['module'];

        $line = [
            'etudiant' => $et['prenom'] . ' ' . $et['nom'],
            'promo'    => $et['niveau'] . ($et['promo'] ? ' — ' . $et['promo'] : ''),
            'module'   => $mod['nom'] ?? '',
        ];

        foreach ($types as $tp) {
            $note = $row['notes'][$tp['id']] ?? null;
            $line['note_' . $tp['nom']] = $note ? (float)$note['valeur'] : '';
        }

        $line['moyenne'] = $row['moyenne'] !== null ? $row['moyenne'] : '';

        $rows[] = $line;
    }

    $headers = ['etudiant' => 'Étudiant', 'promo' => 'Promo', 'module' => 'Module'];
    foreach ($types as $tp) {
        $headers['note_' . $tp['nom']] = $tp['nom'] . ' / ' . (float)$tp['sur'] . ' (×' . (float)$tp['coef'] . ')';
    }
    $headers['moyenne'] = 'Moyenne';

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        if (!empty($rows)) {
            fputcsv($out, array_values($headers), ';');
            foreach ($rows as $r) {
                fputcsv($out, array_values($r), ';');
            }
        }
        fclose($out);
        exit;
    }

    if ($format === 'json') {
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.json"');

        echo json_encode([
            'export_date' => date('c'),
            'app'         => APP_NAME . ' ' . APP_VERSION,
            'user'        => $me['email'],
            'filters' => [
                'q'      => $search,
                'promo'  => $filterPromo,
                'module' => $filterModule,
                'type'   => $filterType,
            ],
            'headers' => $headers,
            'types'   => array_map(fn($t) => [
                'nom'  => $t['nom'],
                'sur'  => (float)$t['sur'],
                'coef' => (float)$t['coef'],
            ], $types),
            'count'   => count($rows),
            'data'    => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ============================================================================
// AFFICHAGE
// ============================================================================

if (isset($_GET['delete_note'])) {
    $id = (int)$_GET['delete_note'];
    if ($isSuperviseur) {
        $pdo->prepare("DELETE FROM notes WHERE id = ?")->execute([$id]);
    } else {
        $pdo->prepare("DELETE FROM notes WHERE id = ? AND module_id IN (SELECT id FROM modules WHERE professeur_id = ?)")
            ->execute([$id, $myId]);
    }
    redirect('/ICEANOTES/pages/notes.php?ok=delete');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    header('Content-Type: application/json');

    $etudiantId = (int)($_POST['etudiant_id'] ?? 0);
    $moduleId   = (int)($_POST['module_id'] ?? 0);
    $typeId     = (int)($_POST['note_type_id'] ?? 0);
    $valeur     = $_POST['valeur'] ?? '';

    if (!$etudiantId || !$moduleId || !$typeId || !is_numeric($valeur)) {
        echo json_encode(['ok' => false, 'error' => 'Champs invalides']);
        exit;
    }

    if (!$isSuperviseur) {
        $check = $pdo->prepare("SELECT 1 FROM modules WHERE id = ? AND professeur_id = ?");
        $check->execute([$moduleId, $myId]);
        if (!$check->fetchColumn()) {
            echo json_encode(['ok' => false, 'error' => t('error_prof_module')]);
            exit;
        }
    }

    $stmt = $pdo->prepare("SELECT sur FROM note_types WHERE id = ?");
    $stmt->execute([$typeId]);
    $sur = (float)$stmt->fetchColumn();

    if ($sur <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Type de note invalide']);
        exit;
    }

    $valeur = (float)$valeur;
    if ($valeur < 0 || $valeur > $sur) {
        echo json_encode(['ok' => false, 'error' => "La note doit être entre 0 et $sur"]);
        exit;
    }

    try {
        $pdo->prepare("
            INSERT INTO notes (etudiant_id, module_id, note_type_id, valeur)
            VALUES (?, ?, ?, ?)
            ON CONFLICT(etudiant_id, module_id, note_type_id)
            DO UPDATE SET valeur = excluded.valeur, date_saisie = CURRENT_TIMESTAMP
        ")->execute([$etudiantId, $moduleId, $typeId, $valeur]);

        $moy = computeMoyenne($pdo, $etudiantId, $moduleId);
        echo json_encode(['ok' => true, 'moyenne' => $moy]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => 'Erreur d\'enregistrement']);
    }
    exit;
}

[$page, $perPage, $offset, $sort, $dir] = array_values(listParams(20));

$data  = buildGrid($pdo, $isSuperviseur, $myId, $filterPromo, $filterModule, $filterType, $search);
$grid  = $data['grid'];
$types = $data['types'];

// ============================================================================
// Tri côté PHP (grille en mémoire)
// ============================================================================
$sortKeys = [
    'etudiant' => fn($a, $b) => strcmp(
        $a['etudiant']['nom'] . $a['etudiant']['prenom'],
        $b['etudiant']['nom'] . $b['etudiant']['prenom']
    ),
    'promo'    => fn($a, $b) => strcmp(
        ($a['etudiant']['niveau'] ?? '') . '|' . ($a['etudiant']['promo'] ?? '') . '|' . ($a['etudiant']['annee'] ?? ''),
        ($b['etudiant']['niveau'] ?? '') . '|' . ($b['etudiant']['promo'] ?? '') . '|' . ($b['etudiant']['annee'] ?? '')
    ),
    'module'   => fn($a, $b) => strcmp($a['module']['nom'] ?? '', $b['module']['nom'] ?? ''),
    'moyenne'  => fn($a, $b) => ($a['moyenne'] ?? -1) <=> ($b['moyenne'] ?? -1),
];
if (isset($sortKeys[$sort])) {
    usort($grid, $sortKeys[$sort]);
    if ($dir === 'DESC') $grid = array_reverse($grid);
}

$total = count($grid);
$grid  = array_slice($grid, $offset, $perPage);

$promotions = $pdo->query("
    SELECT p.*, f.nom AS filiere FROM promotions p JOIN filieres f ON f.id = p.filiere_id
    WHERE p.actif = 1 ORDER BY p.ordre, f.nom
")->fetchAll();

$allModules = $pdo->query("
    SELECT m.*, f.nom AS filiere FROM modules m JOIN filieres f ON f.id = m.filiere_id
    WHERE m.actif = 1 ORDER BY m.niveau, f.nom, m.nom
")->fetchAll();

// Types actifs pour le filtre
$allTypes = $pdo->query("SELECT * FROM note_types WHERE actif = 1 ORDER BY ordre, nom")->fetchAll();

require_once BASE_PATH . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            <?= $isSuperviseur ? t('notes_title') : t('notes_title_prof') ?>
        </h1>
        <p class="text-sm text-gray-500"><?= t('notes_page_count', ['n' => $total]) ?> (étudiants × modules)</p>
    </div>

    <div class="flex gap-2">
        <a href="<?= e(currentUrl(['export' => 'csv'])) ?>"
           class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-3 py-2 rounded-lg transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            CSV
        </a>
        <a href="<?= e(currentUrl(['export' => 'json'])) ?>"
           class="inline-flex items-center gap-2 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium px-3 py-2 rounded-lg transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            JSON
        </a>
    </div>
</div>

<?php if ($msg): ?><div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4"><?= e($msg) ?></div><?php endif; ?>
<?php if (isset($_GET['ok'])): ?><div class="bg-green-50 text-green-700 text-sm p-3 rounded-lg mb-4"><?= t('ok_' . $_GET['ok']) ?></div><?php endif; ?>

<form method="GET" id="filterForm" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" id="searchInput" value="<?= e($search) ?>"
           placeholder="<?= e(t('search')) ?>"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">

    <select name="promo" id="promoSelect" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value="">— <?= t('promotion_label') ?> —</option>
        <?php foreach ($promotions as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $filterPromo == $p['id'] ? 'selected' : '' ?>>
                <?= e($p['nom'] . ' — ' . ($p['promo'] ?? '') . ' (' . $p['annee'] . ')') ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="module" id="moduleSelect" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value="">— <?= t('module') ?> —</option>
        <?php foreach ($allModules as $m): ?>
            <option value="<?= $m['id'] ?>" <?= $filterModule == $m['id'] ? 'selected' : '' ?>>
                <?= e($m['nom']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <!-- Filtre par type de note -->
    <select name="type" id="typeSelect" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value="">— <?= t('type') ?> —</option>
        <?php foreach ($allTypes as $tp): ?>
            <option value="<?= $tp['id'] ?>" <?= $filterType == $tp['id'] ? 'selected' : '' ?>>
                <?= e($tp['nom']) ?> (×<?= (float)$tp['coef'] ?>)
            </option>
        <?php endforeach; ?>
    </select>

    <?php if ($search !== '' || $filterPromo || $filterModule || $filterType): ?>
        <a href="/ICEANOTES/pages/notes.php?per_page=<?= $perPage ?>"
           class="text-sm text-red-500 hover:text-red-700 hover:underline self-center px-3 whitespace-nowrap">
            ✕ <?= t('reset') ?>
        </a>
    <?php endif; ?>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="text-left px-4 py-3"><?= sortLink('etudiant', t('etudiant'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('promo', t('promo'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('module', t('module'), $sort, $dir) ?></th>
                    <?php foreach ($types as $tp): ?>
                        <th class="text-center px-3 py-3 min-w-[100px]">
                            <?= e($tp['nom']) ?><br>
                            <span class="text-xs text-gray-400 font-normal">/ <?= (float)$tp['sur'] ?> (×<?= (float)$tp['coef'] ?>)</span>
                        </th>
                    <?php endforeach; ?>
                    <th class="text-center px-3 py-3"><?= sortLink('moyenne', t('moyenne'), $sort, $dir) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($grid)): ?>
                <tr><td colspan="<?= 4 + count($types) ?>" class="text-center text-gray-400 py-8"><?= t('no_students_or_modules') ?></td></tr>
            <?php else: foreach ($grid as $row): ?>
                <?php
                    $et  = $row['etudiant'];
                    $mod = $row['module'];
                    $notesRow = $row['notes'];
                    $moy = $row['moyenne'];
                ?>
                <?php if ($row['empty']): ?>
                    <tr class="bg-gray-50 italic text-gray-500">
                        <td class="px-4 py-3 font-medium not-italic text-gray-700"><?= e($et['prenom'] . ' ' . $et['nom']) ?></td>
                        <td class="px-4 py-3 text-xs"><?= e($et['niveau']) ?><?= $et['promo'] ? ' — ' . e($et['promo']) : '' ?></td>
                        <td colspan="<?= 2 + count($types) ?>" class="px-4 py-3 text-center">
                            <?= t('no_module_for_level') ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium"><?= e($et['prenom'] . ' ' . $et['nom']) ?></td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            <?= e($et['niveau']) ?><?= $et['promo'] ? ' — ' . e($et['promo']) : '' ?>
                        </td>
                        <td class="px-4 py-3">
                            <?= e($mod['nom']) ?>
                            <span class="inline-block ml-1 px-1.5 py-0.5 rounded text-xs bg-purple-100 text-purple-700">
                                <?= e($mod['filiere']) ?>
                            </span>
                        </td>
                        <?php foreach ($types as $tp):
                            $note = $notesRow[$tp['id']] ?? null;
                            $val  = $note ? (float)$note['valeur'] : null;
                            $sur  = (float)$tp['sur'];
                            $ok   = $val !== null && ($val / $sur) * 20 >= 10;
                            $noteId = $note ? (int)$note['id'] : 0;
                        ?>
                            <td class="px-3 py-3 text-center">
                                <?php if ($note): ?>
                                    <button type="button"
                                            onclick="openNoteModal(<?= $et['id'] ?>, <?= $mod['id'] ?>, <?= $tp['id'] ?>, <?= $noteId ?>, <?= $val ?>, '<?= e(addslashes($et['prenom'] . ' ' . $et['nom'])) ?>', '<?= e(addslashes($mod['nom'])) ?>', '<?= e(addslashes($tp['nom'])) ?>', <?= $sur ?>)"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded hover:bg-blue-50 transition"
                                            title="<?= t('edit') ?>">
                                        <span class="font-bold <?= $ok ? 'text-green-600' : 'text-red-600' ?>">
                                            <?= number_format($val, 1) ?>
                                        </span>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5h6m2 4v10a2 2 0 01-2 2H7a2 2 0 01-2-2V7a2 2 0 012-2h6m4-3l3 3-9 9H8v-3l9-9z"/></svg>
                                    </button>
                                <?php else: ?>
                                    <button type="button"
                                            onclick="openNoteModal(<?= $et['id'] ?>, <?= $mod['id'] ?>, <?= $tp['id'] ?>, 0, '', '<?= e(addslashes($et['prenom'] . ' ' . $et['nom'])) ?>', '<?= e(addslashes($mod['nom'])) ?>', '<?= e(addslashes($tp['nom'])) ?>', <?= $sur ?>)"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100 hover:bg-blue-100 text-gray-400 hover:text-blue-600 transition"
                                            title="<?= t('add') ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                    </button>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td class="px-3 py-3 text-center" data-moyenne-cell="<?= $et['id'] ?>-<?= $mod['id'] ?>">
                            <?php if ($moy !== null): ?>
                                <span class="font-bold <?= $moy >= 10 ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= number_format($moy, 2) ?>/20
                                </span>
                            <?php else: ?>
                                <span class="text-gray-400">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Barre du bas : per_page + pagination -->
<?php
    $pages = max(1, (int)ceil($total / $perPage));
    $buildPageUrl = function (int $p) use ($perPage) {
        $params = array_merge($_GET, ['page' => $p, 'per_page' => $perPage]);
        return '?' . http_build_query($params);
    };
    $buildPerPageUrl = function (int $n) {
        $params = array_merge($_GET, ['per_page' => $n, 'page' => 1]);
        return '?' . http_build_query($params);
    };
?>

<div class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-3">
    <div>
        <select id="perPageSelect"
                class="px-3 py-1.5 rounded-lg text-sm border border-gray-300 bg-white text-gray-700 cursor-pointer hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <?php foreach ([10, 20, 50, 100] as $n): ?>
                <option value="<?= e($buildPerPageUrl($n)) ?>" <?= $perPage == $n ? 'selected' : '' ?>>
                    <?= $n ?> / page
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($pages > 1): ?>
        <div class="flex flex-wrap items-center gap-1">
            <?php if ($page > 1): ?>
                <a href="<?= e($buildPageUrl($page - 1)) ?>"
                   class="px-3 py-1.5 rounded-lg text-sm bg-white text-gray-700 hover:bg-gray-100 border border-gray-300">‹</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <?php if ($i === 1 || $i === $pages || abs($i - $page) <= 2): ?>
                    <a href="<?= e($buildPageUrl($i)) ?>"
                       class="px-3 py-1.5 rounded-lg text-sm
                              <?= $i === $page
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-300' ?>">
                        <?= $i ?>
                    </a>
                <?php elseif (abs($i - $page) === 3): ?>
                    <span class="px-2 text-gray-400">…</span>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $pages): ?>
                <a href="<?= e($buildPageUrl($page + 1)) ?>"
                   class="px-3 py-1.5 rounded-lg text-sm bg-white text-gray-700 hover:bg-gray-100 border border-gray-300">›</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de saisie -->
<div id="noteModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800" id="modalTitle"><?= t('add_note_btn') ?></h3>
            <button onclick="closeNoteModal()" class="text-gray-400 hover:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="mb-4 text-sm text-gray-600" id="modalContext"></div>

        <form id="noteForm" onsubmit="submitNote(event)">
            <input type="hidden" id="modalEtudiantId">
            <input type="hidden" id="modalModuleId">
            <input type="hidden" id="modalTypeId">

            <label class="block text-sm font-medium text-gray-700 mb-1" id="modalLabel"><?= t('note') ?></label>
            <input type="number" id="modalValeur" step="0.1" min="0" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-lg font-bold focus:ring-2 focus:ring-blue-500 focus:border-transparent">

            <div class="flex gap-2 mt-6">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 rounded-lg">
                    <?= t('save') ?>
                </button>
                <button type="button" onclick="deleteNote()" id="modalDeleteBtn" class="hidden px-4 py-2 bg-red-500 hover:bg-red-600 text-white font-medium rounded-lg">
                    <?= t('delete') ?>
                </button>
                <button type="button" onclick="closeNoteModal()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg">
                    <?= t('cancel') ?>
                </button>
            </div>

            <div id="modalError" class="hidden mt-3 p-2 bg-red-50 text-red-700 text-sm rounded"></div>
        </form>
    </div>
</div>

<script>
(function () {
    // --- Filtres live ---
    const search = document.getElementById('searchInput');
    const promo  = document.getElementById('promoSelect');
    const module = document.getElementById('moduleSelect');
    const type   = document.getElementById('typeSelect');

    let searchTimeout = null;

    if (search) {
        search.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function () {
                const url = new URL(window.location.href);
                url.searchParams.set('q', search.value);
                url.searchParams.delete('page');
                window.location.href = url.toString();
            }, 400);
        });
    }

    [promo, module, type].forEach(function (sel) {
        if (!sel) return;
        sel.addEventListener('change', function () {
            const url = new URL(window.location.href);
            if (sel.value === '') {
                url.searchParams.delete(sel.name);
            } else {
                url.searchParams.set(sel.name, sel.value);
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        });
    });

    // --- Changement per_page ---
    const perPageSelect = document.getElementById('perPageSelect');
    if (perPageSelect) {
        perPageSelect.addEventListener('change', function () {
            window.location.href = this.value;
        });
    }
})();

// --- Modal ---
let currentDeleteId = 0;

function openNoteModal(etudiantId, moduleId, typeId, noteId, valeur, etudiantNom, moduleNom, typeNom, sur) {
    document.getElementById('modalEtudiantId').value = etudiantId;
    document.getElementById('modalModuleId').value = moduleId;
    document.getElementById('modalTypeId').value = typeId;
    document.getElementById('modalValeur').value = valeur;
    document.getElementById('modalValeur').max = sur;
    document.getElementById('modalValeur').placeholder = `Note sur ${sur}`;

    document.getElementById('modalContext').innerHTML =
        `<strong>${etudiantNom}</strong><br><span class="text-gray-500">${moduleNom} — ${typeNom} / ${sur}</span>`;
    document.getElementById('modalLabel').textContent = `Note sur ${sur}`;
    document.getElementById('modalTitle').textContent = noteId ? '<?= t('edit') ?>' : '<?= t('add_note_btn') ?>';

    currentDeleteId = noteId || 0;
    document.getElementById('modalDeleteBtn').classList.toggle('hidden', !noteId);

    document.getElementById('modalError').classList.add('hidden');
    document.getElementById('noteModal').classList.remove('hidden');
    document.getElementById('modalValeur').focus();
    document.getElementById('modalValeur').select();
}

function closeNoteModal() {
    document.getElementById('noteModal').classList.add('hidden');
    currentDeleteId = 0;
}

async function submitNote(e) {
    e.preventDefault();
    const data = new URLSearchParams();
    data.append('action', 'save');
    data.append('etudiant_id', document.getElementById('modalEtudiantId').value);
    data.append('module_id', document.getElementById('modalModuleId').value);
    data.append('note_type_id', document.getElementById('modalTypeId').value);
    data.append('valeur', document.getElementById('modalValeur').value);

    try {
        const res = await fetch(window.location.pathname, { method: 'POST', body: data });
        const json = await res.json();

        if (json.ok) {
            closeNoteModal();
            window.location.reload();
        } else {
            const err = document.getElementById('modalError');
            err.textContent = json.error;
            err.classList.remove('hidden');
        }
    } catch (err) {
        const e2 = document.getElementById('modalError');
        e2.textContent = 'Erreur réseau';
        e2.classList.remove('hidden');
    }
}

function deleteNote() {
    if (!currentDeleteId) return;
    if (!confirm('<?= t('confirm_delete') ?>')) return;
    window.location.href = '?delete_note=' + currentDeleteId;
}
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>