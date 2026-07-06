<?php
// modules/sales/orders/save.php
session_start();
require_once '../../../config/database.php';
require_once '../../../config/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_logged_in() || !has_permission('sales_so_manage')) {
        http_response_code(403);
        die('Akses ditolak.');
    }
    $csrf = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
        http_response_code(400);
        die('Permintaan tidak valid (CSRF).');
    }
    if (function_exists('mms_ensure_sales_orders_fulfillment_source_column')) {
        mms_ensure_sales_orders_fulfillment_source_column($pdo);
    }

    $id = $_POST['id'] ?? null;
    $quote_id = isset($_POST['quote_id']) && $_POST['quote_id'] !== '' ? (int)$_POST['quote_id'] : null;
    $is_edit = $id ? true : false;
    if ($is_edit) {
        $stmt_status = $pdo->prepare("SELECT status FROM sales_orders WHERE id = ? LIMIT 1");
        $stmt_status->execute([$id]);
        $cur_status = (string)$stmt_status->fetchColumn();
        if (!in_array($cur_status, ['draft', 'rejected'], true)) {
            die('Sales Order tidak bisa diubah pada status saat ini.');
        }
    }

    // Ambil data header
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $so_date = trim((string)($_POST['so_date'] ?? ''));
    $delivery_date = $_POST['delivery_date'] ?? null;
    $cust_po_number = trim((string)($_POST['cust_po_number'] ?? ''));
    $payment_terms = trim((string)($_POST['payment_terms'] ?? ''));
    $fulfillment_source = function_exists('mms_normalize_sales_order_fulfillment_source')
        ? mms_normalize_sales_order_fulfillment_source($_POST['fulfillment_source'] ?? 'spk')
        : 'spk';
    $notes = trim((string)($_POST['notes'] ?? ''));
    $ppn_percent = isset($_POST['ppn_percent']) ? (float)$_POST['ppn_percent'] : 11.0;
    if ($ppn_percent <= 0 || $ppn_percent > 100) {
        $ppn_percent = 11.0;
    }
    $tax_mode = (string)($_POST['tax_mode'] ?? 'exclude');
    $tax_included = ($tax_mode === 'include') ? 1 : 0;
    $tax_factor = 1 + ($ppn_percent / 100);
    $disc_raw = (string)($_POST['discount_amount'] ?? '0');
    $discount_amount = (float)str_replace(',', '.', preg_replace('/[^0-9,.\-]/', '', $disc_raw));
    if ($discount_amount < 0) {
        $discount_amount = 0.0;
    }
    $status = 'draft';

    // Data Detail Items
    $item_ids = $_POST['item_id'] ?? [];
    $item_codes = $_POST['item_code'] ?? [];
    $item_names = $_POST['item_name'] ?? [];
    $materials = $_POST['material'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $units = $_POST['unit'] ?? [];
    $prices = $_POST['price'] ?? [];
    if ($customer_id <= 0 || $so_date === '') {
        die('Data header SO tidak valid.');
    }

    try {
        $pdo->beginTransaction();

        $stmt_cust_code = $pdo->prepare("SELECT customer_code FROM customers WHERE id = ? LIMIT 1");
        $stmt_cust_code->execute([$customer_id]);
        $customer_code = (string)($stmt_cust_code->fetchColumn() ?: '');

        $stmt_find_item_by_code = $pdo->prepare("SELECT id FROM items WHERE item_code = ? LIMIT 1");
        $stmt_find_item_by_name = $pdo->prepare("SELECT id FROM items WHERE customer_id = ? AND item_name = ? AND item_type = 'finish_good' ORDER BY id DESC LIMIT 1");
        $stmt_insert_item = $pdo->prepare("INSERT INTO items
            (customer_id, item_code, item_name, item_type, ownership, qc_type, unit, base_price, current_stock, min_stock, description)
            VALUES (?, ?, ?, 'finish_good', 'customer', 'general', ?, ?, 0, 0, ?)");

        $generate_item_code = function () use ($pdo, $customer_id, $customer_code) {
            $prefix_raw = trim($customer_code) !== '' ? trim($customer_code) : ('CUST-' . (int)$customer_id);
            $prefix = preg_replace('/[^A-Za-z0-9\\-]/', '', strtoupper($prefix_raw));
            if ($prefix === '') $prefix = 'FG';

            $stmt_last = $pdo->prepare("SELECT item_code FROM items WHERE item_code LIKE ? ORDER BY id DESC LIMIT 1");
            $stmt_last->execute([$prefix . '-%']);
            $last_code = (string)$stmt_last->fetchColumn();

            $next_num = 1;
            if ($last_code !== '') {
                $parts = explode('-', $last_code);
                $last_num = (int)end($parts);
                $next_num = $last_num > 0 ? ($last_num + 1) : 1;
            }
            return $prefix . '-' . str_pad((string)$next_num, 4, '0', STR_PAD_LEFT);
        };

        // 1. Logika Simpan Header SO
        if (!$is_edit) {
            // Auto Generate SO Number
            $ym = date('ym');
            $stmt_no = $pdo->query("SELECT COUNT(*) FROM sales_orders WHERE so_number LIKE 'SO-$ym-%'");
            $count = $stmt_no->fetchColumn() + 1;
            $so_number = "SO-$ym-" . str_pad($count, 4, '0', STR_PAD_LEFT);

            $sql = "INSERT INTO sales_orders (so_number, quotation_id, customer_id, so_date, delivery_date, cust_po_number, payment_terms, fulfillment_source, ppn_percent, tax_included, status, notes, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$so_number, $quote_id, $customer_id, $so_date, $delivery_date, $cust_po_number, $payment_terms, $fulfillment_source, $ppn_percent, $tax_included, $status, $notes, $_SESSION['user_id']]);
            $so_id = $pdo->lastInsertId();
        } else {
            $sql = "UPDATE sales_orders SET quotation_id=?, customer_id=?, so_date=?, delivery_date=?, cust_po_number=?, payment_terms=?, fulfillment_source=?, ppn_percent=?, tax_included=?, notes=? WHERE id=?";
            $pdo->prepare($sql)->execute([$quote_id, $customer_id, $so_date, $delivery_date, $cust_po_number, $payment_terms, $fulfillment_source, $ppn_percent, $tax_included, $notes, $id]);
            $so_id = $id;
            // Bersihkan detail lama untuk update (Sync)
            $pdo->prepare("DELETE FROM sales_order_items WHERE sales_order_id=?")->execute([$so_id]);
        }

        // 2. Simpan Detail Items & Kalkulasi
        $total_bruto = 0;
        $total_gross_input = 0;
        $saved_items = 0;
        $stmt_item = $pdo->prepare("INSERT INTO sales_order_items (sales_order_id, item_id, item_code_manual, item_name_manual, material_manual, unit_manual, qty, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($item_ids as $index => $val) {
            $item_id = (int)$val;
            $item_code = trim((string)($item_codes[$index] ?? ''));
            $item_name = trim((string)($item_names[$index] ?? ''));
            $material = trim((string)($materials[$index] ?? ''));
            $unit = trim((string)($units[$index] ?? ''));
            $qty = floatval($qtys[$index]);
            $price_input = floatval($prices[$index]);
            $price = $tax_included ? ($price_input / $tax_factor) : $price_input;
            $subtotal = $qty * $price;
            $line_gross_input = $qty * $price_input;

            // Auto sync ke Master Barang:
            // jika baris SO belum punya item_id, buat/cocokkan item finish_good di master items.
            if ($item_id <= 0 && ($item_name !== '' || $item_code !== '')) {
                if ($item_code !== '') {
                    $stmt_find_item_by_code->execute([$item_code]);
                    $existing_id = (int)($stmt_find_item_by_code->fetchColumn() ?: 0);
                    if ($existing_id > 0) {
                        $item_id = $existing_id;
                    }
                }

                if ($item_id <= 0 && $item_name !== '') {
                    $stmt_find_item_by_name->execute([$customer_id, $item_name]);
                    $existing_by_name = (int)($stmt_find_item_by_name->fetchColumn() ?: 0);
                    if ($existing_by_name > 0) {
                        $item_id = $existing_by_name;
                    }
                }

                if ($item_id <= 0) {
                    if ($item_code === '') {
                        $item_code = $generate_item_code();
                    }
                    if ($unit === '') {
                        $unit = 'PCS';
                    }
                    if ($item_name === '') {
                        $item_name = $item_code;
                    }

                    // Retry bila item_code bentrok karena race condition.
                    for ($try = 0; $try < 3; $try++) {
                        try {
                            $stmt_insert_item->execute([
                                $customer_id,
                                $item_code,
                            $item_name,
                                $unit,
                                $price,
                                ($material !== '' ? $material : 'Auto-created from Sales Order')
                            ]);
                            $item_id = (int)$pdo->lastInsertId();
                            break;
                        } catch (PDOException $pe) {
                            if ($pe->getCode() === '23000') {
                                $item_code = $generate_item_code();
                                continue;
                            }
                            throw $pe;
                        }
                    }
                }
            }

            // Untuk item manual dari quotation, item_id bisa 0 (fallback ke kolom manual)
            if ($qty <= 0 || $price_input < 0) {
                continue;
            }
            $total_bruto += $subtotal;
            $total_gross_input += $line_gross_input;
            $stmt_item->execute([$so_id, $item_id, $item_code, $item_name, $material, $unit, $qty, $price, $subtotal]);
            $saved_items++;
        }
        if ($saved_items <= 0) {
            throw new Exception('Item SO kosong.');
        }

        $discount_cap = $tax_included ? $total_gross_input : $total_bruto;
        if ($discount_amount > $discount_cap) {
            $discount_amount = (float)$discount_cap;
        }

        // 3. Update Final Totals
        if ($tax_included) {
            // Mode include: ekstrak DPP/PPN dari subtotal gross (jumlah harga input), bukan per-part.
            $gross_after_discount = max(0, $total_gross_input - $discount_amount);
            $subtotal_final = $gross_after_discount / $tax_factor;
            $tax_amount = $gross_after_discount - $subtotal_final;
            $grand_total = $gross_after_discount;
        } else {
            // Mode exclude: nilai PPN pada SO = 0, grand total mengikuti subtotal.
            $subtotal_final = max(0, $total_bruto - $discount_amount);
            $tax_amount = 0.0;
            $grand_total = $subtotal_final;
        }

        $sql_update = "UPDATE sales_orders SET subtotal = ?, discount_amount = ?, tax_amount = ?, grand_total = ?, ppn_percent = ?, tax_included = ? WHERE id = ?";
        $pdo->prepare($sql_update)->execute([$subtotal_final, $discount_amount, $tax_amount, $grand_total, $ppn_percent, $tax_included, $so_id]);

        // Jika SO dibuat dari quotation, tandai quotation sebagai SO Created.
        if (!$is_edit && !empty($quote_id) && (int)$quote_id > 0) {
            $pdo->prepare("UPDATE quotations SET status='so_created' WHERE id=? AND status IN ('won', 'so_created')")
                ->execute([(int)$quote_id]);
        }

        $pdo->commit();
        echo "<script>alert('Sales Order Berhasil Disimpan!'); window.location='../../../index.php?page=sales-so';</script>";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Gagal menyimpan SO. Silakan cek data input.");
    }
}
