<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo  = getDBConnection();
requireValidSession($pdo);
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pencarian Surat &mdash; Sistem Dokumen Kapal</title>
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
            <p class="eyebrow">Pencarian Lintas Kapal</p>
            <h1>Pencarian Surat &amp; Sertifikat</h1>
        </div>
        <button id="btn-export-excel" class="btn btn--primary">&#128190; Export ke Excel</button>
    </div>

    <section class="panel">
        <div class="panel__head">
            <h2>Cari dari Semua Folder Kapal</h2>
        </div>

        <div class="panel__toolbar" style="flex-wrap:wrap; gap:10px;">
            <input type="text" id="ps-search-box" class="text-control" placeholder="Ketik nama surat, mis. SKAT, SIPI, dsb..." style="min-width:260px;">
        </div>

        <div class="quick-filter" style="display:flex; flex-wrap:wrap; gap:8px; margin:4px 0 18px;">
            <button type="button" class="btn btn--sm btn--ghost" data-term="">Semua Surat</button>
            <?php foreach (array_keys(DAFTAR_JENIS_SURAT) as $label): ?>
            <button type="button" class="btn btn--sm btn--ghost" data-term="<?= htmlspecialchars($label, ENT_QUOTES) ?>"><?= htmlspecialchars($label) ?></button>
            <?php endforeach; ?>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Kapal / Folder</th>
                    <th>Nama Surat</th>
                    <th>Tanggal Kedaluwarsa</th>
                    <th>Status</th>
                    <th>File</th>
                </tr>
            </thead>
            <tbody id="ps-table-body">
                <tr><td colspan="6" class="empty-cell">Memuat data...</td></tr>
            </tbody>
        </table>

        <div class="panel__footer">
            <span id="ps-summary"></span>
        </div>
    </section>
</main>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="assets/js/main.js?v=1786156095"></script>
<script>
$(function () {
    let currentSearch = '';
    let searchTimer = null;

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function muatHasil() {
        $('#ps-table-body').html('<tr><td colspan="7" class="empty-cell">Memuat data...</td></tr>');
        axios.get('pencarian_action.php', { params: { search: currentSearch } })
            .then(function (res) {
                const d = res.data;
                if (!d.success) {
                    $('#ps-table-body').html('<tr><td colspan="6" class="empty-cell">' + escapeHtml(d.message || 'Gagal memuat data.') + '</td></tr>');
                    return;
                }
                if (!d.data.length) {
                    $('#ps-table-body').html('<tr><td colspan="6" class="empty-cell">Tidak ada surat yang cocok dengan pencarian.</td></tr>');
                    $('#ps-summary').text('0 hasil ditemukan');
                    return;
                }
                const rows = d.data.map(function (r) {
                    return `
                        <tr>
                            <td>${r.no_urut}</td>
                            <td>${escapeHtml(r.nama_kapal)}</td>
                            <td><strong>${escapeHtml(r.nama_sertifikat)}</strong></td>
                            <td>${escapeHtml(r.tanggal_display)}</td>
                            <td><span class="badge ${r.status_kode === 'expired' ? 'badge--danger' : (r.status_kode === 'warning' ? 'badge--warning' : 'badge--ok')}">${escapeHtml(r.status_label)}</span></td>
                            <td><a class="link-file" href="${escapeHtml(r.file_path)}" target="_blank" rel="noopener">Lihat File</a></td>
                        </tr>`;
                }).join('');
                $('#ps-table-body').html(rows);
                $('#ps-summary').text(d.total + ' hasil ditemukan, diurutkan dari yang paling dekat kedaluwarsa.');
            })
            .catch(function () {
                $('#ps-table-body').html('<tr><td colspan="6" class="empty-cell">Gagal memuat data. Coba muat ulang halaman.</td></tr>');
            });
    }

    $('#ps-search-box').on('input', function () {
        const val = $(this).val();
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            currentSearch = val;
            $('.quick-filter button').removeClass('btn--primary').addClass('btn--ghost');
            muatHasil();
        }, 350);
    });

    $('.quick-filter').on('click', 'button[data-term]', function () {
        const term = $(this).data('term') || '';
        currentSearch = term;
        $('#ps-search-box').val(term);
        $('.quick-filter button').removeClass('btn--primary').addClass('btn--ghost');
        $(this).removeClass('btn--ghost').addClass('btn--primary');
        muatHasil();
    });

    $('#btn-export-excel').on('click', function () {
        const params = new URLSearchParams({ search: currentSearch });
        window.location.href = 'pencarian_export.php?' + params.toString();
    });

    muatHasil();
});
</script>
</body>
</html>
