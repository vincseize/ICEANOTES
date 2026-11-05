<?php
require_once __DIR__ . '/../config/database.php';

echo "<h2>Vérif config/database.php</h2>";
echo "<ul>";
foreach (['t','currentLang','loadTranslations','actionButtons','e','resetLink'] as $f) {
    echo "<li>" . (function_exists($f) ? "✅" : "❌") . " $f</li>";
}
echo "</ul>";

echo "<h2>Vérif app.json</h2>";
echo "<p>APP_NAME : " . APP_NAME . "</p>";
echo "<p>APP_VERSION : " . APP_VERSION . "</p>";
echo "<p>APP_YEAR : " . APP_YEAR . "</p>";

echo "<h2>Vérif lang/</h2>";
foreach (['fr','en','es'] as $l) {
    $path = BASE_PATH . '/lang/' . $l . '.php';
    echo "<p>" . (file_exists($path) ? "✅" : "❌") . " lang/$l.php</p>";
}

echo "<h2>Test t()</h2>";
echo "<p>t('nav_home') = <strong>" . t('nav_home') . "</strong></p>";
echo "<p>t('accueil_title') = <strong>" . t('accueil_title') . "</strong></p>";

echo "<h2>Test actionButtons()</h2>";
echo actionButtons('?edit=1', '?delete=1', '?toggle=1', true);