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
// _SANDBOX/reset_password.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';

// 🔒 Protection : accès réservé aux admin/superviseur connectés
if (!isLoggedIn()) {
    redirect('/ICEANOTES/login.php?error=' . urlencode('Accès réservé aux utilisateurs connectés.'));
}
if (!isSuperviseur()) {
    redirect('/ICEANOTES/pages/accueil.php');
}

$pdo = getDB();
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $msg = "Veuillez saisir un email.";
        $msgType = 'error';
    } else {
        $stmt = $pdo->prepare("
            SELECT u.*, t.label AS type_label
            FROM users u
            JOIN users_types t ON t.id = u.user_type_id
            WHERE u.email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $msg = "Aucun utilisateur trouvé avec l'email : " . htmlspecialchars($email);
            $msgType = 'error';
        } else {
            $newHash = hashPassword('icea');
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                ->execute([$newHash, $user['id']]);

            $msg = "✅ Mot de passe réinitialisé à <code>icea</code> pour <strong>{$user['prenom']} {$user['nom']}</strong> ({$user['email']})";
            $msgType = 'success';
        }
    }
}

$allUsers = $pdo->query("
    SELECT u.email, u.prenom, u.nom, t.label AS type, u.actif
    FROM users u
    JOIN users_types t ON t.id = u.user_type_id
    ORDER BY u.id
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reset Password — ICEANOTES</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen p-6">

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-2xl shadow-lg p-8">

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">🔑 Reset Password</h1>
            <p class="text-sm text-gray-500 mt-1">
                Réinitialise le mot de passe d'un utilisateur à <code class="bg-gray-100 px-1.5 py-0.5 rounded">icea</code>.
            </p>
        </div>

        <?php if ($msg): ?>
            <div class="mb-6 p-4 rounded-xl text-sm
                <?= $msgType === 'success'
                    ? 'bg-green-50 text-green-700 border border-green-200'
                    : 'bg-red-50 text-red-700 border border-red-200' ?>">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="mb-8">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Email de l'utilisateur
            </label>
            <div class="flex flex-col sm:flex-row gap-3">
                <input type="email" name="email" required
                       placeholder="prenom.nom@icea-edu.fr"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-2.5 rounded-lg transition text-sm">
                    Réinitialiser
                </button>
            </div>
            <p class="text-xs text-gray-400 mt-2">
                ⚠️ Le mot de passe sera définitivement remplacé par <code>icea</code>.
            </p>
        </form>

        <div class="border-t border-gray-200 pt-6">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">
                Utilisateurs existants (<?= count($allUsers) ?>)
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 text-left">
                        <tr>
                            <th class="px-3 py-2 font-medium">Email</th>
                            <th class="px-3 py-2 font-medium">Nom</th>
                            <th class="px-3 py-2 font-medium">Type</th>
                            <th class="px-3 py-2 font-medium text-center">Actif</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    <?php foreach ($allUsers as $u): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 font-mono text-xs">
                                <a href="#" onclick="document.querySelector('input[name=email]').value='<?= htmlspecialchars($u['email']) ?>';return false;"
                                   class="text-blue-600 hover:underline">
                                    <?= htmlspecialchars($u['email']) ?>
                                </a>
                            </td>
                            <td class="px-3 py-2"><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?></td>
                            <td class="px-3 py-2">
                                <span class="inline-block px-2 py-0.5 rounded text-xs
                                    <?= match($u['type']) {
                                        'admin'       => 'bg-purple-100 text-purple-700',
                                        'superviseur' => 'bg-blue-100 text-blue-700',
                                        'user', 'professeur' => 'bg-green-100 text-green-700',
                                        default       => 'bg-gray-100 text-gray-600',
                                    } ?>">
                                    <?= htmlspecialchars($u['type']) ?>
                                </span>
                            </td>
                            <td class="px-3 py-2 text-center"><?= $u['actif'] ? '✅' : '❌' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-8 text-center">
            <a href="/ICEANOTES/pages/accueil.php" class="text-sm text-blue-600 hover:underline">← Retour à l'application</a>
        </div>
    </div>
</div>

</body>
</html>