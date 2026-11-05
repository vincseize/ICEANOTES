<?php
// pages/filieres.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
requireAdmin();

$pdo = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $nom       = trim($_POST['nom'] ?? '');
    $sectionId = (int)($_POST['section_id'] ?? 0);
    if ($nom !== '' && $sectionId > 0) {
        try {
            $pdo->prepare("INSERT INTO filieres (nom, section_id) VALUES (?, ?)")->execute([$nom, $sectionId]);
            redirect('/ICEANOTES/pages/filieres.php?ok=1');
        } catch (PDOException $e) {
            $msg = "Cette filière existe déjà.";
        }
    }
}

if (isset($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM filieres WHERE id = ?")->execute([(int)$_GET['delete']]);
        redirect('/ICEANOTES/pages/filieres.php?ok=del');
    } catch (PDOException $e) {
        $msg = "Impossible : des promos sont liées à cette filière.";
    }
}

[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(20));

$sortable = [
    'nom'     => 'f.nom',
    'section' => 's.nom',
];
$orderBy = $sortable[$sort] ?? 's.nom, f.nom';
if ($sort && isset($sortable[$sort])) $orderBy .= " $dir";

$where  = ['1=1'];
$params = [];
if ($search !== '') {
    $where[]  = "f.nom LIKE ?";
    $params[] = "%$search%";
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM filieres f
    JOIN sections s ON s.id = f.section_id
    WHERE $whereSql
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT f.*, s.nom AS section,
           (SELECT COUNT(*) FROM promos p WHERE p.filiere_id = f.id) AS nb_promos
    FROM filieres f
    JOIN sections s ON s.id = f.section_id
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$filieres = $stmt->fetchAll();

$sections = $pdo->query("SELECT * FROM sections ORDER BY nom")->fetchAll();

require_once BASE_PATH . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Filières</h1>
        <p class="text-sm text-gray-500"><?= $total ?> filière<?= $total > 1 ? 's' : '' ?></p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4"><?= e($msg) ?></div>
<?php endif; ?>
<?php if (isset($_GET['ok'])): ?>
    <div class="bg-green-50 text-green-700 text-sm p-3 rounded-lg mb-4">Opération réussie.</div>
<?php endif; ?>

<form method="POST" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col sm:flex-row gap-3">
    <input type="hidden" name="action" value="add">
    <input type="text" name="nom" required placeholder="Nom de la filière"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <select name="section_id" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <?php foreach ($sections as $s): ?>
            <option value="<?= $s['id'] ?>"><?= e($s['nom']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg">Ajouter</button>
</form>

<form method="GET" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Rechercher une filière…"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <select name="per_page" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <?php foreach ([10, 20, 50, 100] as $n): ?>
            <option value="<?= $n ?>" <?= $perPage == $n ? 'selected' : '' ?>><?= $n ?> / page</option>
        <?php endforeach; ?>
    </select>
    <button class="bg-gray-800 hover:bg-gray-900 text-white text-sm px-4 py-2 rounded-lg">Filtrer</button>
    <a href="<?= e(resetLink('filieres.php', $perPage)) ?>"
       class="text-sm text-gray-500 px-3 py-2 hover:underline self-center">Réinitialiser</a>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="text-left px-4 py-3"><?= sortLink('nom', 'Nom', $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('section', 'Section', $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3">Promos</th>
                    <th class="text-right px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($filieres)): ?>
                <tr><td colspan="4" class="text-center text-gray-400 py-8">Aucune filière trouvée.</td></tr>
            <?php else: foreach ($filieres as $f): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium"><?= e($f['nom']) ?></td>
                    <td class="px-4 py-3"><?= e($f['section']) ?></td>
                    <td class="px-4 py-3 text-center"><?= $f['nb_promos'] ?></td>
                    <td class="px-4 py-3 text-right">
                        <a href="?delete=<?= $f['id'] ?>"
                           onclick="return confirm('Supprimer cette filière ?')"
                           class="text-red-600 hover:underline text-sm">Supprimer</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= renderPagination($page, $perPage, $total) ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>