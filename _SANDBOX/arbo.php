<?php
// _SANDBOX/arbo.php
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/includes/auth.php';

// 🔒 Protection sandbox
if (!isLoggedIn()) {
    redirect('/ICEANOTES/login.php?error=' . urlencode('Accès réservé aux utilisateurs connectés.'));
}
if (!isSuperviseur()) {
    redirect('/ICEANOTES/pages/accueil.php');
}

$root = dirname(__DIR__);
$outputFile = __DIR__ . '/arbo.txt';

// --- Exclusions ---
$excludeDirs  = ['.git', 'node_modules', 'vendor'];
$excludeFiles = ['.gitignore', 'arbo.txt'];

function getTree(string $dir, array $excludeDirs, array $excludeFiles, string $prefix = ''): string
{
    $result = '';
    $items = scandir($dir);

    $items = array_filter($items, function ($item) use ($excludeDirs, $excludeFiles) {
        if ($item === '.' || $item === '..') return false;
        if (in_array($item, $excludeDirs, true)) return false;
        if (in_array($item, $excludeFiles, true)) return false;
        return true;
    });

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
            $result .= getTree($path, $excludeDirs, $excludeFiles, $prefix . $extension);
        }
    }
    return $result;
}

$header = "ICEANOTES/\n";
$tree = getTree($root, $excludeDirs, $excludeFiles);
$fullContent = $header . $tree;

file_put_contents($outputFile, $fullContent);

// Pour le bouton copier (éviter tout caractère problématique)
$contentForJs = htmlspecialchars($fullContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Arborescence ICEANOTES</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen p-6">
<div class="max-w-5xl mx-auto bg-white rounded-2xl shadow-lg p-8">

    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center text-2xl">
                📁
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Générateur d'arborescence</h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    Exclut <code class="bg-gray-100 px-1 rounded text-xs">.git/</code> et les fichiers système
                </p>
            </div>
        </div>
        <a href="index.php" title="Retour sandbox"
           class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </a>
    </div>

    <div class="mb-4 p-4 rounded-xl bg-green-50 text-green-700 border border-green-200 text-sm flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
        </svg>
        <span>
            Fichier <code class="bg-white px-1.5 py-0.5 rounded">arbo.txt</code> régénéré dans <code class="bg-white px-1.5 py-0.5 rounded">_SANDBOX/</code>.
        </span>
    </div>

    <div class="flex items-center justify-between mb-2">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Aperçu</h2>

        <div class="flex gap-2">
            <button type="button" id="copyBtn"
                    class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                <span id="copyBtnLabel">Copier</span>
            </button>

            <a href="arbo.txt" download
               class="inline-flex items-center gap-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium px-3 py-1.5 rounded-lg transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Télécharger
            </a>
        </div>
    </div>

    <pre id="arboContent"
         class="bg-gray-900 text-gray-100 p-4 rounded-xl text-xs overflow-auto max-h-[600px] font-mono leading-relaxed"><?= htmlspecialchars($fullContent) ?></pre>

</div>

<script>
(function () {
    const btn      = document.getElementById('copyBtn');
    const btnLabel = document.getElementById('copyBtnLabel');
    const pre      = document.getElementById('arboContent');

    btn.addEventListener('click', async function () {
        const text = pre.textContent;

        try {
            if (navigator.clipboard && window.isSecureContext) {
                // API moderne (HTTPS ou localhost)
                await navigator.clipboard.writeText(text);
            } else {
                // Fallback : textarea + execCommand
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.top = '-9999px';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            }

            // Feedback visuel
            const originalText = btnLabel.textContent;
            btnLabel.textContent = 'Copié !';
            btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            btn.classList.add('bg-green-600', 'hover:bg-green-700');

            setTimeout(function () {
                btnLabel.textContent = originalText;
                btn.classList.remove('bg-green-600', 'hover:bg-green-700');
                btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
            }, 2000);

        } catch (err) {
            console.error('Copy failed:', err);
            btnLabel.textContent = 'Erreur';
            setTimeout(function () {
                btnLabel.textContent = 'Copier';
            }, 2000);
        }
    });
})();
</script>

</body>
</html>