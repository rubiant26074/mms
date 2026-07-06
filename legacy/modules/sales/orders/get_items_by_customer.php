<?php
// modules/sales/orders/get_items_by_customer.php
require_once '../../../config/database.php';
require_once '../../../config/functions.php';

header('Content-Type: application/json');

$customer_id = (int)($_GET['customer_id'] ?? 0);

if (!is_logged_in() || !has_permission('sales_so_manage')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($customer_id) {
    try {
        // 1. Ambil kode customer (e.g., CT-001)
        $stmt_cust = $pdo->prepare("SELECT customer_code FROM customers WHERE id = ?");
        $stmt_cust->execute([$customer_id]);
        $cust_code = $stmt_cust->fetchColumn();

        if ($cust_code) {
            // 2. Ambil item yang kodenya diawali dengan kode customer tersebut
            $stmt_items = $pdo->prepare("SELECT id, item_code, item_name, unit, COALESCE(description, '') AS material, COALESCE(base_price, 0) AS hpp
                                         FROM items
                                         WHERE item_code LIKE ?
                                         ORDER BY item_name ASC");
            $stmt_items->execute([$cust_code . '%']);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($items);
        } else {
            echo json_encode([]);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Gagal mengambil data item.']);
    }
} else {
    echo json_encode([]);
}
