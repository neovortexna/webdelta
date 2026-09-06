<?php
/**
 * ============================================================
 * notifikasi_action.php
 * PT Delta Ocean Shipping
 * ------------------------------------------------------------
 * Endpoint AJAX untuk mengubah status tinjauan (belum/proses/
 * selesai) pada satu item di halaman Notifikasi Surat Expired.
 * Dipanggil oleh notifikasi_expired.php lewat axios.post().
 * ============================================================
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesi Anda telah berakhir. Silakan login ulang.']);
    exit;
}

$pdo  = getDBConnection();
requireValidSession($pdo);

$tipe   = $_POST['tipe'] ?? '';
$id     = (int) ($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';

if (!in_array($tipe, ['dokumen', 'kapal'], true) || $id <= 0 || !in_array($status, ['belum', 'proses', 'selesai'], true)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid.']);
    exit;
}

try {
    $ok = simpanStatusNotifikasi($pdo, $tipe, $id, $status);
    echo json_encode(['success' => $ok, 'status' => $status]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan status ke database.']);
}
