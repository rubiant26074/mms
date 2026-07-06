<?php
// modules/engineering/items/import_ajax.php
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

global $pdo;
if (!isset($pdo)) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database tidak ditemukan.']);
    exit;
}
if (!isset($_SESSION['user_id']) || !has_permission('eng_items')) {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['rows']) || !is_array($input['rows'])) {
    echo json_encode(['status' => 'error', 'message' => 'Data import tidak valid.']);
    exit;
}
if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($input['csrf'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Permintaan tidak valid (CSRF).']);
    exit;
}

$mode = ($input['mode'] ?? 'skip') === 'update' ? 'update' : 'skip';
$rows = $input['rows'];
$can_see_price = has_permission('item_price_view');

// Helper normalize
$map_item_type = [
    'finish_good' => 'finish_good',
    'finish good' => 'finish_good',
    'fg' => 'finish_good',
    'raw_material' => 'raw_material',
    'raw material' => 'raw_material',
    'rm' => 'raw_material',
    'wip' => 'wip',
    'work in progress' => 'wip',
    'consumable' => 'consumable',
];
$map_ownership = [
    'internal' => 'internal',
    'customer' => 'customer',
    'consignment' => 'customer',
];
$map_qc = [
    'general' => 'general',
    'sheet_metal' => 'sheet_metal',
    'sheet metal' => 'sheet_metal',
    'plate' => 'plate',
    'coating' => 'coating',
    'paint' => 'coating',
    'machining' => 'machining',
    'consumable' => 'consumable',
];

function norm_key($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/\s+/', ' ', $v);
}

function to_number($v) {
    $v = str_replace(['.', ','], ['', '.'], (string)$v);
    $v = preg_replace('/[^0-9.]/', '', $v);
    return $v === '' ? 0 : (float)$v;
}

// Preload customer map
$cust_by_code = [];
$cust_by_name = [];
try {
    $stmt_c = $pdo->query("SELECT id, customer_code, name FROM customers");
    while ($c = $stmt_c->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($c['customer_code'])) $cust_by_code[strtolower($c['customer_code'])] = (int)$c['id'];
        $cust_by_name[strtolower($c['name'])] = (int)$c['id'];
    }
} catch (Exception $e) {}

$inserted = 0;
$updated = 0;
$skipped = 0;
$errors = [];

$stmt_check = $pdo->prepare("SELECT id FROM items WHERE item_code = ? LIMIT 1");
$stmt_insert = $pdo->prepare("INSERT INTO items (customer_id, item_code, item_name, item_type, ownership, qc_type, unit, base_price, min_stock, description, drawing_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt_update = $pdo->prepare("UPDATE items SET customer_id=?, item_name=?, item_type=?, ownership=?, qc_type=?, unit=?, base_price=?, min_stock=?, description=?, drawing_file=? WHERE id=?");

foreach ($rows as $idx => $r) {
    $row_no = $idx + 1;
    $item_code = trim((string)($r['item_code'] ?? ''));
    $item_name = trim((string)($r['item_name'] ?? ''));
    if ($item_code === '' || $item_name === '') {
        $errors[] = "Baris {$row_no}: item_code dan item_name wajib diisi.";
        $skipped++;
        continue;
    }

    $item_type_raw = norm_key($r['item_type'] ?? 'finish_good');
    $item_type = $map_item_type[$item_type_raw] ?? 'finish_good';

    $own_raw = norm_key($r['ownership'] ?? 'internal');
    $ownership = $map_ownership[$own_raw] ?? 'internal';

    $qc_raw = norm_key($r['qc_type'] ?? 'general');
    $qc_type = $map_qc[$qc_raw] ?? 'general';

    $unit = trim((string)($r['unit'] ?? 'Pcs'));
    $min_stock = (int)to_number($r['min_stock'] ?? 0);
    $base_price = $can_see_price ? to_number($r['base_price'] ?? 0) : 0;
    $description = trim((string)($r['description'] ?? ''));
    $drawing_file = trim((string)($r['drawing_file'] ?? ''));

    $cust_code = strtolower(trim((string)($r['customer_code'] ?? '')));
    $cust_name = strtolower(trim((string)($r['customer_name'] ?? '')));
    $customer_id = null;
    if ($cust_code !== '' && isset($cust_by_code[$cust_code])) $customer_id = $cust_by_code[$cust_code];
    if (!$customer_id && $cust_name !== '' && isset($cust_by_name[$cust_name])) $customer_id = $cust_by_name[$cust_name];

    if ($ownership === 'customer' && empty($customer_id)) {
        $errors[] = "Baris {$row_no}: ownership=customer but customer_code/name tidak ditemukan.";
        $skipped++;
        continue;
    }

    $stmt_check->execute([$item_code]);
    $existing_id = $stmt_check->fetchColumn();
    if ($existing_id) {
        if ($mode === 'update') {
            $stmt_update->execute([
                $customer_id,
                $item_name,
                $item_type,
                $ownership,
                $qc_type,
                $unit,
                $base_price,
                $min_stock,
                $description,
                $drawing_file,
                $existing_id
            ]);
            $updated++;
        } else {
            $skipped++;
        }
        continue;
    }

    $stmt_insert->execute([
        $customer_id,
        $item_code,
        $item_name,
        $item_type,
        $ownership,
        $qc_type,
        $unit,
        $base_price,
        $min_stock,
        $description,
        $drawing_file
    ]);
    $inserted++;
}

echo json_encode([
    'status' => 'success',
    'inserted' => $inserted,
    'updated' => $updated,
    'skipped' => $skipped,
    'errors' => $errors
]);
?>
