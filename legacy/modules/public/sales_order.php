<?php
// modules/public/sales_order.php

$so_id = isset($_GET['s']) ? (int)$_GET['s'] : 0;
$token = trim((string)($_GET['t'] ?? ''));

if ($so_id <= 0 || $token === '') {
    http_response_code(400);
    die('Link sales order tidak valid.');
}

if (!function_exists('verify_public_link_token') || !verify_public_link_token('sales_order', $so_id, $token)) {
    http_response_code(403);
    die('Link sales order tidak valid atau sudah kedaluwarsa.');
}

if (function_exists('mms_ensure_sales_orders_client_signature_columns')) {
    mms_ensure_sales_orders_client_signature_columns($pdo);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action !== 'save_client_signature') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Aksi tidak valid.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, status, so_number, client_signature_path FROM sales_orders WHERE id = ? LIMIT 1");
        $stmt->execute([$so_id]);
        $so = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$so) throw new Exception('Sales Order tidak ditemukan.');

        $allow_status = ['confirmed', 'in_production', 'delivered', 'completed'];
        if (!in_array((string)($so['status'] ?? ''), $allow_status, true)) {
            throw new Exception('Sales Order belum siap untuk tanda tangan client.');
        }

        if (!empty($so['client_signature_path'])) {
            throw new Exception('Tanda tangan client sudah tersimpan dan tidak dapat ditimpa.');
        }

        $signed_name = trim((string)($_POST['signed_name'] ?? ''));
        if ($signed_name === '') $signed_name = 'Customer';
        $signed_len = function_exists('mb_strlen') ? mb_strlen($signed_name) : strlen($signed_name);
        if ($signed_len > 150) {
            $signed_name = function_exists('mb_substr') ? mb_substr($signed_name, 0, 150) : substr($signed_name, 0, 150);
        }

        $signature_data = trim((string)($_POST['signature_data'] ?? ''));
        if ($signature_data === '' || strpos($signature_data, 'data:image/png;base64,') !== 0) {
            throw new Exception('Data tanda tangan tidak valid.');
        }
        $binary = base64_decode(str_replace(' ', '+', substr($signature_data, 22)), true);
        if ($binary === false || strlen($binary) < 200) throw new Exception('Gambar tanda tangan tidak valid.');
        if (strlen($binary) > (2 * 1024 * 1024)) throw new Exception('Ukuran tanda tangan terlalu besar.');

        $rel_dir = 'uploads/sales_order_signatures';
        $abs_dir = function_exists('mms_abs_path') ? mms_abs_path($rel_dir) : (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $rel_dir);
        if (!is_dir($abs_dir) && !@mkdir($abs_dir, 0777, true) && !is_dir($abs_dir)) {
            throw new Exception('Gagal menyiapkan folder tanda tangan.');
        }

        $safe_no = preg_replace('/[^A-Za-z0-9\\-]/', '', (string)($so['so_number'] ?? ('SO' . $so_id)));
        if ($safe_no === '') $safe_no = 'SO' . $so_id;
        $file = 'client_sig_' . $safe_no . '_' . time() . '.png';
        $rel_path = $rel_dir . '/' . $file;
        $abs_path = function_exists('mms_abs_path') ? mms_abs_path($rel_path) : ($abs_dir . DIRECTORY_SEPARATOR . $file);

        if (@file_put_contents($abs_path, $binary) === false || !is_file($abs_path)) {
            throw new Exception('Gagal menyimpan tanda tangan.');
        }

        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if (strlen($ua) > 255) $ua = substr($ua, 0, 255);

        $upd = $pdo->prepare("UPDATE sales_orders
                              SET client_signature_path=?,
                                  client_signed_name=?,
                                  client_signed_at=NOW(),
                                  client_signature_ip=?,
                                  client_signature_user_agent=?
                              WHERE id=? AND (client_signature_path IS NULL OR client_signature_path='')");
        $upd->execute([$rel_path, $signed_name, $ip, $ua, $so_id]);
        if ($upd->rowCount() <= 0) {
            @unlink($abs_path);
            throw new Exception('Tanda tangan client sudah tersimpan dan tidak dapat ditimpa.');
        }

        if (function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'sales.so.client_signed',
                'SO Ditandatangani Client',
                "Sales Order {$so['so_number']} sudah ditandatangani client.",
                "index.php?page=sales-so&action=print&id={$so_id}",
                'success',
                ['permission_slug' => 'sales_so_manage']
            );
        }

        echo json_encode(['status' => 'success', 'message' => 'Tanda tangan berhasil disimpan.', 'reload' => true]);
        exit;
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

if (!defined('MMS_PUBLIC_SO_MODE')) {
    define('MMS_PUBLIC_SO_MODE', true);
}

$_GET['id'] = $so_id;
require __DIR__ . '/../sales/orders/print.php';

