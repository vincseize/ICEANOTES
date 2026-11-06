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

// --- AJAX : réordonnancement ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reorder') {
    header('Content-Type: application/json');

    $ids = json_decode($_POST['ids'] ?? '[]', true);
    if (!is_array($ids) || empty($ids)) {
        echo json_encode(['ok' => false, 'error' => 'IDs invalides']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE modules SET ordre = ? WHERE id = ?");
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
                    $nextOrdre = (int)$pdo->query("SELECT COALESCE(MAX(ordre), 0) + 10 FROM modules")->fetchColumn();
                    $pdo->prepare("
                        INSERT INTO modules (nom, niveau, filiere_id, professeur_id, ordre, actif)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ")->execute([$nom, $niveau, $filiereId, $profId, $nextOrdre, $actif]);
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
[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(100));

$sortable = [
    'nom'     => 'm.nom',
    'niveau'  => 'm.niveau',
    'filiere' => 'f.nom',
    'prof'    => 'u.nom, u.prenom',
    'actif'   => 'm.actif',
];
$orderBy = $sortable[$sort] ?? 'm.ordre ASC, m.nom';
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

// Drag activé seulement si pas de recherche + tri par ordre
$dragEnabled = ($search === '' && (!$sort || $sort === 'ordre'));

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

<!-- Formulaire ajout / édition avec labels -->
<form method="POST" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 grid sm:grid-cols-12 gap-3 items-start">
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= $editRow['id'] ?>"><?php endif; ?>

    <div class="sm:col-span-4">
        <label class="block text-xs font-medium text-gray-600 mb-1"><?= e(t('module_nom')) ?></label>
        <input type="text" name="nom" required placeholder="Maths, Physique…"
               value="<?= $editRow ? e($editRow['nom']) : '' ?>"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>

    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-gray-600 mb-1"><?= e(t('niveau')) ?></label>
        <select name="niveau" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">—</option>
            <?php foreach ($niveaux as $n): ?>
                <option value="<?= $n ?>" <?= $editRow && $editRow['niveau'] === $n ? 'selected' : '' ?>><?= e($n) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-gray-600 mb-1"><?= e(t('filiere')) ?></label>
        <select name="filiere_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">—</option>
            <?php foreach ($filieres as $f): ?>
                <option value="<?= $f['id'] ?>" <?= $editRow && $editRow['filiere_id'] == $f['id'] ? 'selected' : '' ?>><?= e($f['nom']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($isSuperviseur): ?>
        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1"><?= e(t('professeur')) ?></label>
            <select name="professeur_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">—</option>
                <?php foreach ($profs as $p):
                    $typeLabel = t('type_' . ($p['type_label'] === 'professeur' ? 'user' : $p['type_label']));
                ?>
                    <option value="<?= $p['id'] ?>" <?= $editRow && $editRow['professeur_id'] == $p['id'] ? 'selected' : '' ?>>
                        <?= e($p['prenom'] . ' ' . $p['nom']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php else: ?>
        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1"><?= e(t('professeur')) ?></label>
            <div class="px-3 py-2 border border-gray-200 bg-gray-50 rounded-lg text-sm text-gray-600">
                <strong><?= e($me['prenom'] . ' ' . $me['nom']) ?></strong>
            </div>
        </div>
    <?php endif; ?>

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
                    <th class="w-10"></th>
                    <th class="text-left px-4 py-3"><?= sortLink('nom', t('module_nom'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('niveau', t('niveau'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('filiere', t('filiere'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('prof', t('professeur'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('actif', t('active'), $sort, $dir) ?></th>
                    <th class="text-right px-4 py-3"><?= t('actions') ?></th>
                </tr>
            </thead>
            <tbody id="sortableBody" class="divide-y divide-gray-100">
            <?php if (empty($modules)): ?>
                <tr><td colspan="7" class="text-center text-gray-400 py-8">—</td></tr>
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
                <tr data-id="<?= (int)$m['id'] ?>" class="hover:bg-gray-50 transition <?= $m['actif'] ? '' : 'opacity-50 bg-gray-50' ?>">
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
            .then(data => {
                if (!data.ok) console.error('Erreur sauvegarde ordre');
            })
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