<?php
/**
 * ============================================================
 * api/check_expiry.php — Sumber data Pop-up Peringatan H-7
 * ------------------------------------------------------------
 * Dipakai oleh dashboard.php (otomatis saat halaman dibuka) dan
 * tombol lonceng notifikasi di header. Admin Induk melihat semua
 * kapal; Staf Kapal hanya melihat dokumen kapal miliknya sendiri.
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Akses ditolak.']);
    exit;
}

$pdo  = getDBConnection();
$user = currentUser();

// Sesuai permintaan: semua staf melihat peringatan H-7 dari SEMUA kapal
// (bukan hanya kapal miliknya), sama seperti Admin Induk.
//
// Rentang yang ditampilkan di lonceng:
//   - Surat yang AKAN kedaluwarsa dalam POPUP_WARNING_DAYS (H-7) ke depan
//   - Surat yang SUDAH kedaluwarsa, tapi masih dalam masa tenggang
//     EXPIRED_GRACE_DAYS (7 hari setelah tanggal kedaluwarsanya) — lewat
//     dari itu, surat tsb tidak lagi muncul di lonceng (walau datanya
//     tetap ada & bisa dilihat di Manajemen Dokumen / Pencarian Surat).
$stmt = $pdo->prepare('
    SELECT d.id_dokumen, d.nama_sertifikat, d.tanggal_expired, k.nama_kapal
    FROM tb_dokumen d
    JOIN tb_kapal k ON k.id_kapal = d.id_kapal
    WHERE DATEDIFF(d.tanggal_expired, CURDATE()) BETWEEN -:grace_days AND :warn_days
    ORDER BY d.tanggal_expired ASC
');
$stmt->execute([
    'grace_days' => EXPIRED_GRACE_DAYS,
    'warn_days'  => POPUP_WARNING_DAYS,
]);

$rows = $stmt->fetchAll();

$result = array_map(function ($row) {
    [$kode, $label] = statusDokumen($row['tanggal_expired']);
    return [
        'id_dokumen'      => (int) $row['id_dokumen'],
        'nama_sertifikat' => $row['nama_sertifikat'],
        'nama_kapal'      => $row['nama_kapal'],
        'tanggal_expired' => formatTanggalID($row['tanggal_expired']),
        'status_kode'     => $kode,
        'status_label'    => $label,
    ];
}, $rows);

echo json_encode([
    'total' => count($result),
    'data'  => $result,
]);
