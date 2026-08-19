<?php
/**
 * ============================================================
 * cron/trigger_expiry_email.php
 * PT Delta Ocean Shipping
 * ------------------------------------------------------------
 * PINTU MASUK #2 (dipicu lewat URL) untuk mengirim email
 * peringatan dokumen H-7, satu kali per hari.
 *
 * INI JAWABAN untuk kebutuhan "email tetap terkirim jam 07:00
 * WALAU WEB BELUM/TIDAK DIBUKA SAMA SEKALI". PHP di server hosting
 * biasa (termasuk Railway) TIDAK BISA membangunkan dirinya sendiri
 * tanpa salah satu dari dua hal ini:
 *
 *   (a) crontab asli di server (lihat cron/check_expiry_cron.php), ATAU
 *   (b) sesuatu dari LUAR yang memanggil URL ini setiap hari jam 07:00.
 *
 * Kalau server Anda TIDAK punya akses shell/crontab (banyak terjadi
 * di Railway/shared hosting modern), pakai opsi (b): daftarkan URL
 * file ini di layanan penjadwal GRATIS, misalnya:
 *   - cron-job.org      (paling umum & gratis)
 *   - UptimeRobot        (mode "HTTP(s)" tiap 5 menit, aman karena
 *                          endpoint ini sudah anti-dobel-kirim)
 *   - Railway "Cron Job" service (kalau tersedia di paket Anda)
 *   - GitHub Actions dengan jadwal `schedule: cron: "0 0 * * *"` (UTC)
 *
 * CARA PAKAI:
 * 1) Ganti CRON_SECRET_TOKEN di config/database.php (atau set
 *    Environment Variable CRON_SECRET_TOKEN di server) dengan token
 *    rahasia Anda sendiri — supaya URL ini tidak bisa dipicu sembarang
 *    orang.
 * 2) Jadwalkan layanan cron eksternal memanggil URL berikut setiap
 *    hari (boleh lebih sering dari 1x/hari, karena SUDAH aman
 *    anti-dobel-kirim — email tetap hanya terkirim 1x/hari):
 *
 *      https://domain-anda.com/cron/trigger_expiry_email.php?token=TOKEN_RAHASIA_ANDA
 *
 *    Jadwalkan jam 07:00 WIB (00:00 UTC), atau tiap 5-15 menit kalau
 *    layanan cron eksternalnya tidak mendukung timezone Asia/Jakarta
 *    — endpoint ini otomatis menolak mengirim sebelum jam 07:00 WIB.
 *
 * File ini AMAN dipanggil berkali-kali dalam sehari: hanya benar-benar
 * mengirim email SATU KALI per hari (lihat cron/expiry_mailer.php).
 * ============================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/expiry_mailer.php';

header('Content-Type: application/json');

// --- Guard 1: token rahasia wajib cocok ---
$token = $_GET['token'] ?? '';
if (!hash_equals(CRON_SECRET_TOKEN, (string) $token)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'pesan' => 'Token tidak valid.']);
    exit;
}

// --- Guard 2: hanya boleh mengirim mulai jam EMAIL_SEND_HOUR (07:00) waktu Asia/Jakarta ---
$jamSekarang = (int) date('G');
if ($jamSekarang < EMAIL_SEND_HOUR) {
    echo json_encode([
        'status' => 'menunggu',
        'pesan'  => 'Belum jam ' . EMAIL_SEND_HOUR . ':00 WIB, email H-7 belum dikirim. Sekarang jam ' . date('H:i') . ' WIB.',
    ]);
    exit;
}

$pdo   = getDBConnection();
$hasil = jalankanPengecekanExpiryEmail($pdo);

echo json_encode($hasil, JSON_PRETTY_PRINT);
