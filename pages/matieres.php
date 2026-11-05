<?php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
$me = currentUser();
$isAdmin = isAdmin();
$myId = (int)$me['id'];

$pdo = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['add','edit'], true)) {
        $nom       = trim($_POST['nom'] ?? '');
        $diplomeId = (int)($_POST['diplome_id'] ?? 0);
        $profId    = $isAdmin ? (int)($_POST['professeur_id'] ?? 0) : $myId;
        $id        = (int)($_POST['id'] ?? 0);

        if ($nom !== '' && $diplomeId > 0 && $profId > 0) {
            // Vérif : prof ne peut éditer que ses matières
            if (!$isAdmin && $action === 'edit') {
                $check = $pdo->prepare("SELECT professeur_id FROM matieres WHERE id = ?");
                $check->execute([$id]);
                if ((int)$check->fetchColumn() !== $myId) {
                    $msg = "Accès refusé.";
                }
            }
            if (!$msg) {
                if ($action === 'add') {
                    $pdo->prepare("INSERT INTO matieres (nom,diplome_id,professeur_id) VALUES (?,?,?)")
                        ->execute([$nom, $diplomeId, $profId]);
                    redirect('/ICEANOTES/pages/matieres.php?ok=add');
                } else {
                    $pdo->prepare("UPDATE matieres SET nom=?,diplome_id=?,professeur_id=? WHERE id=?")
                        ->execute([$nom, $diplomeId, $profId, $id]);
                    redirect('/ICEANOTES/pages/matieres.php?ok=edit');
                }
            }
        }
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    if ($isAdmin) {
        $pdo->prepare("UPDATE matieres SET actif = 1 - actif WHERE id = ?")->execute([$id]);
    } else {
        $pdo->prepare("UPDATE matieres SET actif = 1 - actif WHERE id = ? AND professeur_id = ?")->execute([$id, $myId]);
    }
    redirect('/ICEANOTES/pages/matieres.php?ok=toggle');
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        if ($isAdmin) {
            $pdo->prepare("DELETE FROM matieres WHERE id = ?")->execute([$id]);
        } else {
            $pdo->prepare("DELETE FROM matieres WHERE id = ? AND professeur_id = ?")->execute([$id, $myId]);
        }
        redirect('/ICEANOTES/pages/matieres.php?ok=delete');
    } catch (PDOException $e) { $msg = "Impossible : des notes existent."; }
}

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

[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(20));
$sortable = ['nom'=>'m.nom', 'diplome'=>'d.ordre, d.nom', 'prof'=>'u.nom, u.prenom'];
$orderBy = $sortable[$sort] ?? 'd.ordre, m.nom';
if ($sort && isset($sortable[$sort])) $orderBy .= " $dir";

$where = ['1=1']; $params = [];
if (!$isAdmin) { $where[] = "m.professeur_id = ?"; $params[] = $myId; }
if ($search !== '') {
    $where[] = "(m.nom LIKE ? OR d.nom LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM matieres m
    JOIN diplomes d ON d.id=m.diplome_id
    JOIN users u ON u.id=m.professeur_id
    WHERE $whereSql
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT m.*, d.nom AS diplome, u.nom AS prof_nom, u.prenom AS prof_prenom
    FROM matieres m
    JOIN diplomes d ON d.id=m.diplome_id
    JOIN users u ON u.id=m.professeur_id
    WHERE $whereSql ORDER BY $orderBy LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$matieres = $stmt->fetchAll();

$diplomes = $pdo->query("SELECT * FROM diplomes ORDER BY ordre, nom")->fetchAll();
$profs = $isAdmin ? $pdo->query("
    SELECT u.* FROM users u JOIN users_types t ON t.id=u.user_type_id
    WHERE t.label IN ('professeur','admin') ORDER BY u.nom, u.prenom
")->fetchAll() : [];

require_once BASE_PATH . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= $isAdmin ? t('matieres_title') : t('matieres_title_prof') ?></h1>
        <p class="text-sm text-gray-500"><?= t('matieres_count', ['n'=>$total]) ?></p>
    </div>
</div>

<?php if ($msg): ?><div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4"><?= e($msg) ?></div><?php endif; ?>
<?php if (isset($_GET['ok'])): ?><div class="bg-green-50 text-green-700 text-sm p-3 rounded-lg mb-4"><?= t('ok_' . $_GET['ok']) ?></div><?php endif; ?>

<form method="POST" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 grid sm:grid-cols-4 gap-3">
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= $editRow['id'] ?>"><?php endif; ?>
    <input type="text" name="nom" required placeholder="<?= e(t('matiere_nom')) ?>" value="<?= $editRow ? e($editRow['nom']) : '' ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">
    <select name="diplome_id" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value="">— <?= t('diplome') ?> —</option>
        <?php foreach ($diplomes as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $editRow && $editRow['diplome_id']==$d['id']?'selected':'' ?>><?= e($d['nom']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($isAdmin): ?>
        <select name="professeur_id" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">— <?= t('professeur') ?> —</option>
            <?php foreach ($profs as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $editRow && $editRow['professeur_id']==$p['id']?'selected':'' ?>><?= e($p['prenom'].' '.$p['nom']) ?></option>
            <?php endforeach; ?>
        </select>
    <?php else: ?>
        <div class="px-3 py-2 border border-gray-200 bg-gray-50 rounded-lg text-sm text-gray-600">
            <strong><?= e($me['prenom'].' '.$me['nom']) ?></strong>
        </div>
    <?php endif; ?>
    <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg sm:col-span-4">
        <?= $editRow ? t('save') : t('add_matiere') ?>
    </button>
    <?php if ($editRow): ?>
        <a href="/ICEANOTES/pages/matieres.php" class="text-sm text-gray-500 px-3 py-2 hover:underline self-center sm:col-span-4 text-center"><?= t('cancel') ?></a>
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
    <a href="<?= e(resetLink('matieres.php', $perPage)) ?>" class="text-sm text-gray-500 px-3 py-2 hover:underline self-center"><?= t('reset') ?></a>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="text-left px-4 py-3"><?= sortLink('nom', t('matiere_nom'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('diplome', t('diplome'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('prof', t('professeur'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= t('active') ?></th>
                    <th class="text-right px-4 py-3"><?= t('actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($matieres)): ?>
                <tr><td colspan="5" class="text-center text-gray-400 py-8">—</td></tr>
            <?php else: foreach ($matieres as $m): ?>
                <tr class="hover:bg-gray-50 <?= $m['actif']?'':'opacity-60' ?>">
                    <td class="px-4 py-3 font-medium"><?= e($m['nom']) ?></td>
                    <td class="px-4 py-3"><span class="inline-block px-2 py-0.5 rounded text-xs bg-indigo-100 text-indigo-700"><?= e($m['diplome']) ?></span></td>
                    <td class="px-4 py-3 text-gray-600"><?= e($m['prof_prenom'].' '.$m['prof_nom']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($m['actif']): ?>
                            <span class="inline-block px-2 py-0.5 rounded text-xs bg-green-100 text-green-700"><?= t('active') ?></span>
                        <?php else: ?>
                            <span class="inline-block px-2 py-0.5 rounded text-xs bg-gray-200 text-gray-600"><?= t('inactive') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?= actionButtons('?edit='.$m['id'], '?delete='.$m['id'], '?toggle='.$m['id'], (bool)$m['actif']) ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= renderPagination($page, $perPage, $total) ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>