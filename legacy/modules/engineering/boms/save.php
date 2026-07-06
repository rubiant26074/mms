<?php
// modules/engineering/boms/save.php
session_start();
require_once __DIR__ . '/../../../config/database.php'; // Sesuaikan path ke koneksi PDO
require_once __DIR__ . '/../../../config/functions.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!is_logged_in() || !has_permission('eng_bom')) {
        http_response_code(403);
        die('Akses ditolak.');
    }
    $csrf = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
        http_response_code(400);
        die('Permintaan tidak valid (CSRF).');
    }

    $action = $_POST['action'] ?? 'create';
    $id = $_POST['id'] ?? null;
    $item_id = $_POST['item_id'];
    $qty_result = $_POST['qty_result'];
    $status = $_POST['status'];
    
    // Data Detail Komposisi
    $material_ids = $_POST['material_id'] ?? [];
    $qty_needed = $_POST['qty_needed'] ?? [];

    try {
        $pdo->beginTransaction();

        if ($action === 'create') {
            // Generate BOM Code Otomatis
            $ym = date('ym');
            $stmt_code = $pdo->query("SELECT COUNT(*) FROM boms WHERE bom_code LIKE 'BOM-$ym-%'");
            $count = $stmt_code->fetchColumn() + 1;
            $bom_code = "BOM-" . $ym . "-" . str_pad($count, 4, '0', STR_PAD_LEFT);

            // Insert Header
            $sql_header = "INSERT INTO boms (bom_code, item_id, qty_result, status, created_by, created_at) 
                           VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql_header);
            $stmt->execute([$bom_code, $item_id, $qty_result, $status, $_SESSION['user_id']]);
            $bom_id = $pdo->lastInsertId();
        } else {
            // Update Header
            $sql_header = "UPDATE boms SET item_id = ?, qty_result = ?, status = ?, updated_at = NOW() WHERE id = ?";
            $pdo->prepare($sql_header)->execute([$item_id, $qty_result, $status, $id]);
            $bom_id = $id;

            // Hapus detail lama untuk diganti dengan yang baru (Sync)
            $pdo->prepare("DELETE FROM bom_details WHERE bom_id = ?")->execute([$bom_id]);
        }

        // Insert Detail Komposisi
        $sql_detail = "INSERT INTO bom_details (bom_id, material_id, qty) VALUES (?, ?, ?)";
        $stmt_det = $pdo->prepare($sql_detail);

        for ($i = 0; $i < count($material_ids); $i++) {
            if (!empty($material_ids[$i]) && $qty_needed[$i] > 0) {
                $stmt_det->execute([$bom_id, $material_ids[$i], $qty_needed[$i]]);
            }
        }

        $pdo->commit();
        echo "<script>alert('BOM Berhasil Disimpan!'); window.location='../../../index.php?page=eng-bom';</script>";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "<script>alert('Gagal menyimpan BOM. Silakan cek input.'); window.location='../../../index.php?page=eng-bom';</script>";
    }
}
