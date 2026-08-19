<?php
/**
 * ============================================================
 * includes/mailer.php
 * PT Delta Ocean Shipping
 * ------------------------------------------------------------
 * Helper pengiriman email terpusat.
 *
 * KENAPA FILE INI DIBUTUHKAN:
 * Sebelumnya sistem hanya memakai fungsi mail() bawaan PHP.
 * Fungsi ini SANGAT SERING GAGAL TANPA ERROR yang jelas di
 * hosting modern (mis. Railway/VPS tanpa MTA lokal terpasang),
 * sehingga email notifikasi terlihat "tidak pernah terkirim"
 * walau kode sudah dijalankan. Ini salah satu penyebab utama
 * kenapa email H-7 belum berjalan.
 *
 * SOLUSI:
 * - Jika library PHPMailer sudah terpasang (composer require
 *   phpmailer/phpmailer) DAN kredensial SMTP di config/database.php
 *   sudah diisi dengan benar (bukan nilai contoh), maka email
 *   dikirim lewat SMTP asli (jauh lebih andal — Gmail, SendGrid,
 *   Mailgun, dsb).
 * - Jika belum, sistem otomatis jatuh ke mail() bawaan PHP
 *   supaya aplikasi tetap berjalan tanpa error fatal.
 * ============================================================
 */

/** Cek apakah kredensial SMTP di config/database.php sudah diisi (bukan nilai contoh default) */
function smtpSudahDikonfigurasi(): bool
{
    return SMTP_HOST !== 'smtp.yourmailserver.com'
        && SMTP_PASS !== 'ganti_dengan_password_smtp'
        && SMTP_HOST !== ''
        && SMTP_USER !== '';
}

/**
 * Kirim satu email HTML.
 * Mengembalikan array ['success' => bool, 'metode' => string, 'pesan' => string]
 *
 * CATATAN: Sesuai permintaan, notifikasi peringatan surat kedaluwarsa TIDAK
 * PERLU lagi dikirim lewat email — notifikasi sekarang cukup dilihat lewat
 * fitur "Notifikasi Surat Expired" di dalam aplikasi (lonceng H-7 & panel
 * kategori di Dashboard). Fungsi ini sengaja dijadikan tidak aktif (no-op)
 * supaya cron/expiry_mailer.php & trigger lama tetap bisa dipanggil tanpa
 * error, tapi tidak benar-benar mengirim email apa pun.
 */
function kirimEmail(string $tujuanEmail, string $tujuanNama, string $subjek, string $htmlBody): array
{
    return [
        'success' => false,
        'metode'  => 'nonaktif',
        'pesan'   => 'Pengiriman notifikasi via email telah dinonaktifkan. Gunakan fitur Notifikasi Surat Expired di dalam aplikasi.',
    ];

    // --- Kode di bawah ini sengaja tidak lagi dijalankan (unreachable) ---
    // Dibiarkan sebagai referensi jika suatu saat email ingin diaktifkan lagi.
    $autoload = __DIR__ . '/../vendor/autoload.php';

    // --- Jalur 1: PHPMailer + SMTP (disarankan, paling andal) ---
    if (file_exists($autoload) && smtpSudahDikonfigurasi()) {
        require_once $autoload;

        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = (int) SMTP_PORT === 465
                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($tujuanEmail, $tujuanNama);
            $mail->isHTML(true);
            $mail->Subject = $subjek;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</tr>'], "\n", $htmlBody));

            $mail->send();
            return ['success' => true, 'metode' => 'smtp', 'pesan' => 'Terkirim via SMTP.'];
        } catch (Throwable $e) {
            // Kalau SMTP gagal (kredensial salah, dsb), coba fallback ke mail() di bawah
            // supaya tidak langsung menyerah.
            $errSmtp = $e->getMessage();
        }
    }

    // --- Jalur 2: fallback mail() bawaan PHP ---
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= 'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM . ">\r\n";

    $sent = @mail($tujuanEmail, $subjek, $htmlBody, $headers);

    if ($sent) {
        return ['success' => true, 'metode' => 'mail()', 'pesan' => 'Terkirim via mail() bawaan PHP.'];
    }

    $pesanGagal = 'Gagal mengirim email (mail() bawaan PHP tidak tersedia/gagal di server ini).';
    if (isset($errSmtp)) {
        $pesanGagal .= ' SMTP juga gagal: ' . $errSmtp;
    } elseif (!file_exists($autoload)) {
        $pesanGagal .= ' Saran: jalankan "composer require phpmailer/phpmailer" di server dan isi SMTP_HOST/SMTP_USER/SMTP_PASS agar pengiriman memakai SMTP asli (jauh lebih andal daripada mail()).';
    } elseif (!smtpSudahDikonfigurasi()) {
        $pesanGagal .= ' Saran: isi SMTP_HOST, SMTP_USER, SMTP_PASS (Environment Variable) di server agar memakai SMTP asli.';
    }

    return ['success' => false, 'metode' => 'gagal', 'pesan' => $pesanGagal];
}
