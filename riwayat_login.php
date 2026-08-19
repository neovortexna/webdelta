<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo  = getDBConnection();
requireValidSession($pdo);
$user = currentUser();

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

if ($user['role'] === 'admin_induk') {
    $total = (int) $pdo->query('SELECT COUNT(*) FROM tb_riwayat_login')->fetchColumn();
    $stmt = $pdo->prepare('
        SELECT r.id_riwayat, r.aksi, r.ip_address, r.user_agent, r.waktu,
               u.username, u.nama_lengkap, u.role
        FROM tb_riwayat_login r
        JOIN tb_user u ON u.id_user = r.id_user
        ORDER BY r.waktu DESC
        LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset . '
    ');
    $stmt->execute();
} else {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tb_riwayat_login WHERE id_user = ?');
    $stmt->execute([$user['id_user']]);
    $total = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('
        SELECT r.id_riwayat, r.aksi, r.ip_address, r.user_agent, r.waktu,
               u.username, u.nama_lengkap, u.role
        FROM tb_riwayat_login r
        JOIN tb_user u ON u.id_user = r.id_user
        WHERE r.id_user = ?
        ORDER BY r.waktu DESC
        LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset . '
    ');
    $stmt->execute([$user['id_user']]);
}
$rows = $stmt->fetchAll();
$totalPages = max(1, (int) ceil($total / $perPage));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Riwayat Login/Logout &mdash; Sistem Dokumen Kapal</title>
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
            <p class="eyebrow">Audit Trail</p>
            <h1>Riwayat Login &amp; Logout</h1>
        </div>
    </div>

    <section class="panel">
        <div class="panel__head">
            <h2><?= $user['role'] === 'admin_induk' ? 'Aktivitas Seluruh User' : 'Aktivitas Akun Saya' ?></h2>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <?php if ($user['role'] === 'admin_induk'): ?><th>User</th><th>Role</th><?php endif; ?>
                    <th>Aksi</th>
                    <th>Alamat IP</th>
                    <th>Perangkat / Browser</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                <tr><td colspan="6" class="empty-cell">Belum ada riwayat aktivitas.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars(date('d M Y H:i:s', strtotime($r['waktu']))) ?></td>
                    <?php if ($user['role'] === 'admin_induk'): ?>
                    <td><?= htmlspecialchars($r['nama_lengkap'] ?: $r['username']) ?> <small>(<?= htmlspecialchars($r['username']) ?>)</small></td>
                    <td><?= $r['role'] === 'admin_induk' ? 'Admin Induk' : 'Staf Kapal' ?></td>
                    <?php endif; ?>
                    <td>
                        <?php if ($r['aksi'] === 'login'): ?>
                        <span class="badge badge--ok">Login</span>
                        <?php else: ?>
                        <span class="badge badge--warning">Logout</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($r['ip_address'] ?: '-') ?></td>
                    <td class="ua-cell" title="<?= htmlspecialchars($r['user_agent']) ?>"><?= htmlspecialchars(mb_strimwidth($r['user_agent'] ?: '-', 0, 60, '…')) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="panel__footer">
            <span>Menampilkan halaman <?= $page ?> dari <?= $totalPages ?> (<?= $total ?> aktivitas)</span>
            <div class="pagination">
                <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>" class="btn btn--sm btn--ghost">&lsaquo; Sebelumnya</a><?php endif; ?>
                <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>" class="btn btn--sm btn--ghost">Berikutnya &rsaquo;</a><?php endif; ?>
            </div>
        </div>
    </section>
</main>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="assets/js/main.js?v=1786156095"></script>
</body>
</html>
