<?php
// pages/professeurs.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
requireSuperviseur();

$me = currentUser();
$isAdmin = isAdmin();
$pdo = getDB();
$msg = '';

// --- Ajout / Édition ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['add', 'edit'], true)) {
        $email  = trim($_POST['email'] ?? '');
        $nom    = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $mdp    = $_POST['password'] ?? '';
        $typeId = (int)($_POST['user_type_id'] ?? 0);
        $actif  = isset($_POST['actif']) ? 1 : 0;
        $id     = (int)($_POST['id'] ?? 0);

        $typeLabel = $pdo->query("SELECT label FROM users_types WHERE id = " . (int)$typeId)->fetchColumn();
        if (!$typeLabel) {
            $msg = "Type invalide.";
        }

        if (!$msg && !$isAdmin && !in_array($typeLabel, ['user', 'guest'], true)) {
            $msg = "Vous ne pouvez pas créer ce type d'utilisateur.";
        }

        if (!$msg && $typeLabel === 'admin' && $action === 'add') {
            $adminCount = (int)$pdo->query("
                SELECT COUNT(*) FROM users u JOIN users_types t ON t.id=u.user_type_id WHERE t.label='admin'
            ")->fetchColumn();
            if ($adminCount >= 1) {
                $msg = "Un seul administrateur est autorisé.";
            }
        }

        if (!$msg && $action === 'edit') {
            $curType = $pdo->query("
                SELECT t.label FROM users u JOIN users_types t ON t.id=u.user_type_id WHERE u.id = " . (int)$id
            )->fetchColumn();
            if ($curType === 'admin' && $typeLabel !== 'admin') {
                $msg = "Impossible de changer le rôle de l'admin.";
            }
        }

        if (!$msg && $email && $nom && $prenom && $typeLabel) {
            try {
                if ($action === 'add') {
                    $pass = $mdp !== '' ? $mdp : 'icea';
                    $pdo->prepare("
                        INSERT INTO users (email, password, nom, prenom, user_type_id, actif, protected)
                        VALUES (?, ?, ?, ?, ?, ?, 0)
                    ")->execute([$email, hashPassword($pass), $nom, $prenom, $typeId, $actif]);
                    redirect('/ICEANOTES/pages/professeurs.php?ok=add');
                } else {
                    if ($mdp !== '') {
                        $pdo->prepare("
                            UPDATE users SET email=?, nom=?, prenom=?, password=?, user_type_id=?, actif=?
                            WHERE id=?
                        ")->execute([$email, $nom, $prenom, hashPassword($mdp), $typeId, $actif, $id]);
                    } else {
                        $pdo->prepare("
                            UPDATE users SET email=?, nom=?, prenom=?, user_type_id=?, actif=?
                            WHERE id=?
                        ")->execute([$email, $nom, $prenom, $typeId, $actif, $id]);
                    }
                    redirect('/ICEANOTES/pages/professeurs.php?ok=edit');
                }
            } catch (PDOException $e) {
                $msg = "Cet email existe déjà.";
            }
        }
    }
}

// --- Toggle actif ---
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("
        SELECT u.*, t.label AS type_label
        FROM users u JOIN users_types t ON t.id = u.user_type_id
        WHERE u.id = ?
    ");
    $stmt->execute([$id]);
    $target = $stmt->fetch();

    if (!$target) {
        $msg = "Utilisateur introuvable.";
    } elseif (!canToggleUser($target)) {
        if ($target['type_label'] === 'admin') {
            $msg = t('cannot_deactivate_admin');
        } else {
            $msg = "Action non autorisée.";
        }
    } else {
        $pdo->prepare("UPDATE users SET actif = 1 - actif WHERE id = ?")->execute([$id]);
        redirect('/ICEANOTES/pages/professeurs.php?ok=toggle');
    }
}

// --- Suppression ---
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("
        SELECT u.*, t.label AS type_label
        FROM users u JOIN users_types t ON t.id = u.user_type_id
        WHERE u.id = ?
    ");
    $stmt->execute([$id]);
    $target = $stmt->fetch();

    if (!$target) {
        $msg = "Utilisateur introuvable.";
    } elseif (!canDeleteUser($target)) {
        if ($target['type_label'] === 'admin') {
            $msg = t('cannot_delete_admin');
        } elseif ($target['type_label'] === 'superviseur') {
            $msg = t('cannot_delete_superviseur');
        } else {
            $msg = "Action non autorisée.";
        }
    } else {
        try {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            redirect('/ICEANOTES/pages/professeurs.php?ok=delete');
        } catch (PDOException $e) {
            $msg = "Impossible : modules ou notes assignés.";
        }
    }
}

// --- Édition ? ---
$editRow = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("
        SELECT u.*, t.label AS type_label
        FROM users u JOIN users_types t ON t.id = u.user_type_id
        WHERE u.id = ?
    ");
    $stmt->execute([(int)$_GET['edit']]);
    $editRow = $stmt->fetch() ?: null;
    if ($editRow && !canEditUser($editRow)) {
        $editRow = null;
        $msg = "Accès refusé.";
    }
}

// --- Liste ---
[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(20));

$sort = $sort ?: 'nom';
$sortable = [
    'nom'     => 'u.nom, u.prenom',           // ← tri principal (Nom Prénom)
    'prenom'  => 'u.prenom, u.nom',           // ← NOUVEAU
    'email'   => 'u.email',                   // ← NOUVEAU
    'type'    => 't.label',                   // ← déjà présent
    'modules' => 'nb_modules',                // ← NOUVEAU (alias)
    'actif'   => 'u.actif',                   // ← NOUVEAU
];
if (!isset($sortable[$sort])) $sort = 'nom';
$orderBy = $sortable[$sort] . " $dir";

$where  = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM users u JOIN users_types t ON t.id = u.user_type_id WHERE $whereSql
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT u.*, t.label AS type_label,
           (SELECT COUNT(*) FROM modules m WHERE m.professeur_id = u.id) AS nb_modules
    FROM users u JOIN users_types t ON t.id = u.user_type_id
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$users = $stmt->fetchAll();

$types = $pdo->query("SELECT * FROM users_types ORDER BY id")->fetchAll();

require_once BASE_PATH . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Professeurs</h1>
        <p class="text-sm text-gray-500"><?= $total ?> utilisateur<?= $total > 1 ? 's' : '' ?></p>
    </div>
</div>

<?php if ($msg): ?><div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4"><?= e($msg) ?></div><?php endif; ?>
<?php if (isset($_GET['ok'])): ?><div class="bg-green-50 text-green-700 text-sm p-3 rounded-lg mb-4"><?= t('ok_' . $_GET['ok']) ?></div><?php endif; ?>

<form method="POST" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 grid sm:grid-cols-12 gap-3 items-start">
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= $editRow['id'] ?>"><?php endif; ?>

    <input type="text" name="prenom" required placeholder="<?= e(t('prof_prenom')) ?>"
           value="<?= $editRow ? e($editRow['prenom']) : '' ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">

    <input type="text" name="nom" required placeholder="<?= e(t('prof_nom')) ?>"
           value="<?= $editRow ? e($editRow['nom']) : '' ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">

    <input type="email" name="email" required placeholder="<?= e(t('prof_email')) ?>"
           value="<?= $editRow ? e($editRow['email']) : '' ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-3">

    <input type="text" name="password" placeholder="Mot de passe (défaut : icea)"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">

    <select name="user_type_id" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">
        <?php foreach ($types as $tp):
            if (!$isAdmin && !in_array($tp['label'], ['user', 'guest'], true)) continue;
            if ($tp['label'] === 'admin' && !$editRow) continue;
            $labelKey = 'type_' . $tp['label'];
        ?>
            <option value="<?= $tp['id'] ?>" <?= $editRow && $editRow['user_type_id'] == $tp['id'] ? 'selected' : '' ?>>
                <?= t($labelKey) ?>
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
        <a href="/ICEANOTES/pages/professeurs.php" class="text-sm text-gray-500 px-3 py-2 hover:underline text-center sm:col-span-12"><?= t('cancel') ?></a>
    <?php endif; ?>
</form>

<form method="GET" id="filterForm" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" id="searchInput" value="<?= e($search) ?>"
           placeholder="<?= e(t('search')) ?>"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">

    <?php if ($search !== ''): ?>
        <a href="/ICEANOTES/pages/professeurs.php?per_page=<?= $perPage ?>"
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
                    <th class="text-left px-4 py-3"><?= sortLink('prenom', t('prof_prenom'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('nom', t('prof_nom'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('email', t('prof_email'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('type', 'Type', $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('modules', t('modules_count'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('actif', t('active'), $sort, $dir) ?></th>
                    <th class="text-right px-4 py-3"><?= t('actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($users)): ?>
                <tr><td colspan="7" class="text-center text-gray-400 py-8">—</td></tr>
            <?php else: foreach ($users as $u): ?>
                <?php
                    $typeColor = match($u['type_label']) {
                        'admin'       => 'bg-purple-100 text-purple-700',
                        'superviseur' => 'bg-blue-100 text-blue-700',
                        'user'        => 'bg-green-100 text-green-700',
                        'guest'       => 'bg-gray-100 text-gray-600',
                        default       => 'bg-gray-100 text-gray-600',
                    };
                    $canEdit   = canEditUser($u);
                    $canDelete = canDeleteUser($u);
                    $canToggle = canToggleUser($u);
                ?>
                <tr class="hover:bg-gray-50 transition <?= $u['actif'] ? '' : 'opacity-50 bg-gray-50' ?>">
                    <td class="px-4 py-3 font-medium">
                        <?= e($u['prenom']) ?>
                    </td>
                    <td class="px-4 py-3 font-medium">
                        <?= e($u['nom']) ?>
                        <?php if ($u['protected']): ?>
                            <span class="ml-1 inline-block px-1.5 py-0.5 rounded text-xs bg-yellow-100 text-yellow-700">🔒 <?= t('protected_badge') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-gray-600"><?= e($u['email']) ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-block px-2 py-0.5 rounded text-xs font-medium <?= $typeColor ?>">
                            <?= t('type_' . $u['type_label']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center"><?= (int)$u['nb_modules'] ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($canToggle): ?>
                            <a href="?toggle=<?= $u['id'] ?>" title="<?= e($u['actif'] ? t('deactivate') : t('activate')) ?>"
                               class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition
                                      <?= $u['actif'] ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-200 text-gray-600 hover:bg-gray-300' ?>">
                                <?= $u['actif'] ? '✅ ' . t('active') : '❌ ' . t('inactive') ?>
                            </a>
                        <?php else: ?>
                            <span class="inline-block px-2.5 py-1 rounded-full text-xs font-medium <?= $u['actif'] ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' ?>">
                                <?= $u['actif'] ? '✅ ' . t('active') : '❌ ' . t('inactive') ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?= actionButtons(
                            $canEdit   ? '/ICEANOTES/pages/professeurs.php?edit='   . $u['id'] : '',
                            $canDelete ? '/ICEANOTES/pages/professeurs.php?delete=' . $u['id'] : '',
                            $canToggle ? '/ICEANOTES/pages/professeurs.php?toggle=' . $u['id'] : '',
                            (bool)$u['actif'],
                            $canDelete,
                            false
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

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