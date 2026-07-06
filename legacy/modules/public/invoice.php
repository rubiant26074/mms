<?php
// modules/public/invoice.php

$invoice_id = isset($_GET['i']) ? (int)$_GET['i'] : 0;
$token = trim((string)($_GET['t'] ?? ''));

if ($invoice_id <= 0 || $token === '') {
    http_response_code(400);
    die('Link invoice tidak valid.');
}

if (!function_exists('verify_public_link_token') || !verify_public_link_token('invoice', $invoice_id, $token)) {
    http_response_code(403);
    die('Link invoice tidak valid atau sudah kedaluwarsa.');
}

if (!defined('MMS_PUBLIC_INVOICE_MODE')) {
    define('MMS_PUBLIC_INVOICE_MODE', true);
}

$_GET['id'] = $invoice_id;
require __DIR__ . '/../finance/ar/print.php';

