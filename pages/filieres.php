<?php
// pages/filieres.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
requireAdmin();

$pdo = getDB();
$msg = '';

// --- AJAX : réordonnancement ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reorder') {
    header('Content-Type: application/json');
    $ids = json_decode($_POST['ids'] ?? '[]', true);
    if (!is_array($ids) || empty($ids)) {
        echo json_encode(['ok' => false, 'error' => 'IDs invalides']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("UPDATE filieres SET ordre = ? WHERE id = ?");
        foreach ($ids as $i => $id) {
            $stmt->execute([($i + 1) * 10, (int)$id]);
        }
        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => 'Erreur BDD']);
    }
    exit;
}

// --- Ajout / Édition ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    if (in_array($action, ['add', 'edit'], true)) {
        $nom     = trim($_POST['nom'] ?? '');
        $ecoleId = (int)($_POST['ecole_id'] ?? 0);
        $actif   = isset($_POST['actif']) ? 1 : 0;
        $id      = (int)($_POST['id'] ?? 0);

        if ($nom !== '' && $ecoleId > 0) {
            try {
                if ($action === 'add') {
                    $nextOrdre = (int)$pdo->query("SELECT COALESCE(MAX(ordre), 0) + 10 FROM filieres")->fetchColumn();
                    $pdo->prepare("INSERT INTO filieres (nom, ordre, ecole_id, actif) VALUES (?, ?, ?, ?)")
                        ->execute([$nom, $nextOrdre, $ecoleId, $actif]);
                    redirect('/ICEANOTES/pages/filieres.php?ok=add');
                } else {
                    $pdo->prepare("UPDATE filieres SET nom=?, ecole_id=?, actif=? WHERE id=?")
                        ->execute([$nom, $ecoleId, $actif, $id]);
                    redirect('/ICEANOTES/pages/filieres.php?ok=edit');
                }
            } catch (PDOException $e) {
                $msg = "Cette filière existe déjà.";
            }
        }
    }
}

// --- Toggle actif ---
if (isset($_GET['toggle'])) {
    $pdo->prepare("UPDATE filieres SET actif = 1 - actif WHERE id = ?")->execute([(int)$_GET['toggle']]);
    redirect('/ICEANOTES/pages/filieres.php?ok=toggle');
}

// --- Suppression ---
if (isset($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM filieres WHERE id = ?")->execute([(int)$_GET['delete']]);
        redirect('/ICEANOTES/pages/filieres.php?ok=delete');
    } catch (PDOException $e) {
        $msg = "Impossible : des promotions ou modules sont liés à cette filière.";
    }
}

// --- Édition ? ---
$editRow = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM filieres WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRow = $stmt->fetch() ?: null;
}

// --- Liste ---
[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(100));

$sort = $sort ?: 'ordre';
$sortable = [
    'nom'         => 'f.nom',
    'promotions'  => 'nb_promotions',     // ← NOUVEAU (alias)
    'modules'     => 'nb_modules',        // ← NOUVEAU (alias)
    'actif'       => 'f.actif',
    // 'ecole' retiré (colonne supprimée)
];
if (!isset($sortable[$sort])) $sort = 'ordre';

if ($sort === 'ordre') {
    $orderBy = 'f.ordre ASC, f.nom ASC';
} else {
    // Pour les alias (nb_promotions, nb_modules), on trie par la valeur calculée
    $orderBy = $sortable[$sort] . " $dir";
}

$where  = ['1=1'];
$params = [];
if ($search !== '') {
    $where[]  = "f.nom LIKE ?";
    $params[] = "%$search%";
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM filieres f
    JOIN ecoles e ON e.id = f.ecole_id
    WHERE $whereSql
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT f.*,
           (SELECT COUNT(*) FROM promotions p WHERE p.filiere_id = f.id) AS nb_promotions,
           (SELECT COUNT(*) FROM modules m WHERE m.filiere_id = f.id) AS nb_modules
    FROM filieres f
    JOIN ecoles e ON e.id = f.ecole_id
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$filieres = $stmt->fetchAll();

$ecoles = $pdo->query("SELECT * FROM ecoles ORDER BY nom")->fetchAll();

$dragEnabled = ($search === '' && $sort === 'ordre');

require_once BASE_PATH . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= t('filieres_title') ?></h1>
        <p class="text-sm text-gray-500"><?= t('filieres_count', ['n' => $total]) ?></p>
    </div>
</div>

<?php if ($msg): ?><div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4"><?= e($msg) ?></div><?php endif; ?>
<?php if (isset($_GET['ok'])): ?><div class="bg-green-50 text-green-700 text-sm p-3 rounded-lg mb-4"><?= t('ok_' . $_GET['ok']) ?></div><?php endif; ?>

<!-- Formulaire ajout / édition -->
<form method="POST" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col sm:flex-row gap-3 items-start sm:items-center">
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= $editRow['id'] ?>"><?php endif; ?>

    <input type="text" name="nom" required placeholder="<?= e(t('filiere_nom')) ?>"
           value="<?= $editRow ? e($editRow['nom']) : '' ?>"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">

    <select name="ecole_id" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <?php foreach ($ecoles as $e): ?>
            <option value="<?= $e['id'] ?>" <?= $editRow && $editRow['ecole_id'] == $e['id'] ? 'selected' : '' ?>>
                <?= e($e['nom']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none px-3 py-2">
        <input type="checkbox" name="actif" value="1" <?= (!$editRow || $editRow['actif']) ? 'checked' : '' ?>
               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <?= t('active') ?>
    </label>

    <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg">
        <?= $editRow ? t('save') : t('add') ?>
    </button>

    <?php if ($editRow): ?>
        <a href="/ICEANOTES/pages/filieres.php" class="text-sm text-gray-500 px-3 py-2 hover:underline self-center"><?= t('cancel') ?></a>
    <?php endif; ?>
</form>

<!-- Filtres en live -->
<form method="GET" id="filterForm" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" id="searchInput" value="<?= e($search) ?>"
           placeholder="<?= e(t('search')) ?>"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">

    <?php if ($search !== ''): ?>
        <a href="/ICEANOTES/pages/filieres.php?per_page=<?= $perPage ?>"
           class="text-sm text-red-500 hover:text-red-700 hover:underline self-center px-3 whitespace-nowrap">
            ✕ <?= t('reset') ?>
        </a>
    <?php endif; ?>
</form>

<!-- Tableau -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="w-10"></th>
                    <th class="text-left px-4 py-3"><?= sortLink('nom', t('filiere_nom'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('promotions', t('promotions_count'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('modules', t('modules_count'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('actif', t('active'), $sort, $dir) ?></th>
                    <th class="text-right px-4 py-3"><?= t('actions') ?></th>
                </tr>
            </thead>
            <tbody id="sortableBody" class="divide-y divide-gray-100">
            <?php if (empty($filieres)): ?>
                <tr><td colspan="6" class="text-center text-gray-400 py-8">—</td></tr>
            <?php else: foreach ($filieres as $f): ?>
                <tr data-id="<?= (int)$f['id'] ?>" class="hover:bg-gray-50 transition <?= $f['actif'] ? '' : 'opacity-50 bg-gray-50' ?>">
                    <td class="px-2 py-3 text-gray-400 <?= $dragEnabled ? 'cursor-move drag-handle' : '' ?>">
                        <?php if ($dragEnabled): ?>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                <circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/>
                                <circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>
                                <circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/>
                            </svg>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 font-medium"><?= e($f['nom']) ?></td>
                    <td class="px-4 py-3 text-center"><?= (int)$f['nb_promotions'] ?></td>
                    <td class="px-4 py-3 text-center"><?= (int)$f['nb_modules'] ?></td>
                    <td class="px-4 py-3 text-center">
                        <a href="?toggle=<?= $f['id'] ?>" title="<?= e($f['actif'] ? t('deactivate') : t('activate')) ?>"
                           class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition
                                  <?= $f['actif'] ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-200 text-gray-600 hover:bg-gray-300' ?>">
                            <?php if ($f['actif']): ?>
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
                            '/ICEANOTES/pages/filieres.php?edit=' . $f['id'],
                            '/ICEANOTES/pages/filieres.php?delete=' . $f['id'],
                            '/ICEANOTES/pages/filieres.php?toggle=' . $f['id'],
                            (bool)$f['actif'],
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
                class="px-3 py-1.5 rounded-lg text-sm border border-gray-300 bg-white text-gray-700 cursor-pointer">
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

<!-- SortableJS -->
<?php if ($dragEnabled && $total > 1): ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    const tbody = document.getElementById('sortableBody');
    if (!tbody || typeof Sortable === 'undefined') return;

    Sortable.create(tbody, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'bg-blue-50',
        chosenClass: 'bg-blue-100',
        dragClass: 'opacity-50',
        onEnd: function (evt) {
            if (evt.oldIndex === evt.newIndex) return;
            const ids = Array.from(tbody.querySelectorAll('tr[data-id]'))
                .map(tr => tr.getAttribute('data-id'));
            const body = new URLSearchParams();
            body.append('action', 'reorder');
            body.append('ids', JSON.stringify(ids));
            fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
            .then(r => r.json())
            .then(data => { if (!data.ok) console.error('Erreur sauvegarde ordre'); })
            .catch(err => console.error(err));
        }
    });
})();
</script>
<?php endif; ?>

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
                search.value === '' ? url.searchParams.delete('q') : url.searchParams.set('q', search.value);
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