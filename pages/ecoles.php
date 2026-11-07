<?php
// pages/ecoles.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
requireSuperviseur();

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
        $stmt = $pdo->prepare("UPDATE ecoles SET ??? = ? WHERE id = ?"); // pas d'ordre sur ecoles
        // On n'a pas de colonne ordre sur ecoles → on trie par nom.
        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => 'Erreur BDD']);
    }
    exit;
}

// --- Ajout / Édition ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = $_POST['action'] ?? '';
    if (in_array($action, ['add', 'edit'], true)) {
        $nom         = trim($_POST['nom'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $color       = trim($_POST['color'] ?? '#3b82f6');
        $actif       = isset($_POST['actif']) ? 1 : 0;
        $id          = (int)($_POST['id'] ?? 0);

        $description = $description !== '' ? $description : null;

        // Validation couleur hex
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#3b82f6';
        }

        if ($nom !== '') {
            try {
                if ($action === 'add') {
                    $pdo->prepare("INSERT INTO ecoles (nom, description, color, actif) VALUES (?, ?, ?, ?)")
                        ->execute([$nom, $description, $color, $actif]);
                    redirect('/ICEANOTES/pages/ecoles.php?ok=add');
                } else {
                    $pdo->prepare("UPDATE ecoles SET nom=?, description=?, color=?, actif=? WHERE id=?")
                        ->execute([$nom, $description, $color, $actif, $id]);
                    redirect('/ICEANOTES/pages/ecoles.php?ok=edit');
                }
            } catch (PDOException $e) {
                $msg = t('ecole_nom') . " déjà utilisé.";
            }
        } else {
            $msg = t('ecole_nom') . " requis.";
        }
    }
}

// --- Toggle actif ---
if (isset($_GET['toggle'])) {
    $pdo->prepare("UPDATE ecoles SET actif = 1 - actif WHERE id = ?")->execute([(int)$_GET['toggle']]);
    redirect('/ICEANOTES/pages/ecoles.php?ok=toggle');
}

// --- Suppression ---
if (isset($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM ecoles WHERE id = ?")->execute([(int)$_GET['delete']]);
        redirect('/ICEANOTES/pages/ecoles.php?ok=delete');
    } catch (PDOException $e) {
        $msg = t('ecole_delete_blocked');
    }
}

// --- Édition ? ---
$editRow = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM ecoles WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRow = $stmt->fetch() ?: null;
}

// --- Liste ---
[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(50));

$sort = $sort ?: 'nom';
$sortable = [
    'nom'     => 'ec.nom',
    'filiere' => 'nb_filieres',
    'actif'   => 'ec.actif',
];
if (!isset($sortable[$sort])) $sort = 'nom';
$orderBy = $sortable[$sort] . " $dir";

$where  = ['1=1'];
$params = [];
if ($search !== '') {
    $where[]  = "(ec.nom LIKE ? OR ec.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM ecoles ec WHERE $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT ec.*,
           (SELECT COUNT(*) FROM filieres f WHERE f.ecole_id = ec.id) AS nb_filieres
    FROM ecoles ec
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$ecoles = $stmt->fetchAll();

require_once BASE_PATH . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= t('ecoles_title') ?></h1>
        <p class="text-sm text-gray-500"><?= t('ecoles_count', ['n' => $total]) ?></p>
    </div>
</div>

<?php if ($msg): ?><div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4"><?= e($msg) ?></div><?php endif; ?>
<?php if (isset($_GET['ok'])): ?><div class="bg-green-50 text-green-700 text-sm p-3 rounded-lg mb-4"><?= t('ok_' . $_GET['ok']) ?></div><?php endif; ?>

<!-- Formulaire ajout / édition -->
<form method="POST" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 grid sm:grid-cols-12 gap-3 items-start">
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= $editRow['id'] ?>"><?php endif; ?>

    <div class="sm:col-span-3">
        <label class="block text-xs font-medium text-gray-600 mb-1"><?= e(t('ecole_nom')) ?></label>
        <input type="text" name="nom" required placeholder="ICEA, ENSA…"
               value="<?= $editRow ? e($editRow['nom']) : '' ?>"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>

    <div class="sm:col-span-4">
        <label class="block text-xs font-medium text-gray-600 mb-1"><?= e(t('ecole_description')) ?></label>
        <input type="text" name="description" placeholder="Institut Catholique…"
               value="<?= $editRow ? e($editRow['description'] ?? '') : '' ?>"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>

    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-gray-600 mb-1"><?= e(t('ecole_color')) ?></label>
        <input type="color" name="color" value="<?= $editRow ? e($editRow['color']) : '#3b82f6' ?>"
               class="w-full h-[38px] px-1 py-1 border border-gray-300 rounded-lg cursor-pointer">
    </div>

    <div class="sm:col-span-1">
        <label class="block text-xs font-medium text-gray-600 mb-1">&nbsp;</label>
        <label class="inline-flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer select-none px-2 py-2">
            <input type="checkbox" name="actif" value="1" <?= (!$editRow || $editRow['actif']) ? 'checked' : '' ?>
                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <?= t('active') ?>
        </label>
    </div>

    <div class="sm:col-span-1">
        <label class="block text-xs font-medium text-gray-600 mb-1">&nbsp;</label>
        <button class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg">
            <?= $editRow ? t('save') : t('add') ?>
        </button>
    </div>

    <?php if ($editRow): ?>
        <a href="/ICEANOTES/pages/ecoles.php" class="text-sm text-gray-500 px-3 py-2 hover:underline text-center sm:col-span-12"><?= t('cancel') ?></a>
    <?php endif; ?>
</form>

<!-- Filtres en live -->
<form method="GET" id="filterForm" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" id="searchInput" value="<?= e($search) ?>"
           placeholder="<?= e(t('search')) ?>"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">

    <?php if ($search !== ''): ?>
        <a href="/ICEANOTES/pages/ecoles.php?per_page=<?= $perPage ?>"
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
                    <th class="text-left px-4 py-3"><?= sortLink('nom', t('ecole_nom'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= t('ecole_color') ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('filiere', t('ecole_filieres_count'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('actif', t('active'), $sort, $dir) ?></th>
                    <th class="text-right px-4 py-3"><?= t('actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($ecoles)): ?>
                <tr><td colspan="5" class="text-center text-gray-400 py-8">—</td></tr>
            <?php else: foreach ($ecoles as $ec): ?>
                <tr class="hover:bg-gray-50 transition <?= $ec['actif'] ? '' : 'opacity-50 bg-gray-50' ?>">
                    <td class="px-4 py-3">
                        <div class="font-medium"><?= e($ec['nom']) ?></div>
                        <?php if (!empty($ec['description'])): ?>
                            <div class="text-xs text-gray-500 mt-0.5"><?= e($ec['description']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-4 h-4 rounded-full border border-gray-200"
                                  style="background-color: <?= e($ec['color']) ?>;"></span>
                            <code class="text-xs text-gray-500"><?= e($ec['color']) ?></code>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center"><?= (int)$ec['nb_filieres'] ?></td>
                    <td class="px-4 py-3 text-center">
                        <a href="?toggle=<?= $ec['id'] ?>" title="<?= e($ec['actif'] ? t('deactivate') : t('activate')) ?>"
                           class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition
                                  <?= $ec['actif'] ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-200 text-gray-600 hover:bg-gray-300' ?>">
                            <?= $ec['actif'] ? '✅ ' . t('active') : '❌ ' . t('inactive') ?>
                        </a>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?= actionButtons(
                            '/ICEANOTES/pages/ecoles.php?edit=' . $ec['id'],
                            '/ICEANOTES/pages/ecoles.php?delete=' . $ec['id'],
                            '/ICEANOTES/pages/ecoles.php?toggle=' . $ec['id'],
                            (bool)$ec['actif'],
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

<!-- Barre du bas -->
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