<?php
$user = currentUser();
?>
<header class="app-header">
    <div class="app-header__brand">
        <img src="assets/img/logo-delta-ocean.png" alt="Logo PT Delta Ocean Shipping" class="brand-mark__logo">

        <div class="app-header__brand-text">
            <strong>PT Delta Ocean Shipping</strong>
            <small>Sistem Manajemen Dokumen Kapal</small>
        </div>
    </div>

    <div class="app-header__actions">
        <button id="btn-bell" class="icon-btn" title="Peringatan Dokumen (H-7)" aria-label="Notifikasi">
            <span class="icon-btn__bell">&#128276;</span>
            <span id="bell-badge" class="icon-btn__badge" hidden>0</span>
        </button>

        <div class="app-header__profile">
            <div class="avatar"><?= strtoupper(substr($user['nama_lengkap'] ?: $user['username'], 0, 1)) ?></div>
            <div class="app-header__profile-text">
                <strong><?= htmlspecialchars($user['nama_lengkap'] ?: $user['username']) ?></strong>
                <small><?= $user['role'] === 'admin_induk' ? 'Admin Induk' : 'Staf Kapal' ?></small>
            </div>
            <a href="logout.php" class="btn btn--ghost btn--sm">Keluar</a>
        </div>
    </div>
</header>
