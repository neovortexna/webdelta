-- ============================================================
-- PT DELTA OCEAN SHIPPING
-- Sistem Manajemen Dokumen & Notifikasi Kedaluwarsa Surat Kapal
-- Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS delta_ocean_shipping
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE delta_ocean_shipping;

-- ------------------------------------------------------------
-- Tabel: tb_user
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tb_user (
    id_user     INT(11) NOT NULL AUTO_INCREMENT,
    username    VARCHAR(50) NOT NULL,
    password    VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL DEFAULT '',
    email       VARCHAR(100) NOT NULL DEFAULT '',
    role        ENUM('admin_induk','staf_kapal') NOT NULL DEFAULT 'staf_kapal',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_user),
    UNIQUE KEY uq_username (username)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabel: tb_kapal
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tb_kapal (
    id_kapal            INT(11) NOT NULL AUTO_INCREMENT,
    nama_kapal          VARCHAR(100) NOT NULL,
    id_user             INT(11) NOT NULL,
    no_hp_pemilik       VARCHAR(30) NOT NULL DEFAULT '',
    nama_pemilik_kapal  VARCHAR(150) NOT NULL DEFAULT '',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_kapal),
    KEY fk_kapal_user (id_user),
    CONSTRAINT fk_kapal_user FOREIGN KEY (id_user)
        REFERENCES tb_user (id_user) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabel: tb_dokumen
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tb_dokumen (
    id_dokumen      INT(11) NOT NULL AUTO_INCREMENT,
    id_kapal        INT(11) NOT NULL,
    nama_sertifikat VARCHAR(100) NOT NULL,
    file_path       VARCHAR(255) NOT NULL,
    tanggal_expired DATE NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_dokumen),
    KEY fk_dokumen_kapal (id_kapal),
    CONSTRAINT fk_dokumen_kapal FOREIGN KEY (id_kapal)
        REFERENCES tb_kapal (id_kapal) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabel: tb_riwayat_login
-- Mencatat setiap aktivitas login & logout user (audit trail)
-- ------------------------------------------------------------
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

-- ------------------------------------------------------------
-- Tabel: tb_pengaturan_global
-- Menyimpan SATU password bersama yang dipakai untuk login oleh
-- SEMUA akun (Admin Induk & Staf Kapal). Baris datanya (id=1)
-- otomatis diisi oleh aplikasi (lihat autoMigrateSchema() di
-- config/database.php) dengan hash bcrypt dari password default
-- "DELT@111213", karena hash bcrypt harus dibuat oleh PHP
-- (password_hash), bukan lewat SQL biasa.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tb_pengaturan_global (
    id                 TINYINT(1) NOT NULL DEFAULT 1,
    password_bersama   VARCHAR(255) NOT NULL,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabel: tb_status_notifikasi
-- Status tinjauan (belum/proses/selesai) untuk tiap dokumen pada
-- fitur "Notifikasi Surat Expired" di Dashboard.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tb_status_notifikasi (
    id_dokumen  INT(11) NOT NULL,
    status      ENUM('belum','proses','selesai') NOT NULL DEFAULT 'belum',
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_dokumen),
    CONSTRAINT fk_statusnotif_dokumen FOREIGN KEY (id_dokumen)
        REFERENCES tb_dokumen (id_dokumen) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabel: tb_status_notifikasi_kapal
-- Sama seperti di atas, tapi untuk kartu kategori yang sumber
-- datanya per kapal (mis. "No HP Pemilik"), bukan per dokumen.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tb_status_notifikasi_kapal (
    id_kapal    INT(11) NOT NULL,
    kategori    VARCHAR(50) NOT NULL DEFAULT 'no_hp_pemilik',
    status      ENUM('belum','proses','selesai') NOT NULL DEFAULT 'belum',
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_kapal, kategori),
    CONSTRAINT fk_statusnotifkapal_kapal FOREIGN KEY (id_kapal)
        REFERENCES tb_kapal (id_kapal) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Trigger: batasi maksimal 50 dokumen per kapal
-- (validasi utama tetap dilakukan di PHP; trigger ini adalah
--  lapisan pengaman kedua di level database)
-- ------------------------------------------------------------
DELIMITER $$
CREATE TRIGGER trg_limit_dokumen
BEFORE INSERT ON tb_dokumen
FOR EACH ROW
BEGIN
    DECLARE doc_count INT;
    SELECT COUNT(*) INTO doc_count FROM tb_dokumen WHERE id_kapal = NEW.id_kapal;
    IF doc_count >= 50 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Batas maksimal 50 dokumen per kapal telah tercapai.';
    END IF;
END$$
DELIMITER ;

-- ------------------------------------------------------------
-- Data awal (akun & contoh dokumen) TIDAK di-seed lewat SQL ini,
-- karena kolom password butuh hash bcrypt yang harus dibuat oleh
-- PHP (password_hash) di server Anda supaya dijamin valid.
--
-- Setelah menjalankan file SQL ini, buka file setup.php sekali
-- lewat browser (mis. http://localhost/delta-ocean-shipping/setup.php)
-- untuk membuat akun demo Admin Induk & Staf Kapal beserta contoh
-- data kapal/dokumen. Hapus setup.php setelah selesai dipakai.
-- ------------------------------------------------------------

-- ============================================================
-- MIGRASI UNTUK DATABASE YANG SUDAH ADA (upgrade)
-- ------------------------------------------------------------
-- Jika database delta_ocean_shipping sudah pernah dibuat sebelum
-- pembaruan ini, jalankan blok di bawah agar skema ikut ter-update
-- tanpa perlu menghapus data yang sudah ada. Aman dijalankan
-- berulang kali (baris yang sudah ada akan gagal secara wajar
-- dan bisa diabaikan jika muncul pesan "Duplicate column name").
-- ============================================================
-- ALTER TABLE tb_kapal ADD COLUMN no_hp_pemilik VARCHAR(30) NOT NULL DEFAULT '' AFTER id_user;
--
-- CREATE TABLE IF NOT EXISTS tb_riwayat_login (
--     id_riwayat  INT(11) NOT NULL AUTO_INCREMENT,
--     id_user     INT(11) NOT NULL,
--     aksi        ENUM('login','logout') NOT NULL,
--     ip_address  VARCHAR(45) NOT NULL DEFAULT '',
--     user_agent  VARCHAR(255) NOT NULL DEFAULT '',
--     waktu       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
--     PRIMARY KEY (id_riwayat),
--     KEY fk_riwayat_user (id_user),
--     CONSTRAINT fk_riwayat_user FOREIGN KEY (id_user)
--         REFERENCES tb_user (id_user) ON DELETE CASCADE
-- ) ENGINE=InnoDB;
--
-- CREATE TABLE IF NOT EXISTS tb_pengaturan_global (
--     id                 TINYINT(1) NOT NULL DEFAULT 1,
--     password_bersama   VARCHAR(255) NOT NULL,
--     updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--     PRIMARY KEY (id)
-- ) ENGINE=InnoDB;
-- ------------------------------------------------------------
-- CATATAN: Semua blok migrasi di atas TIDAK PERLU dijalankan manual —
-- aplikasi sudah memeriksa & membuatnya sendiri secara otomatis setiap
-- kali ada halaman yang dibuka (lihat autoMigrateSchema() di
-- config/database.php). Blok ini disediakan hanya sebagai referensi /
-- cadangan jika user database tidak memiliki hak ALTER TABLE.
-- ------------------------------------------------------------
