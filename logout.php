<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Catat riwayat logout SEBELUM sesi dihancurkan
if (isLoggedIn()) {
    $pdo = getDBConnection();
    catatRiwayatLogin($pdo, (int) ($_SESSION['id_user'] ?? 0), 'logout');
}

$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;
