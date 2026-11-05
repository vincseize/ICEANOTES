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

<!-- Filtres en live -->
<form method="GET" id="filterForm" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" id="searchInput" value="<?= e($search) ?>"
           placeholder="<?= e(t('search')) ?>"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">

    <?php if ($search !== ''): ?>
        <a href="/ICEANOTES/pages/note_types.php?per_page=<?= $perPage ?>"
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