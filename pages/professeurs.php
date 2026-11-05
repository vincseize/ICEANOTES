<?php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
requireAdmin();

$pdo = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['add', 'edit'], true)) {
        $email  = trim($_POST['email'] ?? '');
        $nom    = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $mdp    = $_POST['password'] ?? '';
        $actif  = isset($_POST['actif']) ? 1 : 0;
        $id     = (int)($_POST['id'] ?? 0);

        if ($email && $nom && $prenom) {
            try {
                $typeId = (int)$pdo->query("SELECT id FROM users_types WHERE label='professeur'")->fetchColumn();
                if ($action === 'add') {
                    $pass = $mdp !== '' ? $mdp : 'icea';
                    $pdo->prepare("INSERT INTO users (email, password, nom, prenom, user_type_id, actif) VALUES (?, ?, ?, ?, ?, ?)")
                        ->execute([$email, hashPassword($pass), $nom, $prenom, $typeId, $actif]);
                    redirect('/ICEANOTES/pages/professeurs.php?ok=add');
                } else {
                    if ($mdp !== '') {
                        $pdo->prepare("UPDATE users SET email=?, nom=?, prenom=?, password=?, actif=? WHERE id=?")
                            ->execute([$email, $nom, $prenom, hashPassword($mdp), $actif, $id]);
                    } else {
                        $pdo->prepare("UPDATE users SET email=?, nom=?, prenom=?, actif=? WHERE id=?")
                            ->execute([$email, $nom, $prenom, $actif, $id]);
                    }
                    redirect('/ICEANOTES/pages/professeurs.php?ok=edit');
                }
            } catch (PDOException $e) { $msg = "Cet email existe déjà."; }
        }
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("SELECT t.label FROM users u JOIN users_types t ON t.id=u.user_type_id WHERE u.id=?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() === 'admin') {
        $msg = t('cannot_delete') . " (admin)";
    } else {
        $pdo->prepare("UPDATE users SET actif = 1 - actif WHERE id = ?")->execute([$id]);
        redirect('/ICEANOTES/pages/professeurs.php?ok=toggle');
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT t.label FROM users u JOIN users_types t ON t.id=u.user_type_id WHERE u.id=?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() === 'admin') {
        $msg = t('cannot_delete') . " (admin)";
    } else {
        try {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            redirect('/ICEANOTES/pages/professeurs.php?ok=delete');
        } catch (PDOException $e) { $msg = "Impossible : matières assignées."; }
    }
}

$editRow = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRow = $stmt->fetch() ?: null;
}

[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(20));
$sortable = ['nom' => 'u.nom, u.prenom', 'email' => 'u.email', 'actif' => 'u.actif'];
$orderBy = $sortable[$sort] ?? 'u.nom, u.prenom';
if ($sort && isset($sortable[$sort])) $orderBy .= " $dir";

$where = ['1=1']; $params = [];
if ($search !== '') {
    $where[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users u JOIN users_types t ON t.id=u.user_type_id WHERE $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT u.*, t.label AS type_label,
           (SELECT COUNT(*) FROM matieres m WHERE m.professeur_id = u.id) AS nb_mat
    FROM users u JOIN users_types t ON t.id=u.user_type_id
    WHERE $whereSql ORDER BY $orderBy LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$users = $stmt->fetchAll();

require_once BASE_PATH . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= t('professeurs_title') ?></h1>
        <p class="text-sm text-gray-500"><?= t('professeurs_count', ['n' => $total]) ?></p>
    </div>
</div>

<?php if ($msg): ?><div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4"><?= e($msg) ?></div><?php endif; ?>
<?php if (isset($_GET['ok'])): ?><div class="bg-green-50 text-green-700 text-sm p-3 rounded-lg mb-4"><?= t('ok_' . $_GET['ok']) ?></div><?php endif; ?>

<form method="POST" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 grid sm:grid-cols-12 gap-3 items-start">
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= $editRow['id'] ?>"><?php endif; ?>
    <input type="text" name="prenom" required placeholder="<?= e(t('prof_prenom')) ?>" value="<?= $editRow ? e($editRow['prenom']) : '' ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">
    <input type="text" name="nom" required placeholder="<?= e(t('prof_nom')) ?>" value="<?= $editRow ? e($editRow['nom']) : '' ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">
    <input type="email" name="email" required placeholder="<?= e(t('prof_email')) ?>" value="<?= $editRow ? e($editRow['email']) : '' ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-3">
    <input type="text" name="password" placeholder="<?= e(t('prof_password')) ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">
    <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none px-3 py-2 sm:col-span-2">
        <input type="checkbox" name="actif" value="1" <?= (!$editRow || $editRow['actif']) ? 'checked' : '' ?>
               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <?= t('active') ?>
    </label>
    <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg sm:col-span-1">
        <?= $editRow ? t('save') : t('add') ?>
    </button>
    <?php if ($editRow): ?>
        <a href="/ICEANOTES/pages/professeurs.php" class="text-sm text-gray-500 px-3 py-2 hover:underline text-center sm:col-span-12"><?= t('cancel') ?></a>
    <?php endif; ?>
</form>

<form method="GET" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('search')) ?>"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <select name="per_page" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <?php foreach ([10, 20, 50, 100] as $n): ?>
            <option value="<?= $n ?>" <?= $perPage == $n ? 'selected' : '' ?>><?= t('per_page', ['n'=>$n]) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="bg-gray-800 hover:bg-gray-900 text-white text-sm px-4 py-2 rounded-lg"><?= t('filter') ?></button>
    <a href="<?= e(resetLink('professeurs.php', $perPage)) ?>" class="text-sm text-gray-500 px-3 py-2 hover:underline self-center"><?= t('reset') ?></a>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="text-left px-4 py-3"><?= sortLink('nom', t('prof_nom'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('email', t('prof_email'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= t('matieres_count') ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('actif', t('active'), $sort, $dir) ?></th>
                    <th class="text-right px-4 py-3"><?= t('actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($users)): ?>
                <tr><td colspan="5" class="text-center text-gray-400 py-8">—</td></tr>
            <?php else: foreach ($users as $u): ?>
                <?php $isUserAdmin = $u['type_label'] === 'admin'; ?>
                <tr class="hover:bg-gray-50 transition <?= $u['actif'] ? '' : 'opacity-50 bg-gray-50' ?>">
                    <td class="px-4 py-3 font-medium">
                        <?= e($u['prenom'] . ' ' . $u['nom']) ?>
                        <?php if ($isUserAdmin): ?>
                            <span class="ml-1 inline-block px-1.5 py-0.5 rounded text-xs bg-purple-100 text-purple-700"><?= t('admin_badge') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-gray-600"><?= e($u['email']) ?></td>
                    <td class="px-4 py-3 text-center"><?= $u['nb_mat'] ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($isUserAdmin): ?>
                            <span class="inline-block px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700"><?= t('active') ?></span>
                        <?php else: ?>
                            <a href="?toggle=<?= $u['id'] ?>" title="<?= e($u['actif'] ? t('deactivate') : t('activate')) ?>"
                               class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition
                                      <?= $u['actif'] ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-200 text-gray-600 hover:bg-gray-300' ?>">
                                <?php if ($u['actif']): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    <?= t('active') ?>
                                <?php else: ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    <?= t('inactive') ?>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?= actionButtons(
                            '/ICEANOTES/pages/professeurs.php?edit=' . $u['id'],
                            '/ICEANOTES/pages/professeurs.php?delete=' . $u['id'],
                            '/ICEANOTES/pages/professeurs.php?toggle=' . $u['id'],
                            (bool)$u['actif'],
                            !$isUserAdmin,
                            !$isUserAdmin
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