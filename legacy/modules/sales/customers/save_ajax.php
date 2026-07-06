<?php
// modules/sales/customers/save_ajax.php

// 1. BERSIHKAN BUFFER
// Penting! Hapus output HTML apa pun (seperti spasi/enter di file lain) sebelum kirim JSON
if (ob_get_length()) ob_clean(); 

header('Content-Type: application/json');

// 2. CEK KONEKSI & LOGIN
// Kita gunakan variabel global $pdo dari index.php karena file ini di-include lewat router
global $pdo;

if (!isset($pdo)) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database tidak ditemukan (Akses langsung ditolak).']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi habis, silakan login ulang.']);
    exit;
}
if (!has_permission('sales_customer_manage')) {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}

// 3. TERIMA DATA JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Tidak ada data yang dikirim.']);
    exit;
}
if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($input['csrf'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Permintaan tidak valid (CSRF).']);
    exit;
}

if (empty($input['name'])) {
    echo json_encode(['status' => 'error', 'message' => 'Nama Customer wajib diisi']);
    exit;
}

$name = trim($input['name']);
$address = trim($input['address'] ?? '');
$phone = trim($input['phone'] ?? '');
$pic = trim($input['pic'] ?? '');
$email = trim($input['email'] ?? '');
$tax_id = trim($input['tax_id'] ?? '');
$tax_invoice_number = trim($input['tax_invoice_number'] ?? $input['nsfp'] ?? '');
$tax_invoice_number = preg_replace('/\s+/', '', $tax_invoice_number);

if ($tax_invoice_number !== '' && !preg_match('/^\d{3}\.\d{3}-\d{2}\.\d{8}$/', $tax_invoice_number)) {
    echo json_encode(['status' => 'error', 'message' => 'Format No. Seri Faktur Pajak tidak valid. Gunakan format 000.000-YY.12345678']);
    exit;
}

try {
    // 4. CEK DUPLIKAT NAMA
    $chk = $pdo->prepare("SELECT id FROM customers WHERE name = ?");
    $chk->execute([$name]);
    if($chk->rowCount() > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Nama Customer sudah ada di database!']);
        exit;
    }

    // 5. GENERATE AUTO CODE (CT-XXX) - 3 DIGIT
    $stmt_last = $pdo->query("SELECT customer_code FROM customers WHERE customer_code LIKE 'CT-%' ORDER BY id DESC LIMIT 1");
    $last_code = $stmt_last->fetchColumn();

    if ($last_code) {
        // Ambil angka dari string (misal CT-001 -> ambil 001)
        // substr 3 karakter ('CT-')
        $last_no = intval(substr($last_code, 3));
        $new_no = $last_no + 1;
    } else {
        $new_no = 1;
    }
    
    // Format ulang ke 3 digit (misal: 1 -> 001)
    $code = 'CT-' . str_pad($new_no, 3, '0', STR_PAD_LEFT);

    // 6. INSERT KE DATABASE
    $sql = "INSERT INTO customers (customer_code, name, address, phone, pic, email, tax_id, tax_invoice_number, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$code, $name, $address, $phone, $pic, $email, $tax_id, $tax_invoice_number !== '' ? $tax_invoice_number : null, $_SESSION['user_id']]);
    
    $new_id = $pdo->lastInsertId();

    // 7. RETURN SUCCESS JSON
    echo json_encode([
        'status' => 'success', 
        'data' => [
            'id' => $new_id,
            'name' => $name,
            'code' => $code
        ]
    ]);

} catch (Exception $e) {
    // Tangkap error SQL agar tidak jadi 500, tapi jadi pesan JSON
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan saat menyimpan customer.']);
}
?>
