<?php
// pages/matieres.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
$me      = currentUser();
$isAdmin = isAdmin();
$myId    = (int)$me['id'];

$pdo = getDB();
$msg = '';

// --- Ajout / Édition ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['add', 'edit'], true)) {
        $nom       = trim($_POST['nom'] ?? '');
        $diplomeId = (int)($_POST['diplome_id'] ?? 0);
        $filiereId = (int)($_POST['filiere_id'] ?? 0);
        $profId    = $isAdmin ? (int)($_POST['professeur_id'] ?? 0) : $myId;
        $actif     = isset($_POST['actif']) ? 1 : 0;
        $id        = (int)($_POST['id'] ?? 0);

        if ($nom !== '' && $diplomeId > 0 && $filiereId > 0 && $profId > 0) {
            // Sécurité prof : on ne peut éditer que ses matières
            if (!$isAdmin && $action === 'edit') {
                $check = $pdo->prepare("SELECT professeur_id FROM matieres WHERE id = ?");
                $check->execute([$id]);
                if ((int)$check->fetchColumn() !== $myId) {
                    $msg = "Accès refusé.";
                }
            }
            if (!$msg) {
                if ($action === 'add') {
                    $pdo->prepare("
                        INSERT INTO matieres (nom, diplome_id, filiere_id, professeur_id, actif)
                        VALUES (?, ?, ?, ?, ?)
                    ")->execute([$nom, $diplomeId, $filiereId, $profId, $actif]);
                    redirect('/ICEANOTES/pages/matieres.php?ok=add');
                } else {
                    $pdo->prepare("
                        UPDATE matieres SET nom=?, diplome_id=?, filiere_id=?, professeur_id=?, actif=?
                        WHERE id=?
                    ")->execute([$nom, $diplomeId, $filiereId, $profId, $actif, $id]);
                    redirect('/ICEANOTES/pages/matieres.php?ok=edit');
                }
            }
        }
    }
}

// --- Toggle actif ---
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    if ($isAdmin) {
        $pdo->prepare("UPDATE matieres SET actif = 1 - actif WHERE id = ?")->execute([$id]);
    } else {
        $pdo->prepare("UPDATE matieres SET actif = 1 - actif WHERE id = ? AND professeur_id = ?")->execute([$id, $myId]);
    }
    redirect('/ICEANOTES/pages/matieres.php?ok=toggle');
}

// --- Suppression ---
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        if ($isAdmin) {
            $pdo->prepare("DELETE FROM matieres WHERE id = ?")->execute([$id]);
        } else {
            $pdo->prepare("DELETE FROM matieres WHERE id = ? AND professeur_id = ?")->execute([$id, $myId]);
        }
        redirect('/ICEANOTES/pages/matieres.php?ok=delete');
    } catch (PDOException $e) {
        $msg = "Impossible : des notes existent pour cette matière.";
    }
}

// --- Édition ? ---
$editRow = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM matieres WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRow = $stmt->fetch() ?: null;
    if ($editRow && !$isAdmin && (int)$editRow['professeur_id'] !== $myId) {
        $editRow = null;
        $msg = "Accès refusé.";
    }
}

// --- Liste ---
[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(20));

$sortable = [
    'nom'     => 'm.nom',
    'diplome' => 'd.ordre, d.nom',
    'filiere' => 'f.nom',
    'prof'    => 'u.nom, u.prenom',
    'actif'   => 'm.actif',
];
$orderBy = $sortable[$sort] ?? 'd.ordre, f.nom, m.nom';
if ($sort && isset($sortable[$sort])) $orderBy .= " $dir";

$where  = ['1=1'];
$params = [];
if (!$isAdmin) {
    $where[]  = "m.professeur_id = ?";
    $params[] = $myId;
}
if ($search !== '') {
    $where[] = "(m.nom LIKE ? OR d.nom LIKE ? OR f.nom LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM matieres m
    JOIN diplomes d ON d.id = m.diplome_id
    JOIN filieres f ON f.id = m.filiere_id
    JOIN users u    ON u.id = m.professeur_id
    WHERE $whereSql
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT m.*, d.nom AS diplome, f.nom AS filiere,
           u.nom AS prof_nom, u.prenom AS prof_prenom
    FROM matieres m
    JOIN diplomes d ON d.id = m.diplome_id
    JOIN filieres f ON f.id = m.filiere_id
    JOIN users u    ON u.id = m.professeur_id
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$matieres = $stmt->fetchAll();

$diplomes  = $pdo->query("SELECT * FROM diplomes ORDER BY ordre, nom")->fetchAll();
$filieres  = $pdo->query("SELECT * FROM filieres ORDER BY nom")->fetchAll();
$profs     = $isAdmin ? $pdo->query("
    SELECT u.* FROM users u
    JOIN users_types t ON t.id = u.user_type_id
    WHERE t.label IN ('professeur','admin') AND u.actif = 1
    ORDER BY u.nom, u.prenom
")->fetchAll() : [];

require_once BASE_PATH . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            <?= $isAdmin ? t('matieres_title') : t('matieres_title_prof') ?>
        </h1>
        <p class="text-sm text-gray-500"><?= t('matieres_count', ['n' => $total]) ?></p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4"><?= e($msg) ?></div>
<?php endif; ?>
<?php if (isset($_GET['ok'])): ?>
    <div class="bg-green-50 text-green-700 text-sm p-3 rounded-lg mb-4"><?= t('ok_' . $_GET['ok']) ?></div>
<?php endif; ?>

<!-- Formulaire ajout / édition -->
<form method="POST" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 grid sm:grid-cols-12 gap-3 items-start">
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= $editRow['id'] ?>"><?php endif; ?>

    <input type="text" name="nom" required placeholder="<?= e(t('matiere_nom')) ?>"
           value="<?= $editRow ? e($editRow['nom']) : '' ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-4">

    <select name="diplome_id" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">
        <option value="">— <?= t('diplome') ?> —</option>
        <?php foreach ($diplomes as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $editRow && $editRow['diplome_id'] == $d['id'] ? 'selected' : '' ?>>
                <?= e($d['nom']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="filiere_id" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">
        <option value="">— <?= t('filiere') ?> —</option>
        <?php foreach ($filieres as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $editRow && $editRow['filiere_id'] == $f['id'] ? 'selected' : '' ?>>
                <?= e($f['nom']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <?php if ($isAdmin): ?>
        <select name="professeur_id" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">
            <option value="">— <?= t('professeur') ?> —</option>
            <?php foreach ($profs as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $editRow && $editRow['professeur_id'] == $p['id'] ? 'selected' : '' ?>>
                    <?= e($p['prenom'] . ' ' . $p['nom']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php else: ?>
        <div class="px-3 py-2 border border-gray-200 bg-gray-50 rounded-lg text-sm text-gray-600 sm:col-span-2">
            <strong><?= e($me['prenom'] . ' ' . $me['nom']) ?></strong>
        </div>
    <?php endif; ?>

    <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none px-3 py-2 sm:col-span-1">
        <input type="checkbox" name="actif" value="1"
               <?= (!$editRow || $editRow['actif']) ? 'checked' : '' ?>
               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <?= t('active') ?>
    </label>

    <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg sm:col-span-1">
        <?= $editRow ? t('save') : t('add') ?>
    </button>

    <?php if ($editRow): ?>
        <a href="/ICEANOTES/pages/matieres.php" class="text-sm text-gray-500 px-3 py-2 hover:underline self-center text-center sm:col-span-12">
            <?= t('cancel') ?>
        </a>
    <?php endif; ?>
</form>

<!-- Recherche -->
<form method="GET" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('search')) ?>"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <select name="per_page" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <?php foreach ([10, 20, 50, 100] as $n): ?>
            <option value="<?= $n ?>" <?= $perPage == $n ? 'selected' : '' ?>><?= t('per_page', ['n'=>$n]) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="bg-gray-800 hover:bg-gray-900 text-white text-sm px-4 py-2 rounded-lg"><?= t('filter') ?></button>
    <a href="<?= e(resetLink('matieres.php', $perPage)) ?>" class="text-sm text-gray-500 px-3 py-2 hover:underline self-center"><?= t('reset') ?></a>
</form>

<!-- Tableau -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="text-left px-4 py-3"><?= sortLink('nom', t('matiere_nom'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('diplome', t('diplome'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('filiere', t('filiere'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('prof', t('professeur'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('actif', t('active'), $sort, $dir) ?></th>
                    <th class="text-right px-4 py-3"><?= t('actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($matieres)): ?>
                <tr><td colspan="6" class="text-center text-gray-400 py-8">—</td></tr>
            <?php else: foreach ($matieres as $m): ?>
                <tr class="hover:bg-gray-50 transition <?= $m['actif'] ? '' : 'opacity-50 bg-gray-50' ?>">
                    <td class="px-4 py-3 font-medium"><?= e($m['nom']) ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-block px-2 py-0.5 rounded text-xs bg-indigo-100 text-indigo-700"><?= e($m['diplome']) ?></span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-block px-2 py-0.5 rounded text-xs bg-purple-100 text-purple-700"><?= e($m['filiere']) ?></span>
                    </td>
                    <td class="px-4 py-3 text-gray-600"><?= e($m['prof_prenom'] . ' ' . $m['prof_nom']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <a href="?toggle=<?= $m['id'] ?>"
                           title="<?= e($m['actif'] ? t('deactivate') : t('activate')) ?>"
                           class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition
                                  <?= $m['actif']
                                        ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                        : 'bg-gray-200 text-gray-600 hover:bg-gray-300' ?>">
                            <?php if ($m['actif']): ?>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                <?= t('active') ?>
                            <?php else: ?>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                <?= t('inactive') ?>
                            <?php endif; ?>
                        </a>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?= actionButtons(
                            '/ICEANOTES/pages/matieres.php?edit='   . $m['id'],
                            '/ICEANOTES/pages/matieres.php?delete=' . $m['id'],
                            '/ICEANOTES/pages/matieres.php?toggle=' . $m['id'],
                            (bool)$m['actif'],
                            true,
                            false
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= renderPagination($page, $perPage, $total) ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>