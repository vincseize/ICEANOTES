<?php
// login.php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: /ICEANOTES/pages/accueil.php');
    exit;
}

$error = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — ICEANOTES</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-8">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-blue-600">ICEANOTES</h1>
            <p class="text-sm text-gray-500 mt-1">Espace professeurs</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="actions/login_action.php" method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" required
                       placeholder="prenom.nom@icea-edu.fr"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                <input type="password" name="password" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 rounded-lg transition">
                Se connecter
            </button>
        </form>
    </div>

</body>
</html>