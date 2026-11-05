<?php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
requireAdmin();

$pdo = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['add','edit'], true)) {
        $nom   = trim($_POST['nom'] ?? '');
        $ordre = (int)($_POST['ordre'] ?? 0);
        $id    = (int)($_POST['id'] ?? 0);
        if ($nom !== '') {
            try {
                if ($action === 'add') {
                    $pdo->prepare("INSERT INTO diplomes (nom, ordre) VALUES (?, ?)")->execute([$nom, $ordre]);
                    redirect('/ICEANOTES/pages/diplomes.php?ok=add');
                } else {
                    $pdo->prepare("UPDATE diplomes SET nom=?, ordre=? WHERE id=?")->execute([$nom, $ordre, $id]);
                    redirect('/ICEANOTES/pages/diplomes.php?ok=edit');
                }
            } catch (PDOException $e) { $msg = "Ce diplôme existe déjà."; }
        }
    }
}

if (isset($_GET['toggle'])) {
    $pdo->prepare("UPDATE diplomes SET actif = 1 - actif WHERE id = ?")->execute([(int)$_GET['toggle']]);
    redirect('/ICEANOTES/pages/diplomes.php?ok=toggle');
}
if (isset($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM diplomes WHERE id = ?")->execute([(int)$_GET['delete']]);
        redirect('/ICEANOTES/pages/diplomes.php?ok=delete');
    } catch (PDOException $e) { $msg = "Impossible : promos ou matières liées."; }
}

$editRow = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM diplomes WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRow = $stmt->fetch() ?: null;
}

[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(20));
$sortable = ['nom'=>'d.nom', 'ordre'=>'d.ordre'];
$orderBy = $sortable[$sort] ?? 'd.ordre, d.nom';
if ($sort && isset($sortable[$sort])) $orderBy .= " $dir";

$where = ['1=1']; $params = [];
if ($search !== '') { $where[] = "d.nom LIKE ?"; $params[] = "%$search%"; }
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM diplomes d WHERE $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT d.*,
        (SELECT COUNT(*) FROM promos p WHERE p.diplome_id=d.id) AS nb_promos,
        (SELECT COUNT(*) FROM matieres m WHERE m.diplome_id=d.id) AS nb_matieres
    FROM diplomes d WHERE $whereSql ORDER BY $orderBy LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$diplomes = $stmt->fetchAll();

require_once BASE_PATH . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= t('diplomes_title') ?></h1>
        <p class="text-sm text-gray-500"><?= t('diplomes_count', ['n'=>$total]) ?></p>
    </div>
</div>

<?php if ($msg): ?><div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4"><?= e($msg) ?></div><?php endif; ?>
<?php if (isset($_GET['ok'])): ?><div class="bg-green-50 text-green-700 text-sm p-3 rounded-lg mb-4"><?= t('ok_' . $_GET['ok']) ?></div><?php endif; ?>

<form method="POST" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col sm:flex-row gap-3">
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= $editRow['id'] ?>"><?php endif; ?>
    <input type="text" name="nom" required placeholder="<?= e(t('diplome_nom')) ?>"
           value="<?= $editRow ? e($editRow['nom']) : '' ?>"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <input type="number" name="ordre" value="<?= $editRow ? (int)$editRow['ordre'] : 5 ?>" min="0"
           class="w-28 px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg">
        <?= $editRow ? t('save') : t('add') ?>
    </button>
    <?php if ($editRow): ?>
        <a href="/ICEANOTES/pages/diplomes.php" class="text-sm text-gray-500 px-3 py-2 hover:underline self-center"><?= t('cancel') ?></a>
    <?php endif; ?>
</form>

<form method="GET" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('search')) ?>"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <select name="per_page" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <?php foreach ([10,20,50,100] as $n): ?>
            <option value="<?= $n ?>" <?= $perPage==$n?'selected':'' ?>><?= t('per_page',['n'=>$n]) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="bg-gray-800 hover:bg-gray-900 text-white text-sm px-4 py-2 rounded-lg"><?= t('filter') ?></button>
    <a href="<?= e(resetLink('diplomes.php', $perPage)) ?>" class="text-sm text-gray-500 px-3 py-2 hover:underline self-center"><?= t('reset') ?></a>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="text-left px-4 py-3"><?= sortLink('nom', t('diplome_nom'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('ordre', t('ordre'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= t('promos_count') ?></th>
                    <th class="text-center px-4 py-3"><?= t('matieres_count') ?></th>
                    <th class="text-center px-4 py-3"><?= t('active') ?></th>
                    <th class="text-right px-4 py-3"><?= t('actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($diplomes)): ?>
                <tr><td colspan="6" class="text-center text-gray-400 py-8">—</td></tr>
            <?php else: foreach ($diplomes as $d): ?>
                <tr class="hover:bg-gray-50 <?= $d['actif']?'':'opacity-60' ?>">
                    <td class="px-4 py-3 font-medium"><?= e($d['nom']) ?></td>
                    <td class="px-4 py-3 text-center"><?= (int)$d['ordre'] ?></td>
                    <td class="px-4 py-3 text-center"><?= $d['nb_promos'] ?></td>
                    <td class="px-4 py-3 text-center"><?= $d['nb_matieres'] ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($d['actif']): ?>
                            <span class="inline-block px-2 py-0.5 rounded text-xs bg-green-100 text-green-700"><?= t('active') ?></span>
                        <?php else: ?>
                            <span class="inline-block px-2 py-0.5 rounded text-xs bg-gray-200 text-gray-600"><?= t('inactive') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?= actionButtons('?edit='.$d['id'], '?delete='.$d['id'], '?toggle='.$d['id'], (bool)$d['actif']) ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= renderPagination($page, $perPage, $total) ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>