<?php
/**
 * ============================================================
 * notifikasi_expired.php
 * PT Delta Ocean Shipping
 * ------------------------------------------------------------
 * Fitur "Notifikasi Surat Expired" — dibuka lewat tombol di
 * Dashboard. Menampilkan 3 halaman (tab):
 *
 *   Halaman 1: SKAT (7 hari), Airtime/BPJS (7 hari),
 *              No HP Pemilik (7 hari), SIPI/EBKP/IOTC (45 hari)
 *   Halaman 2: Kelaikan/SKKP (90 hari), Pas Besar/Surat Laut (30 hari),
 *              SSCEC (14 hari), ISR (40 hari)
 *   Halaman 3: Tampilan gabungan (overview) SEMUA 8 kategori sekaligus
 *              dalam satu layar, dirancang supaya nyaman dilihat penuh
 *              di layar monitor tanpa perlu pindah tab.
 *
 * Catatan penting:
 *   - Surat yang SUDAH kedaluwarsa TIDAK ditampilkan di sini lagi —
 *     surat itu cukup terlihat di Manajemen Dokumen / Pencarian Surat.
 *     Kartu-kartu di halaman ini hanya untuk surat yang PERLU disiapkan
 *     sebelum tanggal jatuh tempo.
 *   - Setiap kartu bisa discroll ke bawah jika isinya banyak.
 *   - Tiap baris punya 2 kontrol: tombol "Diproses" (kuning) dan
 *     checkbox "Selesai" yang bisa dicentang manual kapan saja
 *     (baris otomatis disembunyikan begitu dicentang selesai).
 * ============================================================
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo  = getDBConnection();
requireValidSession($pdo);
$user = currentUser();

// Ambil semua item untuk semua kartu, dikelompokkan per halaman, supaya
// halaman ini tidak perlu AJAX terpisah untuk tiap kartu (lebih ringan & sederhana).
$dataHalaman = [];
foreach (DAFTAR_KATEGORI_NOTIFIKASI as $noHalaman => $daftarKategori) {
    $dataHalaman[$noHalaman] = [];
    foreach ($daftarKategori as $kategori) {
        $dataHalaman[$noHalaman][] = [
            'kategori' => $kategori,
            'items'    => ambilItemKategoriNotifikasi($pdo, $kategori),
        ];
    }
    // Kartu yang paling mendesak (surat H-3 atau kurang, lalu jumlah surat
    // yang masih perlu perhatian, lalu tenggat paling dekat) ditampilkan
    // paling atas, supaya yang paling butuh perhatian langsung terlihat.
    $dataHalaman[$noHalaman] = urutkanKartuNotifikasi($dataHalaman[$noHalaman]);
}

// Urutan dasar kartu untuk Halaman 3 (tampilan gabungan/overview), mengikuti
// susunan pada sketsa: SKAT, Airtime/BPJS, Pas Besar/Surat Laut, Kelaikan/SKKP,
// No HP Pemilik, SSCEC, ISR, SIPI/EBKP/IOTC. Urutan ini lalu ditimpa oleh
// urutkanKartuNotifikasi() di bawah supaya kartu paling mendesak tetap naik
// ke atas.
$urutanOverview = ['skat', 'airtime', 'pasbesar_suratlaut', 'kelaikan_skkp', 'no_hp_pemilik', 'sscec', 'isr', 'sipi_ebkp_iotc'];
$semuaKartu = array_merge($dataHalaman[1], $dataHalaman[2]);
$kartuByKey = [];
foreach ($semuaKartu as $kartu) {
    $kartuByKey[$kartu['kategori']['key']] = $kartu;
}
$kartuOverview = [];
foreach ($urutanOverview as $key) {
    if (isset($kartuByKey[$key])) {
        $kartuOverview[] = $kartuByKey[$key];
    }
}
// Sama seperti Halaman 1 & 2: kartu paling mendesak naik ke atas di Overview juga.
$kartuOverview = urutkanKartuNotifikasi($kartuOverview);

$pageTitle = 'Notifikasi Surat Expired';

/** Helper cetak satu kartu kategori (dipakai ulang di ketiga halaman) */
function cetakKartuNotifikasi(array $kartu): void
{
    $kat   = $kartu['kategori'];
    $items = $kartu['items'];
    $belumSelesai = array_filter($items, fn($it) => $it['status'] !== 'selesai');
    ?>
    <div class="notif-card" data-kategori="<?= htmlspecialchars($kat['key']) ?>">
        <div class="notif-card__head">
            <span class="notif-card__title"><?= htmlspecialchars($kat['label']) ?></span>
            <span class="notif-card__hari">H-<?= (int) $kat['hari'] ?> hari</span>
        </div>
        <div class="notif-card__count"><?= count($belumSelesai) ?> perlu perhatian &middot; <?= count($items) ?> total</div>
        <ul class="notif-card__list">
            <?php if (!$items): ?>
            <li class="notif-card__empty">Tidak ada surat pada kategori ini.</li>
            <?php endif; ?>
            <?php foreach ($items as $it): ?>
            <li class="notif-item <?= $it['status'] === 'selesai' ? 'is-selesai' : '' ?> <?= !empty($it['urgen']) ? 'is-urgen' : '' ?>"
                data-tipe="dokumen" data-id="<?= (int) $it['id'] ?>"
                <?= $it['status'] === 'selesai' ? 'hidden' : '' ?>>
                <div class="notif-item__body">
                    <div class="notif-item__judul"><?= htmlspecialchars($it['judul']) ?></div>
                    <div class="notif-item__sub"><?= htmlspecialchars($it['sub']) ?></div>
                    <div class="notif-item__ket"><?= htmlspecialchars($it['keterangan']) ?></div>
                </div>
                <div class="notif-item__controls">
                    <button type="button" class="notif-pill-proses <?= $it['status'] === 'proses' ? 'is-active' : '' ?>">Diproses</button>
                    <label class="notif-check-selesai" title="Tandai selesai">
                        <input type="checkbox" <?= $it['status'] === 'selesai' ? 'checked' : '' ?>>
                        <span>Selesai</span>
                    </label>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($items): ?>
        <div style="padding:0 14px 14px;">
            <button type="button" class="btn btn--sm btn--ghost btn--block btn-toggle-selesai">Tampilkan yang sudah selesai</button>
        </div>
        <?php endif; ?>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifikasi Surat Expired &mdash; Sistem Dokumen Kapal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=1786156095">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="app-body notif-expired-page" data-role="<?= htmlspecialchars($user['role']) ?>">

<!-- Halaman ini sengaja TIDAK menyertakan header.php (bar PT Delta / lonceng / profil staf) —
     dirancang untuk tampilan bersih di layar TV/monitor, hanya kartu-kartu notifikasi yang tampil. -->

<div class="app-shell app-shell--tv">

    <!-- Sidebar dijadikan panel yang bisa ditarik keluar/masuk dari pinggir layar,
         supaya tidak memakan tempat saat layar dipakai sebagai tampilan TV. -->
    <div class="tv-drawer" id="tv-drawer">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
    </div>
    <button type="button" class="tv-drawer__handle" id="tv-drawer-handle" title="Buka menu" aria-label="Buka menu">
        <span class="tv-drawer__handle-arrow">&#8250;</span>
    </button>
    <div class="tv-drawer__overlay" id="tv-drawer-overlay"></div>

    <a href="dashboard.php" class="btn btn--ghost tv-back-btn">&larr; Kembali ke Dashboard</a>

    <main class="app-main app-main--tv">

        <section class="notif-halaman" data-halaman="1">
            <div class="notif-grid">
                <?php foreach ($dataHalaman[1] as $kartu) { cetakKartuNotifikasi($kartu); } ?>
            </div>
        </section>

        <section class="notif-halaman" data-halaman="2" hidden>
            <div class="notif-grid">
                <?php foreach ($dataHalaman[2] as $kartu) { cetakKartuNotifikasi($kartu); } ?>
            </div>
        </section>

        <section class="notif-halaman" data-halaman="3" hidden>
            <div id="notif-overview-grid" class="notif-grid notif-grid--overview">
                <?php foreach ($kartuOverview as $kartu) { cetakKartuNotifikasi($kartu); } ?>
            </div>
        </section>
    </main>

    <!-- Penunjuk halaman kecil di pojok bawah, menggantikan tab besar di atas -->
    <div class="notif-tabs notif-tabs--corner">
        <button type="button" class="notif-tabs__btn is-active" data-halaman="1" title="Halaman 1">1</button>
        <button type="button" class="notif-tabs__btn" data-halaman="2" title="Halaman 2">2</button>
        <button type="button" class="notif-tabs__btn notif-tabs__btn--wide" data-halaman="3" title="Halaman 3 — Semua (Overview)">Semua</button>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="assets/js/main.js?v=1786156095"></script>
<script>
(function () {
    // Panel menu (sidebar) yang bisa ditarik keluar/masuk dari pinggir layar —
    // cocok dipakai lewat sentuhan/klik maupun remote TV Android.
    const $drawer   = $('#tv-drawer');
    const $handle   = $('#tv-drawer-handle');
    const $overlay  = $('#tv-drawer-overlay');

    function bukaDrawer() {
        $drawer.addClass('is-open');
        $overlay.addClass('is-open');
        $handle.addClass('is-open').attr('aria-label', 'Tutup menu');
    }
    function tutupDrawer() {
        $drawer.removeClass('is-open');
        $overlay.removeClass('is-open');
        $handle.removeClass('is-open').attr('aria-label', 'Buka menu');
    }
    $handle.on('click', function () {
        if ($drawer.hasClass('is-open')) tutupDrawer(); else bukaDrawer();
    });
    $overlay.on('click', tutupDrawer);
    $(document).on('keyup', function (e) {
        if (e.key === 'Escape') tutupDrawer();
    });

    // Tab Halaman 1 / 2 / 3
    $('.notif-tabs__btn').on('click', function () {
        const h = $(this).data('halaman');
        $('.notif-tabs__btn').removeClass('is-active');
        $(this).addClass('is-active');
        $('.notif-halaman').attr('hidden', true);
        $('.notif-halaman[data-halaman="' + h + '"]').removeAttr('hidden');
        if (h === 3) fitOverviewGrid();
    });

    /* Halaman 3 dirancang supaya SEMUA 8 kartu terlihat dalam satu layar
       (tanpa perlu men-scroll halamannya) di layar besar (>860px). Tinggi
       grid dihitung dinamis dari sisa ruang layar yang tersedia, supaya
       selalu pas apa pun ukuran monitornya — hanya isi tiap kartu yang
       tetap bisa discroll sendiri kalau suratnya banyak. */
    function fitOverviewGrid() {
        const $grid = $('#notif-overview-grid');
        if (!$grid.length) return;

        if (window.innerWidth <= 860) {
            $grid.css('height', ''); // di layar sempit, biarkan mengalir normal & halaman yang discroll
            return;
        }
        const top = $grid[0].getBoundingClientRect().top;
        const tinggiTersedia = Math.max(window.innerHeight - top - 24, 420);
        $grid.css('height', tinggiTersedia + 'px');
    }

    let resizeTimer = null;
    $(window).on('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (!$('.notif-halaman[data-halaman="3"]').is('[hidden]')) fitOverviewGrid();
        }, 150);
    });

    // Kalau halaman dibuka langsung dalam kondisi tab 3 aktif (jarang terjadi,
    // tapi jaga-jaga), langsung ukur begitu semua elemen selesai dirender.
    if (!$('.notif-halaman[data-halaman="3"]').is('[hidden]')) fitOverviewGrid();

    function kirimStatus(tipe, id, status) {
        return axios.post('notifikasi_action.php', new URLSearchParams({ tipe, id, status }));
    }

    /* Item yang sama bisa muncul lebih dari sekali (mis. di Halaman 1 DAN
       Halaman 3 sekaligus). Setiap kali status berubah, semua salinan
       item itu di seluruh halaman ikut disinkronkan supaya tidak ada
       tampilan yang basi saat pindah tab. */
    function sinkronItem(tipe, id, status) {
        $('.notif-item[data-tipe="' + tipe + '"][data-id="' + id + '"]').each(function () {
            const $item = $(this);
            const $pill = $item.find('.notif-pill-proses');
            const $chk  = $item.find('.notif-check-selesai input');

            $item.toggleClass('is-selesai', status === 'selesai');
            $pill.toggleClass('is-active', status === 'proses');
            $chk.prop('checked', status === 'selesai');

            if (status === 'selesai') {
                $item.fadeOut(180);
            } else {
                $item.show();
            }
        });
    }

    // Tombol "Diproses": toggle antara belum <-> proses (tidak berlaku kalau sudah selesai)
    $('.notif-card__list').on('click', '.notif-pill-proses', function () {
        const $item = $(this).closest('.notif-item');
        if ($item.hasClass('is-selesai')) return;
        const statusBaru = $(this).hasClass('is-active') ? 'belum' : 'proses';

        kirimStatus('dokumen', $item.data('id'), statusBaru).then(function (res) {
            if (!res.data || !res.data.success) {
                Swal.fire('Gagal', 'Gagal menyimpan status.', 'error');
                return;
            }
            sinkronItem('dokumen', $item.data('id'), statusBaru);
        }).catch(function () {
            Swal.fire('Gagal', 'Terjadi kesalahan pada server.', 'error');
        });
    });

    // Checkbox "Selesai": bisa dicentang manual kapan saja, termasuk saat sedang "Diproses"
    $('.notif-card__list').on('change', '.notif-check-selesai input', function () {
        const $chk  = $(this);
        const $item = $chk.closest('.notif-item');
        const statusBaru = $chk.is(':checked') ? 'selesai' : 'belum';

        kirimStatus('dokumen', $item.data('id'), statusBaru).then(function (res) {
            if (!res.data || !res.data.success) {
                Swal.fire('Gagal', 'Gagal menyimpan status.', 'error');
                $chk.prop('checked', !$chk.is(':checked')); // rollback tampilan
                return;
            }
            sinkronItem('dokumen', $item.data('id'), statusBaru);
        }).catch(function () {
            Swal.fire('Gagal', 'Terjadi kesalahan pada server.', 'error');
            $chk.prop('checked', !$chk.is(':checked'));
        });
    });

    // Tampilkan/sembunyikan item yang berstatus "selesai" per kartu
    $('.btn-toggle-selesai').on('click', function () {
        const $card = $(this).closest('.notif-card');
        const $selesai = $card.find('.notif-item.is-selesai');
        const sedangTampil = $(this).data('tampil') === true;
        if (sedangTampil) {
            $selesai.hide();
            $(this).data('tampil', false).text('Tampilkan yang sudah selesai');
        } else {
            $selesai.show();
            $(this).data('tampil', true).text('Sembunyikan yang sudah selesai');
        }
    });
})();
</script>
</body>
</html>
