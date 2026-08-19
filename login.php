<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if (isset($_GET['expired'])) {
    $error = 'Sesi Anda tidak valid lagi (kemungkinan akun sudah dihapus atau database di-reset). Silakan login ulang.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $pdo  = getDBConnection();
        $stmt = $pdo->prepare('SELECT id_user, username, password, nama_lengkap, role FROM tb_user WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Password login memakai PASSWORD BERSAMA (sama untuk semua akun),
        // bukan password unik per-user. Username tetap harus terdaftar
        // untuk menentukan identitas & role yang login.
        if ($user && verifikasiPasswordBersama($pdo, $password)) {
            session_regenerate_id(true);
            $_SESSION['id_user']      = $user['id_user'];
            $_SESSION['username']     = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role']         = $user['role'];

            // Catat riwayat login (untuk audit trail / Riwayat Login-Logout)
            catatRiwayatLogin($pdo, (int) $user['id_user'], 'login');

            header('Location: dashboard.php');
            exit;
        }

        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk &mdash; Sistem Dokumen Kapal | PT Delta Ocean Shipping</title>
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
            <h1 class="auth-visual__title">Manifest digital<br>untuk setiap surat kapal.</h1>
            <p class="auth-visual__subtitle">
                Satu dasbor untuk memantau kedaluwarsa sertifikat seluruh armada &mdash;
                sebelum surat kadaluwarsa mengganggu pelayaran.
            </p>
            <ul class="auth-visual__stamps">
                <li><span class="stamp stamp--ok">LAIK LAUT</span> Dokumen terpantau otomatis</li>
                <li><span class="stamp stamp--warn">H-7</span> Notifikasi dini via email</li>
                <li><span class="stamp stamp--danger">EXPIRED</span> Peringatan sebelum jatuh tempo</li>
            </ul>
        </div>
    </div>

    <div class="auth-panel">
        <form class="auth-form" method="POST" action="login.php" autocomplete="off">
            <div class="auth-form__header">
                <p class="eyebrow">Portal Internal</p>
                <h2>Masuk ke Sistem</h2>
                <p class="auth-form__hint">Gunakan akun Anda untuk masuk.</p>
            </div>

            <?php if ($error): ?>
            <div class="alert-inline alert-inline--danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <label class="field">
                <span class="field__label">Username</span>
                <input type="text" name="username" placeholder="mis. admin" required autofocus>
            </label>

            <label class="field">
                <span class="field__label">Password</span>
                <input type="password" name="password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required>
            </label>

            <button type="submit" class="btn btn--primary btn--block">Masuk Dasbor</button>

            <!-- Bagian ini ditambahkan untuk tautan pendaftaran -->
            <p class="auth-form__footer" style="margin-top: 1.5rem; text-align: center;">
                Belum punya akun? <a href="register.php" style="color: var(--color-primary); font-weight: 600;">Daftar sekarang</a><br><br>
                Lupa kata sandi? Hubungi Admin Induk kantor pusat.
            </p>
        </form>
    </div>
</div>

</body>
<script>
  // Reset status "popup H-7 sudah ditampilkan" setiap kali balik ke halaman login
  // (logout atau ganti akun), supaya user berikutnya tetap dapat notifikasi segar.
  try { sessionStorage.removeItem('simas_h7_popup_shown'); } catch (e) {}
</script>
</html>