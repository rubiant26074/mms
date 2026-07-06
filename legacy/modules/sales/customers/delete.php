<?php
// modules/sales/customers/delete.php

// Ambil ID dari URL
if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('sales_customer_manage')) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=sales-customers';</script>";
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$csrf = $_GET['csrf'] ?? $_POST['csrf'] ?? '';
if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
    echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=sales-customers';</script>";
    exit;
}

if ($id > 0) {
    try {
        // 1. Cek apakah customer ada
        $check = $pdo->prepare("SELECT name FROM customers WHERE id = ?");
        $check->execute([$id]);
        $data = $check->fetch();

        if (!$data) {
            echo "<script>alert('Data customer tidak ditemukan!'); window.location='index.php?page=sales-customers';</script>";
            exit;
        }

        // 2. Cek Relasi (Opsional: Jika nanti sudah ada tabel transaksi)
        /* $relasi = $pdo->prepare("SELECT COUNT(*) FROM sales_orders WHERE customer_id = ?");
        $relasi->execute([$id]);
        if ($relasi->fetchColumn() > 0) {
             throw new Exception("Customer tidak bisa dihapus karena memiliki riwayat transaksi Sales Order.");
        }
        */

        // 3. Proses Hapus
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        
        echo "<script>alert('Customer ". clean($data['name']) ." berhasil dihapus.'); window.location='index.php?page=sales-customers';</script>";

    } catch (PDOException $e) {
        // Menangkap error constraint foreign key (jika database sudah berelasi ketat)
        echo "<script>alert('Gagal menghapus. Data ini sedang digunakan oleh modul lain.'); window.location='index.php?page=sales-customers';</script>";
    } catch (Exception $e) {
        // Menangkap error logika manual
        echo "<script>alert('Terjadi kesalahan saat menghapus customer.'); window.location='index.php?page=sales-customers';</script>";
    }
} else {
    // Jika tidak ada ID, kembalikan ke list
    mms_redirect('index.php?page=sales-customers');
}
?>
