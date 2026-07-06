<?php
// modules/ppic/spk/delete.php

// 1. Proteksi Akses: Hanya Administrator yang boleh menghapus
if ($_SESSION['role'] !== 'admin') {
    echo "<script>alert('Akses Ditolak! Hanya Administrator yang boleh menghapus data.'); window.location='index.php?page=ppic-spk';</script>";
    exit;
}

$csrf = $_GET['csrf'] ?? $_POST['csrf'] ?? '';
if (!verify_mms_csrf_token($csrf)) {
    echo "<script>alert('Token keamanan tidak valid.'); window.location='index.php?page=ppic-spk';</script>";
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    try {
        // 2. Cek status SPK
        $check = $pdo->prepare("SELECT status FROM spk WHERE id = ?");
        $check->execute([$id]);
        $status = $check->fetchColumn();

        if (!$status) {
             echo "<script>alert('Data tidak ditemukan.'); window.location='index.php?page=ppic-spk';</script>";
             exit;
        }

        // 3. Jalankan Database Transaction untuk keamanan data
        $pdo->beginTransaction();

        // 4. Hapus rincian material terlebih dahulu agar tidak error Foreign Key
        $stmt_mat = $pdo->prepare("DELETE FROM spk_materials WHERE spk_id = ?");
        $stmt_mat->execute([$id]);

        // 5. Hapus data utama SPK
        $stmt = $pdo->prepare("DELETE FROM spk WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
        echo "<script>alert('SPK berhasil dihapus.'); window.location='index.php?page=ppic-spk';</script>";

    } catch (PDOException $e) {
        // Rollback jika terjadi kesalahan
        try {
            if ($pdo->inTransaction()) $pdo->rollBack();
        } catch (Throwable $rollbackError) {}
        error_log('[PPIC-SPK delete] ' . $e->getMessage());
        echo "<script>alert('Gagal menghapus data SPK.'); window.location='index.php?page=ppic-spk';</script>";
    }
} else {
    header('Location: index.php?page=ppic-spk');
    exit;
}
?>
