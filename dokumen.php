<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo  = getDBConnection();
requireValidSession($pdo);
$user = currentUser();

// Daftar kapal yang boleh dikelola user ini.
// Sesuai permintaan: semua staf boleh melihat & mengelola dokumen SEMUA kapal,
// sama seperti Admin Induk — jadi daftar kapal tidak lagi dibatasi per pemilik.
$daftarKapal = $pdo->query('SELECT id_kapal, nama_kapal FROM tb_kapal ORDER BY nama_kapal')->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen Dokumen &mdash; Sistem Dokumen Kapal</title>
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
            <p class="eyebrow">Manajemen Dokumen</p>
            <h1>Sertifikat &amp; Surat Kapal</h1>
        </div>
        <button id="btn-tambah" class="btn btn--primary">+ Tambah Folder/Dokumen</button>
    </div>

    <section class="panel">
        <div class="panel__head">
            <h2>Daftar Dokumen</h2>
        </div>

        <div class="panel__toolbar">
            <select id="filter-kapal" class="select-control">
                <?php foreach ($daftarKapal as $k): ?>
                <option value="<?= (int) $k['id_kapal'] ?>"><?= htmlspecialchars($k['nama_kapal']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" id="search-box" class="text-control" placeholder="Cari nama dokumen...">
            <span id="quota-info" class="quota-info">0 / <?= MAX_DOKUMEN_PER_KAPAL ?> dokumen</span>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Nama Dokumen</th>
                    <?php if ($user['role'] === 'admin_induk'): ?><th>Kapal</th><?php endif; ?>
                    <th>Tanggal Kedaluwarsa</th>
                    <th>Status</th>
                    <th>File</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="table-body">
                <tr><td colspan="6" class="empty-cell">Memuat data...</td></tr>
            </tbody>
        </table>

        <div class="panel__footer">
            <span id="pagination-summary"></span>
            <div id="pagination-controls" class="pagination"></div>
        </div>
    </section>
</main>
</div>

<!-- Modal Tambah / Edit Dokumen -->
<div id="modal-dokumen" class="modal-overlay" hidden>
    <div class="modal-content">
        <div class="modal-content__head">
            <h3 id="modal-title">Tambah Dokumen</h3>
            <button type="button" id="modal-close" class="modal-close" aria-label="Tutup">&times;</button>
        </div>
        <form id="form-dokumen" class="modal-form" enctype="multipart/form-data">
            <input type="hidden" name="id_dokumen" id="f-id_dokumen">

            <label class="field">
                <span class="field__label">Kapal</span>
                <select name="id_kapal" id="f-id_kapal" class="select-control" required>
                    <?php foreach ($daftarKapal as $k): ?>
                    <option value="<?= (int) $k['id_kapal'] ?>"><?= htmlspecialchars($k['nama_kapal']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <!-- Nama Sertifikat menjadi opsional jika unggah folder (nama file akan digunakan) -->
            <label class="field">
                <span class="field__label">Nama Dokumen (Kosongkan jika unggah folder)</span>
                <input type="text" name="nama_sertifikat" id="f-nama_sertifikat" placeholder="Otomatis menggunakan nama file jika folder">
            </label>

            <!-- Tanggal expired: dipakai sebagai CADANGAN. Saat unggah folder,
                 tanggal tiap file dideteksi otomatis dari nama filenya. -->
            <label class="field">
                <span class="field__label" id="f-tanggal_label">Tanggal Kedaluwarsa <small>(cadangan — dipakai jika tanggal tidak terdeteksi dari nama file)</small></span>
                <input type="date" name="tanggal_expired" id="f-tanggal_expired">
            </label>

            <label class="field">
                <span class="field__label">File / Folder Dokumen (.pdf / .doc / .docx / .xls / .xlsx / .jpg / .png)</span>

                <!-- Input asli disembunyikan; dipicu lewat tombol supaya kita bisa
                     memisahkan "Pilih Folder" (webkitdirectory) dari "Pilih File". -->
                <input type="file" id="f-file_dokumen" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" webkitdirectory multiple hidden>
                <input type="file" id="f-file_dokumen-single" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" multiple hidden>

                <div class="file-picker-buttons" style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="button" id="btn-pilih-folder" class="btn btn--sm btn--ghost">&#128193; Pilih Folder</button>
                    <button type="button" id="btn-pilih-file" class="btn btn--sm btn--ghost">&#128196; Pilih File</button>
                </div>

                <small class="field__hint" id="f-file-hint">— wajib untuk dokumen baru. Saat memilih folder, sistem membaca isinya (termasuk sub-folder) lalu menampilkan daftar file di bawah ini — centang hanya file yang ingin benar-benar diunggah. Tanggal kedaluwarsa otomatis dibaca dari nama tiap file (mis. <code>SIUP_2026-08-15.pdf</code>, <code>Sertifikat_15-08-2026.pdf</code>, <code>Sertifikat_15082026.pdf</code>, atau tahun 2 digit seperti <code>SKAT_27-07-27.pdf</code>).</small>

                <div id="f-file-preview" class="file-preview" hidden>
                    <div class="file-preview__toolbar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <p class="file-preview__title" id="f-file-preview-title" style="margin:0;"></p>
                        <div style="display:flex; gap:6px;">
                            <button type="button" id="btn-pilih-semua-file" class="btn btn--sm btn--ghost">Pilih Semua</button>
                            <button type="button" id="btn-batal-semua-file" class="btn btn--sm btn--ghost">Batal Semua</button>
                        </div>
                    </div>
                    <ul class="file-preview__list" id="f-file-preview-list"></ul>
                </div>
            </label>

            <div class="modal-form__actions">
                <button type="button" id="btn-batal" class="btn btn--ghost">Batal</button>
                <button type="submit" class="btn btn--primary">Simpan Dokumen</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="assets/js/main.js?v=1786156095"></script>
<script>
  $(function () { DeltaOcean.initDokumenPage('<?= $user['role'] ?>'); });
</script>
</body>
</html>