<?php
// modules/warehouse/delivery/get_so_items.php
require_once '../../../config/database.php';

if (!isset($_GET['so_id'])) exit(json_encode([]));
$so_id = (int)$_GET['so_id'];
if ($so_id <= 0) {
    exit(json_encode([]));
}

// Ambil item SO dengan qty sisa kirim (qty SO - qty sudah terkirim pada SJ approved/sent)
$sql = "SELECT 
            soi.item_id,
            GREATEST((soi.qty - COALESCE(sent.sent_qty, 0)), 0) AS qty,
            i.item_code, 
            i.item_name, 
            i.unit, 
            i.current_stock
        FROM sales_order_items soi
        JOIN sales_orders so ON so.id = soi.sales_order_id
        JOIN items i ON soi.item_id = i.id
        LEFT JOIN (
            SELECT 
                dn.sales_order_id, 
                dni.item_id, 
                SUM(dni.qty_sent) AS sent_qty
            FROM delivery_notes dn
            JOIN delivery_note_items dni ON dni.delivery_note_id = dn.id
            WHERE dn.status IN ('approved', 'sent')
            GROUP BY dn.sales_order_id, dni.item_id
        ) sent ON sent.sales_order_id = soi.sales_order_id AND sent.item_id = soi.item_id
        WHERE soi.sales_order_id = ?
          AND EXISTS (
              SELECT 1
              FROM spk s
              WHERE s.sales_order_id = so.id
                AND s.status = 'closed'
          )
          AND (soi.qty - COALESCE(sent.sent_qty, 0)) > 0";
$stmt = $pdo->prepare($sql);
$stmt->execute([$so_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
