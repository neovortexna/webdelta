<?php
/**
 * ============================================================
 * pencarian_action.php — Backend AJAX untuk Pencarian Surat
 * Lintas Kapal (semua folder kapal sesuai hak akses user)
 * PT Delta Ocean Shipping
 * ============================================================
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$pdo    = getDBConnection();
$user   = currentUser();
$search = trim($_GET['search'] ?? '');

$rows = cariSuratLintasKapal($pdo, $user, $search);

$data = array_map(function ($r) {
    return [
        'no_urut'          => $r['no_urut'],
        'nama_kapal'       => $r['nama_kapal'],
        'nama_sertifikat'  => $r['nama_sertifikat'],
        'file_path'        => $r['file_path'],
        'tanggal_expired'  => $r['tanggal_expired'],
        'tanggal_display'  => $r['tanggal_display'],
        'status_kode'      => $r['status_kode'],
        'status_label'     => $r['status_label'],
    ];
}, $rows);

echo json_encode([
    'success' => true,
    'total'   => count($data),
    'data'    => $data,
]);
