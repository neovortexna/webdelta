<?php
/**
 * ============================================================
 * pencarian_export.php — Export Hasil Pencarian Surat ke Excel
 * PT Delta Ocean Shipping
 * ------------------------------------------------------------
 * Mengekspor SEMUA hasil pencarian surat (lintas folder kapal,
 * sesuai hak akses user) ke file .xlsx, dengan kolom:
 *   NO URUT | NAMA KAPAL/FOLDER | NAMA SURAT | TANGGAL KADALUARSA
 *   | STATUS
 * Diurutkan dari tanggal kedaluwarsa PALING DEKAT (H-EXPIRED
 * terdekat) di baris paling atas.
 *
 * Tampilan Excel: header biru tebal, border rapi, baris selang-
 * seling, kolom TANGGAL KADALUARSA dipaksa bertipe teks (supaya
 * tidak tampil "########"), dan kolom STATUS diwarnai otomatis
 * (hijau/kuning/merah) sesuai kondisi dokumen.
 * ============================================================
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/xlsx_writer.php';
requireLogin();

$pdo    = getDBConnection();
$user   = currentUser();
$search = trim($_GET['search'] ?? '');

$rows = cariSuratLintasKapal($pdo, $user, $search);

$headers = ['NO URUT', 'NAMA KAPAL/FOLDER', 'NAMA SURAT', 'TANGGAL KADALUARSA', 'STATUS'];

$data        = [];
$statusCodes = [];
foreach ($rows as $r) {
    $data[] = [
        $r['no_urut'],
        $r['nama_kapal'],
        $r['nama_sertifikat'],
        $r['tanggal_display'],
        $r['status_label'],
    ];
    $statusCodes[] = $r['status_kode'];
}

$namaFile = 'Pencarian_Surat_' . ($search !== '' ? preg_replace('/[^A-Za-z0-9_-]/', '_', $search) . '_' : '') . date('Ymd_His') . '.xlsx';

streamXlsxDownload($namaFile, $headers, $data, 'Hasil Pencarian Surat', [
    'textColumns'  => [3], // kolom TANGGAL KADALUARSA (index 0-based) dipaksa teks murni
    'statusColumn' => 4,   // kolom STATUS (index 0-based) diwarnai sesuai kode status
    'statusCodes'  => $statusCodes,
]);
