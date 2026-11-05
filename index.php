<?php
// index.php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: /ICEANOTES/pages/accueil.php');
} else {
    header('Location: /ICEANOTES/login.php');
}
exit;