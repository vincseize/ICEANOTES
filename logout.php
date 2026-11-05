<?php
// logout.php
require_once __DIR__ . '/config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_COOKIE['remember_token'])) {
    $hash = hash('sha256', $_COOKIE['remember_token']);
    getDB()->prepare("DELETE FROM remember_tokens WHERE token_hash = ?")->execute([$hash]);
    setcookie('remember_token', '', ['expires' => time() - 3600, 'path' => '/']);
    setcookie('remember_email', '', ['expires' => time() - 3600, 'path' => '/']);
}

session_unset();
session_destroy();
header('Location: /ICEANOTES/login.php');
exit;