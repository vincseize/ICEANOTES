<?php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';

// 🔒 Protection sandbox
if (!isLoggedIn()) {
    redirect('/ICEANOTES/login.php?error=' . urlencode('Accès réservé aux utilisateurs connectés.'));
}
if (!isSuperviseur()) {
    redirect('/ICEANOTES/pages/accueil.php');
}

// arbo.php
// Génère l'arborescence complète du projet dans _SANDBOX/arbo.txt

$root = dirname(__DIR__); // Racine du projet ICEANOTES
$outputFile = __DIR__ . '/arbo.txt';

function getTree($dir, $prefix = '') {
    $result = '';
    $items = scandir($dir);
    
    // Filtrer les éléments système . et ..
    $items = array_filter($items, function($item) {
        return $item !== '.' && $item !== '..';
    });
    
    // Réindexer le tableau
    $items = array_values($items);
    $count = count($items);
    $i = 0;
    
    foreach ($items as $item) {
        $i++;
        $isLast = ($i === $count);
        $branch = $isLast ? '└── ' : '├── ';
        $result .= $prefix . $branch . $item . "\n";
        
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            $extension = $isLast ? '    ' : '│   ';
            $result .= getTree($path, $prefix . $extension);
        }
    }
    return $result;
}

$header = "ICEANOTES/\n";
$tree = getTree($root);
$fullContent = $header . $tree;

// Sauvegarde dans arbo.txt
file_put_contents($outputFile, $fullContent);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Arborescence ICEANOTES</title>
    <style>
        body { font-family: monospace; background: #f4f4f4; padding: 20px; }
        pre { background: #fff; padding: 15px; border: 1px solid #ccc; border-radius: 5px; }
        .success { color: green; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Générateur d'arborescence</h1>
    <p class="success">✔ Le fichier <code>arbo.txt</code> a été généré avec succès dans le dossier <code>_SANDBOX/</code>.</p>
    <h3>Aperçu :</h3>
    <pre><?= htmlspecialchars($fullContent) ?></pre>
</body>
</html>