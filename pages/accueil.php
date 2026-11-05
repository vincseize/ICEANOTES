<?php
// pages/accueil.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/header.php';

$pdo = getDB();
$me = currentUser();
$isSuperviseur = isSuperviseur();
$myId = (int)$me['id'];

// --- Filtres ---
$filterFiliere = (int)($_GET['filiere'] ?? 0);
$filterNiveau  = trim($_GET['niveau'] ?? '');
$filterPromo   = (int)($_GET['promo'] ?? 0);
$filterType    = (int)($_GET['type'] ?? 0);
$search        = trim($_GET['q'] ?? '');

[$page, $perPage, $offset, $sort, $dir] = array_values(listParams(20));

// --- Tri ---
$sortable = [
    'etu'     => 'e.nom, e.prenom',
    'niveau'  => 'p.ordre, p.nom',
    'promo'   => 'p.annee, p.promo',
    'filiere' => 'f.nom',
    'module'  => 'm.nom',
    'type'    => 'nt.ordre, nt.nom',
    'valeur'  => 'n.valeur',
    'date'    => 'n.date_saisie',
];
$orderBy = $sortable[$sort] ?? 'n.date_saisie DESC';
if ($sort && isset($sortable[$sort])) $orderBy .= " $dir";
else $orderBy = 'n.date_saisie DESC';

// --- WHERE ---
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
if ($filterType)    { $where[] = "nt.id = ?"; $params[] = $filterType; }
$whereSql = implode(' AND ', $where);

// --- Total ---
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM notes n
    JOIN etudiants e ON e.id = n.etudiant_id
    JOIN promotions p ON p.id = e.promotion_id
    JOIN filieres f ON f.id = p.filiere_id
    JOIN modules m ON m.id = n.module_id
    JOIN note_types nt ON nt.id = n.note_type_id
    WHERE $whereSql
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

// --- Lignes ---
$stmt = $pdo->prepare("
    SELECT n.id, n.valeur, n.date_saisie,
           e.nom AS etu_nom, e.prenom AS etu_prenom,
           p.nom AS niveau, p.promo, p.annee,
           f.nom AS filiere,
           m.nom AS module,
           nt.nom AS type_nom, nt.sur AS type_sur, nt.coef AS type_coef,
           u.nom AS prof_nom, u.prenom AS prof_prenom
    FROM notes n
    JOIN etudiants e ON e.id = n.etudiant_id
    JOIN promotions p ON p.id = e.promotion_id
    JOIN filieres f ON f.id = p.filiere_id
    JOIN modules m ON m.id = n.module_id
    JOIN note_types nt ON nt.id = n.note_type_id
    JOIN users u ON u.id = m.professeur_id
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$notes = $stmt->fetchAll();

// --- Listes pour les filtres ---
$filieres  = $pdo->query("SELECT * FROM filieres ORDER BY nom")->fetchAll();
$niveaux   = ['L1', 'L2', 'L3', 'Master'];
$promotions = $pdo->query("
    SELECT p.*, f.nom AS filiere FROM promotions p JOIN filieres f ON f.id = p.filiere_id
    WHERE p.actif = 1 ORDER BY p.ordre, f.nom, p.annee
")->fetchAll();
$noteTypes = $pdo->query("SELECT * FROM note_types WHERE actif = 1 ORDER BY ordre, nom")->fetchAll();

$hasFilters = $search !== '' || $filterFiliere || $filterNiveau || $filterPromo || $filterType;
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

<!-- Filtres en live -->
<form method="GET" id="filterForm" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-6 flex flex-col md:flex-row flex-wrap gap-3">
    <input type="text" name="q" id="searchInput" value="<?= e($search) ?>"
           placeholder="<?= e(t('search')) ?>"
           class="flex-1 min-w-[200px] px-3 py-2 border border-gray-300 rounded-lg text-sm">

    <select name="filiere" id="filiereSelect" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value=""><?= t('filiere') ?></option>
        <?php foreach ($filieres as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $filterFiliere == $f['id'] ? 'selected' : '' ?>>
                <?= e($f['nom']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="niveau" id="niveauSelect" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value=""><?= t('niveau') ?></option>
        <?php foreach ($niveaux as $n): ?>
            <option value="<?= $n ?>" <?= $filterNiveau === $n ? 'selected' : '' ?>>
                <?= e($n) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="promo" id="promoSelect" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value=""><?= t('promotion_label') ?></option>
        <?php foreach ($promotions as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $filterPromo == $p['id'] ? 'selected' : '' ?>>
                <?= e($p['nom'] . ' — ' . ($p['promo'] ?? '') . ' (' . $p['annee'] . ')') ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="type" id="typeSelect" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value=""><?= t('type') ?></option>
        <?php foreach ($noteTypes as $tp): ?>
            <option value="<?= $tp['id'] ?>" <?= $filterType == $tp['id'] ? 'selected' : '' ?>>
                <?= e($tp['nom']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <?php if ($hasFilters): ?>
        <a href="/ICEANOTES/pages/accueil.php?per_page=<?= $perPage ?>"
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
                <?php
                    $sur20 = ((float)$n['valeur'] / (float)$n['type_sur']) * 20;
                    $ok = $sur20 >= 10;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800"><?= e($n['etu_prenom'] . ' ' . $n['etu_nom']) ?></td>
                    <td class="px-4 py-3"><span class="inline-block px-2 py-0.5 rounded text-xs bg-indigo-100 text-indigo-700"><?= e($n['niveau']) ?></span></td>
                    <td class="px-4 py-3"><?= $n['promo'] ? e($n['promo']) : '<span class="text-gray-400 italic text-xs">(sans promo)</span>' ?></td>
                    <td class="px-4 py-3"><span class="inline-block px-2 py-0.5 rounded text-xs bg-purple-100 text-purple-700"><?= e($n['filiere']) ?></span></td>
                    <td class="px-4 py-3"><?= e($n['module']) ?></td>
                    <td class="px-4 py-3 text-gray-500"><?= e($n['prof_prenom'] . ' ' . $n['prof_nom']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-700"><?= e($n['type_nom']) ?></span>
                    </td>
                    <td class="px-4 py-3 text-center font-bold <?= $ok ? 'text-green-600' : 'text-red-600' ?>">
                        <?= number_format((float)$n['valeur'], 1) ?> / <?= (float)$n['type_sur'] ?>
                        <?php if ((float)$n['type_coef'] != 1): ?>
                            <span class="text-xs text-gray-400 block">×<?= (float)$n['type_coef'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs"><?= date('d/m/Y H:i', strtotime($n['date_saisie'])) ?></td>
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

<script>
(function () {
    const search  = document.getElementById('searchInput');
    const filiere = document.getElementById('filiereSelect');
    const niveau  = document.getElementById('niveauSelect');
    const promo   = document.getElementById('promoSelect');
    const type    = document.getElementById('typeSelect');
    const perPage = document.getElementById('perPageSelect');

    let searchTimeout = null;

    // Recherche texte : debounce 400ms
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

    // Selects : changement immédiat
    [filiere, niveau, promo, type].forEach(function (sel) {
        if (!sel) return;
        sel.addEventListener('change', function () {
            const url = new URL(window.location.href);
            if (sel.value === '') {
                url.searchParams.delete(sel.name);
            } else {
                url.searchParams.set(sel.name, sel.value);
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        });
    });

    // per_page
    if (perPage) {
        perPage.addEventListener('change', function () {
            window.location.href = this.value;
        });
    }
})();
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>