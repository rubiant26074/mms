<?php
// modules/users/delete.php

$id = isset($_GET['id']) ? $_GET['id'] : null;

if ($id) {
    // 1. Cek apakah user yang dihapus adalah Admin Utama atau diri sendiri
    if ($id == $_SESSION['user_id']) {
        echo "<script>alert('Anda tidak bisa menghapus akun sendiri!'); window.location='index.php?page=users';</script>";
        exit;
    }

    // 2. Cek apakah user admin 'hardcoded'
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $u = $stmt->fetch();

    if ($u && $u['username'] === 'admin') {
        echo "<script>alert('Super Admin tidak boleh dihapus!'); window.location='index.php?page=users';</script>";
        exit;
    }

    // 3. Proses Hapus
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt->execute([$id])) {
        echo "<script>alert('User berhasil dihapus.'); window.location='index.php?page=users';</script>";
    } else {
        echo "<script>alert('Gagal menghapus user.'); window.location='index.php?page=users';</script>";
    }
} else {
    mms_redirect('index.php?page=users');
}
?>