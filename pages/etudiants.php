<?php
// pages/etudiants.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
$isSuperviseur = isSuperviseur();

$pdo = getDB();
$msg = '';

// --- Ajout / Édition ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isSuperviseur) {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['add', 'edit'], true)) {
        $nom         = trim($_POST['nom'] ?? '');
        $prenom      = trim($_POST['prenom'] ?? '');
        $promotionId = (int)($_POST['promotion_id'] ?? 0);
        $actif       = isset($_POST['actif']) ? 1 : 0;
        $id          = (int)($_POST['id'] ?? 0);

        if ($nom !== '' && $prenom !== '' && $promotionId > 0) {
            $checkPromo = $pdo->prepare("
                SELECT p.actif AS promo_actif, f.actif AS filiere_actif
                FROM promotions p
                JOIN filieres f ON f.id = p.filiere_id
                WHERE p.id = ?
            ");
            $checkPromo->execute([$promotionId]);
            $row = $checkPromo->fetch();

            if (!$row) {
                $msg = "Promotion introuvable.";
            } elseif (!$row['promo_actif']) {
                $msg = "La promotion sélectionnée est inactive.";
            } elseif (!$row['filiere_actif']) {
                $msg = "La filière de cette promotion est inactive.";
            }

            if (!$msg) {
                if ($action === 'add') {
                    $pdo->prepare("INSERT INTO etudiants (nom, prenom, promotion_id, actif) VALUES (?, ?, ?, ?)")
                        ->execute([$nom, $prenom, $promotionId, $actif]);
                    redirect('/ICEANOTES/pages/etudiants.php?ok=add');
                } else {
                    $pdo->prepare("UPDATE etudiants SET nom=?, prenom=?, promotion_id=?, actif=? WHERE id=?")
                        ->execute([$nom, $prenom, $promotionId, $actif, $id]);
                    redirect('/ICEANOTES/pages/etudiants.php?ok=edit');
                }
            }
        }
    }
}

if (isset($_GET['toggle']) && $isSuperviseur) {
    $pdo->prepare("UPDATE etudiants SET actif = 1 - actif WHERE id = ?")->execute([(int)$_GET['toggle']]);
    redirect('/ICEANOTES/pages/etudiants.php?ok=toggle');
}
if (isset($_GET['delete']) && $isSuperviseur) {
    try {
        $pdo->prepare("DELETE FROM etudiants WHERE id = ?")->execute([(int)$_GET['delete']]);
        redirect('/ICEANOTES/pages/etudiants.php?ok=delete');
    } catch (PDOException $e) { $msg = "Impossible : notes liées."; }
}

$editRow = null;
if (isset($_GET['edit']) && $isSuperviseur) {
    $stmt = $pdo->prepare("SELECT * FROM etudiants WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRow = $stmt->fetch() ?: null;
}

// --- Liste ---
[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(20));
$filterPromotion = (int)($_GET['promotion'] ?? 0);

$sortable = [
    'nom'       => 'e.nom, e.prenom',
    'prenom'    => 'e.prenom, e.nom',
    'niveau'    => 'p.nom, p.ordre',
    'promo'     => 'p.annee, p.promo',
    'filiere'   => 'f.nom',
    'actif'     => 'e.actif',
];
$orderBy = $sortable[$sort] ?? 'p.ordre, e.nom, e.prenom';
if ($sort && isset($sortable[$sort])) $orderBy .= " $dir";

$where = ['1=1']; $params = [];
if ($search !== '') {
    $where[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR p.nom LIKE ? OR p.promo LIKE ? OR f.nom LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like, $like);
}
if ($filterPromotion > 0) { $where[] = "e.promotion_id = ?"; $params[] = $filterPromotion; }
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM etudiants e
    JOIN promotions p ON p.id = e.promotion_id
    JOIN filieres f ON f.id = p.filiere_id
    WHERE $whereSql
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT e.*, p.nom AS niveau, p.promo, p.annee, p.actif AS promotion_actif,
           f.nom AS filiere, f.actif AS filiere_actif
    FROM etudiants e
    JOIN promotions p ON p.id = e.promotion_id
    JOIN filieres f ON f.id = p.filiere_id
    WHERE $whereSql ORDER BY $orderBy LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$etudiants = $stmt->fetchAll();

// Promotions actives ET dont la filière est active (pour le select)
$promotions = $pdo->query("
    SELECT p.*, f.nom AS filiere
    FROM promotions p
    JOIN filieres f ON f.id = p.filiere_id
    WHERE p.actif = 1 AND f.actif = 1
    ORDER BY p.ordre, f.nom, p.annee, p.promo
")->fetchAll();

// Pour le filtre, toutes les promotions (même inactives)
$allPromos = $pdo->query("
    SELECT p.*, f.nom AS filiere
    FROM promotions p
    JOIN filieres f ON f.id = p.filiere_id
    ORDER BY p.ordre, f.nom, p.annee
")->fetchAll();

require_once BASE_PATH . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= t('etudiants_title') ?></h1>
        <p class="text-sm text-gray-500"><?= t('etudiants_page_count', ['n' => $total]) ?></p>
    </div>
</div>

<?php if ($msg): ?><div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4"><?= e($msg) ?></div><?php endif; ?>
<?php if (isset($_GET['ok'])): ?><div class="bg-green-50 text-green-700 text-sm p-3 rounded-lg mb-4"><?= t('ok_' . $_GET['ok']) ?></div><?php endif; ?>

<?php if ($isSuperviseur): ?>
<form method="POST" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 grid sm:grid-cols-12 gap-3 items-start">
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= $editRow['id'] ?>"><?php endif; ?>
    <input type="text" name="prenom" required placeholder="<?= e(t('etudiant_prenom')) ?>" value="<?= $editRow ? e($editRow['prenom']) : '' ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-3">
    <input type="text" name="nom" required placeholder="<?= e(t('etudiant_nom')) ?>" value="<?= $editRow ? e($editRow['nom']) : '' ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-3">
    <select name="promotion_id" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-4">
        <option value="">— <?= t('promotion_label') ?> —</option>
        <?php foreach ($promotions as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $editRow && $editRow['promotion_id'] == $p['id'] ? 'selected' : '' ?>>
                <?= e($p['nom'] . ' — ' . ($p['promo'] ?? '(sans promo)') . ' — ' . $p['annee'] . ' — ' . $p['filiere']) ?>
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
        <a href="/ICEANOTES/pages/etudiants.php" class="text-sm text-gray-500 px-3 py-2 hover:underline text-center sm:col-span-12"><?= t('cancel') ?></a>
    <?php endif; ?>
</form>
<?php endif; ?>

<!-- Filtres en live -->
<form method="GET" id="filterForm" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" id="searchInput" value="<?= e($search) ?>"
           placeholder="<?= e(t('search')) ?>"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">

    <select name="promotion" id="promoSelect" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value="">— <?= t('promotion_label') ?> —</option>
        <?php foreach ($allPromos as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $filterPromotion == $p['id'] ? 'selected' : '' ?>>
                <?= e($p['nom'] . ' — ' . ($p['promo'] ?? '') . ' (' . $p['annee'] . ')') ?>
            </option>
        <?php endforeach; ?>
    </select>

    <?php if ($search !== '' || $filterPromotion): ?>
        <a href="/ICEANOTES/pages/etudiants.php?per_page=<?= $perPage ?>"
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
                    <th class="text-left px-4 py-3"><?= sortLink('prenom', t('etudiant_prenom'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('nom', t('etudiant_nom'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('niveau', t('niveau'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('promo', t('promo'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('filiere', t('filiere'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('actif', t('active'), $sort, $dir) ?></th>
                    <th class="text-right px-4 py-3"><?= t('actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($etudiants)): ?>
                <tr><td colspan="7" class="text-center text-gray-400 py-8">—</td></tr>
            <?php else: foreach ($etudiants as $et): ?>
                <?php $rowInactive = !$et['actif'] || !$et['promotion_actif'] || !$et['filiere_actif']; ?>
                <tr class="hover:bg-gray-50 transition <?= $rowInactive ? 'opacity-50 bg-gray-50' : '' ?>">
                    <td class="px-4 py-3 font-medium"><?= e($et['prenom']) ?></td>
                    <td class="px-4 py-3"><?= e($et['nom']) ?></td>
                    <td class="px-4 py-3"><span class="inline-block px-2 py-0.5 rounded text-xs bg-indigo-100 text-indigo-700"><?= e($et['niveau']) ?></span></td>
                    <td class="px-4 py-3">
                        <?php if ($et['promo']): ?>
                            <?= e($et['promo']) ?>
                        <?php else: ?>
                            <span class="text-gray-400 italic text-xs">(sans promo)</span>
                        <?php endif; ?>
                        <?php if (!$et['promotion_actif']): ?>
                            <span class="ml-1 inline-block px-1.5 py-0.5 rounded text-xs bg-gray-200 text-gray-600"><?= t('inactive') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-block px-2 py-0.5 rounded text-xs <?= $et['filiere_actif'] ? 'bg-purple-100 text-purple-700' : 'bg-gray-200 text-gray-500 line-through' ?>">
                            <?= e($et['filiere']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($isSuperviseur): ?>
                            <a href="?toggle=<?= $et['id'] ?>" title="<?= e($et['actif'] ? t('deactivate') : t('activate')) ?>"
                               class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition
                                      <?= $et['actif'] ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-200 text-gray-600 hover:bg-gray-300' ?>">
                                <?= $et['actif'] ? '✅ ' . t('active') : '❌ ' . t('inactive') ?>
                            </a>
                        <?php else: ?>
                            <span class="inline-block px-2 py-0.5 rounded text-xs <?= $et['actif'] ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' ?>">
                                <?= $et['actif'] ? t('active') : t('inactive') ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?php if ($isSuperviseur): ?>
                            <?= actionButtons(
                                '/ICEANOTES/pages/etudiants.php?edit=' . $et['id'],
                                '/ICEANOTES/pages/etudiants.php?delete=' . $et['id'],
                                '/ICEANOTES/pages/etudiants.php?toggle=' . $et['id'],
                                (bool)$et['actif'],
                                true,
                                false
                            ) ?>
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
    const promo   = document.getElementById('promoSelect');
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

    if (promo) {
        promo.addEventListener('change', function () {
            const url = new URL(window.location.href);
            if (promo.value === '') {
                url.searchParams.delete('promotion');
            } else {
                url.searchParams.set('promotion', promo.value);
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
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