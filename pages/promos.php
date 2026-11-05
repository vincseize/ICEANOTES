<?php
// pages/promos.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
requireAdmin();

$pdo = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $nom       = trim($_POST['nom'] ?? '');
    $annee     = trim($_POST['annee'] ?? '');
    $filiereId = (int)($_POST['filiere_id'] ?? 0);
    $diplomeId = (int)($_POST['diplome_id'] ?? 0);

    if ($nom !== '' && $annee !== '' && $filiereId > 0 && $diplomeId > 0) {
        try {
            $pdo->prepare("INSERT INTO promos (nom, annee, filiere_id, diplome_id) VALUES (?, ?, ?, ?)")
                ->execute([$nom, $annee, $filiereId, $diplomeId]);
            redirect('/ICEANOTES/pages/promos.php?ok=1');
        } catch (PDOException $e) {
            $msg = "Cette promo existe déjà (même nom + année + filière + diplôme).";
        }
    } else {
        $msg = "Champs invalides.";
    }
}

if (isset($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM promos WHERE id = ?")->execute([(int)$_GET['delete']]);
        redirect('/ICEANOTES/pages/promos.php?ok=del');
    } catch (PDOException $e) {
        $msg = "Impossible : des étudiants sont liés à cette promo.";
    }
}

[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(20));

$sortable = [
    'nom'     => 'p.nom',
    'annee'   => 'p.annee',
    'filiere' => 'f.nom',
    'diplome' => 'd.ordre, d.nom',
];
$orderBy = $sortable[$sort] ?? 'p.annee DESC, f.nom, d.ordre';
if ($sort && isset($sortable[$sort])) $orderBy .= " $dir";

$where  = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = "(p.nom LIKE ? OR p.annee LIKE ? OR f.nom LIKE ? OR d.nom LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM promos p
    JOIN filieres f ON f.id = p.filiere_id
    JOIN diplomes d ON d.id = p.diplome_id
    WHERE $whereSql
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT p.*, f.nom AS filiere, d.nom AS diplome,
           (SELECT COUNT(*) FROM etudiants e WHERE e.promo_id = p.id) AS nb_etu
    FROM promos p
    JOIN filieres f ON f.id = p.filiere_id
    JOIN diplomes d ON d.id = p.diplome_id
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$promos = $stmt->fetchAll();

$filieres = $pdo->query("SELECT * FROM filieres ORDER BY nom")->fetchAll();
$diplomes = $pdo->query("SELECT * FROM diplomes ORDER BY ordre, nom")->fetchAll();

require_once BASE_PATH . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Promos</h1>
        <p class="text-sm text-gray-500"><?= $total ?> promo<?= $total > 1 ? 's' : '' ?></p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4"><?= e($msg) ?></div>
<?php endif; ?>
<?php if (isset($_GET['ok'])): ?>
    <div class="bg-green-50 text-green-700 text-sm p-3 rounded-lg mb-4">Opération réussie.</div>
<?php endif; ?>

<form method="POST" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 grid sm:grid-cols-6 gap-3">
    <input type="hidden" name="action" value="add">
    <input type="text" name="nom" required placeholder="Nom de la promo"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">
    <input type="text" name="annee" required placeholder="Année (2026-2027)"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <select name="filiere_id" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value="">— Filière —</option>
        <?php foreach ($filieres as $f): ?>
            <option value="<?= $f['id'] ?>"><?= e($f['nom']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="diplome_id" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value="">— Diplôme —</option>
        <?php foreach ($diplomes as $d): ?>
            <option value="<?= $d['id'] ?>"><?= e($d['nom']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg">Ajouter</button>
</form>

<form method="GET" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Rechercher nom, année, filière, diplôme…"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <select name="per_page" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <?php foreach ([10, 20, 50, 100] as $n): ?>
            <option value="<?= $n ?>" <?= $perPage == $n ? 'selected' : '' ?>><?= $n ?> / page</option>
        <?php endforeach; ?>
    </select>
    <button class="bg-gray-800 hover:bg-gray-900 text-white text-sm px-4 py-2 rounded-lg">Filtrer</button>
    <a href="<?= e(resetLink('promos.php', $perPage)) ?>"
       class="text-sm text-gray-500 px-3 py-2 hover:underline self-center">Réinitialiser</a>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="text-left px-4 py-3"><?= sortLink('nom', 'Nom', $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('annee', 'Année', $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('filiere', 'Filière', $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('diplome', 'Diplôme', $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3">Étudiants</th>
                    <th class="text-right px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($promos)): ?>
                <tr><td colspan="6" class="text-center text-gray-400 py-8">Aucune promo trouvée.</td></tr>
            <?php else: foreach ($promos as $p): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium"><?= e($p['nom']) ?></td>
                    <td class="px-4 py-3"><?= e($p['annee']) ?></td>
                    <td class="px-4 py-3"><?= e($p['filiere']) ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-block px-2 py-0.5 rounded text-xs bg-indigo-100 text-indigo-700"><?= e($p['diplome']) ?></span>
                    </td>
                    <td class="px-4 py-3 text-center"><?= $p['nb_etu'] ?></td>
                    <td class="px-4 py-3 text-right">
                        <a href="?delete=<?= $p['id'] ?>"
                           onclick="return confirm('Supprimer cette promo ?')"
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