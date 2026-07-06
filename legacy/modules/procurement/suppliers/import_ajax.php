<?php
// modules/procurement/suppliers/import_ajax.php
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

global $pdo;
if (!isset($pdo)) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database tidak ditemukan.']);
    exit;
}
if (!isset($_SESSION['user_id']) || !has_permission('purch_vendor_manage')) {
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

$inserted = 0;
$updated = 0;
$skipped = 0;
$errors = [];

$stmt_check_code = $pdo->prepare("SELECT id FROM suppliers WHERE code = ? LIMIT 1");
$stmt_check_name = $pdo->prepare("SELECT id FROM suppliers WHERE name = ? LIMIT 1");
$stmt_insert = $pdo->prepare("INSERT INTO suppliers (code, name, address, phone, email, contact_person, bank_name, bank_number, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
$stmt_update = $pdo->prepare("UPDATE suppliers SET name=?, address=?, phone=?, email=?, contact_person=?, bank_name=?, bank_number=?, updated_at=NOW() WHERE id=?");

foreach ($rows as $idx => $r) {
    $row_no = $idx + 1;
    $code = trim((string)($r['code'] ?? $r['vendor_code'] ?? ''));
    $name = trim((string)($r['name'] ?? $r['vendor_name'] ?? ''));

    if ($name === '') {
        $errors[] = "Baris {$row_no}: name wajib diisi.";
        $skipped++;
        continue;
    }

    $address = trim((string)($r['address'] ?? ''));
    $phone = trim((string)($r['phone'] ?? ''));
    $email = trim((string)($r['email'] ?? ''));
    $contact = trim((string)($r['contact_person'] ?? ''));
    $bank_name = trim((string)($r['bank_name'] ?? ''));
    $bank_number = trim((string)($r['bank_number'] ?? ''));

    if ($code === '') {
        // Auto-generate code: VD-XXX (sequence by id)
        $stmt_last = $pdo->query("SELECT code FROM suppliers WHERE code LIKE 'VD-%' ORDER BY id DESC LIMIT 1");
        $last_code = $stmt_last->fetchColumn();
        if ($last_code) {
            $last_no = intval(substr($last_code, 3));
            $new_no = $last_no + 1;
        } else {
            $new_no = 1;
        }
        $code = 'VD-' . str_pad($new_no, 3, '0', STR_PAD_LEFT);
    }

    $existing_id = null;
    $stmt_check_code->execute([$code]);
    $existing_id = $stmt_check_code->fetchColumn();
    if (!$existing_id) {
        $stmt_check_name->execute([$name]);
        $existing_id = $stmt_check_name->fetchColumn();
    }

    if ($existing_id) {
        if ($mode === 'update') {
            $stmt_update->execute([$name, $address, $phone, $email, $contact, $bank_name, $bank_number, $existing_id]);
            $updated++;
        } else {
            $skipped++;
        }
        continue;
    }

    $stmt_insert->execute([$code, $name, $address, $phone, $email, $contact, $bank_name, $bank_number]);
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
