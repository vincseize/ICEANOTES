<?php
// pages/diplomes.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
requireAdmin();

$pdo = getDB();
$msg = '';

// --- Ajout / Édition ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'reorder') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['add', 'edit'], true)) {
        $nom   = trim($_POST['nom'] ?? '');
        $actif = isset($_POST['actif']) ? 1 : 0;
        $id    = (int)($_POST['id'] ?? 0);

        if ($nom !== '') {
            try {
                if ($action === 'add') {
                    $nextOrdre = (int)$pdo->query("SELECT COALESCE(MAX(ordre), 0) + 10 FROM diplomes")->fetchColumn();
                    $pdo->prepare("INSERT INTO diplomes (nom, ordre, actif) VALUES (?, ?, ?)")
                        ->execute([$nom, $nextOrdre, $actif]);
                    redirect('/ICEANOTES/pages/diplomes.php?ok=add');
                } else {
                    $pdo->prepare("UPDATE diplomes SET nom=?, actif=? WHERE id=?")
                        ->execute([$nom, $actif, $id]);
                    redirect('/ICEANOTES/pages/diplomes.php?ok=edit');
                }
            } catch (PDOException $e) {
                $msg = "Ce diplôme existe déjà.";
            }
        }
    }
}

// --- Réordonnancement (AJAX SortableJS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reorder') {
    header('Content-Type: application/json');
    $ids = json_decode($_POST['ids'] ?? '[]', true);
    if (is_array($ids) && !empty($ids)) {
        $stmt = $pdo->prepare("UPDATE diplomes SET ordre = ? WHERE id = ?");
        foreach ($ids as $i => $id) {
            $stmt->execute([($i + 1) * 10, (int)$id]);
        }
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'invalid']);
    }
    exit;
}

// --- Toggle actif ---
if (isset($_GET['toggle'])) {
    $pdo->prepare("UPDATE diplomes SET actif = 1 - actif WHERE id = ?")->execute([(int)$_GET['toggle']]);
    redirect('/ICEANOTES/pages/diplomes.php?ok=toggle');
}

// --- Suppression ---
if (isset($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM diplomes WHERE id = ?")->execute([(int)$_GET['delete']]);
        redirect('/ICEANOTES/pages/diplomes.php?ok=delete');
    } catch (PDOException $e) {
        $msg = "Impossible : des promos ou matières sont liées à ce diplôme.";
    }
}

// --- Édition ? ---
$editRow = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM diplomes WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRow = $stmt->fetch() ?: null;
}

// --- Liste ---
[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(100));

$sortable = [
    'nom'   => 'd.nom',
    'ordre' => 'd.ordre',
    'actif' => 'd.actif',
];
$orderBy = $sortable[$sort] ?? 'd.ordre ASC, d.nom';
if ($sort && isset($sortable[$sort])) $orderBy .= " $dir";

$where  = ['1=1'];
$params = [];
if ($search !== '') {
    $where[]  = "d.nom LIKE ?";
    $params[] = "%$search%";
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM diplomes d WHERE $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT d.*,
           (SELECT COUNT(*) FROM promos p WHERE p.diplome_id = d.id) AS nb_promos,
           (SELECT COUNT(*) FROM matieres m WHERE m.diplome_id = d.id) AS nb_matieres
    FROM diplomes d
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$diplomes = $stmt->fetchAll();

// Active le drag & drop uniquement si pas de recherche + tri par ordre
$dragEnabled = ($search === '' && (!$sort || $sort === 'ordre'));

require_once BASE_PATH . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= t('diplomes_title') ?></h1>
        <p class="text-sm text-gray-500"><?= t('diplomes_count', ['n' => $total]) ?></p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4"><?= e($msg) ?></div>
<?php endif; ?>
<?php if (isset($_GET['ok'])): ?>
    <div class="bg-green-50 text-green-700 text-sm p-3 rounded-lg mb-4"><?= t('ok_' . $_GET['ok']) ?></div>
<?php endif; ?>

<form method="POST" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col sm:flex-row gap-3 items-start sm:items-center">
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= $editRow['id'] ?>"><?php endif; ?>
    <input type="text" name="nom" required placeholder="<?= e(t('diplome_nom')) ?>"
           value="<?= $editRow ? e($editRow['nom']) : '' ?>"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none px-3 py-2">
        <input type="checkbox" name="actif" value="1"
               <?= (!$editRow || $editRow['actif']) ? 'checked' : '' ?>
               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <?= t('active') ?>
    </label>
    <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg">
        <?= $editRow ? t('save') : t('add') ?>
    </button>
    <?php if ($editRow): ?>
        <a href="/ICEANOTES/pages/diplomes.php" class="text-sm text-gray-500 px-3 py-2 hover:underline self-center"><?= t('cancel') ?></a>
    <?php endif; ?>
</form>

<form method="GET" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('search')) ?>"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <button class="bg-gray-800 hover:bg-gray-900 text-white text-sm px-4 py-2 rounded-lg"><?= t('filter') ?></button>
    <a href="/ICEANOTES/pages/diplomes.php" class="text-sm text-gray-500 px-3 py-2 hover:underline self-center"><?= t('reset') ?></a>
</form>

<?php if ($dragEnabled): ?>
<div class="bg-blue-50 border border-blue-200 text-blue-700 text-xs p-2 rounded-lg mb-3 flex items-center gap-2">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16M4 16h16"/>
    </svg>
    Glissez-déposez les lignes par la poignée <strong>⠿</strong> pour changer l'ordre (sauvegarde auto).
</div>
<?php else: ?>
<div class="bg-yellow-50 border border-yellow-200 text-yellow-700 text-xs p-2 rounded-lg mb-3">
    ⚠️ Le glisser-déposer est désactivé pendant une recherche ou un tri personnalisé.
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="w-10"></th>
                    <th class="text-left px-4 py-3"><?= sortLink('nom', t('diplome_nom'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= t('promos_count') ?></th>
                    <th class="text-center px-4 py-3"><?= t('matieres_count') ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('actif', t('active'), $sort, $dir) ?></th>
                    <th class="text-right px-4 py-3"><?= t('actions') ?></th>
                </tr>
            </thead>
            <tbody id="sortableBody" class="divide-y divide-gray-100">
            <?php if (empty($diplomes)): ?>
                <tr><td colspan="6" class="text-center text-gray-400 py-8">—</td></tr>
            <?php else: foreach ($diplomes as $d): ?>
                <tr data-id="<?= (int)$d['id'] ?>"
                    class="hover:bg-gray-50 transition <?= $d['actif'] ? '' : 'opacity-50 bg-gray-50' ?>">
                    <td class="px-2 py-3 text-gray-400 <?= $dragEnabled ? 'cursor-move drag-handle' : '' ?>"
                        title="<?= $dragEnabled ? 'Glisser pour réordonner' : '' ?>">
                        <?php if ($dragEnabled): ?>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                <circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/>
                                <circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>
                                <circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/>
                            </svg>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 font-medium"><?= e($d['nom']) ?></td>
                    <td class="px-4 py-3 text-center"><?= $d['nb_promos'] ?></td>
                    <td class="px-4 py-3 text-center"><?= $d['nb_matieres'] ?></td>
                    <td class="px-4 py-3 text-center">
                        <a href="?toggle=<?= $d['id'] ?>"
                           title="<?= e($d['actif'] ? t('deactivate') : t('activate')) ?>"
                           class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition
                                  <?= $d['actif']
                                        ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                        : 'bg-gray-200 text-gray-600 hover:bg-gray-300' ?>">
                            <?php if ($d['actif']): ?>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                <?= t('active') ?>
                            <?php else: ?>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                <?= t('inactive') ?>
                            <?php endif; ?>
                        </a>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?= actionButtons(
                            '/ICEANOTES/pages/diplomes.php?edit='   . $d['id'],
                            '/ICEANOTES/pages/diplomes.php?delete=' . $d['id'],
                            '/ICEANOTES/pages/diplomes.php?toggle=' . $d['id'],
                            (bool)$d['actif'],
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

<?php if ($dragEnabled && $total > 1): ?>
<p class="text-xs text-gray-400 text-center mt-2">
    Ordre sauvegardé automatiquement. <span id="reorderStatus" class="font-medium text-blue-600"></span>
</p>
<?php endif; ?>

<?= renderPagination($page, $perPage, $total) ?>

<?php if ($dragEnabled && $total > 1): ?>
<!-- SortableJS via CDN -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    const tbody = document.getElementById('sortableBody');
    if (!tbody || typeof Sortable === 'undefined') {
        console.warn('SortableJS non chargé');
        return;
    }

    const status = document.getElementById('reorderStatus');

    Sortable.create(tbody, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'bg-blue-50',
        chosenClass: 'bg-blue-100',
        dragClass: 'opacity-50',
        onEnd: function (evt) {
            if (evt.oldIndex === evt.newIndex) return;

            if (status) status.textContent = 'Sauvegarde…';

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
            .then(data => {
                if (status) {
                    status.textContent = data.ok ? '✅ Enregistré' : '❌ Erreur';
                    setTimeout(() => { status.textContent = ''; }, 2000);
                }
            })
            .catch(err => {
                console.error(err);
                if (status) status.textContent = '❌ Erreur réseau';
            });
        }
    });
})();
</script>
<?php endif; ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>