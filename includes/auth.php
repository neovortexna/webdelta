<?php
/**
 * ============================================================
 * Auth Helper
 * Mengatur sesi login, proteksi halaman, dan pengecekan role
 * ============================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Cek apakah user sudah login */
function isLoggedIn(): bool
{
    return isset($_SESSION['id_user']);
}

/** Wajibkan login, jika tidak redirect ke halaman login */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/** Wajibkan role tertentu, kalau tidak sesuai -> ditolak */
function requireRole(string $role): void
{
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header('Location: dashboard.php');
        exit;
    }
}

function currentUser(): array
{
    return [
        'id_user'      => $_SESSION['id_user']      ?? null,
        'username'     => $_SESSION['username']     ?? null,
        'nama_lengkap' => $_SESSION['nama_lengkap']  ?? null,
        'role'         => $_SESSION['role']          ?? null,
    ];
}

/**
 * Validasi bahwa id_user pada sesi login MASIH benar-benar ada di database.
 * Ini mencegah error "foreign key constraint fails" saat database sempat
 * di-reset/dibuat ulang (mis. re-import SQL) tapi sesi browser lama masih aktif.
 * Jika tidak valid, sesi otomatis dihapus dan user diarahkan ke login.php.
 */
function requireValidSession(PDO $pdo): void
{
    $id_user = $_SESSION['id_user'] ?? null;
    if (!$id_user) {
        return; // requireLogin() yang menangani kasus belum login
    }

    $stmt = $pdo->prepare('SELECT id_user FROM tb_user WHERE id_user = ?');
    $stmt->execute([$id_user]);

    if (!$stmt->fetch()) {
        session_unset();
        session_destroy();
        header('Location: login.php?expired=1');
        exit;
    }
}

/** Cek apakah staf kapal berhak mengakses id_kapal tertentu */
function stafBerhakAtasKapal(PDO $pdo, int $id_kapal, int $id_user): bool
{
    $stmt = $pdo->prepare('SELECT id_kapal FROM tb_kapal WHERE id_kapal = ? AND id_user = ?');
    $stmt->execute([$id_kapal, $id_user]);
    return (bool) $stmt->fetch();
}

/**
 * Cek apakah user (Admin Induk atau Staf Kapal) berhak mengelola
 * dokumen milik kapal tertentu.
 *
 * CATATAN: Sesuai permintaan, SEMUA user yang sudah login (Admin
 * Induk maupun Staf Kapal) kini punya hak akses PENUH (lihat,
 * tambah, edit, hapus) atas dokumen SEMUA kapal — tidak lagi
 * dibatasi hanya pada kapal miliknya sendiri. Satu-satunya syarat
 * adalah kapal tersebut benar-benar ada di database.
 */
function pastikanBerhak(PDO $pdo, array $user, int $id_kapal): bool
{
    if (empty($id_kapal)) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT id_kapal FROM tb_kapal WHERE id_kapal = ?');
    $stmt->execute([$id_kapal]);
    return (bool) $stmt->fetch();
}
