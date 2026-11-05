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

<!-- Filtres en live -->
<form method="GET" id="filterForm" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" id="searchInput" value="<?= e($search) ?>"
           placeholder="<?= e(t('search')) ?>"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">

    <?php if ($search !== ''): ?>
        <a href="/ICEANOTES/pages/promotions.php?per_page=<?= $perPage ?>"
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