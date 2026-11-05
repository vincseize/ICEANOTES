<?php
// pages/professeurs.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
requireAdmin();

$pdo = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $email  = trim($_POST['email'] ?? '');
    $nom    = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $mdp    = $_POST['password'] ?: 'icea';

    if ($email && $nom && $prenom) {
        try {
            $typeId = (int)$pdo->query("SELECT id FROM users_types WHERE label='professeur'")->fetchColumn();
            $pdo->prepare("INSERT INTO users (email, password, nom, prenom, user_type_id) VALUES (?, ?, ?, ?, ?)")
                ->execute([$email, hashPassword($mdp), $nom, $prenom, $typeId]);
            redirect('/ICEANOTES/pages/professeurs.php?ok=1');
        } catch (PDOException $e) {
            $msg = "Cet email existe déjà.";
        }
    }
}

if (isset($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM users WHERE id = ? AND user_type_id = (SELECT id FROM users_types WHERE label='professeur')")
            ->execute([(int)$_GET['delete']]);
        redirect('/ICEANOTES/pages/professeurs.php?ok=del');
    } catch (PDOException $e) {
        $msg = "Impossible : ce professeur a des matières assignées.";
    }
}

[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(20));

$sortable = [
    'nom'   => 'u.nom, u.prenom',
    'email' => 'u.email',
];
$orderBy = $sortable[$sort] ?? 'u.nom, u.prenom';
if ($sort && isset($sortable[$sort])) $orderBy .= " $dir";

$where  = ["t.label = 'professeur'"];
$params = [];
if ($search !== '') {
    $where[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM users u
    JOIN users_types t ON t.id = u.user_type_id
    WHERE $whereSql
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT u.*, (SELECT COUNT(*) FROM matieres m WHERE m.professeur_id = u.id) AS nb_mat
    FROM users u
    JOIN users_types t ON t.id = u.user_type_id
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$profs = $stmt->fetchAll();

require_once BASE_PATH . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Professeurs</h1>
        <p class="text-sm text-gray-500"><?= $total ?> professeur<?= $total > 1 ? 's' : '' ?></p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4"><?= e($msg) ?></div>
<?php endif; ?>
<?php if (isset($_GET['ok'])): ?>
    <div class="bg-green-50 text-green-700 text-sm p-3 rounded-lg mb-4">Opération réussie.</div>
<?php endif; ?>

<form method="POST" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 grid sm:grid-cols-5 gap-3">
    <input type="hidden" name="action" value="add">
    <input type="text" name="prenom" required placeholder="Prénom"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <input type="text" name="nom" required placeholder="Nom"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <input type="email" name="email" required placeholder="prenom.nom@icea-edu.fr"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">
    <input type="text" name="password" placeholder="Mot de passe (défaut : icea)"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg sm:col-span-5">
        Ajouter un professeur
    </button>
</form>

<form method="GET" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Rechercher nom, prénom ou email…"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <select name="per_page" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <?php foreach ([10, 20, 50, 100] as $n): ?>
            <option value="<?= $n ?>" <?= $perPage == $n ? 'selected' : '' ?>><?= $n ?> / page</option>
        <?php endforeach; ?>
    </select>
    <button class="bg-gray-800 hover:bg-gray-900 text-white text-sm px-4 py-2 rounded-lg">Filtrer</button>
    <a href="<?= e(resetLink('professeurs.php', $perPage)) ?>"
       class="text-sm text-gray-500 px-3 py-2 hover:underline self-center">Réinitialiser</a>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="text-left px-4 py-3"><?= sortLink('nom', 'Nom', $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('email', 'Email', $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3">Matières</th>
                    <th class="text-right px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($profs)): ?>
                <tr><td colspan="4" class="text-center text-gray-400 py-8">Aucun professeur trouvé.</td></tr>
            <?php else: foreach ($profs as $p): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium"><?= e($p['prenom'] . ' ' . $p['nom']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= e($p['email']) ?></td>
                    <td class="px-4 py-3 text-center"><?= $p['nb_mat'] ?></td>
                    <td class="px-4 py-3 text-right">
                        <a href="?delete=<?= $p['id'] ?>"
                           onclick="return confirm('Supprimer ce professeur ?')"
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