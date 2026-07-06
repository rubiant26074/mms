<?php
// modules/sales/quotations/save_ajax.php
// Backward-compat shim: keep legacy endpoint path but delegate to canonical customer AJAX handler.

if (!defined('MMS_QUOTE_SAVE_AJAX_SHIM')) {
    define('MMS_QUOTE_SAVE_AJAX_SHIM', true);
}

if (!isset($pdo)) {
    require_once __DIR__ . '/../../../config/database.php';
    require_once __DIR__ . '/../../../config/functions.php';
}

require __DIR__ . '/../customers/save_ajax.php';
