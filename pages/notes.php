<?php
// pages/notes.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';
requireLogin();
$me      = currentUser();
$isAdmin = isAdmin();
$myId    = (int)$me['id'];

$pdo = getDB();
$msg = '';

// --- Ajout ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $etudiantId = (int)($_POST['etudiant_id'] ?? 0);
    $matiereId  = (int)($_POST['matiere_id'] ?? 0);
    $typeNote   = $_POST['type_note'] ?? '';
    $valeur     = $_POST['valeur'] ?? '';

    if (!$isAdmin) {
        $check = $pdo->prepare("SELECT 1 FROM matieres WHERE id = ? AND professeur_id = ?");
        $check->execute([$matiereId, $myId]);
        if (!$check->fetchColumn()) {
            $msg = "Vous ne pouvez saisir des notes que dans vos matières.";
        }
    }

    if (!$msg && $etudiantId && $matiereId && in_array($typeNote, ['CC1','CC2'], true) && is_numeric($valeur)) {
        $valeur = (float)$valeur;
        if ($valeur >= 0 && $valeur <= 20) {
            try {
                $pdo->prepare("INSERT INTO notes (etudiant_id, matiere_id, type_note, valeur) VALUES (?, ?, ?, ?)")
                    ->execute([$etudiantId, $matiereId, $typeNote, $valeur]);
                redirect('/ICEANOTES/pages/notes.php?ok=1');
            } catch (PDOException $e) {
                $msg = "Cette note existe déjà (même étudiant + matière + type).";
            }
        } else {
            $msg = "La note doit être entre 0 et 20.";
        }
    }
}

// --- Suppression ---
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($isAdmin) {
        $pdo->prepare("DELETE FROM notes WHERE id = ?")->execute([$id]);
    } else {
        $pdo->prepare("
            DELETE FROM notes
            WHERE id = ? AND matiere_id IN (SELECT id FROM matieres WHERE professeur_id = ?)
        ")->execute([$id, $myId]);
    }
    redirect('/ICEANOTES/pages/notes.php?ok=del');
}

// --- Liste ---
[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(20));

$sortable = [
    'etu'     => 'e.nom, e.prenom',
    'promo'   => 'p.annee DESC, p.nom',
    'diplome' => 'd.ordre, d.nom',
    'matiere' => 'm.nom',
    'type'    => 'n.type_note',
    'valeur'  => 'n.valeur',
    'date'    => 'n.date_saisie',
];
$orderBy = $sortable[$sort] ?? 'n.date_saisie DESC';
if ($sort && isset($sortable[$sort])) $orderBy .= " $dir";
else $orderBy = 'n.date_saisie DESC';

$where  = ['1=1'];
$params = [];
if (!$isAdmin) { $where[] = "m.professeur_id = ?"; $params[] = $myId; }
if ($search !== '') {
    $where[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR m.nom LIKE ? OR p.nom LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM notes n
    JOIN etudiants e ON e.id = n.etudiant_id
    JOIN promos p    ON p.id = e.promo_id
    JOIN matieres m  ON m.id = n.matiere_id
    WHERE $whereSql
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT n.*, e.nom AS etu_nom, e.prenom AS etu_prenom,
           p.nom AS promo_nom, p.annee AS promo_annee,
           d.nom AS diplome, m.nom AS matiere
    FROM notes n
    JOIN etudiants e ON e.id = n.etudiant_id
    JOIN promos p    ON p.id = e.promo_id
    JOIN diplomes d  ON d.id = p.diplome_id
    JOIN matieres m  ON m.id = n.matiere_id
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$notes = $stmt->fetchAll();

$etudiants = $pdo->query("
    SELECT e.id, e.nom, e.prenom, p.nom AS promo_nom, p.annee AS promo_annee
    FROM etudiants e
    JOIN promos p ON p.id = e.promo_id
    ORDER BY p.annee DESC, e.nom, e.prenom
")->fetchAll();

if ($isAdmin) {
    $matieres = $pdo->query("
        SELECT m.id, m.nom, d.nom AS diplome
        FROM matieres m JOIN diplomes d ON d.id = m.diplome_id
        ORDER BY d.ordre, m.nom
    ")->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT m.id, m.nom, d.nom AS diplome
        FROM matieres m JOIN diplomes d ON d.id = m.diplome_id
        WHERE m.professeur_id = ?
        ORDER BY d.ordre, m.nom
    ");
    $stmt->execute([$myId]);
    $matieres = $stmt->fetchAll();
}

require_once BASE_PATH . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            Notes <?= !$isAdmin ? '— mes matières' : '' ?>
        </h1>
        <p class="text-sm text-gray-500"><?= $total ?> note<?= $total > 1 ? 's' : '' ?></p>
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
    <select name="etudiant_id" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">
        <option value="">— Étudiant —</option>
        <?php foreach ($etudiants as $et): ?>
            <option value="<?= $et['id'] ?>">
                <?= e($et['prenom'] . ' ' . $et['nom'] . ' (' . $et['promo_nom'] . ' ' . $et['promo_annee'] . ')') ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="matiere_id" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value="">— Matière —</option>
        <?php foreach ($matieres as $m): ?>
            <option value="<?= $m['id'] ?>"><?= e($m['nom'] . ' (' . $m['diplome'] . ')') ?></option>
        <?php endforeach; ?>
    </select>
    <select name="type_note" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value="CC1">CC1</option>
        <option value="CC2">CC2</option>
    </select>
    <input type="number" name="valeur" step="0.1" min="0" max="20" required placeholder="Note /20"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg sm:col-span-5">
        Ajouter la note
    </button>
</form>

<form method="GET" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Rechercher étudiant, matière, promo…"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <select name="per_page" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <?php foreach ([10, 20, 50, 100] as $n): ?>
            <option value="<?= $n ?>" <?= $perPage == $n ? 'selected' : '' ?>><?= $n ?> / page</option>
        <?php endforeach; ?>
    </select>
    <button class="bg-gray-800 hover:bg-gray-900 text-white text-sm px-4 py-2 rounded-lg">Filtrer</button>
    <a href="<?= e(resetLink('notes.php', $perPage)) ?>"
       class="text-sm text-gray-500 px-3 py-2 hover:underline self-center">Réinitialiser</a>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="text-left px-4 py-3"><?= sortLink('etu', 'Étudiant', $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('promo', 'Promo', $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('diplome', 'Diplôme', $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('matiere', 'Matière', $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('type', 'Type', $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('valeur', 'Note', $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('date', 'Date', $sort, $dir) ?></th>
                    <th class="text-right px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($notes)): ?>
                <tr><td colspan="8" class="text-center text-gray-400 py-8">Aucune note trouvée.</td></tr>
            <?php else: foreach ($notes as $n): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium"><?= e($n['etu_prenom'] . ' ' . $n['etu_nom']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= e($n['promo_nom'] . ' (' . $n['promo_annee'] . ')') ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-block px-2 py-0.5 rounded text-xs bg-indigo-100 text-indigo-700"><?= e($n['diplome']) ?></span>
                    </td>
                    <td class="px-4 py-3"><?= e($n['matiere']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-700"><?= e($n['type_note']) ?></span>
                    </td>
                    <td class="px-4 py-3 text-center font-bold <?= $n['valeur'] >= 10 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= number_format((float)$n['valeur'], 1) ?>/20
                    </td>
                    <td class="px-4 py-3 text-gray-500"><?= date('d/m/Y H:i', strtotime($n['date_saisie'])) ?></td>
                    <td class="px-4 py-3 text-right">
                        <a href="?delete=<?= $n['id'] ?>"
                           onclick="return confirm('Supprimer cette note ?')"
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