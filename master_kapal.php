<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin(); // Mengizinkan semua user yang sudah login (Admin Induk & Staf Kapal)

$pdo  = getDBConnection();
requireValidSession($pdo); // cegah error FK jika sesi lama tidak lagi cocok dengan database
$user = currentUser();

// Proses form tambah atau hapus kapal
$flashError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $nama          = trim($_POST['nama_kapal'] ?? '');
            $namaPemilik   = trim($_POST['nama_pemilik_kapal'] ?? '');

            // Kolom "Staf Penanggung Jawab" (pilih akun staf) sudah dihapus dari
            // form — sekarang penanggung jawab kapal cukup diisi manual lewat
            // nama pemiliknya. id_kapal tetap butuh id_user (untuk kompatibilitas
            // skema & histori), jadi otomatis diisi akun yang sedang login.
            $id_user = (int) $user['id_user'];

            if ($nama === '') {
                $flashError = 'Nama kapal wajib diisi.';
            } elseif ($namaPemilik === '') {
                $flashError = 'Nama pemilik kapal wajib diisi.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO tb_kapal (nama_kapal, id_user, nama_pemilik_kapal) VALUES (?, ?, ?)');
                $stmt->execute([$nama, $id_user, $namaPemilik]);
            }
        } elseif ($action === 'edit') {
            $id_kapal      = (int) ($_POST['id_kapal'] ?? 0);
            $nama          = trim($_POST['nama_kapal'] ?? '');
            $namaPemilik   = trim($_POST['nama_pemilik_kapal'] ?? '');

            if (!pastikanBerhak($pdo, $user, $id_kapal)) {
                $flashError = 'Anda tidak berhak mengubah data kapal ini.';
            } elseif ($nama === '') {
                $flashError = 'Nama kapal wajib diisi.';
            } elseif ($namaPemilik === '') {
                $flashError = 'Nama pemilik kapal wajib diisi.';
            } else {
                $stmt = $pdo->prepare('UPDATE tb_kapal SET nama_kapal = ?, nama_pemilik_kapal = ? WHERE id_kapal = ?');
                $stmt->execute([$nama, $namaPemilik, $id_kapal]);
            }
        } elseif ($action === 'delete') {
            $id_kapal = (int) ($_POST['id_kapal'] ?? 0);

            // Validasi hak akses hapus: Staf hanya boleh menghapus kapalnya sendiri, Admin bebas
            if ($user['role'] === 'admin_induk') {
                $pdo->prepare('DELETE FROM tb_kapal WHERE id_kapal = ?')->execute([$id_kapal]);
            } else {
                $stmt = $pdo->prepare('DELETE FROM tb_kapal WHERE id_kapal = ? AND id_user = ?');
                $stmt->execute([$id_kapal, $user['id_user']]);
            }
        }
    } catch (PDOException $e) {
        // Terjemahkan error database umum jadi pesan yang mudah dipahami,
        // bukan menampilkan Fatal Error mentah ke layar.
        if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'fk_kapal_user')) {
            $flashError = 'Gagal: akun Anda tidak ditemukan di database. Coba logout &amp; login ulang.';
        } elseif ($e->getCode() === '23000') {
            $flashError = 'Gagal: data ini masih terhubung dengan data lain (mis. masih ada dokumen di kapal ini) sehingga tidak bisa dihapus/diubah.';
        } else {
            $flashError = 'Terjadi kesalahan database. Silakan coba lagi.';
        }
    }

    if ($flashError === null) {
        header('Location: master_kapal.php');
        exit;
    }
    // Jika ada error, lanjut render halaman di bawah supaya pesannya bisa ditampilkan
}

// Ambil data kapal. Sesuai permintaan, semua user (Admin Induk & Staf Kapal)
// melihat SELURUH armada, jadi tidak perlu lagi difilter berdasarkan id_user.
$kapalList = $pdo->query('
    SELECT k.id_kapal, k.nama_kapal, k.nama_pemilik_kapal,
           (SELECT COUNT(*) FROM tb_dokumen d WHERE d.id_kapal = k.id_kapal) AS jumlah_dokumen
    FROM tb_kapal k
    ORDER BY k.nama_kapal
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Master Kapal &mdash; Sistem Dokumen Kapal</title>
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
            <p class="eyebrow">Master Data</p>
            <h1>Armada Kapal</h1>
        </div>
        <button id="btn-tambah-kapal" class="btn btn--primary">+ Tambah Kapal</button>
    </div>

    <?php if ($flashError): ?>
    <div class="alert-inline alert-inline--danger" style="margin-bottom:18px;"><?= $flashError ?></div>
    <?php endif; ?>

    <section class="panel">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nama Kapal</th>
                        <th>Pemilik Kapal</th>
                        <th>Jumlah Dokumen</th>
                        <th class="col-actions">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$kapalList): ?>
                    <tr><td colspan="4" class="empty-cell">Belum ada data kapal.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($kapalList as $k): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($k['nama_kapal']) ?></strong></td>
                        <td><?= $k['nama_pemilik_kapal'] !== '' ? htmlspecialchars($k['nama_pemilik_kapal']) : '<span class="empty-cell" style="padding:0;">— belum diisi</span>' ?></td>
                        <td><?= (int) $k['jumlah_dokumen'] ?> / <?= MAX_DOKUMEN_PER_KAPAL ?></td>
                        <td class="col-actions">
                            <div class="row-actions">
                                <button type="button" class="btn btn--sm btn--ghost"
                                    data-action="edit-kapal"
                                    data-id="<?= (int) $k['id_kapal'] ?>"
                                    data-nama="<?= htmlspecialchars($k['nama_kapal'], ENT_QUOTES) ?>"
                                    data-pemilik="<?= htmlspecialchars($k['nama_pemilik_kapal'], ENT_QUOTES) ?>">Edit</button>
                                <form method="POST" onsubmit="return confirm('Hapus kapal ini beserta seluruh dokumennya?');" style="display:inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id_kapal" value="<?= (int) $k['id_kapal'] ?>">
                                    <button type="submit" class="btn btn--sm btn--danger-outline">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</div>

<!-- Modal Tambah Kapal -->
<div id="modal-kapal" class="modal-overlay" hidden>
    <div class="modal-content">
        <div class="modal-content__head">
            <h3>Tambah Kapal Baru</h3>
            <button type="button" id="modal-kapal-close" class="modal-close">&times;</button>
        </div>
        <form method="POST" class="modal-form">
            <input type="hidden" name="action" value="add">
            <label class="field">
                <span class="field__label">Nama Kapal</span>
                <input type="text" name="nama_kapal" placeholder="mis. KM. Delta 03" required>
            </label>

            <label class="field">
                <span class="field__label">Nama Pemilik Kapal</span>
                <input type="text" name="nama_pemilik_kapal" placeholder="mis. Budi Santoso" required>
            </label>

            <div class="modal-form__actions">
                <button type="button" id="btn-batal-kapal" class="btn btn--ghost">Batal</button>
                <button type="submit" class="btn btn--primary">Simpan Kapal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Kapal (ubah nama kapal) -->
<div id="modal-edit-kapal" class="modal-overlay" hidden>
    <div class="modal-content">
        <div class="modal-content__head">
            <h3>Edit Data Kapal</h3>
            <button type="button" id="modal-edit-kapal-close" class="modal-close">&times;</button>
        </div>
        <form method="POST" class="modal-form">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id_kapal" id="ek-id_kapal">
            <label class="field">
                <span class="field__label">Nama Kapal</span>
                <input type="text" name="nama_kapal" id="ek-nama_kapal" required>
            </label>
            <label class="field">
                <span class="field__label">Nama Pemilik Kapal</span>
                <input type="text" name="nama_pemilik_kapal" id="ek-nama_pemilik_kapal" required>
            </label>
            <div class="modal-form__actions">
                <button type="button" id="btn-batal-edit-kapal" class="btn btn--ghost">Batal</button>
                <button type="submit" class="btn btn--primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="assets/js/main.js?v=1786156095"></script>
<script>
  $('#btn-tambah-kapal').on('click', () => $('#modal-kapal').removeAttr('hidden'));
  $('#modal-kapal-close, #btn-batal-kapal').on('click', () => $('#modal-kapal').attr('hidden', true));

  $('[data-action="edit-kapal"]').on('click', function () {
    $('#ek-id_kapal').val($(this).data('id'));
    $('#ek-nama_kapal').val($(this).data('nama'));
    $('#ek-nama_pemilik_kapal').val($(this).data('pemilik'));
    $('#modal-edit-kapal').removeAttr('hidden');
  });
  $('#modal-edit-kapal-close, #btn-batal-edit-kapal').on('click', () => $('#modal-edit-kapal').attr('hidden', true));
</script>
</body>
</html>