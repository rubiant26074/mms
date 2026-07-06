<?php
// modules/sales/customers/import_ajax.php
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

global $pdo;
if (!isset($pdo)) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database tidak ditemukan.']);
    exit;
}
if (!isset($_SESSION['user_id']) || !has_permission('sales_customer_manage')) {
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

function norm_key($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/\s+/', '_', $v);
}

$inserted = 0;
$updated = 0;
$skipped = 0;
$errors = [];

$stmt_check_code = $pdo->prepare("SELECT id FROM customers WHERE customer_code = ? LIMIT 1");
$stmt_check_name = $pdo->prepare("SELECT id FROM customers WHERE name = ? LIMIT 1");
$stmt_insert = $pdo->prepare("INSERT INTO customers (customer_code, name, address, phone, pic, email, tax_id, tax_invoice_number, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt_update = $pdo->prepare("UPDATE customers SET name=?, address=?, phone=?, pic=?, email=?, tax_id=?, tax_invoice_number=? WHERE id=?");

foreach ($rows as $idx => $r) {
    $row_no = $idx + 1;
    $customer_code = trim((string)($r['customer_code'] ?? ''));
    $name = trim((string)($r['name'] ?? $r['customer_name'] ?? ''));

    if ($name === '') {
        $errors[] = "Baris {$row_no}: name wajib diisi.";
        $skipped++;
        continue;
    }

    $address = trim((string)($r['address'] ?? ''));
    $phone = trim((string)($r['phone'] ?? ''));
    $pic = trim((string)($r['pic'] ?? ''));
    $email = trim((string)($r['email'] ?? ''));
    $tax_id = trim((string)($r['tax_id'] ?? ''));
    $tax_invoice_number = trim((string)($r['tax_invoice_number'] ?? $r['nsfp'] ?? ''));
    $tax_invoice_number = preg_replace('/\s+/', '', $tax_invoice_number);

    if ($tax_invoice_number !== '' && !preg_match('/^\d{3}\.\d{3}-\d{2}\.\d{8}$/', $tax_invoice_number)) {
        $errors[] = "Baris {$row_no}: format NSFP tidak valid (000.000-YY.12345678).";
        $skipped++;
        continue;
    }

    // Auto generate code if empty
    if ($customer_code === '') {
        $stmt_last = $pdo->query("SELECT customer_code FROM customers WHERE customer_code LIKE 'CT-%' ORDER BY id DESC LIMIT 1");
        $last_code = $stmt_last->fetchColumn();
        if ($last_code) {
            $last_no = intval(substr($last_code, 3));
            $new_no = $last_no + 1;
        } else {
            $new_no = 1;
        }
        $customer_code = 'CT-' . str_pad($new_no, 3, '0', STR_PAD_LEFT);
    }

    // Check existing by code or name
    $existing_id = null;
    $stmt_check_code->execute([$customer_code]);
    $existing_id = $stmt_check_code->fetchColumn();
    if (!$existing_id) {
        $stmt_check_name->execute([$name]);
        $existing_id = $stmt_check_name->fetchColumn();
    }

    if ($existing_id) {
        if ($mode === 'update') {
            $stmt_update->execute([$name, $address, $phone, $pic, $email, $tax_id, $tax_invoice_number !== '' ? $tax_invoice_number : null, $existing_id]);
            $updated++;
        } else {
            $skipped++;
        }
        continue;
    }

    $stmt_insert->execute([$customer_code, $name, $address, $phone, $pic, $email, $tax_id, $tax_invoice_number !== '' ? $tax_invoice_number : null, $_SESSION['user_id']]);
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
