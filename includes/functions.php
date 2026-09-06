<?php
/**
 * ============================================================
 * Password Bersama (Shared Login Password)
 * ------------------------------------------------------------
 * Sesuai permintaan: SEMUA akun login memakai SATU password yang
 * sama (default: DELT@111213). Password ini disimpan terpisah
 * dari data per-user di tabel tb_pengaturan_global, dan bisa
 * diubah lewat halaman Pengaturan oleh SIAPA SAJA yang sudah
 * login — begitu diubah, otomatis berlaku untuk semua akun lain.
 * ============================================================
 */

/** Ambil hash password bersama yang sedang aktif */
function ambilHashPasswordBersama(PDO $pdo): ?string
{
    $row = $pdo->query('SELECT password_bersama FROM tb_pengaturan_global WHERE id = 1')->fetch();
    return $row['password_bersama'] ?? null;
}

/** Cek apakah password yang dimasukkan cocok dengan password bersama saat ini */
function verifikasiPasswordBersama(PDO $pdo, string $passwordDimasukkan): bool
{
    $hash = ambilHashPasswordBersama($pdo);
    return $hash !== null && password_verify($passwordDimasukkan, $hash);
}

/** Ganti password bersama — berlaku langsung untuk SEMUA akun */
function ubahPasswordBersama(PDO $pdo, string $passwordBaru): void
{
    $hash = password_hash($passwordBaru, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('
        INSERT INTO tb_pengaturan_global (id, password_bersama) VALUES (1, ?)
        ON DUPLICATE KEY UPDATE password_bersama = VALUES(password_bersama)
    ');
    $stmt->execute([$hash]);
}

/**
 * ============================================================
 * Riwayat Login / Logout (audit trail)
 * ------------------------------------------------------------
 * Mencatat setiap kali user login atau logout ke tabel
 * tb_riwayat_login, lengkap dengan alamat IP & user agent,
 * supaya aktivitas akses sistem bisa dipantau/ditelusuri.
 * ============================================================
 */
function catatRiwayatLogin(PDO $pdo, int $id_user, string $aksi): void
{
    if ($id_user <= 0 || !in_array($aksi, ['login', 'logout'], true)) {
        return;
    }
    try {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        // Jika ada beberapa IP (mis. di belakang proxy/load balancer), ambil yang pertama
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        $stmt = $pdo->prepare('INSERT INTO tb_riwayat_login (id_user, aksi, ip_address, user_agent) VALUES (?, ?, ?, ?)');
        $stmt->execute([$id_user, $aksi, $ip, $ua]);
    } catch (Exception $e) {
        // Jangan sampai kegagalan pencatatan riwayat menggagalkan proses login/logout utama.
        // (mis. tabel belum dimigrasikan di instalasi lama)
    }
}

/**
 * Menentukan status sebuah dokumen berdasarkan tanggal kedaluwarsa.
 * Mengembalikan array [kode, label, kelas_css]
 */
function statusDokumen(string $tanggalExpired): array
{
    $today = new DateTime('today');
    $exp   = new DateTime($tanggalExpired);
    $diff  = (int) $today->diff($exp)->format('%r%a'); // signed day difference

    if ($diff < 0) {
        return ['expired', 'Kedaluwarsa', 'badge badge--danger'];
    }
    if ($diff <= EXPIRY_WARNING_DAYS) {
        return ['warning', 'H-' . $diff, 'badge badge--warning'];
    }
    return ['ok', 'Berlaku', 'badge badge--ok'];
}

function formatTanggalID(string $date): string
{
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $d = new DateTime($date);
    return $d->format('d') . ' ' . $bulan[(int) $d->format('n')] . ' ' . $d->format('Y');
}

/**
 * ============================================================
 * Auto-Deteksi Tanggal Kedaluwarsa dari Nama File
 * ------------------------------------------------------------
 * Dipakai saat unggah folder berisi banyak dokumen sekaligus,
 * dimana setiap file sudah memiliki keterangan tanggal expired
 * pada nama filenya. Fungsi ini mencoba beberapa pola umum
 * penamaan file secara berurutan dan mengembalikan tanggal
 * dalam format 'Y-m-d', atau null jika tidak ada pola yang cocok.
 *
 * Pola yang didukung (contoh untuk 15 Agustus 2026):
 *  - YYYY-MM-DD / YYYY.MM.DD / YYYY_MM_DD  -> Sertifikat_2026-08-15.pdf
 *  - DD-MM-YYYY / DD.MM.YYYY / DD_MM_YYYY  -> Sertifikat_15-08-2026.pdf
 *  - DDMMYYYY (8 digit)                    -> Sertifikat_15082026.pdf
 *  - YYYYMMDD (8 digit)                    -> Sertifikat_20260815.pdf
 *  - DD <NamaBulan> YYYY                   -> Sertifikat_15 Agustus 2026.pdf
 * ============================================================
 */
function ekstrakTanggalDariNamaFile(string $namaFile): ?string
{
    $nama = pathinfo($namaFile, PATHINFO_FILENAME);

    $petaBulan = [
        'jan' => 1, 'januari' => 1, 'january' => 1,
        'feb' => 2, 'februari' => 2, 'february' => 2,
        'mar' => 3, 'maret' => 3, 'march' => 3,
        'apr' => 4, 'april' => 4,
        'mei' => 5, 'may' => 5,
        'jun' => 6, 'juni' => 6, 'june' => 6,
        'jul' => 7, 'juli' => 7, 'july' => 7,
        'agu' => 8, 'agt' => 8, 'agustus' => 8, 'aug' => 8, 'august' => 8,
        'sep' => 9, 'sept' => 9, 'september' => 9,
        'okt' => 10, 'oktober' => 10, 'oct' => 10, 'october' => 10,
        'nov' => 11, 'november' => 11,
        'des' => 12, 'desember' => 12, 'dec' => 12, 'december' => 12,
    ];

    // Pola 1: YYYY-MM-DD / YYYY.MM.DD / YYYY_MM_DD
    if (preg_match('/(20\d{2})[-_.](0[1-9]|1[0-2])[-_.](0[1-9]|[12]\d|3[01])(?!\d)/', $nama, $m)) {
        if ($tgl = validasiTanggal((int) $m[1], (int) $m[2], (int) $m[3])) return $tgl;
    }

    // Pola 2: DD-MM-YYYY / DD.MM.YYYY / DD_MM_YYYY
    if (preg_match('/(?<!\d)(0[1-9]|[12]\d|3[01])[-_.](0[1-9]|1[0-2])[-_.](20\d{2})/', $nama, $m)) {
        if ($tgl = validasiTanggal((int) $m[3], (int) $m[2], (int) $m[1])) return $tgl;
    }

    // Pola 3: DDMMYYYY (8 digit berurutan tanpa pemisah)
    if (preg_match('/(?<!\d)(0[1-9]|[12]\d|3[01])(0[1-9]|1[0-2])(20\d{2})(?!\d)/', $nama, $m)) {
        if ($tgl = validasiTanggal((int) $m[3], (int) $m[2], (int) $m[1])) return $tgl;
    }

    // Pola 4: YYYYMMDD (8 digit berurutan tanpa pemisah)
    if (preg_match('/(?<!\d)(20\d{2})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])(?!\d)/', $nama, $m)) {
        if ($tgl = validasiTanggal((int) $m[1], (int) $m[2], (int) $m[3])) return $tgl;
    }

    // Pola 5: DD <Nama Bulan> YYYY (mis. "15 Agustus 2026", "15-Agu-2026")
    if (preg_match('/(?<!\d)(0?[1-9]|[12]\d|3[01])[\s_-]+([A-Za-z]{3,9})[\s_-]+(20\d{2})/', $nama, $m)) {
        $key = strtolower($m[2]);
        if (isset($petaBulan[$key])) {
            if ($tgl = validasiTanggal((int) $m[3], $petaBulan[$key], (int) $m[1])) return $tgl;
        }
    }

    // Pola 6: DD-MM-YY / DD.MM.YY / DD_MM_YY — tahun 2 digit (mis. "27-07-27" = 27 Juli 2027).
    // Dicek PALING TERAKHIR (setelah semua pola tahun 4 digit di atas) supaya tidak salah
    // menangkap sebagian dari tanggal berformat 4 digit tahun. Tahun 2 digit diasumsikan 20YY.
    if (preg_match('/(?<!\d)(0[1-9]|[12]\d|3[01])[-_.](0[1-9]|1[0-2])[-_.](\d{2})(?!\d)/', $nama, $m)) {
        if ($tgl = validasiTanggal(2000 + (int) $m[3], (int) $m[2], (int) $m[1])) return $tgl;
    }

    return null;
}

/**
 * ============================================================
 * Pencarian Surat Lintas Kapal
 * ------------------------------------------------------------
 * Mencari dokumen di SEMUA folder/kapal (sesuai hak akses user)
 * berdasarkan nama sertifikat, dipakai oleh halaman
 * pencarian_surat.php baik untuk tampilan tabel (AJAX) maupun
 * export Excel. Hasil selalu diurutkan dari tanggal kedaluwarsa
 * PALING DEKAT (H-EXPIRED terdekat) di baris paling atas.
 * ============================================================
 */
function cariSuratLintasKapal(PDO $pdo, array $user, string $search): array
{
    $where  = [];
    $params = [];

    if ($search !== '') {
        // Jika $search cocok persis dengan salah satu label di DAFTAR_JENIS_SURAT
        // (tombol pencarian cepat), gunakan SEMUA kata kunci terkait dengan OR
        // (mis. "PAS BESAR / SURAT LAUT" -> cocokkan "PAS BESAR" ATAU "SURAT LAUT").
        $daftarJenis = defined('DAFTAR_JENIS_SURAT') ? DAFTAR_JENIS_SURAT : [];
        if (isset($daftarJenis[$search]) && is_array($daftarJenis[$search])) {
            $orParts = [];
            foreach ($daftarJenis[$search] as $i => $kw) {
                $key = 'kw' . $i;
                $orParts[]    = 'd.nama_sertifikat LIKE :' . $key;
                $params[$key] = '%' . $kw . '%';
            }
            $where[] = '(' . implode(' OR ', $orParts) . ')';
        } else {
            $where[]          = 'd.nama_sertifikat LIKE :search';
            $params['search'] = '%' . $search . '%';
        }
    }

    // Sesuai permintaan: semua staf boleh mencari/melihat surat dari SEMUA
    // kapal (bukan hanya kapal miliknya), sama seperti Admin Induk — jadi
    // tidak ada lagi filter tambahan berdasarkan kepemilikan kapal di sini.

    $sql = '
        SELECT d.id_dokumen, d.nama_sertifikat, d.file_path, d.tanggal_expired,
               k.nama_kapal
        FROM tb_dokumen d
        JOIN tb_kapal k ON k.id_kapal = d.id_kapal
    ';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    // Terdekat H-expired paling atas: ASC berdasarkan tanggal kedaluwarsa
    $sql .= ' ORDER BY d.tanggal_expired ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $no = 1;
    foreach ($rows as &$r) {
        [$kode, $label] = statusDokumen($r['tanggal_expired']);
        $r['no_urut']       = $no++;
        $r['status_kode']   = $kode;
        $r['status_label']  = $label;
        $r['tanggal_display'] = formatTanggalID($r['tanggal_expired']);
    }
    unset($r);

    return $rows;
}

/**
 * ============================================================
 * Notifikasi Surat Expired (fitur Dashboard — 2 halaman x 4 kartu)
 * ------------------------------------------------------------
 * Setiap kartu kategori (lihat DAFTAR_KATEGORI_NOTIFIKASI di
 * config/database.php) punya ambang "H- berapa hari" sendiri.
 * Fungsi di bawah ini mengambil daftar dokumen/kapal yang masuk
 * ke ambang tersebut, lengkap dengan status tinjauan
 * (belum / proses / selesai) yang bisa diklik user.
 * ============================================================
 */

/** Ambil daftar item dokumen untuk satu kartu kategori notifikasi.
 *  Surat yang SUDAH kedaluwarsa sengaja TIDAK disertakan di sini — surat
 *  yang sudah lewat tanggalnya cukup terlihat di halaman lain (mis.
 *  Manajemen Dokumen / Pencarian Surat dengan status "Kadaluarsa"), supaya
 *  kartu-kartu notifikasi ini fokus hanya pada surat yang PERLU disiapkan
 *  sebelum tanggal jatuh temponya. */
function ambilItemKategoriNotifikasi(PDO $pdo, array $kategori): array
{
    $orParts = [];
    $params  = [];
    foreach ($kategori['kata_kunci'] as $i => $kw) {
        $key = 'kw' . $i;
        $orParts[]    = 'd.nama_sertifikat LIKE :' . $key;
        $params[$key] = '%' . $kw . '%';
    }
    if (!$orParts) {
        return [];
    }

    $sql = '
        SELECT d.id_dokumen AS id, d.nama_sertifikat, d.tanggal_expired, k.nama_kapal,
               COALESCE(sn.status, \'belum\') AS status
        FROM tb_dokumen d
        JOIN tb_kapal k ON k.id_kapal = d.id_kapal
        LEFT JOIN tb_status_notifikasi sn ON sn.id_dokumen = d.id_dokumen
        WHERE (' . implode(' OR ', $orParts) . ')
          AND DATEDIFF(d.tanggal_expired, CURDATE()) BETWEEN 0 AND :hari
        ORDER BY d.tanggal_expired ASC
    ';
    $params['hari'] = (int) $kategori['hari'];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        $sisaHari = (int) (new DateTime('today'))->diff(new DateTime($r['tanggal_expired']))->format('%r%a');
        $out[] = [
            'id'          => (int) $r['id'],
            'judul'       => $r['nama_sertifikat'],
            'sub'         => $r['nama_kapal'],
            'keterangan'  => 'H-' . $sisaHari . ' — jatuh tempo ' . formatTanggalID($r['tanggal_expired']),
            'status'      => $r['status'],
            'urgen'       => $sisaHari <= 3, // ditandai mendesak jika tinggal 3 hari lagi atau kurang
            'sisaHari'    => $sisaHari, // dipakai untuk mengurutkan kartu (bukan untuk ditampilkan langsung)
        ];
    }
    return $out;
}

/** Urutkan daftar kartu kategori supaya yang paling mendesak tampil PALING ATAS.
 *  Urutan prioritas:
 *   1) Kartu dengan lebih banyak surat "mendesak" (H-3 atau kurang) naik duluan.
 *   2) Kalau seri, kartu dengan lebih banyak surat yang masih perlu perhatian
 *      (status belum/proses) naik duluan.
 *   3) Kalau masih seri, kartu dengan tenggat paling dekat naik duluan.
 *  Kartu tanpa surat yang perlu perhatian otomatis turun ke bawah. */
function urutkanKartuNotifikasi(array $daftarKartu): array
{
    $ringkas = function (array $kartu): array {
        $belumSelesai = array_filter($kartu['items'], fn($it) => $it['status'] !== 'selesai');
        $jumlahUrgen  = count(array_filter($belumSelesai, fn($it) => !empty($it['urgen'])));
        $sisaTerdekat = null;
        foreach ($belumSelesai as $it) {
            if ($sisaTerdekat === null || $it['sisaHari'] < $sisaTerdekat) {
                $sisaTerdekat = $it['sisaHari'];
            }
        }
        return [
            'urgen'   => $jumlahUrgen,
            'jumlah'  => count($belumSelesai),
            'terdekat'=> $sisaTerdekat, // null = tidak ada surat yang perlu perhatian
        ];
    };

    usort($daftarKartu, function ($a, $b) use ($ringkas) {
        $ra = $ringkas($a);
        $rb = $ringkas($b);

        if ($ra['urgen'] !== $rb['urgen']) {
            return $rb['urgen'] <=> $ra['urgen'];
        }
        if ($ra['jumlah'] !== $rb['jumlah']) {
            return $rb['jumlah'] <=> $ra['jumlah'];
        }
        if ($ra['terdekat'] === null && $rb['terdekat'] === null) {
            return 0;
        }
        if ($ra['terdekat'] === null) {
            return 1; // kartu kosong turun ke bawah
        }
        if ($rb['terdekat'] === null) {
            return -1;
        }
        return $ra['terdekat'] <=> $rb['terdekat'];
    });

    return $daftarKartu;
}

/** Hitung total item yang masih butuh perhatian (status belum/proses) di SEMUA kartu kategori */
function hitungTotalNotifikasiPending(PDO $pdo): int
{
    $total = 0;
    foreach (DAFTAR_KATEGORI_NOTIFIKASI as $halaman) {
        foreach ($halaman as $kategori) {
            foreach (ambilItemKategoriNotifikasi($pdo, $kategori) as $item) {
                if ($item['status'] !== 'selesai') {
                    $total++;
                }
            }
        }
    }
    return $total;
}

/** Simpan/ubah status tinjauan (belum/proses/selesai) untuk satu dokumen atau kapal */
function simpanStatusNotifikasi(PDO $pdo, string $tipe, int $id, string $status): bool
{
    if (!in_array($status, ['belum', 'proses', 'selesai'], true)) {
        return false;
    }
    if ($tipe === 'dokumen') {
        $stmt = $pdo->prepare('
            INSERT INTO tb_status_notifikasi (id_dokumen, status) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ');
        return $stmt->execute([$id, $status]);
    }
    if ($tipe === 'kapal') {
        $stmt = $pdo->prepare('
            INSERT INTO tb_status_notifikasi_kapal (id_kapal, kategori, status) VALUES (?, \'no_hp_pemilik\', ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ');
        return $stmt->execute([$id, $status]);
    }
    return false;
}

/** Validasi kombinasi tahun/bulan/tanggal, kembalikan format Y-m-d atau null jika tidak valid */
function validasiTanggal(int $tahun, int $bulan, int $tanggal): ?string
{
    if (!checkdate($bulan, $tanggal, $tahun)) {
        return null;
    }
    return sprintf('%04d-%02d-%02d', $tahun, $bulan, $tanggal);
}

/**
 * Bersihkan nama sertifikat dari potongan tanggal yang terdeteksi
 * pada nama file, supaya nama dokumen yang tersimpan lebih rapi.
 * Contoh: "SIUP_Kapal_Mentari_2026-08-15" -> "SIUP Kapal Mentari"
 */
function bersihkanNamaSertifikat(string $namaFileTanpaEkstensi): string
{
    $nama = $namaFileTanpaEkstensi;

    // Hapus pola-pola tanggal yang sama seperti ekstrakTanggalDariNamaFile()
    $polaTanggal = [
        '/(20\d{2})[-_.](0[1-9]|1[0-2])[-_.](0[1-9]|[12]\d|3[01])(?!\d)/',
        '/(?<!\d)(0[1-9]|[12]\d|3[01])[-_.](0[1-9]|1[0-2])[-_.](20\d{2})/',
        '/(?<!\d)(0[1-9]|[12]\d|3[01])(0[1-9]|1[0-2])(20\d{2})(?!\d)/',
        '/(?<!\d)(20\d{2})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])(?!\d)/',
        '/(?<!\d)(0?[1-9]|[12]\d|3[01])[\s_-]+([A-Za-z]{3,9})[\s_-]+(20\d{2})/',
        '/(?<!\d)(0[1-9]|[12]\d|3[01])[-_.](0[1-9]|1[0-2])[-_.](\d{2})(?!\d)/',
    ];
    $nama = preg_replace($polaTanggal, '', $nama);

    // Rapikan sisa pemisah ganda/di ujung string
    $nama = preg_replace('/[-_.\s]+/', ' ', $nama);
    $nama = trim($nama, " -_.\t\n");

    return $nama !== '' ? $nama : $namaFileTanpaEkstensi;
}
