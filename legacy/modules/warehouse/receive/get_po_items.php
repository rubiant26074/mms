<?php
// modules/warehouse/receive/get_po_items.php
require_once '../../../config/database.php';

// Pastikan parameter po_id ada
if (!isset($_GET['po_id'])) exit(json_encode([]));

$po_id = $_GET['po_id'];

try {
    // Query Cerdas:
    // 1. Ambil Item di tabel detail PO
    // 2. Sub-query: Hitung total qty yang SUDAH diterima (di tabel goods_receipt_items) 
    //    dengan syarat status penerimaan BUKAN 'rejected'
    $sql = "SELECT poi.item_id, poi.qty, i.item_code, i.item_name, i.unit,
                   (
                       SELECT COALESCE(SUM(gri.qty_received), 0)
                       FROM goods_receipt_items gri
                       JOIN goods_receipts gr ON gri.goods_receipt_id = gr.id
                       WHERE gr.purchase_order_id = poi.purchase_order_id 
                       AND gri.item_id = poi.item_id
                       AND gr.status != 'rejected'
                   ) as total_received
            FROM purchase_order_items poi
            JOIN items i ON poi.item_id = i.id
            WHERE poi.purchase_order_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$po_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return format JSON untuk dibaca JavaScript di form.php
    echo json_encode($items);

} catch (Exception $e) {
    // Return empty array jika error agar tidak merusak tampilan
    echo json_encode([]);
}
?>