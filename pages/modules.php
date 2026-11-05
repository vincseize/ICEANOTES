<?php
// pages/modules.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
$me = currentUser();
$isAdmin = isAdmin();
$isSuperviseur = isSuperviseur();
$myId = (int)$me['id'];

$pdo = getDB();
$msg = '';

// --- Ajout / Édition ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['add', 'edit'], true)) {
        $nom       = trim($_POST['nom'] ?? '');
        $niveau    = trim($_POST['niveau'] ?? '');
        $filiereId = (int)($_POST['filiere_id'] ?? 0);
        $profId    = $isSuperviseur ? (int)($_POST['professeur_id'] ?? 0) : $myId;
        $actif     = isset($_POST['actif']) ? 1 : 0;
        $id        = (int)($_POST['id'] ?? 0);

        if ($nom !== '' && $niveau !== '' && $filiereId > 0 && $profId > 0) {
            $checkFiliere = $pdo->prepare("SELECT actif FROM filieres WHERE id = ?");
            $checkFiliere->execute([$filiereId]);
            if (!$checkFiliere->fetchColumn()) {
                $msg = "La filière sélectionnée est inactive.";
            }

            if (!$msg && $isSuperviseur) {
                $checkProf = $pdo->prepare("SELECT actif FROM users WHERE id = ?");
                $checkProf->execute([$profId]);
                if (!$checkProf->fetchColumn()) {
                    $msg = "Le professeur sélectionné est inactif.";
                }
            }

            if (!$msg && !$isSuperviseur && $action === 'edit') {
                $check = $pdo->prepare("SELECT professeur_id FROM modules WHERE id = ?");
                $check->execute([$id]);
                if ((int)$check->fetchColumn() !== $myId) {
                    $msg = "Accès refusé.";
                }
            }

            if (!$msg) {
                if ($action === 'add') {
                    $pdo->prepare("
                        INSERT INTO modules (nom, niveau, filiere_id, professeur_id, actif)
                        VALUES (?, ?, ?, ?, ?)
                    ")->execute([$nom, $niveau, $filiereId, $profId, $actif]);
                    redirect('/ICEANOTES/pages/modules.php?ok=add');
                } else {
                    $pdo->prepare("
                        UPDATE modules SET nom=?, niveau=?, filiere_id=?, professeur_id=?, actif=?
                        WHERE id=?
                    ")->execute([$nom, $niveau, $filiereId, $profId, $actif, $id]);
                    redirect('/ICEANOTES/pages/modules.php?ok=edit');
                }
            }
        } else {
            $msg = "Tous les champs sont requis.";
        }
    }
}

// --- Toggle actif ---
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    if ($isSuperviseur) {
        $pdo->prepare("UPDATE modules SET actif = 1 - actif WHERE id = ?")->execute([$id]);
    } else {
        $pdo->prepare("UPDATE modules SET actif = 1 - actif WHERE id = ? AND professeur_id = ?")->execute([$id, $myId]);
    }
    redirect('/ICEANOTES/pages/modules.php?ok=toggle');
}

// --- Suppression ---
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        if ($isSuperviseur) {
            $pdo->prepare("DELETE FROM modules WHERE id = ?")->execute([$id]);
        } else {
            $pdo->prepare("DELETE FROM modules WHERE id = ? AND professeur_id = ?")->execute([$id, $myId]);
        }
        redirect('/ICEANOTES/pages/modules.php?ok=delete');
    } catch (PDOException $e) {
        $msg = "Impossible : des notes existent pour ce module.";
    }
}

// --- Édition ---
$editRow = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM modules WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRow = $stmt->fetch() ?: null;
    if ($editRow && !$isSuperviseur && (int)$editRow['professeur_id'] !== $myId) {
        $editRow = null;
        $msg = "Accès refusé.";
    }
}

// --- Liste ---
[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(20));

$sortable = [
    'nom'     => 'm.nom',
    'niveau'  => 'm.niveau',
    'filiere' => 'f.nom',
    'prof'    => 'u.nom, u.prenom',
    'actif'   => 'm.actif',
];
$orderBy = $sortable[$sort] ?? 'm.niveau, f.nom, m.nom';
if ($sort && isset($sortable[$sort])) $orderBy .= " $dir";

$where  = ['1=1'];
$params = [];
if (!$isSuperviseur) {
    $where[]  = "m.professeur_id = ?";
    $params[] = $myId;
}
if ($search !== '') {
    $where[] = "(m.nom LIKE ? OR m.niveau LIKE ? OR f.nom LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM modules m
    JOIN filieres f ON f.id = m.filiere_id
    JOIN users u    ON u.id = m.professeur_id
    WHERE $whereSql
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT m.*, f.nom AS filiere, f.actif AS filiere_actif,
           u.nom AS prof_nom, u.prenom AS prof_prenom,
           t.label AS prof_type
    FROM modules m
    JOIN filieres f ON f.id = m.filiere_id
    JOIN users u    ON u.id = m.professeur_id
    JOIN users_types t ON t.id = u.user_type_id
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$modules = $stmt->fetchAll();

$niveaux  = ['L1', 'L2', 'L3', 'Master'];

$filieres = $pdo->query("SELECT * FROM filieres WHERE actif = 1 ORDER BY nom")->fetchAll();

$profs = $isSuperviseur ? $pdo->query("
    SELECT u.*, t.label AS type_label
    FROM users u
    JOIN users_types t ON t.id = u.user_type_id
    WHERE t.label IN ('admin','superviseur','user','professeur')
      AND u.actif = 1
    ORDER BY
        CASE t.label
            WHEN 'admin'       THEN 0
            WHEN 'superviseur' THEN 1
            WHEN 'user'        THEN 2
            WHEN 'professeur'  THEN 3
            ELSE 4
        END,
        u.nom, u.prenom
")->fetchAll() : [];

require_once BASE_PATH . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            <?= $isSuperviseur ? t('modules_title') : t('modules_title_prof') ?>
        </h1>
        <p class="text-sm text-gray-500"><?= t('modules_page_count', ['n' => $total]) ?></p>
    </div>
</div>

<?php if ($msg): ?><div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4"><?= e($msg) ?></div><?php endif; ?>
<?php if (isset($_GET['ok'])): ?><div class="bg-green-50 text-green-700 text-sm p-3 rounded-lg mb-4"><?= t('ok_' . $_GET['ok']) ?></div><?php endif; ?>

<form method="POST" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 grid sm:grid-cols-12 gap-3 items-start">
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= $editRow['id'] ?>"><?php endif; ?>

    <input type="text" name="nom" required placeholder="<?= e(t('module_nom')) ?>"
           value="<?= $editRow ? e($editRow['nom']) : '' ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-3">

    <select name="niveau" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">
        <option value="">— <?= t('niveau') ?> —</option>
        <?php foreach ($niveaux as $n): ?>
            <option value="<?= $n ?>" <?= $editRow && $editRow['niveau'] === $n ? 'selected' : '' ?>><?= e($n) ?></option>
        <?php endforeach; ?>
    </select>

    <select name="filiere_id" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">
        <option value="">— <?= t('filiere') ?> —</option>
        <?php foreach ($filieres as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $editRow && $editRow['filiere_id'] == $f['id'] ? 'selected' : '' ?>><?= e($f['nom']) ?></option>
        <?php endforeach; ?>
    </select>

    <?php if ($isSuperviseur): ?>
        <select name="professeur_id" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">
            <option value="">— <?= t('professeur') ?> —</option>
            <?php foreach ($profs as $p):
                $typeLabel = t('type_' . ($p['type_label'] === 'professeur' ? 'user' : $p['type_label']));
            ?>
                <option value="<?= $p['id'] ?>" <?= $editRow && $editRow['professeur_id'] == $p['id'] ? 'selected' : '' ?>>
                    <?= e($p['prenom'] . ' ' . $p['nom']) ?> (<?= e($typeLabel) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    <?php else: ?>
        <div class="px-3 py-2 border border-gray-200 bg-gray-50 rounded-lg text-sm text-gray-600 sm:col-span-2">
            <strong><?= e($me['prenom'] . ' ' . $me['nom']) ?></strong>
        </div>
    <?php endif; ?>

    <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none px-3 py-2 sm:col-span-1">
        <input type="checkbox" name="actif" value="1" <?= (!$editRow || $editRow['actif']) ? 'checked' : '' ?>
               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <?= t('active') ?>
    </label>

    <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg sm:col-span-2">
        <?= $editRow ? t('save') : t('add') ?>
    </button>

    <?php if ($editRow): ?>
        <a href="/ICEANOTES/pages/modules.php" class="text-sm text-gray-500 px-3 py-2 hover:underline text-center sm:col-span-12"><?= t('cancel') ?></a>
    <?php endif; ?>
</form>

<!-- Filtres en live -->
<form method="GET" id="filterForm" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" id="searchInput" value="<?= e($search) ?>"
           placeholder="<?= e(t('search')) ?>"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">

    <?php if ($search !== ''): ?>
        <a href="/ICEANOTES/pages/modules.php?per_page=<?= $perPage ?>"
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
                    <th class="text-left px-4 py-3"><?= sortLink('nom', t('module_nom'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('niveau', t('niveau'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('filiere', t('filiere'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('prof', t('professeur'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('actif', t('active'), $sort, $dir) ?></th>
                    <th class="text-right px-4 py-3"><?= t('actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($modules)): ?>
                <tr><td colspan="6" class="text-center text-gray-400 py-8">—</td></tr>
            <?php else: foreach ($modules as $m): ?>
                <?php
                    $profType = t('type_' . ($m['prof_type'] === 'professeur' ? 'user' : $m['prof_type']));
                    $typeColor = match($m['prof_type']) {
                        'admin'       => 'bg-purple-100 text-purple-700',
                        'superviseur' => 'bg-blue-100 text-blue-700',
                        'user', 'professeur' => 'bg-green-100 text-green-700',
                        default       => 'bg-gray-100 text-gray-600',
                    };
                ?>
                <tr class="hover:bg-gray-50 transition <?= $m['actif'] ? '' : 'opacity-50 bg-gray-50' ?>">
                    <td class="px-4 py-3 font-medium"><?= e($m['nom']) ?></td>
                    <td class="px-4 py-3"><span class="inline-block px-2 py-0.5 rounded text-xs bg-indigo-100 text-indigo-700"><?= e($m['niveau']) ?></span></td>
                    <td class="px-4 py-3">
                        <span class="inline-block px-2 py-0.5 rounded text-xs <?= $m['filiere_actif'] ? 'bg-purple-100 text-purple-700' : 'bg-gray-200 text-gray-500 line-through' ?>">
                            <?= e($m['filiere']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-600">
                        <?= e($m['prof_prenom'] . ' ' . $m['prof_nom']) ?>
                        <span class="ml-1 inline-block px-1.5 py-0.5 rounded text-xs <?= $typeColor ?>">
                            <?= e($profType) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <a href="?toggle=<?= $m['id'] ?>" title="<?= e($m['actif'] ? t('deactivate') : t('activate')) ?>"
                           class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition
                                  <?= $m['actif'] ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-200 text-gray-600 hover:bg-gray-300' ?>">
                            <?= $m['actif'] ? '✅ ' . t('active') : '❌ ' . t('inactive') ?>
                        </a>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?= actionButtons(
                            '/ICEANOTES/pages/modules.php?edit=' . $m['id'],
                            '/ICEANOTES/pages/modules.php?delete=' . $m['id'],
                            '/ICEANOTES/pages/modules.php?toggle=' . $m['id'],
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

<script>
(function () {
    const search  = document.getElementById('searchInput');
    const perPage = document.getElementById('perPageSelect');

    let searchTimeout = null;

    if (search) {
        search.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function () {
                const url = new URL(window.location.href);
                if (search.value === '') {
                    url.searchParams.delete('q');
                } else {
                    url.searchParams.set('q', search.value);
                }
                url.searchParams.delete('page');
                window.location.href = url.toString();
            }, 400);
        });
    }

    if (perPage) {
        perPage.addEventListener('change', function () {
            window.location.href = this.value;
        });
    }
})();
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>