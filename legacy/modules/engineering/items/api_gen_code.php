<?php
// modules/engineering/items/api_gen_code.php
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/functions.php';

if (!is_logged_in() || !has_permission('eng_items')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$cust_id = isset($_GET['customer_id']) ? $_GET['customer_id'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : 'internal';
$item_type = isset($_GET['item_type']) ? $_GET['item_type'] : '';

try {
    $prefix = "INT"; // Default Internal

    $cust_code = '';
    if (!empty($cust_id)) {
        $stmt_c = $pdo->prepare("SELECT customer_code FROM customers WHERE id = ?");
        $stmt_c->execute([$cust_id]);
        $cust_code = (string)($stmt_c->fetchColumn() ?: '');
    }

    if ($item_type === 'consumable') {
        // Consumable: CS-XXXX
        $prefix = "CS";
    } elseif ($item_type === 'raw_material') {
        // Raw Material: RM-INT-XXXX atau RM-(CUST_CODE)-XXXX
        if ($type === 'customer') {
            if ($cust_code === '') {
                throw new Exception("Kode customer belum tersedia.");
            }
            $prefix = "RM-" . $cust_code;
        } else {
            $prefix = "RM-INT";
        }
    } else {
        // Default: internal/customer code (CT-001-XXXX)
        if ($type === 'customer') {
            if ($cust_code === '') {
                throw new Exception("Kode customer belum tersedia.");
            }
            $prefix = $cust_code;
        } else {
            $prefix = "INT";
        }
    }

    // Cari nomor urut terakhir berdasarkan prefix ini di tabel items
    // Contoh query: Mencari item_code yang diawali "CT-001-"
    $sql = "SELECT item_code FROM items WHERE item_code LIKE ? ORDER BY id DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$prefix . '-%']);
    $last_code = $stmt->fetchColumn();

    if ($last_code) {
        // Pecah string. Contoh CT-001-0005. Ambil 0005
        $parts = explode('-', $last_code);
        $last_num = end($parts);
        $next_num = intval($last_num) + 1;
    } else {
        $next_num = 1;
    }

    // Format baru: CT-001-0001
    $new_code = $prefix . '-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);

    echo json_encode(['status' => 'success', 'code' => $new_code]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
