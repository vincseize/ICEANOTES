<?php
// pages/accueil.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/header.php';

$pdo = getDB();
$me = currentUser();
$isSuperviseur = isSuperviseur();
$myId = (int)$me['id'];

[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(20));

$sortable = [
    'etu'     => 'e.nom, e.prenom',
    'niveau'  => 'p.ordre, p.nom',
    'promo'   => 'p.annee, p.promo',
    'filiere' => 'f.nom',
    'module'  => 'm.nom',
    'type'    => 'n.type_note',
    'valeur'  => 'n.valeur',
    'date'    => 'n.date_saisie',
];
$orderBy = $sortable[$sort] ?? 'n.date_saisie DESC';
if ($sort && isset($sortable[$sort])) $orderBy .= " $dir";
else $orderBy = 'n.date_saisie DESC';

$filterFiliere = (int)($_GET['filiere'] ?? 0);
$filterNiveau  = trim($_GET['niveau'] ?? '');
$filterPromo   = (int)($_GET['promo'] ?? 0);

$where = ['1=1']; $params = [];
if (!$isSuperviseur) { $where[] = "m.professeur_id = ?"; $params[] = $myId; }
if ($search !== '') {
    $where[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR m.nom LIKE ? OR p.nom LIKE ? OR p.promo LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like, $like);
}
if ($filterFiliere) { $where[] = "f.id = ?"; $params[] = $filterFiliere; }
if ($filterNiveau)  { $where[] = "p.nom = ?"; $params[] = $filterNiveau; }
if ($filterPromo)   { $where[] = "p.id = ?"; $params[] = $filterPromo; }
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM notes n
    JOIN etudiants e ON e.id = n.etudiant_id
    JOIN promotions p ON p.id = e.promotion_id
    JOIN filieres f ON f.id = p.filiere_id
    JOIN modules m ON m.id = n.module_id
    WHERE $whereSql
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT n.id, n.type_note, n.valeur, n.date_saisie,
           e.nom AS etu_nom, e.prenom AS etu_prenom,
           p.nom AS niveau, p.promo, p.annee,
           f.nom AS filiere,
           m.nom AS module,
           u.nom AS prof_nom, u.prenom AS prof_prenom
    FROM notes n
    JOIN etudiants e ON e.id = n.etudiant_id
    JOIN promotions p ON p.id = e.promotion_id
    JOIN filieres f ON f.id = p.filiere_id
    JOIN modules m ON m.id = n.module_id
    JOIN users u ON u.id = m.professeur_id
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$notes = $stmt->fetchAll();

$filieres  = $pdo->query("SELECT * FROM filieres ORDER BY nom")->fetchAll();
$niveaux   = ['L1', 'L2', 'L3', 'Master'];
$promotions = $pdo->query("
    SELECT p.*, f.nom AS filiere
    FROM promotions p JOIN filieres f ON f.id = p.filiere_id
    ORDER BY p.ordre, f.nom, p.annee
")->fetchAll();
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            <?= $isSuperviseur ? t('accueil_title') : t('accueil_title_prof') ?>
        </h1>
        <p class="text-sm text-gray-500"><?= t('notes_total', ['n' => $total]) ?></p>
    </div>
    <a href="/ICEANOTES/pages/notes.php"
       class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg text-center">
        <?= t('add_note') ?>
    </a>
</div>

<form method="GET" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-6 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('search')) ?>"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <select name="filiere" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value=""><?= t('filiere') ?></option>
        <?php foreach ($filieres as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $filterFiliere == $f['id'] ? 'selected' : '' ?>><?= e($f['nom']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="niveau" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value=""><?= t('niveau') ?></option>
        <?php foreach ($niveaux as $n): ?>
            <option value="<?= $n ?>" <?= $filterNiveau === $n ? 'selected' : '' ?>><?= e($n) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="promo" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value=""><?= t('promotion_label') ?></option>
        <?php foreach ($promotions as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $filterPromo == $p['id'] ? 'selected' : '' ?>>
                <?= e($p['nom'] . ' — ' . ($p['promo'] ?? '') . ' (' . $p['annee'] . ')') ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="per_page" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <?php foreach ([10, 20, 50, 100] as $n): ?>
            <option value="<?= $n ?>" <?= $perPage == $n ? 'selected' : '' ?>><?= t('per_page', ['n' => $n]) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="bg-gray-800 hover:bg-gray-900 text-white text-sm px-4 py-2 rounded-lg"><?= t('filter') ?></button>
    <a href="<?= e(resetLink('accueil.php', $perPage)) ?>" class="text-sm text-gray-500 px-3 py-2 hover:underline self-center"><?= t('reset') ?></a>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="text-left px-4 py-3"><?= sortLink('etu', t('etudiant'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('niveau', t('niveau'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('promo', t('promo'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('filiere', t('filiere'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('module', t('module'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= t('professeur') ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('type', t('type'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('valeur', t('note'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('date', t('date'), $sort, $dir) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($notes)): ?>
                <tr><td colspan="9" class="text-center text-gray-400 py-8">—</td></tr>
            <?php else: foreach ($notes as $n): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800"><?= e($n['etu_nom'] . ' ' . $n['etu_prenom']) ?></td>
                    <td class="px-4 py-3"><span class="inline-block px-2 py-0.5 rounded text-xs bg-indigo-100 text-indigo-700"><?= e($n['niveau']) ?></span></td>
                    <td class="px-4 py-3"><?= $n['promo'] ? e($n['promo']) : '<span class="text-gray-400 italic text-xs">(sans promo)</span>' ?></td>
                    <td class="px-4 py-3"><span class="inline-block px-2 py-0.5 rounded text-xs bg-purple-100 text-purple-700"><?= e($n['filiere']) ?></span></td>
                    <td class="px-4 py-3"><?= e($n['module']) ?></td>
                    <td class="px-4 py-3 text-gray-500"><?= e($n['prof_nom'] . ' ' . $n['prof_prenom']) ?></td>
                    <td class="px-4 py-3 text-center"><span class="inline-block px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-700"><?= e($n['type_note']) ?></span></td>
                    <td class="px-4 py-3 text-center font-bold <?= $n['valeur'] >= 10 ? 'text-green-600' : 'text-red-600' ?>"><?= number_format((float)$n['valeur'], 1) ?>/20</td>
                    <td class="px-4 py-3 text-gray-500"><?= date('d/m/Y H:i', strtotime($n['date_saisie'])) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= renderPagination($page, $perPage, $total) ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>