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
// Vérifie que l'utilisateur est connecté (admin ou superviseur uniquement)

require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';

// --- Rediriger si non connecté ---
if (!isLoggedIn()) {
    redirect('/ICEANOTES/login.php?error=' . urlencode('Accès réservé aux utilisateurs connectés.'));
}

// --- Optionnel : restreindre aux admins/superviseurs ---
if (!isSuperviseur()) {
    redirect('/ICEANOTES/pages/accueil.php');
}

// --- Si un fichier est demandé, le servir ---
$file = $_GET['file'] ?? '';

if ($file !== '') {
    // Sécurité : éviter les remontées de dossier
    $file = str_replace(['..', './'], '', $file);
    $file = ltrim($file, '/');

    $path = __DIR__ . '/' . $file;

    // Vérifier que le fichier existe et est dans le dossier
    if (!file_exists($path) || !is_file($path)) {
        http_response_code(404);
        echo "Fichier introuvable : " . htmlspecialchars($file);
        exit;
    }

    // Servir selon l'extension
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    switch ($ext) {
        case 'php':
            // PHP : on inclut (le fichier s'exécute)
            require $path;
            break;

        case 'css':
            header('Content-Type: text/css; charset=UTF-8');
            readfile($path);
            break;

        case 'js':
            header('Content-Type: application/javascript; charset=UTF-8');
            readfile($path);
            break;

        case 'html':
        case 'htm':
            header('Content-Type: text/html; charset=UTF-8');
            readfile($path);
            break;

        default:
            header('Content-Type: application/octet-stream');
            readfile($path);
            break;
    }
    exit;
}

// --- Si pas de fichier demandé, afficher un menu ---
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Sandbox — ICEANOTES</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen p-6">
<div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-lg p-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-2">🧪 Sandbox — Accès protégé</h1>
    <p class="text-sm text-gray-500 mb-6">
        Connecté en tant que <strong><?= e($user['prenom'] . ' ' . $user['nom']) ?></strong> (<?= e($user['type_label']) ?>).
    </p>

    <h2 class="text-lg font-semibold mb-3">Fichiers disponibles</h2>
    <ul class="space-y-2 text-sm">
        <?php
        $files = glob(__DIR__ . '/*.php');
        foreach ($files as $f):
            $name = basename($f);
            if ($name === 'guard.php' || $name === 'index.php') continue;
        ?>
            <li>
                <a href="guard.php?file=<?= urlencode($name) ?>"
                   class="text-blue-600 hover:underline">
                    <?= htmlspecialchars($name) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="mt-8 pt-6 border-t border-gray-200">
        <a href="/ICEANOTES/pages/accueil.php" class="text-sm text-blue-600 hover:underline">← Retour à l'application</a>
    </div>
</div>
</body>
</html>