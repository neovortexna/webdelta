<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo  = getDBConnection();
$user = currentUser();
$msg  = '';
$msgType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lama  = $_POST['password_lama'] ?? '';
    $baru  = $_POST['password_baru'] ?? '';
    $ulang = $_POST['password_ulang'] ?? '';

    if (!verifikasiPasswordBersama($pdo, $lama)) {
        $msg = 'Password lama tidak sesuai.'; $msgType = 'danger';
    } elseif (strlen($baru) < 8) {
        $msg = 'Password baru minimal 8 karakter.'; $msgType = 'danger';
    } elseif ($baru !== $ulang) {
        $msg = 'Konfirmasi password baru tidak cocok.'; $msgType = 'danger';
    } else {
        ubahPasswordBersama($pdo, $baru);
        $msg = 'Password bersama berhasil diperbarui. Mulai sekarang, SEMUA akun (Admin Induk & Staf Kapal) login memakai password baru ini.';
        $msgType = 'ok';
    }

}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pengaturan &mdash; Sistem Dokumen Kapal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=1786156095">
</head>
<body class="app-body" data-role="<?= htmlspecialchars($user['role']) ?>">

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="app-shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="app-main">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Pengaturan</p>
            <h1>Keamanan Akun</h1>
        </div>
    </div>

    <section class="panel panel--narrow">
        <div class="panel__head"><h2>Ubah Password Bersama</h2></div>
        <p class="field__hint" style="margin-top:-8px; margin-bottom:16px;">
            Password ini dipakai untuk login oleh <strong>semua akun</strong> (Admin Induk maupun Staf Kapal).
            Jika Anda mengubahnya di sini, <strong>seluruh user lain juga wajib memakai password baru ini</strong> saat login berikutnya.
        </p>

        <?php if ($msg): ?>
        <div class="alert-inline alert-inline--<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <form method="POST" class="modal-form">
            <label class="field">
                <span class="field__label">Password Bersama Saat Ini</span>
                <input type="password" name="password_lama" required>
            </label>
            <label class="field">
                <span class="field__label">Password Bersama Baru</span>
                <input type="password" name="password_baru" id="pw-baru" minlength="8" required>
            </label>
            <label class="field">
                <span class="field__label">Ulangi Password Bersama Baru</span>
                <input type="password" name="password_ulang" id="pw-ulang" minlength="8" required>
                <small class="field__hint" id="pw-match-hint"></small>
            </label>
            <div class="modal-form__actions">
                <button type="submit" class="btn btn--primary">Simpan &amp; Berlakukan untuk Semua Akun</button>
            </div>
        </form>
    </section>
</main>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/main.js?v=1786156095"></script>
<script>
(function () {
    var baru = document.getElementById('pw-baru');
    var ulang = document.getElementById('pw-ulang');
    var hint = document.getElementById('pw-match-hint');
    if (!baru || !ulang) return;
    function cekCocok() {
        if (!ulang.value) { hint.textContent = ''; ulang.setCustomValidity(''); return; }
        if (baru.value === ulang.value) {
            hint.textContent = 'Password cocok.';
            hint.style.color = 'var(--color-success, #1e7e34)';
            ulang.setCustomValidity('');
        } else {
            hint.textContent = 'Password tidak sama.';
            hint.style.color = 'var(--color-danger, #B7392C)';
            ulang.setCustomValidity('Password tidak sama.');
        }
    }
    baru.addEventListener('input', cekCocok);
    ulang.addEventListener('input', cekCocok);
})();
</script>
</body>
</html>
