<?php
/**
 * ============================================================
 * check_expiry_cron.php
 * PT Delta Ocean Shipping
 * ------------------------------------------------------------
 * PINTU MASUK #1 (CLI / crontab asli di server) untuk mengirim
 * email peringatan dokumen H-7, satu kali per hari.
 *
 * Dijalankan otomatis oleh Cron Job server setiap hari jam 07:00
 * (waktu Asia/Jakarta). Contoh crontab (jalankan `crontab -e` di
 * server yang punya akses shell/CLI, mis. VPS/cPanel/shared hosting):
 *
 *   0 7 * * * /usr/bin/php /path/ke/delta-ocean-shipping/cron/check_expiry_cron.php >> /path/ke/delta-ocean-shipping/cron/cron.log 2>&1
 *
 * CATATAN PENTING UNTUK HOSTING TANPA AKSES CLI/CRONTAB (mis. Railway):
 * Jika server tidak menyediakan crontab baris perintah, JANGAN pakai
 * file ini. Pakai PINTU MASUK #2: cron/trigger_expiry_email.php
 * (dipicu lewat URL oleh layanan cron eksternal). Lihat komentar di
 * file tersebut untuk instruksi lengkap.
 *
 * Script ini TIDAK boleh diakses lewat browser (lihat guard di bawah).
 * Logika pengiriman ada di cron/expiry_mailer.php (dipakai bersama
 * oleh pintu masuk CLI ini maupun pintu masuk HTTP trigger), sehingga
 * email dijamin hanya terkirim SATU KALI per hari walau dipicu dari
 * dua jalur berbeda.
 * ============================================================
 */

// Guard: hanya boleh dijalankan lewat CLI (cron), bukan lewat HTTP.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Script ini hanya dapat dijalankan melalui CLI / Cron Job. Untuk memicu lewat URL, gunakan cron/trigger_expiry_email.php.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/expiry_mailer.php';

echo '[' . date('Y-m-d H:i:s') . "] Menjalankan pengecekan dokumen H-7 & pengiriman email...\n";

$pdo = getDBConnection();
$hasil = jalankanPengecekanExpiryEmail($pdo);

echo $hasil['pesan'] . "\n";
if (!empty($hasil['detail'])) {
    foreach ($hasil['detail'] as $baris) {
        echo '  - ' . $baris . "\n";
    }
}

echo '[' . date('Y-m-d H:i:s') . '] Selesai. Total dokumen H-7: ' . ($hasil['total_dokumen'] ?? 0)
    . ', total email terkirim: ' . ($hasil['total_email'] ?? 0) . "\n";
