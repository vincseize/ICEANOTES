<?php
// pages/diplomes.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
requireAdmin();

$pdo = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $nom   = trim($_POST['nom'] ?? '');
    $ordre = (int)($_POST['ordre'] ?? 0);
    if ($nom !== '') {
        try {
            $pdo->prepare("INSERT INTO diplomes (nom, ordre) VALUES (?, ?)")->execute([$nom, $ordre]);
            redirect('/ICEANOTES/pages/diplomes.php?ok=1');
        } catch (PDOException $e) {
            $msg = "Ce diplôme existe déjà.";
        }
    }
}

if (isset($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM diplomes WHERE id = ?")->execute([(int)$_GET['delete']]);
        redirect('/ICEANOTES/pages/diplomes.php?ok=del');
    } catch (PDOException $e) {
        $msg = "Impossible : des promos ou matières sont liées à ce diplôme.";
    }
}

[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(20));

$sortable = ['nom' => 'd.nom', 'ordre' => 'd.ordre'];
$orderBy = $sortable[$sort] ?? 'd.ordre, d.nom';
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

require_once BASE_PATH . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Diplômes</h1>
        <p class="text-sm text-gray-500"><?= $total ?> diplôme<?= $total > 1 ? 's' : '' ?></p>
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
    <input type="text" name="nom" required placeholder="Nom (ex: L4, Doctorat…)"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <input type="number" name="ordre" value="5" min="0"
           class="w-28 px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg">Ajouter</button>
</form>

<form method="GET" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Rechercher un diplôme…"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <select name="per_page" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <?php foreach ([10, 20, 50, 100] as $n): ?>
            <option value="<?= $n ?>" <?= $perPage == $n ? 'selected' : '' ?>><?= $n ?> / page</option>
        <?php endforeach; ?>
    </select>
    <button class="bg-gray-800 hover:bg-gray-900 text-white text-sm px-4 py-2 rounded-lg">Filtrer</button>
    <a href="<?= e(resetLink('diplomes.php', $perPage)) ?>"
       class="text-sm text-gray-500 px-3 py-2 hover:underline self-center">Réinitialiser</a>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="text-left px-4 py-3"><?= sortLink('nom', 'Nom', $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('ordre', 'Ordre', $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3">Promos</th>
                    <th class="text-center px-4 py-3">Matières</th>
                    <th class="text-right px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($diplomes)): ?>
                <tr><td colspan="5" class="text-center text-gray-400 py-8">Aucun diplôme trouvé.</td></tr>
            <?php else: foreach ($diplomes as $d): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium"><?= e($d['nom']) ?></td>
                    <td class="px-4 py-3 text-center text-gray-600"><?= (int)$d['ordre'] ?></td>
                    <td class="px-4 py-3 text-center"><?= $d['nb_promos'] ?></td>
                    <td class="px-4 py-3 text-center"><?= $d['nb_matieres'] ?></td>
                    <td class="px-4 py-3 text-right">
                        <a href="?delete=<?= $d['id'] ?>"
                           onclick="return confirm('Supprimer ce diplôme ?')"
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