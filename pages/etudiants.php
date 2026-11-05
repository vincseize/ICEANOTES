<?php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
$isAdmin = isAdmin();

$pdo = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['add','edit'], true)) {
        $nom    = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $promo  = (int)($_POST['promo_id'] ?? 0);
        $id     = (int)($_POST['id'] ?? 0);
        if ($nom !== '' && $prenom !== '' && $promo > 0) {
            if ($action === 'add') {
                $pdo->prepare("INSERT INTO etudiants (nom,prenom,promo_id) VALUES (?,?,?)")
                    ->execute([$nom, $prenom, $promo]);
                redirect('/ICEANOTES/pages/etudiants.php?ok=add');
            } else {
                $pdo->prepare("UPDATE etudiants SET nom=?,prenom=?,promo_id=? WHERE id=?")
                    ->execute([$nom, $prenom, $promo, $id]);
                redirect('/ICEANOTES/pages/etudiants.php?ok=edit');
            }
        }
    }
}

if (isset($_GET['toggle']) && $isAdmin) {
    $pdo->prepare("UPDATE etudiants SET actif = 1 - actif WHERE id = ?")->execute([(int)$_GET['toggle']]);
    redirect('/ICEANOTES/pages/etudiants.php?ok=toggle');
}
if (isset($_GET['delete']) && $isAdmin) {
    try {
        $pdo->prepare("DELETE FROM etudiants WHERE id = ?")->execute([(int)$_GET['delete']]);
        redirect('/ICEANOTES/pages/etudiants.php?ok=delete');
    } catch (PDOException $e) { $msg = "Impossible : notes liées."; }
}

$editRow = null;
if (isset($_GET['edit']) && $isAdmin) {
    $stmt = $pdo->prepare("SELECT * FROM etudiants WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRow = $stmt->fetch() ?: null;
}

[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(20));
$filterPromo = (int)($_GET['promo'] ?? 0);

$sortable = [
    'nom' => 'e.nom, e.prenom', 'prenom' => 'e.prenom, e.nom',
    'promo' => 'p.annee DESC, p.nom', 'filiere' => 'f.nom', 'diplome' => 'd.ordre, d.nom'
];
$orderBy = $sortable[$sort] ?? 'p.annee DESC, e.nom, e.prenom';
if ($sort && isset($sortable[$sort])) $orderBy .= " $dir";

$where = ['1=1']; $params = [];
if ($search !== '') {
    $where[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR p.nom LIKE ? OR f.nom LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like);
}
if ($filterPromo > 0) { $where[] = "e.promo_id = ?"; $params[] = $filterPromo; }
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM etudiants e
    JOIN promos p ON p.id=e.promo_id
    JOIN filieres f ON f.id=p.filiere_id
    JOIN diplomes d ON d.id=p.diplome_id
    WHERE $whereSql
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT e.*, p.nom AS promo_nom, p.annee AS promo_annee, f.nom AS filiere, d.nom AS diplome
    FROM etudiants e
    JOIN promos p ON p.id=e.promo_id
    JOIN filieres f ON f.id=p.filiere_id
    JOIN diplomes d ON d.id=p.diplome_id
    WHERE $whereSql ORDER BY $orderBy LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$etudiants = $stmt->fetchAll();

$promos = $pdo->query("
    SELECT p.*, f.nom AS filiere, d.nom AS diplome
    FROM promos p JOIN filieres f ON f.id=p.filiere_id JOIN diplomes d ON d.id=p.diplome_id
    ORDER BY p.annee DESC, f.nom, d.ordre
")->fetchAll();

require_once BASE_PATH . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= t('etudiants_title') ?></h1>
        <p class="text-sm text-gray-500"><?= t('etudiants_page_count', ['n'=>$total]) ?></p>
    </div>
</div>

<?php if ($msg): ?><div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4"><?= e($msg) ?></div><?php endif; ?>
<?php if (isset($_GET['ok'])): ?><div class="bg-green-50 text-green-700 text-sm p-3 rounded-lg mb-4"><?= t('ok_' . $_GET['ok']) ?></div><?php endif; ?>

<?php if ($isAdmin): ?>
<form method="POST" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 grid sm:grid-cols-4 gap-3">
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= $editRow['id'] ?>"><?php endif; ?>
    <input type="text" name="prenom" required placeholder="<?= e(t('etudiant_prenom')) ?>" value="<?= $editRow ? e($editRow['prenom']) : '' ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <input type="text" name="nom" required placeholder="<?= e(t('etudiant_nom')) ?>" value="<?= $editRow ? e($editRow['nom']) : '' ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <select name="promo_id" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value="">— <?= t('promo') ?> —</option>
        <?php foreach ($promos as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $editRow && $editRow['promo_id']==$p['id']?'selected':'' ?>>
                <?= e($p['nom'].' — '.$p['annee']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg"><?= $editRow ? t('save') : t('add') ?></button>
    <?php if ($editRow): ?>
        <a href="/ICEANOTES/pages/etudiants.php" class="text-sm text-gray-500 px-3 py-2 hover:underline self-center sm:col-span-4 text-center"><?= t('cancel') ?></a>
    <?php endif; ?>
</form>
<?php endif; ?>

<form method="GET" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('search')) ?>"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <select name="promo" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value="">— <?= t('promo') ?> —</option>
        <?php foreach ($promos as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $filterPromo==$p['id']?'selected':'' ?>><?= e($p['nom'].' ('.$p['annee'].')') ?></option>
        <?php endforeach; ?>
    </select>
    <select name="per_page" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <?php foreach ([10,20,50,100] as $n): ?>
            <option value="<?= $n ?>" <?= $perPage==$n?'selected':'' ?>><?= t('per_page',['n'=>$n]) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="bg-gray-800 hover:bg-gray-900 text-white text-sm px-4 py-2 rounded-lg"><?= t('filter') ?></button>
    <a href="<?= e(resetLink('etudiants.php', $perPage)) ?>" class="text-sm text-gray-500 px-3 py-2 hover:underline self-center"><?= t('reset') ?></a>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="text-left px-4 py-3"><?= sortLink('prenom', t('etudiant_prenom'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('nom', t('etudiant_nom'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('promo', t('promo'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('filiere', t('filiere'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('diplome', t('diplome'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= t('active') ?></th>
                    <th class="text-right px-4 py-3"><?= t('actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($etudiants)): ?>
                <tr><td colspan="7" class="text-center text-gray-400 py-8">—</td></tr>
            <?php else: foreach ($etudiants as $et): ?>
                <tr class="hover:bg-gray-50 <?= $et['actif']?'':'opacity-60' ?>">
                    <td class="px-4 py-3 font-medium"><?= e($et['prenom']) ?></td>
                    <td class="px-4 py-3"><?= e($et['nom']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= e($et['promo_nom'].' ('.$et['promo_annee'].')') ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= e($et['filiere']) ?></td>
                    <td class="px-4 py-3"><span class="inline-block px-2 py-0.5 rounded text-xs bg-indigo-100 text-indigo-700"><?= e($et['diplome']) ?></span></td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($et['actif']): ?>
                            <span class="inline-block px-2 py-0.5 rounded text-xs bg-green-100 text-green-700"><?= t('active') ?></span>
                        <?php else: ?>
                            <span class="inline-block px-2 py-0.5 rounded text-xs bg-gray-200 text-gray-600"><?= t('inactive') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?php if ($isAdmin): ?>
                            <?= actionButtons('?edit='.$et['id'], '?delete='.$et['id'], '?toggle='.$et['id'], (bool)$et['actif']) ?>
                        <?php else: ?>
                            <span class="text-xs text-gray-400">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= renderPagination($page, $perPage, $total) ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>