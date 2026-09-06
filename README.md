# SIMAS — Sistem Informasi Manajemen Surat dan Notifikasi Tenggat
### PT Delta Ocean Shipping

Aplikasi web berbasis PHP Native + MySQL (PDO), sesuai dokumen SKPL / System Design yang diberikan.

---

## 1. Struktur Proyek

```
delta-ocean-shipping/
├── api/
│   └── check_expiry.php       # AJAX: cek dokumen mendekati expired (untuk popup & bell)
├── assets/
│   ├── css/style.css          # Desain tema maritim (navy + brass)
│   └── js/main.js             # Logika popup, tabel dinamis, modal, checklist upload (jQuery + Axios)
├── config/
│   └── database.php           # Konfigurasi koneksi DB & konstanta aplikasi (termasuk DAFTAR_JENIS_SURAT)
├── cron/
│   └── check_expiry_cron.php  # Cron job pengirim email H-30 (ke Admin Induk & tiap Staf Kapal)
├── includes/
│   ├── auth.php                # Session guard & role check
│   ├── functions.php           # Helper status/format tanggal, riwayat login, pencarian lintas kapal
│   ├── header.php               # Komponen header
│   ├── sidebar.php              # Komponen sidebar
│   └── xlsx_writer.php          # Penulis file .xlsx minimalis (tanpa dependensi Composer)
├── sql/
│   └── delta_ocean.sql        # Skema database (tabel + trigger + migrasi)
├── uploads/                   # Direktori penyimpanan file sertifikat
├── dashboard.php
├── dokumen.php                 # Halaman manajemen dokumen (CRUD + checklist upload folder)
├── dokumen_action.php           # Backend AJAX untuk CRUD dokumen
├── master_kapal.php             # CRUD kapal + No. HP Pemilik (semua role sesuai hak akses)
├── pencarian_surat.php          # Pencarian surat lintas semua folder kapal
├── pencarian_action.php         # Backend AJAX untuk Pencarian Surat
├── pencarian_export.php         # Export hasil Pencarian Surat ke Excel (.xlsx)
├── riwayat_login.php            # Riwayat aktivitas Login/Logout (audit trail)
├── pengaturan.php                # Ganti password
├── login.php / logout.php
├── setup.php                    # Jalankan SEKALI untuk membuat akun demo
└── index.php
```

## 2. Kebutuhan Server

Sesuai bagian Analisis Kebutuhan pada SKPL:
- PHP **7.4+** (disarankan 8.x) dengan ekstensi `pdo_mysql`, `mbstring`
- MySQL / MariaDB
- Apache atau Nginx (dokumen ini menyertakan `.htaccess` untuk Apache)
- Web server harus mengizinkan `mod_rewrite`/`.htaccess` (`AllowOverride All`) agar folder `config/` dan `sql/` terlindungi

## 3. Langkah Instalasi

### Langkah 1 — Buat database
```bash
mysql -u root -p < sql/delta_ocean.sql
```
Ini akan membuat database `delta_ocean_shipping`, tabel `tb_user`, `tb_kapal`, `tb_dokumen`, beserta trigger pembatas 50 dokumen/kapal.

### Langkah 2 — Atur koneksi database
Edit `config/database.php`, sesuaikan:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'delta_ocean_shipping');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### Langkah 3 — Buat akun demo
Taruh folder ini di web server Anda (mis. `htdocs/delta-ocean-shipping`), lalu buka lewat browser:
```
http://localhost/delta-ocean-shipping/setup.php
```
Script ini membuat akun **admin** (Admin Induk) dan **staf01** (Staf Kapal), keduanya dengan password `password123`, plus 2 kapal contoh & 3 dokumen contoh.

**⚠️ Setelah berhasil, hapus file `setup.php` dari server** (alasan keamanan — script ini bisa membuat akun baru jika dibiarkan).

### Langkah 4 — Login
Buka `http://localhost/delta-ocean-shipping/login.php`
- Admin Induk: `admin` / `password123`
- Staf Kapal: `staf01` / `password123`

### Langkah 5 — Pastikan folder `uploads/` bisa ditulis
```bash
chmod 755 uploads/
```

### Langkah 6 — Aktifkan email otomatis H-7 (1x sehari, jam 07:00)

Email peringatan H-7 dikirim ke **admin_induk** (rekap semua kapal) dan **staf_kapal**
(hanya kapal miliknya), memakai email yang terdaftar saat login/registrasi (`tb_user.email`).
Sistem sudah anti-dobel-kirim (tabel `tb_notifikasi_email_log`) — dipicu berkali-kali
dalam sehari pun email tetap hanya terkirim **satu kali per hari**.

Pilih **salah satu** dari dua cara berikut, tergantung jenis hosting Anda:

**Cara A — Server dengan akses shell/crontab (VPS, cPanel, dsb.)**
Ini cara paling andal karena berjalan walau tidak ada satu pun yang membuka web.
Jalankan `crontab -e` dan tambahkan:
```
0 7 * * * /usr/bin/php /path/ke/delta-ocean-shipping/cron/check_expiry_cron.php >> /path/ke/delta-ocean-shipping/cron/cron.log 2>&1
```

**Cara B — Hosting tanpa akses crontab (mis. Railway, shared hosting tanpa cron)**
Gunakan endpoint HTTP `cron/trigger_expiry_email.php`, lalu daftarkan ke layanan
penjadwal eksternal gratis (mis. **cron-job.org**, UptimeRobot, atau fitur *Cron Job*
Railway kalau tersedia di paket Anda) agar memanggil URL berikut setiap hari jam 07:00 WIB:
```
https://domain-anda.com/cron/trigger_expiry_email.php?token=TOKEN_RAHASIA_ANDA
```
Ganti `TOKEN_RAHASIA_ANDA` sesuai nilai `CRON_SECRET_TOKEN` (atur lewat Environment
Variable `CRON_SECRET_TOKEN` di server — **wajib diganti dari nilai default**).
Endpoint ini aman dipanggil sesering apa pun (mis. tiap 5–15 menit kalau layanan
cron eksternal tidak mendukung timezone Asia/Jakarta) — ia menolak mengirim sebelum
jam 07:00 WIB dan hanya benar-benar mengirim sekali per hari berkat kunci anti-dobel-kirim.
Cara ini bekerja **walau tidak ada satu pun user yang membuka web hari itu**, karena
yang memanggilnya adalah layanan eksternal, bukan browser user.

**Soal keandalan pengiriman (SMTP vs `mail()`)**
Secara default, script memakai fungsi `mail()` bawaan PHP — fungsi ini **sering gagal
diam-diam** di hosting modern (termasuk Railway) karena tidak ada Mail Transfer Agent
lokal terpasang. Untuk pengiriman yang andal, pasang **PHPMailer** dan isi kredensial
SMTP asli (Gmail, SendGrid, Mailgun, dst) — sistem akan **otomatis** memakainya begitu
tersedia, tanpa perlu ubah kode lagi:
```bash
composer require phpmailer/phpmailer
```
lalu isi `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM` lewat
Environment Variable di server (lihat `config/database.php`). Logika pemilihan
SMTP vs `mail()` ada di `includes/mailer.php`.

## 4. Pembaruan Terbaru (Modifikasi)

| Permintaan | Implementasi |
|---|---|
| Perbaikan bug fatal | `login.php` dan `setup.php` sebelumnya berisi kode rusak (parse error) yang membuat halaman login & setup gagal total — sudah diperbaiki. |
| Password saat daftar/ganti harus **disamakan** (konfirmasi) | `register.php` kini punya field "Ulangi Password" + validasi cocok (client & server-side). `pengaturan.php` sudah punya validasi ini, ditambah indikator kecocokan langsung (live). |
| **Riwayat Login/Logout** terdeteksi | Tabel baru `tb_riwayat_login` (id_user, aksi, ip_address, user_agent, waktu). Dicatat otomatis di `login.php` & `logout.php` lewat `catatRiwayatLogin()`. Bisa dilihat di menu **Riwayat Login/Logout** (`riwayat_login.php`) — Admin Induk melihat semua user, Staf Kapal melihat aktivitasnya sendiri. |
| **Warning dokumen kedaluwarsa dikirim ke Email** | `cron/check_expiry_cron.php` sekarang mengirim **dua jenis email**: (1) ringkasan seluruh armada ke setiap Admin Induk, dan (2) email terpisah ke tiap Staf Kapal yang **hanya berisi dokumen kapal miliknya sendiri**. |
| **Upload folder — pilih file yang mau diunggah** | Saat memilih folder (`dokumen.php`), sistem menampilkan **daftar/checklist semua file** yang ditemukan (termasuk isi sub-folder) lengkap dengan pratinjau tanggal kedaluwarsa terdeteksi. User bisa mencentang/membatalkan file satu per satu (atau tombol "Pilih Semua" / "Batal Semua") sebelum menekan Simpan — hanya file yang dicentang yang benar-benar diunggah. |
| **Pencarian Surat lintas semua folder kapal + Export Excel** | Halaman baru **Pencarian Surat** (`pencarian_surat.php`) mencari dokumen dari SEMUA kapal sekaligus (sesuai hak akses), dengan tombol pencarian cepat untuk jenis surat standar: SKAT, SIPI, PAS BESAR/Surat Laut, SKKP, SSCEC, ISR, EBKP, Airtime/BPJS. Hasil selalu diurutkan dari **tanggal kedaluwarsa PALING DEKAT (H-Expired terdekat)** di baris teratas. Tombol **Export ke Excel** (`pencarian_export.php`) mengunduh file `.xlsx` asli (tanpa perlu Composer/PhpSpreadsheet — pakai `includes/xlsx_writer.php`) berisi kolom: **NO URUT, NAMA KAPAL/FOLDER, NAMA SURAT, TANGGAL KADALUARSA, STATUS, NO HP PEMILIK**. |
| **No. HP Pemilik per kapal** | Kolom baru `no_hp_pemilik` pada `tb_kapal`, bisa diisi/diedit di menu **Master Kapal**, dan ikut ditampilkan pada hasil Pencarian Surat & Export Excel supaya pemilik kapal bisa langsung dihubungi saat surat mendekati kedaluwarsa. |
| **Perbaikan: email H-7 otomatis belum berjalan** | Ambang email disamakan dengan pop-up (H-7, `POPUP_WARNING_DAYS`), ditambah kunci anti-dobel-kirim (`tb_notifikasi_email_log`) supaya tetap 1x/hari, ditambah endpoint HTTP `cron/trigger_expiry_email.php` (token-protected) untuk hosting tanpa akses crontab (mis. Railway) agar email tetap terkirim jam 07:00 walau web tidak dibuka, dan pengiriman kini otomatis pakai SMTP asli via PHPMailer (`includes/mailer.php`) begitu terpasang & dikonfigurasi, dengan fallback ke `mail()` bawaan PHP. Alur pop-up H-7 di dashboard **tidak diubah sama sekali**. |
| **Logo perusahaan** | Ikon jangkar teks di pojok kiri atas header, halaman login, dan halaman registrasi diganti dengan logo resmi PT Delta Ocean Shipping (`assets/img/logo-delta-ocean.png`). |
| **Semua Staf Kapal bisa saling lihat & kelola semua dokumen** | Sebelumnya Staf Kapal hanya bisa melihat/mengelola dokumen kapal miliknya sendiri. Sesuai permintaan, sekarang **semua user yang login (Admin Induk maupun Staf Kapal) punya hak akses PENUH** (lihat, tambah, edit, hapus) atas dokumen **SEMUA kapal** — tidak lagi dibatasi kepemilikan. Berlaku konsisten di: Manajemen Dokumen (`dokumen.php`), Pencarian Surat lintas kapal + Export Excel, Dashboard (ringkasan armada penuh untuk semua role), pop-up peringatan H-7, dan email peringatan H-7 harian (kini satu ringkasan armada penuh dikirim ke semua user, apa pun rolenya). Perubahan inti ada di `includes/auth.php` (`pastikanBerhak()`). Fitur **Master Kapal** (menambah/menghapus kapal & menentukan pemilik) tetap seperti semula — hanya akses dokumen yang diubah. |

> **Catatan migrasi:** jika database sudah pernah dibuat sebelumnya, jalankan blok migrasi (ALTER TABLE + CREATE TABLE tb_riwayat_login) yang ada di bagian akhir `sql/delta_ocean.sql` (dikomentari, tinggal jalankan manual satu kali).

## 5. Fitur yang Sudah Diimplementasikan (Sebelumnya)

**SIMAS — Sistem Informasi Manajemen Surat dan Notifikasi Tenggat**, berbasis web untuk PT Delta Ocean Shipping.

| Kebutuhan SKPL | Implementasi |
|---|---|
| Login & role (Admin Induk / Staf Kapal) | `login.php`, session-based, `bcrypt` hashing |
| CRUD Dokumen + limit 50/kapal | `dokumen.php` + `dokumen_action.php` (validasi PHP **dan** trigger MySQL sebagai lapisan kedua) |
| Validasi ekstensi file (.pdf/.jpg) & ukuran | `dokumen_action.php` (`ALLOWED_EXT`, `MAX_FILE_SIZE`) |
| Auto-deteksi tanggal kedaluwarsa dari nama file saat unggah folder | `includes/functions.php` (`ekstrakTanggalDariNamaFile`), dipakai oleh `dokumen_action.php` |
| Notifikasi email H-30 (Cron Job, peringatan awal) | `cron/check_expiry_cron.php`, konstanta `EXPIRY_WARNING_DAYS` |
| **Pop-up peringatan H-7 (mendesak)** saat dashboard dibuka | `api/check_expiry.php` + `assets/js/main.js` (`checkExpiryPopup`), konstanta `POPUP_WARNING_DAYS`. Berlaku untuk **Admin Induk** (semua kapal) maupun **Staf Kapal** (kapal miliknya sendiri) |
| Ikon lonceng notifikasi di header | `includes/header.php`, popup bisa dibuka ulang kapan saja, tersedia untuk semua role |
| Prepared Statements (anti SQL Injection) | Seluruh query memakai PDO `prepare()/execute()` |
| Pagination / Server-Side Processing | `dokumen_action.php` (`LIMIT`/`OFFSET`), dipanggil ulang tiap ganti halaman/pencarian |
| Layout header/sidebar/main | `includes/header.php`, `includes/sidebar.php`, CSS grid di `style.css` |

### 4b. Alur Pop-up Peringatan H-7

1. User (Admin Induk / Staf Kapal) login lalu masuk ke `dashboard.php`.
2. Halaman otomatis memanggil `api/check_expiry.php` lewat AJAX.
3. Endpoint ini menghitung dokumen dengan sisa hari &le; `POPUP_WARNING_DAYS` (default **7 hari**, termasuk yang sudah lewat tanggal).
   - Admin Induk: melihat dokumen dari **semua kapal**.
   - Staf Kapal: hanya melihat dokumen dari **kapal miliknya sendiri**.
4. Jika ada hasil, modal peringatan langsung tampil (centered overlay) berisi daftar dokumen + status (mendekati/sudah kedaluwarsa).
5. Badge angka pada ikon lonceng di header tetap menyala walau modal ditutup — bisa dibuka ulang kapan saja dengan mengklik ikon lonceng.

## 6. Keamanan Tambahan yang Diterapkan
- Password di-hash dengan `password_hash()` (bcrypt), diverifikasi dengan `password_verify()`.
- Semua query database memakai **prepared statement** PDO.
- Validasi ekstensi & ukuran file saat upload, nama file diacak ulang (`bin2hex(random_bytes())`) agar tidak bisa ditebak/dieksekusi.
- Folder `uploads/` dikunci agar PHP tidak bisa dieksekusi di dalamnya (`.htaccess`).
- Folder `config/` dan `sql/` diblokir dari akses langsung browser.
- Setiap aksi CRUD dokumen memvalidasi ulang hak akses (staf hanya bisa mengelola kapal miliknya sendiri).

## 7. Kredensial Demo

| Role | Username | Password |
|---|---|---|
| Admin Induk | `admin` | `password123` |
| Staf Kapal | `staf01` | `password123` |

**Ganti password ini segera setelah instalasi** lewat menu **Pengaturan** di dalam aplikasi.
