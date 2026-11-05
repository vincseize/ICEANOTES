<?php
// pages/note_types.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
requireAdmin();

$pdo = getDB();
$msg = '';

// --- Ajout / Édition ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['add', 'edit'], true)) {
        $nom   = trim($_POST['nom'] ?? '');
        $sur   = (float)($_POST['sur'] ?? 20);
        $coef  = (float)($_POST['coef'] ?? 1);
        $ordre = (int)($_POST['ordre'] ?? 0);
        $actif = isset($_POST['actif']) ? 1 : 0;
        $id    = (int)($_POST['id'] ?? 0);

        if ($nom !== '' && $sur > 0 && $coef > 0) {
            try {
                if ($action === 'add') {
                    $pdo->prepare("INSERT INTO note_types (nom, sur, coef, ordre, actif) VALUES (?, ?, ?, ?, ?)")
                        ->execute([$nom, $sur, $coef, $ordre, $actif]);
                    redirect('/ICEANOTES/pages/note_types.php?ok=add');
                } else {
                    $pdo->prepare("UPDATE note_types SET nom=?, sur=?, coef=?, ordre=?, actif=? WHERE id=?")
                        ->execute([$nom, $sur, $coef, $ordre, $actif, $id]);
                    redirect('/ICEANOTES/pages/note_types.php?ok=edit');
                }
            } catch (PDOException $e) {
                $msg = "Ce type de note existe déjà.";
            }
        }
    }
}

// --- Toggle actif ---
if (isset($_GET['toggle'])) {
    $pdo->prepare("UPDATE note_types SET actif = 1 - actif WHERE id = ?")->execute([(int)$_GET['toggle']]);
    redirect('/ICEANOTES/pages/note_types.php?ok=toggle');
}

// --- Suppression ---
if (isset($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM note_types WHERE id = ?")->execute([(int)$_GET['delete']]);
        redirect('/ICEANOTES/pages/note_types.php?ok=delete');
    } catch (PDOException $e) {
        $msg = "Impossible : des notes existent pour ce type.";
    }
}

// --- Édition ---
$editRow = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM note_types WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRow = $stmt->fetch() ?: null;
}

// --- Liste ---
[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(20));

$sortable = ['nom' => 'nom', 'ordre' => 'ordre', 'sur' => 'sur', 'coef' => 'coef', 'actif' => 'actif'];
$orderBy = $sortable[$sort] ?? 'ordre, nom';
if ($sort && isset($sortable[$sort])) $orderBy .= " $dir";

$where  = ['1=1'];
$params = [];
if ($search !== '') {
    $where[]  = "nom LIKE ?";
    $params[] = "%$search%";
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM note_types WHERE $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT t.*,
           (SELECT COUNT(*) FROM notes n WHERE n.note_type_id = t.id) AS nb_notes
    FROM note_types t
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$types = $stmt->fetchAll();

require_once BASE_PATH . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= t('note_types_title') ?></h1>
        <p class="text-sm text-gray-500"><?= t('note_types_count', ['n' => $total]) ?></p>
    </div>
</div>

<?php if ($msg): ?><div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4"><?= e($msg) ?></div><?php endif; ?>
<?php if (isset($_GET['ok'])): ?><div class="bg-green-50 text-green-700 text-sm p-3 rounded-lg mb-4"><?= t('ok_' . $_GET['ok']) ?></div><?php endif; ?>

<form method="POST" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 grid sm:grid-cols-12 gap-3 items-start">
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= $editRow['id'] ?>"><?php endif; ?>

    <input type="text" name="nom" required placeholder="<?= e(t('note_type_nom')) ?>"
           value="<?= $editRow ? e($editRow['nom']) : '' ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-3">

    <input type="number" name="sur" step="0.1" min="1" required placeholder="<?= e(t('note_type_sur')) ?>"
           value="<?= $editRow ? (float)$editRow['sur'] : 20 ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">

    <input type="number" name="coef" step="0.1" min="0.1" required placeholder="<?= e(t('note_type_coef')) ?>"
           value="<?= $editRow ? (float)$editRow['coef'] : 1 ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">

    <input type="number" name="ordre" min="0" placeholder="<?= e(t('note_type_ordre')) ?>"
           value="<?= $editRow ? (int)$editRow['ordre'] : 0 ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">

    <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none px-3 py-2 sm:col-span-1">
        <input type="checkbox" name="actif" value="1" <?= (!$editRow || $editRow['actif']) ? 'checked' : '' ?>
               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <?= t('active') ?>
    </label>

    <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg sm:col-span-2">
        <?= $editRow ? t('save') : t('add') ?>
    </button>

    <?php if ($editRow): ?>
        <a href="/ICEANOTES/pages/note_types.php" class="text-sm text-gray-500 px-3 py-2 hover:underline text-center sm:col-span-12"><?= t('cancel') ?></a>
    <?php endif; ?>
</form>

<form method="GET" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('search')) ?>"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <select name="per_page" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <?php foreach ([10, 20, 50, 100] as $n): ?>
            <option value="<?= $n ?>" <?= $perPage == $n ? 'selected' : '' ?>><?= t('per_page', ['n' => $n]) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="bg-gray-800 hover:bg-gray-900 text-white text-sm px-4 py-2 rounded-lg"><?= t('filter') ?></button>
    <a href="<?= e(resetLink('note_types.php', $perPage)) ?>" class="text-sm text-gray-500 px-3 py-2 hover:underline self-center"><?= t('reset') ?></a>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="text-left px-4 py-3"><?= sortLink('nom', t('note_type_nom'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('sur', t('note_type_sur'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('coef', t('note_type_coef'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('ordre', t('note_type_ordre'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= t('notes_count') ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('actif', t('active'), $sort, $dir) ?></th>
                    <th class="text-right px-4 py-3"><?= t('actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($types)): ?>
                <tr><td colspan="7" class="text-center text-gray-400 py-8">—</td></tr>
            <?php else: foreach ($types as $tp): ?>
                <tr class="hover:bg-gray-50 transition <?= $tp['actif'] ? '' : 'opacity-50 bg-gray-50' ?>">
                    <td class="px-4 py-3 font-medium"><?= e($tp['nom']) ?></td>
                    <td class="px-4 py-3 text-center">/ <?= (float)$tp['sur'] ?></td>
                    <td class="px-4 py-3 text-center">× <?= (float)$tp['coef'] ?></td>
                    <td class="px-4 py-3 text-center text-gray-600"><?= (int)$tp['ordre'] ?></td>
                    <td class="px-4 py-3 text-center"><?= $tp['nb_notes'] ?></td>
                    <td class="px-4 py-3 text-center">
                        <a href="?toggle=<?= $tp['id'] ?>" title="<?= e($tp['actif'] ? t('deactivate') : t('activate')) ?>"
                           class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition
                                  <?= $tp['actif'] ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-200 text-gray-600 hover:bg-gray-300' ?>">
                            <?= $tp['actif'] ? '✅ ' . t('active') : '❌ ' . t('inactive') ?>
                        </a>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?= actionButtons(
                            '/ICEANOTES/pages/note_types.php?edit=' . $tp['id'],
                            '/ICEANOTES/pages/note_types.php?delete=' . $tp['id'],
                            '/ICEANOTES/pages/note_types.php?toggle=' . $tp['id'],
                            (bool)$tp['actif'],
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