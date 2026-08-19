<?php
$user    = currentUser();
$current = basename($_SERVER['PHP_SELF']);
function navClass(string $file, string $current): string {
    return $file === $current ? 'sidenav__link sidenav__link--active' : 'sidenav__link';
}
?>
<aside class="app-sidebar">
    <nav class="sidenav">
        <a href="dashboard.php" class="<?= navClass('dashboard.php', $current) ?>">
            <span class="sidenav__icon">&#9635;</span> Dashboard
        </a>
        
        <!-- Menu Master Kapal kini dibuka untuk semua user (Admin Induk & Staf Kapal) -->
        <a href="master_kapal.php" class="<?= navClass('master_kapal.php', $current) ?>">
            <span class="sidenav__icon">&#9875;</span> Master Kapal
        </a>

        <a href="dokumen.php" class="<?= navClass('dokumen.php', $current) ?>">
            <span class="sidenav__icon">&#128196;</span> Manajemen Dokumen
        </a>
        <a href="notifikasi_expired.php" class="<?= navClass('notifikasi_expired.php', $current) ?>">
            <span class="sidenav__icon">&#128276;</span> Notifikasi Expired
        </a>
        <a href="pencarian_surat.php" class="<?= navClass('pencarian_surat.php', $current) ?>">
            <span class="sidenav__icon">&#128269;</span> Pencarian Surat
        </a>
        <a href="riwayat_login.php" class="<?= navClass('riwayat_login.php', $current) ?>">
            <span class="sidenav__icon">&#128337;</span> Riwayat Login/Logout
        </a>
        <a href="pengaturan.php" class="<?= navClass('pengaturan.php', $current) ?>">
            <span class="sidenav__icon">&#9881;</span> Pengaturan
        </a>
    </nav>

    <div class="sidenav__foot">
        <p>Batas dokumen<br><strong>50 file / kapal</strong></p>
    </div>
</aside>