<?php
// pages/promotions.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
requireSuperviseur();

$pdo = getDB();
$msg = '';

// --- Ajout / Édition ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['add', 'edit'], true)) {
        $nom       = trim($_POST['nom'] ?? '');
        $ordre     = (int)($_POST['ordre'] ?? 0);
        $promo     = trim($_POST['promo'] ?? '');
        $annee     = trim($_POST['annee'] ?? '');
        $filiereId = (int)($_POST['filiere_id'] ?? 0);
        $actif     = isset($_POST['actif']) ? 1 : 0;
        $id        = (int)($_POST['id'] ?? 0);

        $promo = $promo !== '' ? $promo : null;

        if ($nom !== '' && $annee !== '' && $filiereId > 0) {
            // Vérifier que la filière est active
            $checkFiliere = $pdo->prepare("SELECT actif FROM filieres WHERE id = ?");
            $checkFiliere->execute([$filiereId]);
            if (!$checkFiliere->fetchColumn()) {
                $msg = "La filière sélectionnée est inactive.";
            }

            if (!$msg) {
                try {
                    if ($action === 'add') {
                        $pdo->prepare("
                            INSERT INTO promotions (nom, ordre, promo, annee, filiere_id, actif)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ")->execute([$nom, $ordre, $promo, $annee, $filiereId, $actif]);
                        redirect('/ICEANOTES/pages/promotions.php?ok=add');
                    } else {
                        $pdo->prepare("
                            UPDATE promotions SET nom=?, ordre=?, promo=?, annee=?, filiere_id=?, actif=?
                            WHERE id=?
                        ")->execute([$nom, $ordre, $promo, $annee, $filiereId, $actif, $id]);
                        redirect('/ICEANOTES/pages/promotions.php?ok=edit');
                    }
                } catch (PDOException $e) {
                    $msg = "Cette promotion existe déjà (même niveau + promo + année + filière).";
                }
            }
        }
    }
}

// --- Toggle actif ---
if (isset($_GET['toggle'])) {
    $pdo->prepare("UPDATE promotions SET actif = 1 - actif WHERE id = ?")->execute([(int)$_GET['toggle']]);
    redirect('/ICEANOTES/pages/promotions.php?ok=toggle');
}

// --- Suppression ---
if (isset($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM promotions WHERE id = ?")->execute([(int)$_GET['delete']]);
        redirect('/ICEANOTES/pages/promotions.php?ok=delete');
    } catch (PDOException $e) {
        $msg = "Impossible : des étudiants sont liés à cette promotion.";
    }
}

// --- Édition ---
$editRow = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM promotions WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRow = $stmt->fetch() ?: null;
}

// --- Liste ---
[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(20));

$sortable = [
    'nom'     => 'p.nom',
    'ordre'   => 'p.ordre',
    'promo'   => 'p.promo',
    'annee'   => 'p.annee',
    'filiere' => 'f.nom',
    'actif'   => 'p.actif',
];
$orderBy = $sortable[$sort] ?? 'p.ordre, f.nom, p.annee, p.promo';
if ($sort && isset($sortable[$sort])) $orderBy .= " $dir";

$where  = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = "(p.nom LIKE ? OR p.promo LIKE ? OR p.annee LIKE ? OR f.nom LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM promotions p
    JOIN filieres f ON f.id = p.filiere_id
    WHERE $whereSql
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT p.*, f.nom AS filiere, f.actif AS filiere_actif,
           (SELECT COUNT(*) FROM etudiants e WHERE e.promotion_id = p.id) AS nb_etudiants,
           (SELECT COUNT(*) FROM modules m WHERE m.filiere_id = p.filiere_id AND m.niveau = p.nom) AS nb_modules
    FROM promotions p
    JOIN filieres f ON f.id = p.filiere_id
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$promotions = $stmt->fetchAll();

// Filières actives uniquement (pour le select)
$filieres = $pdo->query("SELECT * FROM filieres WHERE actif = 1 ORDER BY nom")->fetchAll();

require_once BASE_PATH . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= t('promotions_title') ?></h1>
        <p class="text-sm text-gray-500"><?= t('promotions_count', ['n' => $total]) ?></p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4"><?= e($msg) ?></div>
<?php endif; ?>
<?php if (isset($_GET['ok'])): ?>
    <div class="bg-green-50 text-green-700 text-sm p-3 rounded-lg mb-4"><?= t('ok_' . $_GET['ok']) ?></div>
<?php endif; ?>

<form method="POST" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 grid sm:grid-cols-12 gap-3 items-start">
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= $editRow['id'] ?>"><?php endif; ?>

    <input type="text" name="nom" required placeholder="<?= e(t('promotion_nom')) ?>"
           value="<?= $editRow ? e($editRow['nom']) : '' ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">

    <input type="number" name="ordre" min="0" placeholder="<?= e(t('promotion_ordre')) ?>"
           value="<?= $editRow ? (int)$editRow['ordre'] : 0 ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-1">

    <input type="text" name="promo" placeholder="<?= e(t('promotion_promo')) ?>"
           value="<?= $editRow ? e($editRow['promo']) : '' ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-3">

    <input type="text" name="annee" required placeholder="<?= e(t('promotion_annee')) ?>"
           value="<?= $editRow ? e($editRow['annee']) : '2026-2027' ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">

    <select name="filiere_id" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">
        <option value="">— <?= t('filiere') ?> —</option>
        <?php foreach ($filieres as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $editRow && $editRow['filiere_id'] == $f['id'] ? 'selected' : '' ?>>
                <?= e($f['nom']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none px-3 py-2 sm:col-span-1">
        <input type="checkbox" name="actif" value="1" <?= (!$editRow || $editRow['actif']) ? 'checked' : '' ?>
               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <?= t('active') ?>
    </label>

    <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg sm:col-span-1">
        <?= $editRow ? t('save') : t('add') ?>
    </button>

    <?php if ($editRow): ?>
        <a href="/ICEANOTES/pages/promotions.php" class="text-sm text-gray-500 px-3 py-2 hover:underline text-center sm:col-span-12"><?= t('cancel') ?></a>
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
    <a href="<?= e(resetLink('promotions.php', $perPage)) ?>" class="text-sm text-gray-500 px-3 py-2 hover:underline self-center"><?= t('reset') ?></a>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="text-left px-4 py-3"><?= sortLink('nom', t('promotion_nom'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('ordre', t('promotion_ordre'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('promo', t('promo_label'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('annee', t('annee'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('filiere', t('filiere'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= t('etudiants_count') ?></th>
                    <th class="text-center px-4 py-3"><?= t('modules_count') ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('actif', t('active'), $sort, $dir) ?></th>
                    <th class="text-right px-4 py-3"><?= t('actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($promotions)): ?>
                <tr><td colspan="9" class="text-center text-gray-400 py-8">—</td></tr>
            <?php else: foreach ($promotions as $p): ?>
                <tr class="hover:bg-gray-50 transition <?= $p['actif'] ? '' : 'opacity-50 bg-gray-50' ?>">
                    <td class="px-4 py-3 font-medium"><?= e($p['nom']) ?></td>
                    <td class="px-4 py-3 text-center text-gray-600"><?= (int)$p['ordre'] ?></td>
                    <td class="px-4 py-3">
                        <?php if ($p['promo']): ?>
                            <?= e($p['promo']) ?>
                        <?php else: ?>
                            <span class="text-gray-400 italic text-xs"><?= t('promo') ?> —</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3"><?= e($p['annee']) ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-block px-2 py-0.5 rounded text-xs <?= $p['filiere_actif'] ? 'bg-purple-100 text-purple-700' : 'bg-gray-200 text-gray-500 line-through' ?>">
                            <?= e($p['filiere']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center"><?= $p['nb_etudiants'] ?></td>
                    <td class="px-4 py-3 text-center"><?= $p['nb_modules'] ?></td>
                    <td class="px-4 py-3 text-center">
                        <a href="?toggle=<?= $p['id'] ?>" title="<?= e($p['actif'] ? t('deactivate') : t('activate')) ?>"
                           class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition
                                  <?= $p['actif'] ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-200 text-gray-600 hover:bg-gray-300' ?>">
                            <?php if ($p['actif']): ?>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <?= t('active') ?>
                            <?php else: ?>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                <?= t('inactive') ?>
                            <?php endif; ?>
                        </a>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?= actionButtons(
                            '/ICEANOTES/pages/promotions.php?edit=' . $p['id'],
                            '/ICEANOTES/pages/promotions.php?delete=' . $p['id'],
                            '/ICEANOTES/pages/promotions.php?toggle=' . $p['id'],
                            (bool)$p['actif'],
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