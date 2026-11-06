<?php
// pages/ecoles.php
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
        $nom         = trim($_POST['nom'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $color       = trim($_POST['color'] ?? '#3b82f6');
        $id          = (int)($_POST['id'] ?? 0);

        $description = $description !== '' ? $description : null;

        // Validation couleur hex (#RRGGBB)
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $color = '#3b82f6';
        }

        if ($nom !== '') {
            try {
                if ($action === 'add') {
                    $pdo->prepare("INSERT INTO ecoles (nom, description, color) VALUES (?, ?, ?)")
                        ->execute([$nom, $description, $color]);
                    redirect('/ICEANOTES/pages/ecoles.php?ok=add');
                } else {
                    $pdo->prepare("UPDATE ecoles SET nom=?, description=?, color=? WHERE id=?")
                        ->execute([$nom, $description, $color, $id]);
                    redirect('/ICEANOTES/pages/ecoles.php?ok=edit');
                }
            } catch (PDOException $e) {
                $msg = "Cette école existe déjà.";
            }
        }
    }
}

// --- Suppression ---
if (isset($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM ecoles WHERE id = ?")->execute([(int)$_GET['delete']]);
        redirect('/ICEANOTES/pages/ecoles.php?ok=delete');
    } catch (PDOException $e) {
        $msg = "Impossible : des filières sont liées à cette école.";
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
[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(20));

$sort = $sort ?: 'nom';
$sortable = ['nom' => 'e.nom'];
if (!isset($sortable[$sort])) $sort = 'nom';
$orderBy = $sortable[$sort] . " $dir";

$where  = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = "(e.nom LIKE ? OR e.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM ecoles e WHERE $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT e.*,
           (SELECT COUNT(*) FROM filieres f WHERE f.ecole_id = e.id) AS nb_filieres
    FROM ecoles e
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
        <h1 class="text-2xl font-bold text-gray-800">Écoles</h1>
        <p class="text-sm text-gray-500"><?= $total ?> école<?= $total > 1 ? 's' : '' ?></p>
    </div>
</div>

<?php if ($msg): ?><div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4"><?= e($msg) ?></div><?php endif; ?>
<?php if (isset($_GET['ok'])): ?><div class="bg-green-50 text-green-700 text-sm p-3 rounded-lg mb-4">Opération réussie.</div><?php endif; ?>

<!-- Formulaire ajout / édition avec color picker -->
<form method="POST" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4">
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= $editRow['id'] ?>"><?php endif; ?>

    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 mb-3">
        <div class="sm:col-span-4">
            <label class="block text-xs font-medium text-gray-600 mb-1">Nom de l'école</label>
            <input type="text" name="nom" required placeholder="ICEA, autre école…"
                   value="<?= $editRow ? e($editRow['nom']) : '' ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div class="sm:col-span-5">
            <label class="block text-xs font-medium text-gray-600 mb-1">Description (facultatif)</label>
            <input type="text" name="description" placeholder="Description courte"
                   value="<?= $editRow ? e($editRow['description'] ?? '') : '' ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div class="sm:col-span-3">
            <label class="block text-xs font-medium text-gray-600 mb-1">Couleur</label>
            <div class="flex items-center gap-2">
                <input type="color" name="color" id="colorPicker"
                       value="<?= $editRow ? e($editRow['color'] ?? '#3b82f6') : '#3b82f6' ?>"
                       class="h-10 w-14 rounded-lg border border-gray-300 cursor-pointer bg-white p-1">
                <span class="text-xs text-gray-500 font-mono" id="colorPreview">
                    <?= $editRow ? e($editRow['color'] ?? '#3b82f6') : '#3b82f6' ?>
                </span>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg">
            <?= $editRow ? 'Enregistrer' : 'Ajouter' ?>
        </button>
        <?php if ($editRow): ?>
            <a href="/ICEANOTES/pages/ecoles.php" class="text-sm text-gray-500 px-3 py-2 hover:underline">Annuler</a>
        <?php endif; ?>
    </div>
</form>

<!-- Recherche -->
<form method="GET" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" id="searchInput" value="<?= e($search) ?>"
           placeholder="Rechercher…"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">

    <?php if ($search !== ''): ?>
        <a href="/ICEANOTES/pages/ecoles.php?per_page=<?= $perPage ?>"
           class="text-sm text-red-500 hover:text-red-700 hover:underline self-center px-3 whitespace-nowrap">
            ✕ Réinitialiser
        </a>
    <?php endif; ?>
</form>

<!-- Tableau -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="w-12"></th>
                    <th class="text-left px-4 py-3"><?= sortLink('nom', 'Nom', $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3">Description</th>
                    <th class="text-center px-4 py-3">Filières</th>
                    <th class="text-right px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($ecoles)): ?>
                <tr><td colspan="5" class="text-center text-gray-400 py-8">—</td></tr>
            <?php else: foreach ($ecoles as $e):
                $color = $e['color'] ?? '#3b82f6';
            ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="w-6 h-6 rounded-lg shadow-sm" style="background: <?= e($color) ?>"></div>
                    </td>
                    <td class="px-4 py-3 font-medium"><?= e($e['nom']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= e($e['description'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-center"><?= $e['nb_filieres'] ?></td>
                    <td class="px-4 py-3 text-right">
                        <?= actionButtons(
                            '/ICEANOTES/pages/ecoles.php?edit=' . $e['id'],
                            '/ICEANOTES/pages/ecoles.php?delete=' . $e['id'],
                            '',
                            true,
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

<script>
(function () {
    // --- Color picker : preview live ---
    const picker  = document.getElementById('colorPicker');
    const preview = document.getElementById('colorPreview');

    if (picker && preview) {
        picker.addEventListener('input', function () {
            preview.textContent = this.value;
        });
    }

    // --- per_page ---
    const perPage = document.getElementById('perPageSelect');
    if (perPage) {
        perPage.addEventListener('change', function () {
            window.location.href = this.value;
        });
    }

    // --- Recherche live ---
    const search = document.getElementById('searchInput');
    if (search) {
        let t = null;
        search.addEventListener('input', function () {
            clearTimeout(t);
            t = setTimeout(function () {
                const url = new URL(window.location.href);
                search.value === '' ? url.searchParams.delete('q') : url.searchParams.set('q', search.value);
                url.searchParams.delete('page');
                window.location.href = url.toString();
            }, 400);
        });
    }
})();
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>