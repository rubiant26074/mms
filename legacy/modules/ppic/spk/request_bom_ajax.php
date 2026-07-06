<?php
// modules/ppic/spk/request_bom_ajax.php
session_start();
require_once '../../../config/database.php';
require_once '../../../config/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
if (!(($_SESSION['role'] ?? '') === 'admin' || has_permission('ppic_spk_manage') || has_permission('ppic_spk_view'))) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['so_id'])) {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_mms_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Token keamanan tidak valid.']);
        exit;
    }

    $so_id = (int)$_POST['so_id'];

    try {
        // 1. Ambil nomor SO
        $stmt_so = $pdo->prepare("SELECT so_number FROM sales_orders WHERE id = ?");
        $stmt_so->execute([$so_id]);
        $so = $stmt_so->fetch();
        $so_no = $so['so_number'] ?? 'Unknown SO';

        // 2. Cari item di SO ini yang belum punya BOM atau BOM tanpa detail
        $sql_item = "SELECT DISTINCT i.id as item_id, i.item_code, i.item_name,
                            b.id as bom_id,
                            (SELECT COUNT(*) FROM bom_details bd WHERE bd.bom_id = b.id) as bom_detail_count
                     FROM sales_order_items soi 
                     JOIN items i ON soi.item_id = i.id 
                     LEFT JOIN boms b ON i.id = b.item_id 
                     WHERE soi.sales_order_id = ?
                     ORDER BY i.item_name ASC";
        $stmt_item = $pdo->prepare($sql_item);
        $stmt_item->execute([$so_id]);
        $all_items = $stmt_item->fetchAll(PDO::FETCH_ASSOC);

        $missing_items = [];
        foreach ($all_items as $it) {
            $bom_id = isset($it['bom_id']) ? (int)$it['bom_id'] : 0;
            $bom_detail_count = isset($it['bom_detail_count']) ? (int)$it['bom_detail_count'] : 0;
            if ($bom_id <= 0 || $bom_detail_count <= 0) {
                $missing_items[] = $it;
            }
        }

        if (empty($missing_items)) {
            echo json_encode(['status' => 'success', 'notified_count' => 0, 'message' => 'Semua item SO sudah punya BOM.']);
            exit;
        }

        // 2b. Cegah duplikasi jika instruksi untuk semua item sudah ada
        $existing_links = [];
        foreach ($missing_items as $it) {
            $item_id = (int)$it['item_id'];
            $existing_links[] = "index.php?page=eng-bom&action=create&so_id=$so_id&item_id=$item_id";
        }
        $all_already_sent = false;
        if (!empty($existing_links)) {
            $placeholders = implode(',', array_fill(0, count($existing_links), '?'));
            $stmt_exist = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE is_read = 0 AND link IN ($placeholders)");
            $stmt_exist->execute($existing_links);
            $cnt_exist = (int)$stmt_exist->fetchColumn();
            if ($cnt_exist >= count($existing_links)) {
                $all_already_sent = true;
            }
        }

        if ($all_already_sent) {
            echo json_encode(['status' => 'success', 'notified_count' => 0, 'message' => 'Instruksi sudah dikirim sebelumnya.']);
            exit;
        }

        // 3. Kirim notifikasi per item agar Engineering bisa klik langsung ke item yang belum ada BOM
        $notified_count = 0;
        $first_link = '';
        if (function_exists('notify_workflow_event')) {
            foreach ($missing_items as $it) {
                $item_id = (int)$it['item_id'];
                $item_code = (string)($it['item_code'] ?? '');
                $item_name = (string)($it['item_name'] ?? '');
                $item_label = trim($item_code . ' - ' . $item_name, ' -');
                if ($item_label === '') $item_label = 'Item #' . $item_id;

                $title = "Permintaan BOM Baru";
                $message = "PPIC meminta pembuatan BOM untuk $so_no | Item: $item_label";
                $link = "index.php?page=eng-bom&action=create&so_id=$so_id&item_id=$item_id";
                if ($first_link === '') $first_link = $link;

                notify_workflow_event(
                    'ppic.bom.request.' . $so_id . '.' . $item_id,
                    $title,
                    $message,
                    $link,
                    'warning',
                    ['permission_slug' => 'eng_bom']
                );
                $notified_count++;
            }
        }

        echo json_encode(['status' => 'success', 'notified_count' => $notified_count, 'first_link' => $first_link]);
    } catch (PDOException $e) {
        error_log('[PPIC-SPK request_bom_ajax] ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan saat mengirim instruksi BOM.']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Request tidak valid.']);
