<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo  = getDBConnection();
requireValidSession($pdo);
$user = currentUser();

// Sesuai permintaan: semua staf melihat ringkasan dashboard SELURUH armada
// (bukan hanya kapal miliknya), sama seperti Admin Induk.
$totalKapal   = $pdo->query('SELECT COUNT(*) FROM tb_kapal')->fetchColumn();
$totalDokumen = $pdo->query('SELECT COUNT(*) FROM tb_dokumen')->fetchColumn();
$totalExpired = $pdo->query('SELECT COUNT(*) FROM tb_dokumen WHERE tanggal_expired < CURDATE()')->fetchColumn();
$totalWarning = $pdo->query('SELECT COUNT(*) FROM tb_dokumen WHERE tanggal_expired >= CURDATE() AND DATEDIFF(tanggal_expired, CURDATE()) <= ' . POPUP_WARNING_DAYS)->fetchColumn();

$recent = $pdo->query('
    SELECT d.nama_sertifikat, d.tanggal_expired, k.nama_kapal
    FROM tb_dokumen d
    JOIN tb_kapal k ON k.id_kapal = d.id_kapal
    ORDER BY d.tanggal_expired ASC
    LIMIT 8
')->fetchAll();

// Total item yang masih butuh perhatian di fitur Notifikasi Surat Expired
// (dihitung dari 8 kartu kategori di notifikasi_expired.php)
$totalNotifPending = hitungTotalNotifikasiPending($pdo);

$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard &mdash; Sistem Dokumen Kapal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=1786156095">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="app-body" data-role="<?= htmlspecialchars($user['role']) ?>">

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="app-shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="app-main">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Dashboard</p>
            <h1>Selamat Datang, <?= htmlspecialchars($user['nama_lengkap'] ?: $user['username']) ?></h1>
        </div>
    </div>

    <a href="notifikasi_expired.php" class="notif-widget">
        <div class="notif-widget__left">
            <span class="notif-widget__icon">&#128276;</span>
            <div>
                <div class="notif-widget__title">Notifikasi Surat Expired</div>
                <div class="notif-widget__desc">SKAT, Airtime/BPJS, SIPI/EBKP/IOTC, Kelaikan/SKKP, Pas Besar/Surat Laut, SSCEC, ISR &amp; No HP Pemilik</div>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:14px;">
            <span class="notif-widget__badge <?= $totalNotifPending === 0 ? 'notif-widget__badge--zero' : '' ?>">
                <?= $totalNotifPending ?> perlu perhatian
            </span>
            <span class="notif-widget__arrow">&rsaquo;</span>
        </div>
    </a>

    <section class="stat-grid">
        <div class="stat-card">
            <span class="stat-card__label">Total Armada</span>
            <span class="stat-card__value"><?= (int) $totalKapal ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-card__label">Total Dokumen</span>
            <span class="stat-card__value"><?= (int) $totalDokumen ?></span>
        </div>
        <div class="stat-card stat-card--warning">
            <span class="stat-card__label">Mendekati Kedaluwarsa (H-7)</span>
            <span class="stat-card__value"><?= (int) $totalWarning ?></span>
        </div>
        <div class="stat-card stat-card--danger">
            <span class="stat-card__label">Sudah Kedaluwarsa</span>
            <span class="stat-card__value"><?= (int) $totalExpired ?></span>
        </div>
    </section>

    <section class="panel">
        <div class="panel__head">
            <h2>Dokumen Terdekat Jatuh Tempo</h2>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Surat</th>
                    <th>Kapal</th>
                    <th>Tanggal Kedaluwarsa</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$recent): ?>
                <tr><td colspan="4" class="empty-cell">Belum ada data dokumen.</td></tr>
                <?php endif; ?>
                <?php foreach ($recent as $row): [$_, $label, $cls] = statusDokumen($row['tanggal_expired']); ?>
                <tr>
                    <td><?= htmlspecialchars($row['nama_sertifikat']) ?></td>
                    <td><?= htmlspecialchars($row['nama_kapal']) ?></td>
                    <td><?= formatTanggalID($row['tanggal_expired']) ?></td>
                    <td><span class="<?= $cls ?>"><?= $label ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="assets/js/main.js?v=1786156095"></script>
<!-- Popup notifikasi H-7 sekarang otomatis berjalan di semua halaman lewat main.js, tidak perlu dipanggil manual di sini -->
</body>
</html>
