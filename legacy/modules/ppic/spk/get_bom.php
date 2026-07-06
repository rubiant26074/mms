<?php
// modules/ppic/spk/get_bom.php
session_start();
require_once '../../../config/database.php';
require_once '../../../config/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
if (!(($_SESSION['role'] ?? '') === 'admin' || has_permission('ppic_spk_manage') || has_permission('ppic_spk_view'))) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

if (!isset($_GET['so_id']) || $_GET['so_id'] === '') {
    echo json_encode(['error' => 'SO ID Required']);
    exit;
}

$so_id = (int)$_GET['so_id'];

try {
    // 1. Ambil seluruh item produksi dari Sales Order
    $sql_items = "SELECT i.id as item_id, i.item_name, soi.qty as production_qty
                  FROM sales_order_items soi
                  JOIN items i ON soi.item_id = i.id
                  WHERE soi.sales_order_id = ?";

    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute([$so_id]);
    $production_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    if (!$production_items) {
        echo json_encode(['error' => 'Data SO tidak memiliki item produksi']);
        exit;
    }

    // Format Key: id_raw_material => [data...]
    $aggregated_materials = [];

    // 2. Loop setiap barang jadi untuk hitung kebutuhan BOM
    foreach ($production_items as $prod) {
        $prod_id = (int)$prod['item_id'];
        $qty_prod = (float)$prod['production_qty'];

        $sql_bom = "SELECT id FROM boms WHERE item_id = ? AND status IN ('active', 'locked') ORDER BY id DESC LIMIT 1";
        $stmt_bom = $pdo->prepare($sql_bom);
        $stmt_bom->execute([$prod_id]);
        $bom = $stmt_bom->fetch(PDO::FETCH_ASSOC);

        if (!$bom) {
            continue;
        }

        $sql_mat = "SELECT i.id, i.item_code, i.item_name, i.unit, i.current_stock,
                           bd.qty as bom_qty_per_unit
                    FROM bom_details bd
                    JOIN items i ON i.id = COALESCE(bd.material_id, bd.item_id)
                    WHERE bd.bom_id = ?";
        $stmt_mat = $pdo->prepare($sql_mat);
        $stmt_mat->execute([$bom['id']]);
        $materials = $stmt_mat->fetchAll(PDO::FETCH_ASSOC);

        foreach ($materials as $mat) {
            $raw_id = (int)$mat['id'];
            $req_qty = (float)$mat['bom_qty_per_unit'] * $qty_prod;

            if (isset($aggregated_materials[$raw_id])) {
                $aggregated_materials[$raw_id]['required'] += $req_qty;
            } else {
                $aggregated_materials[$raw_id] = [
                    'id' => $raw_id,
                    'item_code' => $mat['item_code'],
                    'item_name' => $mat['item_name'],
                    'unit' => $mat['unit'],
                    'current_stock' => $mat['current_stock'],
                    'required' => $req_qty
                ];
            }
        }
    }

    echo json_encode(array_values($aggregated_materials));
} catch (Exception $e) {
    error_log('[PPIC-SPK get_bom] ' . $e->getMessage());
    echo json_encode(['error' => 'Terjadi kesalahan saat memuat BOM.']);
}
?>
