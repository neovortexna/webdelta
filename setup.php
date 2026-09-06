<?php
/**
 * ============================================================
 * setup.php — Jalankan SEKALI lewat browser setelah import
 * sql/delta_ocean.sql, untuk membuat akun demo & contoh data.
 * HAPUS FILE INI setelah selesai digunakan (alasan keamanan).
 * ============================================================
 */
require_once __DIR__ . '/config/database.php';

$pdo = getDBConnection();

$already = $pdo->query("SELECT COUNT(*) FROM tb_user")->fetchColumn();
if ($already > 0) {
    die('Setup dibatalkan: tabel tb_user sudah berisi data. Hapus file setup.php ini demi keamanan.');
}

$pdo->beginTransaction();
try {
    $hash = password_hash('password123', PASSWORD_BCRYPT);

    $pdo->prepare('INSERT INTO tb_user (username, password, nama_lengkap, email, role) VALUES (?, ?, ?, ?, ?)')
        ->execute(['admin', $hash, 'Admin Induk', 'admin@deltaocean.co.id', 'admin_induk']);
    $idAdmin = $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO tb_user (username, password, nama_lengkap, email, role) VALUES (?, ?, ?, ?, ?)')
        ->execute(['staf01', $hash, 'Budi Santoso', 'budi@deltaocean.co.id', 'staf_kapal']);
    $idStaf = $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO tb_kapal (nama_kapal, id_user) VALUES (?, ?)')->execute(['KM. Delta 01', $idStaf]);
    $idKapal1 = $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tb_kapal (nama_kapal, id_user) VALUES (?, ?)')->execute(['KM. Delta 02', $idStaf]);
    $idKapal2 = $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO tb_dokumen (id_kapal, nama_sertifikat, file_path, tanggal_expired) VALUES (?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 15 DAY))')
        ->execute([$idKapal1, 'Sertifikat Keselamatan Kapal', 'uploads/contoh_placeholder.pdf']);
    $pdo->prepare('INSERT INTO tb_dokumen (id_kapal, nama_sertifikat, file_path, tanggal_expired) VALUES (?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 200 DAY))')
        ->execute([$idKapal1, 'Sertifikat Garis Muat', 'uploads/contoh_placeholder.pdf']);
    $pdo->prepare('INSERT INTO tb_dokumen (id_kapal, nama_sertifikat, file_path, tanggal_expired) VALUES (?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 5 DAY))')
        ->execute([$idKapal2, 'Sertifikat Klasifikasi Kapal', 'uploads/contoh_placeholder.pdf']);

    $pdo->commit();
    echo "<h2>Setup berhasil!</h2>
    <p>Akun demo telah dibuat. Sistem ini memakai <strong>satu Password Bersama</strong> untuk SEMUA akun (default: <strong>DELT@111213</strong>, bisa diubah lewat menu Pengaturan setelah login):</p>
    <ul>
        <li>Admin Induk — username: <strong>admin</strong></li>
        <li>Staf Kapal — username: <strong>staf01</strong></li>
        
    </ul>
    <p><strong>PENTING:</strong> Hapus file <code>setup.php</code> ini sekarang dari server.</p>
    <p><a href='login.php'>Lanjut ke halaman Login &rarr;</a></p>";
} catch (Exception $e) {
    $pdo->rollBack();
    echo 'Setup gagal: ' . htmlspecialchars($e->getMessage());
}