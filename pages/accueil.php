<?php
// pages/accueil.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/header.php';

$pdo     = getDB();
$me      = currentUser();
$isAdmin = isAdmin();
$myId    = (int)$me['id'];

[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(20));

$sortable = [
    'etu'     => 'e.nom, e.prenom',
    'promo'   => 'p.annee DESC, p.nom',
    'filiere' => 'f.nom',
    'diplome' => 'd.ordre, d.nom',
    'matiere' => 'm.nom',
    'type'    => 'n.type_note',
    'valeur'  => 'n.valeur',
    'date'    => 'n.date_saisie',
];
$orderBy = $sortable[$sort] ?? 'n.date_saisie DESC';
if ($sort && isset($sortable[$sort])) $orderBy .= " $dir";
else $orderBy = 'n.date_saisie DESC';

$filterFiliere = (int)($_GET['filiere'] ?? 0);
$filterDiplome = (int)($_GET['diplome'] ?? 0);
$filterPromo   = (int)($_GET['promo'] ?? 0);

$where  = ['1=1'];
$params = [];
if (!$isAdmin) {
    $where[]  = "m.professeur_id = ?";
    $params[] = $myId;
}
if ($search !== '') {
    $where[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR m.nom LIKE ? OR p.nom LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like);
}
if ($filterFiliere) { $where[] = "f.id = ?"; $params[] = $filterFiliere; }
if ($filterDiplome) { $where[] = "d.id = ?"; $params[] = $filterDiplome; }
if ($filterPromo)   { $where[] = "p.id = ?"; $params[] = $filterPromo; }
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM notes n
    JOIN etudiants e ON e.id = n.etudiant_id
    JOIN promos p    ON p.id = e.promo_id
    JOIN filieres f  ON f.id = p.filiere_id
    JOIN diplomes d  ON d.id = p.diplome_id
    JOIN matieres m  ON m.id = n.matiere_id
    WHERE $whereSql
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT n.id, n.type_note, n.valeur, n.date_saisie,
           e.nom AS etu_nom, e.prenom AS etu_prenom,
           p.nom AS promo_nom, p.annee AS promo_annee,
           f.nom AS filiere,
           d.nom AS diplome,
           m.nom AS matiere,
           u.nom AS prof_nom, u.prenom AS prof_prenom
    FROM notes n
    JOIN etudiants e ON e.id = n.etudiant_id
    JOIN promos p    ON p.id = e.promo_id
    JOIN filieres f  ON f.id = p.filiere_id
    JOIN diplomes d  ON d.id = p.diplome_id
    JOIN matieres m  ON m.id = n.matiere_id
    JOIN users u     ON u.id = m.professeur_id
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$notes = $stmt->fetchAll();

$filieres = $pdo->query("SELECT * FROM filieres ORDER BY nom")->fetchAll();
$diplomes = $pdo->query("SELECT * FROM diplomes ORDER BY ordre, nom")->fetchAll();
$promos   = $pdo->query("
    SELECT p.*, f.nom AS filiere, d.nom AS diplome
    FROM promos p
    JOIN filieres f ON f.id = p.filiere_id
    JOIN diplomes d ON d.id = p.diplome_id
    ORDER BY p.annee DESC, p.nom
")->fetchAll();
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= $isAdmin ? 'Toutes les notes' : 'Mes notes' ?></h1>
        <p class="text-sm text-gray-500"><?= $total ?> note<?= $total > 1 ? 's' : '' ?></p>
    </div>
    <a href="/ICEANOTES/pages/notes.php"
       class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg text-center">
        + Ajouter une note
    </a>
</div>

<form method="GET" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-6 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Rechercher étudiant, matière, promo…"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <select name="filiere" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value="">Toutes les filières</option>
        <?php foreach ($filieres as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $filterFiliere == $f['id'] ? 'selected' : '' ?>><?= e($f['nom']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="diplome" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value="">Tous les diplômes</option>
        <?php foreach ($diplomes as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $filterDiplome == $d['id'] ? 'selected' : '' ?>><?= e($d['nom']) ?></option>
        <?php endforeach; ?>
    </select>
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
    <a href="<?= e(resetLink('accueil.php', $perPage)) ?>"
       class="text-sm text-gray-500 px-3 py-2 hover:underline self-center">Réinitialiser</a>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="text-left px-4 py-3"><?= sortLink('etu', 'Étudiant', $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('promo', 'Promo', $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('filiere', 'Filière', $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('diplome', 'Diplôme', $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('matiere', 'Matière', $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3">Prof</th>
                    <th class="text-center px-4 py-3"><?= sortLink('type', 'Type', $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('valeur', 'Note', $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('date', 'Date', $sort, $dir) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($notes)): ?>
                <tr><td colspan="9" class="text-center text-gray-400 py-8">Aucune note trouvée.</td></tr>
            <?php else: foreach ($notes as $n): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800"><?= e($n['etu_nom'] . ' ' . $n['etu_prenom']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= e($n['promo_nom'] . ' (' . $n['promo_annee'] . ')') ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= e($n['filiere']) ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-block px-2 py-0.5 rounded text-xs bg-indigo-100 text-indigo-700"><?= e($n['diplome']) ?></span>
                    </td>
                    <td class="px-4 py-3"><?= e($n['matiere']) ?></td>
                    <td class="px-4 py-3 text-gray-500"><?= e($n['prof_nom'] . ' ' . $n['prof_prenom']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-700"><?= e($n['type_note']) ?></span>
                    </td>
                    <td class="px-4 py-3 text-center font-bold <?= $n['valeur'] >= 10 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= number_format((float)$n['valeur'], 1) ?>/20
                    </td>
                    <td class="px-4 py-3 text-gray-500"><?= date('d/m/Y H:i', strtotime($n['date_saisie'])) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= renderPagination($page, $perPage, $total) ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>