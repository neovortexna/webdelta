<?php
/**
 * ============================================================
 * dokumen_action.php — Backend AJAX untuk CRUD Dokumen
 * PT Delta Ocean Shipping
 * ------------------------------------------------------------
 * Fitur unggulan: saat unggah FOLDER berisi banyak dokumen,
 * tanggal kedaluwarsa setiap file dideteksi OTOMATIS dari nama
 * filenya masing-masing (lihat includes/functions.php ->
 * ekstrakTanggalDariNamaFile()). Field "Tanggal Kedaluwarsa"
 * pada form hanya dipakai sebagai CADANGAN untuk file yang
 * nama filenya tidak mengandung pola tanggal yang dikenali.
 * ============================================================
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$pdo    = getDBConnection();
$user   = currentUser();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ------------------------------------------------------------
    // LIST — tabel dokumen (server-side pagination + pencarian)
    // ------------------------------------------------------------
    case 'list':
        $id_kapal = (int) ($_GET['id_kapal'] ?? 0);
        $search   = trim($_GET['search'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $perPage  = 10;

        if (!$id_kapal || !pastikanBerhak($pdo, $user, $id_kapal)) {
            echo json_encode(['success' => false, 'message' => 'Anda tidak berhak melihat dokumen kapal ini.']);
            exit;
        }

        $where  = 'WHERE d.id_kapal = :id_kapal';
        $params = ['id_kapal' => $id_kapal];

        if ($search !== '') {
            $where .= ' AND d.nama_sertifikat LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM tb_dokumen d $where");
        $stmtTotal->execute($params);
        $total = (int) $stmtTotal->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare("
            SELECT d.id_dokumen, d.nama_sertifikat, d.file_path, d.tanggal_expired, k.nama_kapal
            FROM tb_dokumen d
            JOIN tb_kapal k ON k.id_kapal = d.id_kapal
            $where
            ORDER BY d.tanggal_expired ASC
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $data = array_map(function ($row) {
            [$kode, $label, $cls] = statusDokumen($row['tanggal_expired']);
            return [
                'id_dokumen'      => (int) $row['id_dokumen'],
                'nama_sertifikat' => $row['nama_sertifikat'],
                'nama_kapal'      => $row['nama_kapal'],
                'file_path'       => $row['file_path'],
                'tanggal_expired' => $row['tanggal_expired'],
                'tanggal_display' => formatTanggalID($row['tanggal_expired']),
                'status_kode'     => $kode,
                'status_label'    => $label,
                'status_class'    => $cls,
            ];
        }, $rows);


        $stmtQuota = $pdo->prepare('SELECT COUNT(*) FROM tb_dokumen WHERE id_kapal = ?');
        $stmtQuota->execute([$id_kapal]);
        $quota = (int) $stmtQuota->fetchColumn();

        echo json_encode([
            'success'  => true,
            'data'     => $data,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'quota'    => $quota,
            'quota_max'=> MAX_DOKUMEN_PER_KAPAL,
        ]);
        exit;

           


    // ------------------------------------------------------------
    // SAVE — tambah dokumen baru (file tunggal / folder massal) 
    // atau edit dokumen yang sudah ada
    // ------------------------------------------------------------
    case 'save':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
            exit;
        }

        $id_dokumen            = (int) ($_POST['id_dokumen'] ?? 0);
        $id_kapal              = (int) ($_POST['id_kapal'] ?? 0);
        $nama_sertifikat_input = trim($_POST['nama_sertifikat'] ?? '');
        $tanggal_expired_input = trim($_POST['tanggal_expired'] ?? ''); // dipakai sbg CADANGAN saja

        if (!$id_kapal || !pastikanBerhak($pdo, $user, $id_kapal)) {
            echo json_encode(['success' => false, 'message' => 'Anda tidak berhak mengelola dokumen kapal ini.']);
            exit;
        }

        $isEdit = $id_dokumen > 0;

        // ============================================================
        // --- Logika Tambah Baru (File Tunggal / Folder Massal) ---
        // ============================================================
        if (!$isEdit) {
            $total_uploaded_files = isset($_FILES['file_dokumen']['name']) ? count($_FILES['file_dokumen']['name']) : 0;

            if ($total_uploaded_files === 0 || empty($_FILES['file_dokumen']['name'][0])) {
                echo json_encode(['success' => false, 'message' => 'File atau folder dokumen wajib diunggah.']);
                exit;
            }

            // Validasi kuota dokumen per kapal
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM tb_dokumen WHERE id_kapal = ?');
            $stmt->execute([$id_kapal]);
            $current_docs = (int) $stmt->fetchColumn();

            if (($current_docs + $total_uploaded_files) > MAX_DOKUMEN_PER_KAPAL) {
                echo json_encode(['success' => false, 'message' => "Gagal: Penambahan $total_uploaded_files file akan melebihi batas maksimal " . MAX_DOKUMEN_PER_KAPAL . " dokumen per kapal."]);
                exit;
            }

            // Ambil nama kapal untuk struktur folder fisik
            $stmtKapalFolder = $pdo->prepare('SELECT nama_kapal FROM tb_kapal WHERE id_kapal = ?');
            $stmtKapalFolder->execute([$id_kapal]);
            $namaKapalDB = $stmtKapalFolder->fetchColumn();

            $safeFolderName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $namaKapalDB);
            $shipUploadDir  = __DIR__ . '/uploads/' . $safeFolderName . '/';
            $shipUploadUrl  = 'uploads/' . $safeFolderName . '/';

            if (!is_dir($shipUploadDir)) {
                mkdir($shipUploadDir, 0755, true);
            }

            $uploaded_count = 0;
            $gagal_ekstensi = [];   // file ditolak karena ekstensi/ukuran tidak valid
            $gagal_tanggal  = [];   // file tanpa tanggal terdeteksi & tanpa tanggal cadangan
            $terdeteksi_otomatis = []; // file, tanggal -> untuk laporan ke user

            $pdo->beginTransaction();

            try {
                $insertStmt = $pdo->prepare('INSERT INTO tb_dokumen (id_kapal, nama_sertifikat, file_path, tanggal_expired) VALUES (?, ?, ?, ?)');

                for ($i = 0; $i < $total_uploaded_files; $i++) {
                    $file_name  = $_FILES['file_dokumen']['name'][$i];
                    $file_tmp   = $_FILES['file_dokumen']['tmp_name'][$i];
                    $file_size  = $_FILES['file_dokumen']['size'][$i];
                    $file_error = $_FILES['file_dokumen']['error'][$i];

                    if ($file_error !== UPLOAD_ERR_OK || $file_size > MAX_FILE_SIZE) {
                        $gagal_ekstensi[] = $file_name;
                        continue;
                    }

                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    if (!in_array($ext, ALLOWED_EXT, true)) {
                        $gagal_ekstensi[] = $file_name;
                        continue;
                    }

                    // --- AUTO-DETEKSI TANGGAL EXPIRED DARI NAMA FILE ---
                    $namaFileAsli   = pathinfo($file_name, PATHINFO_FILENAME);
                    $tanggalTerdeteksi = ekstrakTanggalDariNamaFile($file_name);

                    if ($tanggalTerdeteksi !== null) {
                        $tanggal_final = $tanggalTerdeteksi;
                        $terdeteksi_otomatis[] = ['file' => $file_name, 'tanggal' => $tanggal_final];
                    } elseif ($tanggal_expired_input !== '') {
                        // Fallback ke tanggal cadangan dari form jika nama file tidak mengandung pola tanggal
                        $tanggal_final = $tanggal_expired_input;
                    } else {
                        // Tidak ada tanggal yang bisa dipakai sama sekali -> lewati file ini
                        $gagal_tanggal[] = $file_name;
                        continue;
                    }

                    $newName  = 'doc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $destPath = $shipUploadDir . $newName;

                    if (move_uploaded_file($file_tmp, $destPath)) {
                        $file_path = $shipUploadUrl . $newName;

                        if ($nama_sertifikat_input !== '' && $total_uploaded_files === 1) {
                            $final_nama_sertifikat = $nama_sertifikat_input;
                        } else {
                            // Bersihkan potongan tanggal dari nama file agar nama dokumen lebih rapi
                            $final_nama_sertifikat = $tanggalTerdeteksi !== null
                                ? bersihkanNamaSertifikat($namaFileAsli)
                                : $namaFileAsli;
                        }

                        $insertStmt->execute([$id_kapal, $final_nama_sertifikat, $file_path, $tanggal_final]);
                        $uploaded_count++;
                    }
                }

                if ($uploaded_count === 0) {
                    $pdo->rollBack();
                    $pesan = 'Tidak ada file valid yang berhasil diunggah.';
                    if ($gagal_tanggal) {
                        $pesan = 'Tanggal kedaluwarsa tidak terdeteksi pada nama file berikut, dan tidak ada tanggal cadangan yang diisi: '
                            . implode(', ', array_slice($gagal_tanggal, 0, 5))
                            . (count($gagal_tanggal) > 5 ? ' (+' . (count($gagal_tanggal) - 5) . ' lainnya)' : '')
                            . '. Isi "Tanggal Kedaluwarsa (Cadangan)" atau sertakan tanggal pada nama file.';
                    }
                    echo json_encode(['success' => false, 'message' => $pesan]);
                    exit;
                }

                $pdo->commit();

                $pesan = "$uploaded_count dokumen berhasil ditambahkan.";
                $jumlahOtomatis = count($terdeteksi_otomatis);
                if ($jumlahOtomatis > 0) {
                    $pesan .= " ($jumlahOtomatis tanggal terdeteksi otomatis dari nama file.)";
                }
                if ($gagal_tanggal) {
                    $pesan .= ' ' . count($gagal_tanggal) . ' file dilewati karena tanggal tidak ditemukan pada nama file.';
                }
                if ($gagal_ekstensi) {
                    $pesan .= ' ' . count($gagal_ekstensi) . ' file dilewati karena format/ukuran tidak valid.';
                }

                echo json_encode([
                    'success'              => true,
                    'message'              => $pesan,
                    'uploaded_count'       => $uploaded_count,
                    'terdeteksi_otomatis'  => $terdeteksi_otomatis,
                    'gagal_tanggal'        => $gagal_tanggal,
                    'gagal_ekstensi'       => $gagal_ekstensi,
                ]);

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan basis data saat memproses dokumen.']);
            }
            exit;
        }

        // ============================================================
        // --- Logika Edit (Single File) ---
        // ============================================================
        else {
            $stmt = $pdo->prepare('SELECT * FROM tb_dokumen WHERE id_dokumen = ?');
            $stmt->execute([$id_dokumen]);
            $existing = $stmt->fetch();

            if (!$existing || !pastikanBerhak($pdo, $user, (int) $existing['id_kapal'])) {
                echo json_encode(['success' => false, 'message' => 'Dokumen tidak ditemukan atau Anda tidak memiliki hak akses.']);
                exit;
            }

            if ($nama_sertifikat_input === '') {
                echo json_encode(['success' => false, 'message' => 'Nama dokumen wajib diisi.']);
                exit;
            }
            if ($tanggal_expired_input === '') {
                echo json_encode(['success' => false, 'message' => 'Tanggal kedaluwarsa wajib diisi.']);
                exit;
            }

            $file_path = $existing['file_path'];

            // Jika user mengganti file pada mode edit
            if (!empty($_FILES['file_dokumen']['name'][0])) {
                $file_name  = $_FILES['file_dokumen']['name'][0];
                $file_tmp   = $_FILES['file_dokumen']['tmp_name'][0];
                $file_size  = $_FILES['file_dokumen']['size'][0];
                $file_error = $_FILES['file_dokumen']['error'][0];

                if ($file_error === UPLOAD_ERR_OK && $file_size <= MAX_FILE_SIZE) {
                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    if (!in_array($ext, ALLOWED_EXT, true)) {
                        echo json_encode(['success' => false, 'message' => 'Ekstensi file tidak diizinkan.']);
                        exit;
                    }

                    $stmtKapalFolder = $pdo->prepare('SELECT nama_kapal FROM tb_kapal WHERE id_kapal = ?');
                    $stmtKapalFolder->execute([$id_kapal]);
                    $namaKapalDB = $stmtKapalFolder->fetchColumn();
                    $safeFolderName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $namaKapalDB);
                    $shipUploadDir  = __DIR__ . '/uploads/' . $safeFolderName . '/';
                    $shipUploadUrl  = 'uploads/' . $safeFolderName . '/';
                    if (!is_dir($shipUploadDir)) {
                        mkdir($shipUploadDir, 0755, true);
                    }

                    $newName  = 'doc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $destPath = $shipUploadDir . $newName;

                    if (move_uploaded_file($file_tmp, $destPath)) {
                        // Hapus file lama
                        $oldParsed = parse_url($existing['file_path'], PHP_URL_PATH);
                        $oldPath   = __DIR__ . '/' . ltrim($oldParsed, '/');
                        if (is_file($oldPath)) {
                            @unlink($oldPath);
                        }
                        $file_path = $shipUploadUrl . $newName;
                    }
                }
            }

            $updateStmt = $pdo->prepare('UPDATE tb_dokumen SET nama_sertifikat = ?, file_path = ?, tanggal_expired = ? WHERE id_dokumen = ?');
            if ($updateStmt->execute([$nama_sertifikat_input, $file_path, $tanggal_expired_input, $id_dokumen])) {
                echo json_encode(['success' => true, 'message' => 'Dokumen berhasil diperbarui.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal memperbarui dokumen.']);
            }
            exit;
        }

    // ------------------------------------------------------------
    // DELETE
    // ------------------------------------------------------------
    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
            exit;
        }

        $id_dokumen = (int) ($_POST['id_dokumen'] ?? 0);
        $stmt = $pdo->prepare('SELECT id_kapal, file_path FROM tb_dokumen WHERE id_dokumen = ?');
        $stmt->execute([$id_dokumen]);
        $doc = $stmt->fetch();

        if (!$doc || !pastikanBerhak($pdo, $user, (int) $doc['id_kapal'])) {
            echo json_encode(['success' => false, 'message' => 'Dokumen tidak ditemukan atau Anda tidak memiliki hak akses.']);
            exit;
        }

        $del = $pdo->prepare('DELETE FROM tb_dokumen WHERE id_dokumen = ?');
        if ($del->execute([$id_dokumen])) {
            $parsed_url = parse_url($doc['file_path'], PHP_URL_PATH);
            $filePath   = __DIR__ . '/' . ltrim($parsed_url, '/');
            if (is_file($filePath)) {
                @unlink($filePath);
            }
            echo json_encode(['success' => true, 'message' => 'Dokumen berhasil dihapus.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus dokumen dari database.']);
        }
        exit;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
        exit;
}
