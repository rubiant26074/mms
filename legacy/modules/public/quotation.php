<?php
// modules/public/quotation.php

$quote_id = isset($_GET['q']) ? (int)$_GET['q'] : 0;
$token = trim((string)($_GET['t'] ?? ''));

if ($quote_id <= 0 || $token === '') {
    http_response_code(400);
    die('Link quotation tidak valid.');
}

if (!function_exists('verify_public_link_token') || !verify_public_link_token('quotation', $quote_id, $token)) {
    http_response_code(403);
    die('Link quotation tidak valid atau sudah kedaluwarsa.');
}

if (function_exists('mms_ensure_quotations_client_signature_columns')) {
    mms_ensure_quotations_client_signature_columns($pdo);
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
        $stmt = $pdo->prepare("SELECT id, status, quote_number, client_signature_path FROM quotations WHERE id = ? LIMIT 1");
        $stmt->execute([$quote_id]);
        $q = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$q) {
            throw new Exception('Quotation tidak ditemukan.');
        }

        $allowed_status = ['approved', 'sent', 'won', 'so_created'];
        if (!in_array((string)($q['status'] ?? ''), $allowed_status, true)) {
            throw new Exception('Quotation belum siap untuk tanda tangan client.');
        }
        if (!empty($q['client_signature_path'])) {
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

        $base64 = substr($signature_data, strlen('data:image/png;base64,'));
        $base64 = str_replace(' ', '+', $base64);
        $binary = base64_decode($base64, true);
        if ($binary === false || strlen($binary) < 200) {
            throw new Exception('Gambar tanda tangan tidak valid.');
        }
        if (strlen($binary) > (2 * 1024 * 1024)) {
            throw new Exception('Ukuran tanda tangan terlalu besar.');
        }

        $upload_rel_dir = 'uploads/quotation_signatures';
        $upload_abs_dir = function_exists('mms_abs_path') ? mms_abs_path($upload_rel_dir) : (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $upload_rel_dir);
        if (!is_dir($upload_abs_dir) && !@mkdir($upload_abs_dir, 0777, true) && !is_dir($upload_abs_dir)) {
            throw new Exception('Gagal menyiapkan folder tanda tangan.');
        }

        $safe_quote = preg_replace('/[^A-Za-z0-9\\-]/', '', (string)($q['quote_number'] ?? ('Q' . $quote_id)));
        if ($safe_quote === '') $safe_quote = 'Q' . $quote_id;
        $filename = 'client_sig_' . $safe_quote . '_' . time() . '.png';
        $rel_path = $upload_rel_dir . '/' . $filename;
        $abs_path = function_exists('mms_abs_path') ? mms_abs_path($rel_path) : ($upload_abs_dir . DIRECTORY_SEPARATOR . $filename);

        if (@file_put_contents($abs_path, $binary) === false || !is_file($abs_path)) {
            throw new Exception('Gagal menyimpan tanda tangan.');
        }

        // Hapus file lama jika ada.
        $stmt_old = $pdo->prepare("SELECT client_signature_path FROM quotations WHERE id = ? LIMIT 1");
        $stmt_old->execute([$quote_id]);
        $old_rel = (string)($stmt_old->fetchColumn() ?: '');

        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if (strlen($ua) > 255) $ua = substr($ua, 0, 255);

        $stmt_upd = $pdo->prepare("UPDATE quotations
                                   SET client_signature_path = ?,
                                       client_signed_name = ?,
                                       client_signed_at = NOW(),
                                       client_signature_ip = ?,
                                       client_signature_user_agent = ?,
                                       status = CASE WHEN status = 'approved' THEN 'sent' ELSE status END
                                   WHERE id = ?
                                     AND (client_signature_path IS NULL OR client_signature_path = '')
                                   LIMIT 1");
        $stmt_upd->execute([$rel_path, $signed_name, $ip, $ua, $quote_id]);
        if ($stmt_upd->rowCount() <= 0) {
            @unlink($abs_path);
            throw new Exception('Tanda tangan client sudah tersimpan dan tidak dapat ditimpa.');
        }

        if ($old_rel !== '' && $old_rel !== $rel_path && function_exists('mms_abs_path')) {
            $old_abs = mms_abs_path($old_rel);
            if (is_file($old_abs)) { @unlink($old_abs); }
        }

        if (function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'sales.quotation.client_signed',
                'Quotation Ditandatangani Client',
                "Quotation {$q['quote_number']} sudah ditandatangani client.",
                "index.php?page=sales-quote&action=print&id={$quote_id}",
                'success',
                ['permission_slug' => 'sales_quotation_manage']
            );
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Tanda tangan berhasil disimpan.',
            'signed_name' => $signed_name,
            'reload' => true
        ]);
        exit;
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

if (!defined('MMS_PUBLIC_QUOTE_MODE')) {
    define('MMS_PUBLIC_QUOTE_MODE', true);
}

$_GET['id'] = $quote_id;
require __DIR__ . '/../sales/quotations/print.php';
