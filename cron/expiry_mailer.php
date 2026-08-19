<?php
/**
 * ============================================================
 * cron/expiry_mailer.php
 * PT Delta Ocean Shipping
 * ------------------------------------------------------------
 * Logika INTI pengiriman email peringatan dokumen H-7, dipakai
 * bersama oleh dua "pintu masuk":
 *
 *   1. cron/check_expiry_cron.php   -> dijalankan lewat CLI/crontab asli di server.
 *   2. cron/trigger_expiry_email.php -> dipicu lewat URL oleh layanan cron
 *                                       eksternal (cron-job.org, UptimeRobot,
 *                                       Railway Cron Job, cPanel Cron, dst),
 *                                       supaya email tetap terkirim jam 07:00
 *                                       WALAU TIDAK ADA YANG MEMBUKA WEB.
 *
 * ANTI-DOBEL-KIRIM:
 * Sebelum mengirim, fungsi ini SELALU mengecek tabel
 * tb_notifikasi_email_log apakah tanggal hari ini sudah pernah
 * tercatat terkirim. Kalau sudah, fungsi berhenti (skip) — jadi
 * aman dipicu berkali-kali dalam sehari (mis. oleh cron eksternal
 * yang mengecek tiap beberapa menit), email tetap hanya terkirim
 * SATU KALI per hari.
 *
 * Ambang peringatan memakai POPUP_WARNING_DAYS (H-7) — SAMA
 * PERSIS dengan ambang yang dipakai pop-up peringatan di
 * dashboard, sesuai permintaan: email otomatis untuk surat yang
 * mendekati H-7.
 *
 * Sesuai permintaan: semua staf punya hak lihat & kelola PENUH
 * atas SEMUA kapal (sama seperti Admin Induk) — jadi email
 * peringatan H-7 ini pun berupa SATU ringkasan armada penuh yang
 * dikirim ke SEMUA user terdaftar (Admin Induk maupun Staf Kapal),
 * tidak lagi dipersonalisasi per kapal milik masing-masing staf.
 * ============================================================
 */

require_once __DIR__ . '/../includes/mailer.php';

/** Apakah email untuk HARI INI sudah pernah tercatat terkirim? */
function emailExpiryHariIniSudahTerkirim(PDO $pdo): bool
{
    $stmt = $pdo->query('SELECT 1 FROM tb_notifikasi_email_log WHERE tanggal = CURDATE()');
    return (bool) $stmt->fetchColumn();
}

/** Catat bahwa email untuk HARI INI sudah dikirim (kunci anti-dobel-kirim) */
function catatEmailExpiryTerkirim(PDO $pdo, int $totalDokumen, int $totalEmail): void
{
    $stmt = $pdo->prepare('
        INSERT INTO tb_notifikasi_email_log (tanggal, dikirim_pada, total_dokumen, total_email)
        VALUES (CURDATE(), NOW(), :total_dokumen, :total_email)
        ON DUPLICATE KEY UPDATE dikirim_pada = NOW(), total_dokumen = VALUES(total_dokumen), total_email = VALUES(total_email)
    ');
    $stmt->execute(['total_dokumen' => $totalDokumen, 'total_email' => $totalEmail]);
}

/** Susun baris <tr> tabel HTML untuk isi email */
function susunBarisEmailExpiry(array $daftarDokumen): string
{
    $rows = '';
    foreach ($daftarDokumen as $d) {
        [$kode, $label] = statusDokumen($d['tanggal_expired']);
        $warna = $kode === 'expired' ? '#C0392B' : '#B7791F';
        $rows .= '<tr>'
            . '<td style="padding:8px;border-bottom:1px solid #eee;">' . htmlspecialchars($d['nama_kapal']) . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #eee;">' . htmlspecialchars($d['nama_sertifikat']) . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #eee;">' . formatTanggalID($d['tanggal_expired']) . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #eee;color:' . $warna . ';font-weight:bold;">' . htmlspecialchars($label) . '</td>'
            . '</tr>';
    }
    return $rows;
}

/** Bungkus baris tabel menjadi HTML email lengkap */
function susunEmailHtmlExpiry(string $rows): string
{
    return '
<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto;">
    <h2 style="color:#0B2542;">PT Delta Ocean Shipping</h2>
    <p>Berikut daftar dokumen kapal yang mendekati H-7 atau sudah melewati tanggal kedaluwarsa:</p>
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="background:#0B2542;color:#fff;">
                <th style="padding:8px;text-align:left;">Kapal</th>
                <th style="padding:8px;text-align:left;">Sertifikat</th>
                <th style="padding:8px;text-align:left;">Tgl Kedaluwarsa</th>
                <th style="padding:8px;text-align:left;">Status</th>
            </tr>
        </thead>
        <tbody>' . $rows . '</tbody>
    </table>
    <p style="margin-top:16px;">Silakan segera perbarui dokumen terkait melalui sistem.</p>
</div>';
}

/**
 * Jalankan pengecekan + pengiriman email H-7.
 * Aman dipanggil berkali-kali — hanya benar-benar mengirim SATU KALI per hari.
 *
 * Mengembalikan array ringkasan proses (untuk log CLI maupun respons JSON trigger HTTP).
 */
function jalankanPengecekanExpiryEmail(PDO $pdo): array
{
    if (emailExpiryHariIniSudahTerkirim($pdo)) {
        return [
            'status' => 'skip',
            'pesan'  => 'Email peringatan H-7 untuk hari ini sudah pernah dikirim sebelumnya. Tidak dikirim ulang.',
        ];
    }

    // Ambang H-7, SAMA dengan yang dipakai pop-up peringatan di dashboard.
    $stmt = $pdo->prepare('
        SELECT d.nama_sertifikat, d.tanggal_expired, k.nama_kapal
        FROM tb_dokumen d
        JOIN tb_kapal k ON k.id_kapal = d.id_kapal
        WHERE DATEDIFF(d.tanggal_expired, CURDATE()) <= :warn_days
        ORDER BY d.tanggal_expired ASC
    ');
    $stmt->execute(['warn_days' => POPUP_WARNING_DAYS]);
    $dokumen = $stmt->fetchAll();

    if (!$dokumen) {
        // Tidak ada dokumen H-7 hari ini. Tetap dicatat sebagai "sudah dicek hari ini"
        // supaya tidak terus-menerus mengecek ulang di hari yang sama.
        catatEmailExpiryTerkirim($pdo, 0, 0);
        return [
            'status' => 'ok',
            'pesan'  => 'Tidak ada dokumen yang mendekati/lewat H-7 hari ini. Tidak ada email dikirim.',
            'total_dokumen' => 0,
            'total_email'   => 0,
        ];
    }

    $totalEmailTerkirim = 0;
    $log = [];

    // ------------------------------------------------------------
    // Kirim email ringkasan SELURUH ARMADA ke SETIAP user yang punya
    // email terdaftar — Admin Induk maupun Staf Kapal, sama persis.
    //
    // (Sebelumnya Staf Kapal hanya menerima email berisi dokumen
    // kapal miliknya sendiri. Sesuai permintaan terbaru, semua staf
    // kini punya hak lihat & kelola penuh atas SEMUA kapal — jadi
    // email notifikasi pun disamakan: satu ringkasan armada penuh
    // untuk semua user, apa pun rolenya.)
    // ------------------------------------------------------------
    $semuaUser = $pdo->query("SELECT email, nama_lengkap FROM tb_user WHERE email <> ''")->fetchAll();

    if ($semuaUser) {
        $subjectEmail  = 'Peringatan H-7: ' . count($dokumen) . ' Dokumen Kapal Mendekati/Sudah Kedaluwarsa';
        $htmlBodyEmail = susunEmailHtmlExpiry(susunBarisEmailExpiry($dokumen));

        foreach ($semuaUser as $u) {
            $hasil = kirimEmail($u['email'], $u['nama_lengkap'] ?: $u['email'], $subjectEmail, $htmlBodyEmail);
            if ($hasil['success']) {
                $totalEmailTerkirim++;
            }
            $log[] = "{$u['email']}: {$hasil['pesan']}";
        }
    }

    catatEmailExpiryTerkirim($pdo, count($dokumen), $totalEmailTerkirim);

    return [
        'status'        => 'ok',
        'pesan'         => 'Pengecekan selesai.',
        'total_dokumen' => count($dokumen),
        'total_email'   => $totalEmailTerkirim,
        'detail'        => $log,
    ];
}
