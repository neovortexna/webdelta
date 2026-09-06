-- ============================================================
-- migrasi_update.sql
-- PT Delta Ocean Shipping
-- ------------------------------------------------------------
-- JALANKAN FILE INI SATU KALI di database yang SUDAH ADA
-- (mis. database "delta_ocean_shipping" yang sudah berjalan
-- sebelum pembaruan ini) untuk menambahkan kolom & tabel baru
-- TANPA menghapus data yang sudah ada.
--
-- Cara pakai lewat phpMyAdmin:
--   1. Buka phpMyAdmin -> pilih database "delta_ocean_shipping"
--   2. Klik tab "SQL"
--   3. Copy-paste seluruh isi file ini, lalu klik "Go" / "Kirim"
--
-- Cara pakai lewat command line:
--   mysql -u root -p delta_ocean_shipping < migrasi_update.sql
-- CATATAN PENTING: Mulai versi ini, aplikasi sudah bisa MEMPERBAIKI
-- STRUKTUR DATABASE-NYA SENDIRI secara otomatis setiap kali ada
-- halaman yang dibuka (lihat autoMigrateSchema() di
-- config/database.php). Jadi file ini SEBENARNYA TIDAK WAJIB
-- dijalankan lagi — cukup buka aplikasinya di browser.
-- File ini disediakan hanya sebagai cadangan, misalnya jika user
-- database aplikasi Anda tidak memiliki hak ALTER TABLE / CREATE TABLE.
-- ============================================================

-- 1) Tambah kolom no_hp_pemilik di tb_kapal (dipakai di Master Kapal & Pencarian Surat)
ALTER TABLE tb_kapal
    ADD COLUMN no_hp_pemilik VARCHAR(30) NOT NULL DEFAULT '' AFTER id_user;

-- 2) Tabel riwayat login/logout (audit trail)
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
) ENGINE=InnoDB;

-- 3) Tabel password bersama (dipakai untuk login SEMUA akun)
--    Catatan: baris data (id=1) TIDAK dibuat di sini karena butuh
--    hash bcrypt dari PHP. Aplikasi akan mengisinya sendiri secara
--    otomatis (default password: DELT@111213) begitu tabel ini ada.
CREATE TABLE IF NOT EXISTS tb_pengaturan_global (
    id                 TINYINT(1) NOT NULL DEFAULT 1,
    password_bersama   VARCHAR(255) NOT NULL,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;
