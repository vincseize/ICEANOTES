<?php
// login.php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: /ICEANOTES/pages/accueil.php');
    exit;
}

$error    = $_GET['error'] ?? null;
$oldEmail = $_COOKIE['remember_email'] ?? '';
$remember = !empty($_COOKIE['remember_email']);
$lang     = currentLang();
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(t('login_title')) ?> — <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="/ICEANOTES/assets/icons/favicon.svg">
    <link rel="manifest" href="/ICEANOTES/manifest.json">
    <meta name="theme-color" content="#039BE5">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#039BE5',
                        'primary-dark': '#0277BD',
                    },
                    fontFamily: {
                        display: ['Inter', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="bg-slate-50 min-h-screen antialiased" style="font-family: 'Inter', system-ui, sans-serif;">

<div class="flex min-h-screen">

    <!-- ============ COLONNE GAUCHE (desktop uniquement) ============ -->
    <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden bg-gradient-to-br from-[#039BE5] to-[#0277BD] text-white">
        <div class="absolute -top-24 -left-24 w-96 h-96 rounded-full bg-white/10"></div>
        <div class="absolute -bottom-32 -right-32 w-[500px] h-[500px] rounded-full bg-white/5"></div>

        <div class="relative z-10 flex flex-col justify-between p-12 w-full">
            <!-- Logo + nom établissement -->
            <div class="flex items-center gap-6 mb-8">
                <div class="bg-white p-4 rounded-2xl shadow-lg shrink-0">
                    <img src="/ICEANOTES/assets/icons/icon.svg" alt="ICEA" class="h-20 w-auto object-contain">
                </div>
                <div class="min-w-0">
                    <h1 class="text-2xl xl:text-3xl font-bold leading-tight break-words">
                        <?= e(t('app_full_name')) ?>
                    </h1>
                </div>
            </div>

            <!-- Message central -->
            <div class="max-w-md">
                <h2 class="text-4xl xl:text-5xl font-extrabold leading-tight mb-6">
                    <?= e(t('app_brand')) ?>
                </h2>
                <p class="text-lg opacity-90 leading-relaxed">
                    <?= e(t('app_tagline')) ?>
                </p>
            </div>

            <!-- Footer gauche -->
            <div class="flex gap-4 items-center opacity-80 text-sm">
                <span>© <?= date('Y') ?> <?= e(t('app_copyright')) ?></span>
            </div>
        </div>
    </div>

    <!-- ============ COLONNE DROITE (formulaire) ============ -->
    <div class="w-full lg:w-1/2 flex flex-col p-6 sm:p-8 bg-slate-50">

        <!-- Logo mobile -->
        <div class="lg:hidden flex flex-col items-center text-center mb-8">
            <div class="bg-white p-3 rounded-xl shadow-sm mb-3">
                <img src="/ICEANOTES/assets/icons/icon.svg" alt="ICEA" class="h-16 w-auto">
            </div>
            <h1 class="text-xl md:text-2xl font-bold text-slate-900 leading-tight">
                <?= e(t('app_full_name')) ?>
            </h1>
        </div>

        <!-- Formulaire centré verticalement -->
        <div class="flex-1 flex items-center justify-center py-8">
            <div class="w-full max-w-md">

                <!-- Titre + sous-titre -->
                <div class="space-y-2 mb-8">
                    <h2 class="text-3xl font-extrabold text-slate-900"><?= e(t('login_heading')) ?></h2>
                    <p class="text-slate-500"><?= e(t('login_subheading')) ?></p>
                </div>

                <!-- Message d'erreur -->
                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 text-sm p-3 rounded-xl mb-6 flex items-start gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M12 3a9 9 0 100 18 9 9 0 000-18z"/>
                        </svg>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Formulaire -->
                <form action="actions/login_action.php" method="POST" class="space-y-4" autocomplete="on">

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700 mb-1">
                            <?= e(t('login_identifier')) ?>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <input
                                type="email" name="email" id="email" required
                                value="<?= htmlspecialchars($oldEmail) ?>"
                                placeholder="prenom.nom@icea-edu.fr"
                                class="block w-full pl-10 pr-3 py-3 border border-slate-200 rounded-xl bg-white text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                        </div>
                    </div>

                    <!-- Mot de passe -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700 mb-1">
                            <?= e(t('login_password')) ?>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <input
                                type="password" name="password" id="password" required
                                placeholder="••••••••"
                                class="block w-full pl-10 pr-12 py-3 border border-slate-200 rounded-xl bg-white text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                            <button type="button" id="togglePassword"
                                    aria-label="<?= e(t('login_show_password')) ?>"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                                <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.584 10.587A2 2 0 0013.414 13.414M9.879 5.377A9.956 9.956 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.956 9.956 0 01-4.293 5.297M6.228 6.228A9.956 9.956 0 002.458 12c1.274 4.057 5.064 7 9.542 7a9.956 9.956 0 004.294-.964"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Remember me -->
                    <div class="flex items-center justify-between pt-2">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600 cursor-pointer select-none">
                            <input type="checkbox" name="remember" value="1" <?= $remember ? 'checked' : '' ?>
                                   class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                            <?= e(t('login_remember')) ?>
                        </label>
                    </div>

                    <!-- Submit -->
                    <button type="submit"
                            class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-xl text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-all duration-200 shadow-lg shadow-primary/25">
                        <?= e(t('login_submit')) ?>
                    </button>
                </form>

                <!-- Sélecteur de langue -->
                <div class="mt-6 text-center">
                    <form method="GET" class="inline-flex gap-1 text-xs text-slate-400">
                        <?php foreach (['fr'=>'FR', 'en'=>'EN', 'es'=>'ES'] as $code => $lbl): ?>
                            <button type="submit" name="lang" value="<?= $code ?>"
                                    class="px-2 py-1 rounded hover:bg-slate-200 transition <?= $lang === $code ? 'font-bold text-primary' : '' ?>">
                                <?= $lbl ?>
                            </button>
                        <?php endforeach; ?>
                    </form>
                </div>

                <!-- Footer droit -->
                <div class="mt-4 text-center text-slate-500 text-xs">
                    <?= e(APP_NAME) ?> <?= date('Y') ?> — <a href="https://icea-edu.fr" target="_blank" class="hover:text-primary transition-colors">ICEA</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const pwd       = document.getElementById('password');
        const toggle    = document.getElementById('togglePassword');
        const eyeOpen   = document.getElementById('eyeOpen');
        const eyeClosed = document.getElementById('eyeClosed');

        toggle.addEventListener('click', () => {
            const isPwd = pwd.type === 'password';
            pwd.type = isPwd ? 'text' : 'password';
            eyeOpen.classList.toggle('hidden', isPwd);
            eyeClosed.classList.toggle('hidden', !isPwd);
            toggle.setAttribute('aria-label', isPwd ? '<?= e(t('login_hide_password')) ?>' : '<?= e(t('login_show_password')) ?>');
        });
    })();
</script>

</body>
</html>