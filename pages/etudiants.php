<?php
// pages/etudiants.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';
requireLogin();

$pdo = getDB();
$msg = '';

// --- Ajout ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $nom    = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $promo  = (int)($_POST['promo_id'] ?? 0);
    if ($nom !== '' && $prenom !== '' && $promo > 0) {
        $pdo->prepare("INSERT INTO etudiants (nom, prenom, promo_id) VALUES (?, ?, ?)")
            ->execute([$nom, $prenom, $promo]);
        redirect('/ICEANOTES/pages/etudiants.php?ok=1');
    }
}

// --- Suppression ---
if (isset($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM etudiants WHERE id = ?")->execute([(int)$_GET['delete']]);
        redirect('/ICEANOTES/pages/etudiants.php?ok=del');
    } catch (PDOException $e) {
        $msg = "Impossible : des notes existent pour cet étudiant.";
    }
}

// --- Liste ---
[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(20));
$filterPromo = (int)($_GET['promo'] ?? 0);

$sortable = [
    'nom'     => 'e.nom, e.prenom',
    'prenom'  => 'e.prenom, e.nom',
    'promo'   => 'p.annee DESC, p.nom',
    'filiere' => 'f.nom',
    'diplome' => 'd.ordre, d.nom',
];
$orderBy = $sortable[$sort] ?? 'p.annee DESC, e.nom, e.prenom';
if ($sort && isset($sortable[$sort])) $orderBy .= " $dir";

$where  = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR p.nom LIKE ? OR f.nom LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like);
}
if ($filterPromo > 0) {
    $where[]  = "e.promo_id = ?";
    $params[] = $filterPromo;
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM etudiants e
    JOIN promos p   ON p.id = e.promo_id
    JOIN filieres f ON f.id = p.filiere_id
    JOIN diplomes d ON d.id = p.diplome_id
    WHERE $whereSql
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT e.*,
           p.nom AS promo_nom, p.annee AS promo_annee,
           f.nom AS filiere, d.nom AS diplome
    FROM etudiants e
    JOIN promos p   ON p.id = e.promo_id
    JOIN filieres f ON f.id = p.filiere_id
    JOIN diplomes d ON d.id = p.diplome_id
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$etudiants = $stmt->fetchAll();

$promos = $pdo->query("
    SELECT p.*, f.nom AS filiere, d.nom AS diplome
    FROM promos p
    JOIN filieres f ON f.id = p.filiere_id
    JOIN diplomes d ON d.id = p.diplome_id
    ORDER BY p.annee DESC, f.nom, d.ordre
")->fetchAll();

require_once BASE_PATH . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Étudiants</h1>
        <p class="text-sm text-gray-500"><?= $total ?> étudiant<?= $total > 1 ? 's' : '' ?></p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4"><?= e($msg) ?></div>
<?php endif; ?>
<?php if (isset($_GET['ok'])): ?>
    <div class="bg-green-50 text-green-700 text-sm p-3 rounded-lg mb-4">Opération réussie.</div>
<?php endif; ?>

<form method="POST" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 grid sm:grid-cols-4 gap-3">
    <input type="hidden" name="action" value="add">
    <input type="text" name="prenom" required placeholder="Prénom"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <input type="text" name="nom" required placeholder="Nom"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <select name="promo_id" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value="">— Promo —</option>
        <?php foreach ($promos as $p): ?>
            <option value="<?= $p['id'] ?>">
                <?= e($p['nom'] . ' — ' . $p['annee'] . ' (' . $p['filiere'] . ' / ' . $p['diplome'] . ')') ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg">Ajouter</button>
</form>

<form method="GET" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Rechercher nom, prénom, promo…"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <select name="promo" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value="">Toutes les promos</option>
        <?php foreach ($promos as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $filterPromo == $p['id'] ? 'selected' : '' ?>>
                <?= e($p['nom'] . ' (' . $p['annee'] . ')') ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="per_page" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <?php foreach ([10, 20, 50, 100] as $n): ?>
            <option value="<?= $n ?>" <?= $perPage == $n ? 'selected' : '' ?>><?= $n ?> / page</option>
        <?php endforeach; ?>
    </select>
    <button class="bg-gray-800 hover:bg-gray-900 text-white text-sm px-4 py-2 rounded-lg">Filtrer</button>
    <a href="<?= e(resetLink('etudiants.php', $perPage)) ?>"
       class="text-sm text-gray-500 px-3 py-2 hover:underline self-center">Réinitialiser</a>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="text-left px-4 py-3"><?= sortLink('prenom', 'Prénom', $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('nom', 'Nom', $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('promo', 'Promo', $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('filiere', 'Filière', $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('diplome', 'Diplôme', $sort, $dir) ?></th>
                    <th class="text-right px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($etudiants)): ?>
                <tr><td colspan="6" class="text-center text-gray-400 py-8">Aucun étudiant trouvé.</td></tr>
            <?php else: foreach ($etudiants as $et): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium"><?= e($et['prenom']) ?></td>
                    <td class="px-4 py-3"><?= e($et['nom']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= e($et['promo_nom'] . ' (' . $et['promo_annee'] . ')') ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= e($et['filiere']) ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-block px-2 py-0.5 rounded text-xs bg-indigo-100 text-indigo-700">
                            <?= e($et['diplome']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="?delete=<?= $et['id'] ?>"
                           onclick="return confirm('Supprimer cet étudiant ?')"
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