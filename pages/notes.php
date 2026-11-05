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

// --- Ajout / Édition ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['add', 'edit'], true)) {
        $etudiantId = (int)($_POST['etudiant_id'] ?? 0);
        $matiereId  = (int)($_POST['matiere_id'] ?? 0);
        $typeNote   = $_POST['type_note'] ?? '';
        $valeur     = $_POST['valeur'] ?? '';
        $id         = (int)($_POST['id'] ?? 0);

        // Restriction prof : ne peut noter que dans ses matières
        if (!$isAdmin) {
            $check = $pdo->prepare("SELECT 1 FROM matieres WHERE id = ? AND professeur_id = ?");
            $check->execute([$matiereId, $myId]);
            if (!$check->fetchColumn()) {
                $msg = t('error_prof_matiere');
            }
        }

        if (!$msg && $etudiantId && $matiereId && in_array($typeNote, ['CC1','CC2'], true) && is_numeric($valeur)) {
            $valeur = (float)$valeur;
            if ($valeur >= 0 && $valeur <= 20) {
                // Vérif cohérence : la matière doit être de la même filière que l'étudiant
                $check2 = $pdo->prepare("
                    SELECT 1
                    FROM etudiants e
                    JOIN promos p   ON p.id = e.promo_id
                    JOIN matieres m ON m.id = ?
                    WHERE e.id = ? AND p.filiere_id = m.filiere_id
                ");
                $check2->execute([$matiereId, $etudiantId]);
                if (!$check2->fetchColumn()) {
                    $msg = "La matière et l'étudiant ne sont pas dans la même filière.";
                }
            } else {
                $msg = t('error_note_range');
            }
        } elseif (!$msg) {
            $msg = t('error_champs');
        }

        if (!$msg) {
            try {
                if ($action === 'add') {
                    $pdo->prepare("INSERT INTO notes (etudiant_id, matiere_id, type_note, valeur) VALUES (?, ?, ?, ?)")
                        ->execute([$etudiantId, $matiereId, $typeNote, $valeur]);
                    redirect('/ICEANOTES/pages/notes.php?ok=add');
                } else {
                    $pdo->prepare("UPDATE notes SET etudiant_id=?, matiere_id=?, type_note=?, valeur=? WHERE id=?")
                        ->execute([$etudiantId, $matiereId, $typeNote, $valeur, $id]);
                    redirect('/ICEANOTES/pages/notes.php?ok=edit');
                }
            } catch (PDOException $e) {
                $msg = t('error_note_exists');
            }
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
    redirect('/ICEANOTES/pages/notes.php?ok=delete');
}

// --- Édition ? ---
$editRow = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM notes WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRow = $stmt->fetch() ?: null;
    if ($editRow && !$isAdmin) {
        $check = $pdo->prepare("SELECT 1 FROM matieres WHERE id = ? AND professeur_id = ?");
        $check->execute([$editRow['matiere_id'], $myId]);
        if (!$check->fetchColumn()) {
            $editRow = null;
            $msg = "Accès refusé.";
        }
    }
}

// --- Liste : recherche + tri + pagination ---
[$page, $perPage, $offset, $search, $sort, $dir] = array_values(listParams(20));

$sortable = [
    'etu'     => 'e.nom, e.prenom',
    'promo'   => 'p.annee DESC, p.nom',
    'diplome' => 'd.ordre, d.nom',
    'filiere' => 'f.nom',
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
if (!$isAdmin) {
    $where[]  = "m.professeur_id = ?";
    $params[] = $myId;
}
if ($search !== '') {
    $where[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR m.nom LIKE ? OR p.nom LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM notes n
    JOIN etudiants e ON e.id = n.etudiant_id
    JOIN promos p    ON p.id = e.promo_id
    JOIN matieres m  ON m.id = n.matiere_id
    WHERE $whereSql
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT n.*,
           e.nom AS etu_nom, e.prenom AS etu_prenom,
           p.nom AS promo_nom, p.annee AS promo_annee,
           d.nom AS diplome,
           f.nom AS filiere,
           m.nom AS matiere
    FROM notes n
    JOIN etudiants e ON e.id = n.etudiant_id
    JOIN promos p    ON p.id = e.promo_id
    JOIN matieres m  ON m.id = n.matiere_id
    JOIN diplomes d  ON d.id = m.diplome_id
    JOIN filieres f  ON f.id = m.filiere_id
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$notes = $stmt->fetchAll();

// --- Étudiants pour le select (avec filière + promo) ---
$etudiants = $pdo->query("
    SELECT e.id, e.nom, e.prenom,
           p.nom AS promo_nom, p.annee AS promo_annee,
           f.id AS filiere_id, f.nom AS filiere
    FROM etudiants e
    JOIN promos p   ON p.id = e.promo_id
    JOIN filieres f ON f.id = p.filiere_id
    WHERE e.actif = 1
    ORDER BY p.annee DESC, e.nom, e.prenom
")->fetchAll();

// --- Matières pour le select (avec diplôme + filière) ---
if ($isAdmin) {
    $matieres = $pdo->query("
        SELECT m.id, m.nom,
               d.nom AS diplome,
               f.id AS filiere_id, f.nom AS filiere
        FROM matieres m
        JOIN diplomes d ON d.id = m.diplome_id
        JOIN filieres f ON f.id = m.filiere_id
        WHERE m.actif = 1
        ORDER BY d.ordre, f.nom, m.nom
    ")->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT m.id, m.nom,
               d.nom AS diplome,
               f.id AS filiere_id, f.nom AS filiere
        FROM matieres m
        JOIN diplomes d ON d.id = m.diplome_id
        JOIN filieres f ON f.id = m.filiere_id
        WHERE m.professeur_id = ? AND m.actif = 1
        ORDER BY d.ordre, f.nom, m.nom
    ");
    $stmt->execute([$myId]);
    $matieres = $stmt->fetchAll();
}

require_once BASE_PATH . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            <?= $isAdmin ? t('notes_title') : t('notes_title_prof') ?>
        </h1>
        <p class="text-sm text-gray-500"><?= t('notes_page_count', ['n' => $total]) ?></p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4"><?= e($msg) ?></div>
<?php endif; ?>
<?php if (isset($_GET['ok'])): ?>
    <div class="bg-green-50 text-green-700 text-sm p-3 rounded-lg mb-4"><?= t('ok_' . $_GET['ok']) ?></div>
<?php endif; ?>

<!-- Formulaire ajout / édition -->
<form method="POST" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 grid sm:grid-cols-12 gap-3 items-start">
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= $editRow['id'] ?>"><?php endif; ?>

    <!-- Étudiant -->
    <select name="etudiant_id" id="etudiantSelect" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-4">
        <option value="" data-filiere="">— <?= t('etudiant') ?> —</option>
        <?php foreach ($etudiants as $et): ?>
            <option value="<?= $et['id'] ?>"
                    data-filiere="<?= $et['filiere_id'] ?>"
                    <?= $editRow && $editRow['etudiant_id'] == $et['id'] ? 'selected' : '' ?>>
                <?= e($et['prenom'] . ' ' . $et['nom'] . ' (' . $et['promo_nom'] . ' ' . $et['promo_annee'] . ' — ' . $et['filiere'] . ')') ?>
            </option>
        <?php endforeach; ?>
    </select>

    <!-- Matière -->
    <select name="matiere_id" id="matiereSelect" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-4">
        <option value="" data-filiere="">— <?= t('matiere') ?> —</option>
        <?php foreach ($matieres as $m): ?>
            <option value="<?= $m['id'] ?>"
                    data-filiere="<?= $m['filiere_id'] ?>"
                    <?= $editRow && $editRow['matiere_id'] == $m['id'] ? 'selected' : '' ?>>
                <?= e($m['nom'] . ' (' . $m['diplome'] . ' — ' . $m['filiere'] . ')') ?>
            </option>
        <?php endforeach; ?>
    </select>

    <!-- Type -->
    <select name="type_note" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-1">
        <option value="CC1" <?= $editRow && $editRow['type_note'] === 'CC1' ? 'selected' : '' ?>>CC1</option>
        <option value="CC2" <?= $editRow && $editRow['type_note'] === 'CC2' ? 'selected' : '' ?>>CC2</option>
    </select>

    <!-- Valeur -->
    <input type="number" name="valeur" step="0.1" min="0" max="20" required placeholder="<?= e(t('note_slash')) ?>"
           value="<?= $editRow ? (float)$editRow['valeur'] : '' ?>"
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm sm:col-span-2">

    <!-- Submit -->
    <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg sm:col-span-1">
        <?= $editRow ? t('save') : t('add') ?>
    </button>

    <?php if ($editRow): ?>
        <a href="/ICEANOTES/pages/notes.php" class="text-sm text-gray-500 px-3 py-2 hover:underline text-center sm:col-span-12"><?= t('cancel') ?></a>
    <?php endif; ?>
</form>

<!-- Recherche + pagination -->
<form method="GET" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col md:flex-row gap-3">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('search')) ?>"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
    <select name="per_page" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <?php foreach ([10, 20, 50, 100] as $n): ?>
            <option value="<?= $n ?>" <?= $perPage == $n ? 'selected' : '' ?>><?= t('per_page', ['n' => $n]) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="bg-gray-800 hover:bg-gray-900 text-white text-sm px-4 py-2 rounded-lg"><?= t('filter') ?></button>
    <a href="<?= e(resetLink('notes.php', $perPage)) ?>" class="text-sm text-gray-500 px-3 py-2 hover:underline self-center"><?= t('reset') ?></a>
</form>

<!-- Tableau -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="text-left px-4 py-3"><?= sortLink('etu', t('etudiant'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('promo', t('promo'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('diplome', t('diplome'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('filiere', t('filiere'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('matiere', t('matiere'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('type', t('type'), $sort, $dir) ?></th>
                    <th class="text-center px-4 py-3"><?= sortLink('valeur', t('note'), $sort, $dir) ?></th>
                    <th class="text-left px-4 py-3"><?= sortLink('date', t('date'), $sort, $dir) ?></th>
                    <th class="text-right px-4 py-3"><?= t('actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (empty($notes)): ?>
                <tr><td colspan="9" class="text-center text-gray-400 py-8">—</td></tr>
            <?php else: foreach ($notes as $n): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium"><?= e($n['etu_prenom'] . ' ' . $n['etu_nom']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= e($n['promo_nom'] . ' (' . $n['promo_annee'] . ')') ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-block px-2 py-0.5 rounded text-xs bg-indigo-100 text-indigo-700"><?= e($n['diplome']) ?></span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-block px-2 py-0.5 rounded text-xs bg-purple-100 text-purple-700"><?= e($n['filiere']) ?></span>
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
                        <?= actionButtons(
                            '/ICEANOTES/pages/notes.php?edit='   . $n['id'],
                            '/ICEANOTES/pages/notes.php?delete=' . $n['id'],
                            '',
                            true,
                            true,
                            false
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= renderPagination($page, $perPage, $total) ?>

<!-- Filtrage dynamique des matières selon la filière de l'étudiant -->
<script>
(function () {
    const etu = document.getElementById('etudiantSelect');
    const mat = document.getElementById('matiereSelect');
    if (!etu || !mat) return;

    function filterMatieres() {
        const filiere = etu.selectedOptions[0]?.dataset.filiere || '';
        Array.from(mat.options).forEach(opt => {
            if (!opt.value) return;
            opt.hidden = filiere && opt.dataset.filiere !== filiere;
        });
        // Si la matière sélectionnée est masquée → reset
        if (mat.selectedOptions[0]?.hidden) mat.value = '';
    }

    etu.addEventListener('change', filterMatieres);
    filterMatieres(); // init
})();
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>