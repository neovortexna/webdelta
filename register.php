<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $username     = trim($_POST['username'] ?? '');

    if ($nama_lengkap === '' || $email === '' || $username === '') {
        $error = 'Semua kolom wajib diisi.';
    } else {
        $pdo = getDBConnection();
        
        // Cek apakah username atau email sudah digunakan
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM tb_user WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Username atau Email sudah terdaftar.';
        } else {
            // Kolom password tb_user tetap diisi (NOT NULL) tapi TIDAK dipakai untuk
            // login — semua akun login memakai Password Bersama (lihat menu Pengaturan).
            $hashTidakDipakai = ambilHashPasswordBersama($pdo) ?? password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
            $insert = $pdo->prepare('INSERT INTO tb_user (username, password, nama_lengkap, email, role) VALUES (?, ?, ?, ?, ?)');
            
            if ($insert->execute([$username, $hashTidakDipakai, $nama_lengkap, $email, 'staf_kapal'])) {
                $success = 'Pendaftaran berhasil! Silakan masuk menggunakan Username di atas dan Password Bersama yang berlaku saat ini.';
            } else {
                $error = 'Terjadi kesalahan sistem saat mendaftar.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Akun &mdash; Sistem Dokumen Kapal | PT Delta Ocean Shipping</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=1786156095">
</head>
<body class="auth-body">

<div class="auth-shell">
    <div class="auth-visual">
        <div class="auth-visual__grid"></div>
        <div class="auth-visual__content">
            <div class="brand-mark">
                <img src="assets/img/logo-delta-ocean.png" alt="Logo PT Delta Ocean Shipping" class="brand-mark__logo">
                <span class="brand-mark__text">DELTA OCEAN SHIPPING</span>
            </div>
            <h1 class="auth-visual__title">Bergabung dengan<br>Sistem Digital.</h1>
            <p class="auth-visual__subtitle">
                Buat akun staf untuk mulai memantau dan mengelola dokumen kapal di bawah tanggung jawab Anda.
            </p>
        </div>
    </div>

    <div class="auth-panel">
        <form class="auth-form" method="POST" action="register.php" autocomplete="off">
            <div class="auth-form__header">
                <p class="eyebrow">Portal register</p>
                <h2>Daftar Akun Baru</h2>
                <p class="auth-form__hint">Lengkapi data diri Anda di bawah ini.</p>
            </div>

            <?php if ($error): ?>
            <div class="alert-inline alert-inline--danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert-inline alert-inline--ok"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <label class="field">
                <span class="field__label">Nama Lengkap</span>
                <input type="text" name="nama_lengkap" placeholder="mis. Budi Santoso" required autofocus>
            </label>

            <label class="field">
                <span class="field__label">Email</span>
                <input type="email" name="email" placeholder="" required>
            </label>

            <label class="field">
                <span class="field__label">Username</span>
                <input type="text" name="username" placeholder="" required>
            </label>

            <div class="alert-inline" style="background:#EEF2F7; color:var(--ink-600); border:1px solid var(--line);">
                Sistem ini memakai <strong>Password Bersama</strong> untuk semua akun (tidak diatur per pendaftaran).
                Setelah akun dibuat, login dengan Username di atas dan password bersama yang berlaku saat ini
                (hubungi Admin Induk jika belum tahu, atau lihat/atur di menu Pengaturan setelah login).
            </div>

            <button type="submit" class="btn btn--primary btn--block">Daftar Akun</button>

            <p class="auth-form__footer" style="margin-top: 1.5rem; text-align: center;">
                Sudah punya akun? <a href="login.php" style="color: var(--color-primary); font-weight: 600;">Masuk di sini</a>
            </p>
        </form>
    </div>
</div>

</body>
</html>