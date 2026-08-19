<?php
/**
 * ============================================================
 * Konfigurasi Database
 * PT Delta Ocean Shipping
 * ============================================================
 */

// --- Zona waktu resmi aplikasi (dipakai untuk jadwal email jam 07:00) ---
date_default_timezone_set('Asia/Jakarta');

// --- Mengambil konfigurasi dari Environment Variables Railway / Server ---
define('DB_HOST', getenv('MYSQLHOST') ?: (getenv('DB_HOST') ?: 'localhost'));
define('DB_PORT', getenv('MYSQLPORT') ?: (getenv('DB_PORT') ?: '3306'));
define('DB_NAME', getenv('MYSQLDATABASE') ?: (getenv('DB_NAME') ?: 'delta_ocean_shipping'));
define('DB_USER', getenv('MYSQLUSER') ?: (getenv('DB_USER') ?: 'root'));
define('DB_PASS', getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : ''));

// --- Konfigurasi upload dokumen ---
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', 'uploads/');
define('ALLOWED_EXT', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png']);
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('MAX_DOKUMEN_PER_KAPAL', 50);
define('EXPIRY_WARNING_DAYS', 30);
define('POPUP_WARNING_DAYS', 7);

// Setelah sebuah surat kedaluwarsa (H-0 terlewati), lonceng notifikasi di
// header tetap menampilkannya selama beberapa hari sebagai masa tenggang,
// supaya tidak langsung hilang tanpa sempat ditindaklanjuti — lalu otomatis
// tidak lagi muncul di lonceng setelah masa tenggang ini lewat.
define('EXPIRED_GRACE_DAYS', 7);

// --- Konfigurasi email notifikasi harian H-7 (Activity Diagram: email otomatis) ---
// Jam berapa (waktu Asia/Jakarta) email harian boleh mulai dikirim setiap hari.
define('EMAIL_SEND_HOUR', 7);
// Token rahasia untuk memicu pengiriman email lewat URL (dipakai oleh layanan
// cron eksternal seperti cron-job.org / UptimeRobot / Railway Cron Job, agar
// email tetap terkirim otomatis jam 07:00 walau tidak ada yang membuka web).
// GANTI nilai default ini di server produksi lewat Environment Variable CRON_SECRET_TOKEN.
define('CRON_SECRET_TOKEN', getenv('CRON_SECRET_TOKEN') ?: 'delta-ocean-cron-2026-ganti-token-ini');

// --- Daftar jenis surat/dokumen standar ---
define('DAFTAR_JENIS_SURAT', [
    'SKAT'                    => ['SKAT'],
    'SIPI'                    => ['SIPI'],
    'PAS BESAR / SURAT LAUT'  => ['PAS BESAR', 'SURAT LAUT'],
    'SKKP'                    => ['SKKP'],
    'SSCEC'                   => ['SSCEC'],
    'ISR (Ijin Stasiun Radio)'=> ['ISR'],
    'EBKP'                    => ['EBKP'],
    'Airtime / BPJS'          => ['AIRTIME', 'AIR TIME', 'BPJS'],
]);

// ------------------------------------------------------------
// Kategori Notifikasi Peringatan Surat Expired (fitur Dashboard)
// ------------------------------------------------------------
// Dipakai oleh notifikasi_expired.php untuk menampilkan 2 halaman
// x 4 kartu kategori, masing-masing dengan ambang "H- berapa hari"
// sendiri (bukan lagi satu nilai global EXPIRY_WARNING_DAYS untuk
// semua jenis surat). 'sumber' => 'dokumen' berarti kartu ini
// mencocokkan tb_dokumen.nama_sertifikat lewat 'kata_kunci'.
// 'sumber' => 'kapal' berarti kartu ini memeriksa data per kapal
// (dipakai khusus untuk kartu "No HP Pemilik").
// ------------------------------------------------------------
define('DAFTAR_KATEGORI_NOTIFIKASI', [
    1 => [
        ['key' => 'skat',           'label' => 'SKAT',                 'hari' => 7,  'sumber' => 'dokumen', 'kata_kunci' => ['SKAT']],
        ['key' => 'airtime',        'label' => 'Airtime / BPJS',       'hari' => 7,  'sumber' => 'dokumen', 'kata_kunci' => ['AIRTIME', 'AIR TIME', 'BPJS']],
        ['key' => 'no_hp_pemilik',  'label' => 'No HP Pemilik',        'hari' => 7,  'sumber' => 'dokumen', 'kata_kunci' => ['NO HP PEMILIK', 'NO. HP PEMILIK', 'NO HP', 'NOMOR HP PEMILIK']],
        ['key' => 'sipi_ebkp_iotc', 'label' => 'SIPI / EBKP / IOTC',   'hari' => 45, 'sumber' => 'dokumen', 'kata_kunci' => ['SIPI', 'EBKP', 'IOTC']],
    ],
    2 => [
        ['key' => 'kelaikan_skkp',       'label' => 'Kelaikan / SKKP',        'hari' => 90, 'sumber' => 'dokumen', 'kata_kunci' => ['KELAIKAN', 'SKKP']],
        ['key' => 'pasbesar_suratlaut',  'label' => 'Pas Besar / Surat Laut', 'hari' => 30, 'sumber' => 'dokumen', 'kata_kunci' => ['PAS BESAR', 'SURAT LAUT']],
        ['key' => 'sscec',               'label' => 'SSCEC',                  'hari' => 14, 'sumber' => 'dokumen', 'kata_kunci' => ['SSCEC']],
        ['key' => 'isr',                 'label' => 'ISR',                    'hari' => 40, 'sumber' => 'dokumen', 'kata_kunci' => ['ISR']],
    ],
]);

// --- Konfigurasi SMTP ---
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.yourmailserver.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USER', getenv('SMTP_USER') ?: 'notifikasi@deltaocean.co.id');
define('SMTP_PASS', getenv('SMTP_PASS') ?: 'ganti_dengan_password_smtp');
define('SMTP_FROM', getenv('SMTP_FROM') ?: 'notifikasi@deltaocean.co.id');
define('SMTP_FROM_NAME', 'Sistem Dokumen Kapal - PT Delta Ocean Shipping');

function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('Koneksi database gagal: ' . $e->getMessage());
        }

        autoMigrateSchema($pdo);
    }

    return $pdo;
}

/**
 * Auto-migrasi skema database (self-healing)
 */
function autoMigrateSchema(PDO $pdo): void
{
    try {
        $kolom = $pdo->query("SHOW COLUMNS FROM tb_kapal LIKE 'no_hp_pemilik'")->fetch();
        if (!$kolom) {
            $pdo->exec("ALTER TABLE tb_kapal ADD COLUMN no_hp_pemilik VARCHAR(30) NOT NULL DEFAULT '' AFTER id_user");
        }
    } catch (Exception $e) {}

    // Kolom nama pemilik kapal (menggantikan pemilihan "Staf Penanggung Jawab"
    // di form Master Kapal — sekarang diisi manual langsung oleh yang input)
    try {
        $kolom = $pdo->query("SHOW COLUMNS FROM tb_kapal LIKE 'nama_pemilik_kapal'")->fetch();
        if (!$kolom) {
            $pdo->exec("ALTER TABLE tb_kapal ADD COLUMN nama_pemilik_kapal VARCHAR(150) NOT NULL DEFAULT '' AFTER no_hp_pemilik");
        }
    } catch (Exception $e) {}

    // Tabel status tinjauan notifikasi per DOKUMEN (untuk kartu kategori surat
    // di fitur Notifikasi Surat Expired: '-' belum disentuh, 'proses' kuning
    // sedang dikerjakan, 'selesai' sudah dicentang selesai)
    try {
        $tabelStatusDok = $pdo->query("SHOW TABLES LIKE 'tb_status_notifikasi'")->fetch();
        if (!$tabelStatusDok) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tb_status_notifikasi (
                    id_dokumen  INT(11) NOT NULL,
                    status      ENUM('belum','proses','selesai') NOT NULL DEFAULT 'belum',
                    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id_dokumen),
                    CONSTRAINT fk_statusnotif_dokumen FOREIGN KEY (id_dokumen)
                        REFERENCES tb_dokumen (id_dokumen) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        }
    } catch (Exception $e) {}

    // Tabel status tinjauan notifikasi per KAPAL (khusus kartu "No HP Pemilik",
    // karena datanya bukan dari tb_dokumen melainkan dari tb_kapal)
    try {
        $tabelStatusKapal = $pdo->query("SHOW TABLES LIKE 'tb_status_notifikasi_kapal'")->fetch();
        if (!$tabelStatusKapal) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tb_status_notifikasi_kapal (
                    id_kapal    INT(11) NOT NULL,
                    kategori    VARCHAR(50) NOT NULL DEFAULT 'no_hp_pemilik',
                    status      ENUM('belum','proses','selesai') NOT NULL DEFAULT 'belum',
                    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id_kapal, kategori),
                    CONSTRAINT fk_statusnotifkapal_kapal FOREIGN KEY (id_kapal)
                        REFERENCES tb_kapal (id_kapal) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        }
    } catch (Exception $e) {}

    try {
        $tabel = $pdo->query("SHOW TABLES LIKE 'tb_riwayat_login'")->fetch();
        if (!$tabel) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tb_riwayat_login (
                    id_riwayat  INT(11) NOT NULL AUTO_INCREMENT,
                    id_user     INT(11) NOT NULL,
                    aksi        ENUM('login','logout') NOT NULL,
                    ip_address  VARCHAR(45) NOT NULL DEFAULT '',
                    user_agent  VARCHAR(255) NOT NULL DEFAULT '',
                    waktu       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id_riwayat),
                    KEY fk_riwayat_user (id_user),
                    CONSTRAINT fk_riwayat_user FOREIGN KEY (id_user)
                        REFERENCES tb_user (id_user) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        }
    } catch (Exception $e) {}

    try {
        $tabelPengaturan = $pdo->query("SHOW TABLES LIKE 'tb_pengaturan_global'")->fetch();
        if (!$tabelPengaturan) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tb_pengaturan_global (
                    id                 TINYINT(1) NOT NULL DEFAULT 1,
                    password_bersama   VARCHAR(255) NOT NULL,
                    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB
            ");
            $hashDefault = password_hash('DELT@111213', PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO tb_pengaturan_global (id, password_bersama) VALUES (1, ?)');
            $stmt->execute([$hashDefault]);
        }
    } catch (Exception $e) {}

    try {
        $tabelLogEmail = $pdo->query("SHOW TABLES LIKE 'tb_notifikasi_email_log'")->fetch();
        if (!$tabelLogEmail) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tb_notifikasi_email_log (
                    tanggal        DATE NOT NULL,
                    dikirim_pada   DATETIME NOT NULL,
                    total_dokumen  INT(11) NOT NULL DEFAULT 0,
                    total_email    INT(11) NOT NULL DEFAULT 0,
                    PRIMARY KEY (tanggal)
                ) ENGINE=InnoDB
            ");
        }
    } catch (Exception $e) {}
}

// Inisialisasi variabel $koneksi untuk kompatibilitas skrip lama
$pdo = getDBConnection();
$koneksi = $pdo;