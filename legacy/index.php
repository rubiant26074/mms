<?php
// index.php
require_once 'config/database.php';
require_once 'config/functions.php';

$public_page = $_GET['page'] ?? '';
if ($public_page === 'public-quote') {
    require_once 'modules/public/quotation.php';
    return;
}
if ($public_page === 'public-invoice') {
    require_once 'modules/public/invoice.php';
    return;
}
if ($public_page === 'public-so') {
    require_once 'modules/public/sales_order.php';
    return;
}

// 1. CEK LOGIN
if (!is_logged_in()) {
    require_once 'modules/auth/login.php';
    return;
}

// 2. INITIALIZE ROUTING
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$action = isset($_GET['action']) ? $_GET['action'] : 'index';

// Helper: Check Access
function check_access($permission) {
    if (empty($permission)) return; 
    if (!has_permission($permission)) {
        render_header("Akses Ditolak");
        $perm_label = htmlspecialchars((string)$permission, ENT_QUOTES, 'UTF-8');
        echo '<div class="d-flex align-items-center justify-content-center" style="height:60vh"><div class="text-center"><h1 class="display-1 fw-bold text-danger">403</h1><p class="fs-3">Akses Ditolak!</p><div class="text-muted small mb-3">Kode akses: <span class="fw-semibold">'.$perm_label.'</span></div><a href="index.php" class="btn btn-secondary">Kembali</a></div></div>';
        render_footer();
        exit; 
    }
}
function render_construction($title) {
    render_header($title);
    echo '<div class="alert alert-warning shadow-sm border-0 m-4"><h4><i class="bi bi-cone-striped"></i> Modul Belum Tersedia</h4><p>Fitur <strong>'.$title.'</strong> sedang dikembangkan.</p><a href="index.php" class="btn btn-dark btn-sm">Kembali</a></div>';
    render_footer();
}

// 3. ROUTING SWITCH
switch ($page) {
    // --- MODULE: DASHBOARD ---
    case 'dashboard':
        check_access('dashboard_view');
        require_once 'modules/dashboard/index.php';
        break;
        
    // --- GROUP: ADMINISTRATOR ---
    case 'users':
        check_access('user_view');
        if ($action == 'create' || $action == 'edit') { check_access('user_edit'); require_once 'modules/users/form.php'; } 
        elseif ($action == 'delete') { check_access('user_delete'); require_once 'modules/users/delete.php'; } 
        else { require_once 'modules/users/index.php'; }
        break;
    case 'user-settings':
        require_once 'modules/users/settings.php';
        break;

    case 'roles':
        check_access('role_manage');
        if ($action == 'create' || $action == 'edit') { require_once 'modules/roles/form.php'; } 
        elseif ($action == 'delete') { 
             $id = $_GET['id'];
             $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
             $check->execute([$id]);
             if ($check->fetchColumn() > 0) {
                 echo "<script>alert('Gagal! Role sedang dipakai.'); window.location='index.php?page=roles';</script>";
             } else {
                 $pdo->prepare("DELETE FROM roles WHERE id=?")->execute([$id]);
                 echo "<script>window.location='index.php?page=roles';</script>";
             }
        } 
        else { require_once 'modules/roles/index.php'; }
        break;
        
    case 'role-permissions':
        check_access('role_manage');
        require_once 'modules/roles/permissions.php';
        break;

    case 'admin-company':
        check_access('admin_company_manage');
        require_once 'modules/admin/company/index.php';
        break;

    case 'admin-reset':
        check_access('admin_reset_db');
        require_once 'modules/admin/reset/index.php';
        break;
    
    case 'admin-backup':
        if ($_SESSION['role'] === 'admin' || has_permission('admin_reset_db')) {
            require_once 'modules/admin/backup/index.php';
        } else {
            check_access('admin_reset_db');
        }
        break;

    // --- MENU MANAGER (NEW) ---
    case 'admin-menu':
        if ($_SESSION['role'] === 'admin') {
            require_once 'modules/admin/menu_custom/index.php';
        } else {
            check_access('admin_reset_db'); // Blokir user biasa
        }
        break;

    case 'admin-setup-wizard':
        if ($_SESSION['role'] === 'admin') {
            require_once 'modules/admin/setup_wizard/index.php';
        } else {
            check_access('admin_reset_db');
        }
        break;

    case 'admin-wa-logs':
        if ($_SESSION['role'] === 'admin') {
            require_once 'modules/admin/wa_logs/index.php';
        } else {
            check_access('admin_reset_db');
        }
        break;

    // --- GROUP: SALES & MARKETING ---
    case 'sales-customers':
        if ($action == 'save_ajax') {
            if (has_permission('sales_customer_manage') || has_permission('sales_quotation_manage')) {
                require_once 'modules/sales/customers/save_ajax.php'; exit; 
            } else {
                header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit;
            }
        }
        if ($action == 'import_ajax') {
            if (has_permission('sales_customer_manage')) {
                require_once 'modules/sales/customers/import_ajax.php'; exit;
            } else {
                header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit;
            }
        }
        check_access('sales_customer_view');
        if ($action == 'create' || $action == 'edit') { check_access('sales_customer_manage'); require_once 'modules/sales/customers/form.php'; } 
        elseif ($action == 'delete') { check_access('sales_customer_manage'); require_once 'modules/sales/customers/delete.php'; } 
        else { require_once 'modules/sales/customers/index.php'; }
        break;

    case 'sales-quote': 
        check_access('sales_quotation_view');
        if ($action == 'create' || $action == 'edit') { check_access('sales_quotation_manage'); require_once 'modules/sales/quotations/form.php'; } 
        elseif ($action == 'delete') { check_access('sales_quotation_delete'); require_once 'modules/sales/quotations/delete.php'; } 
        elseif ($action == 'print') { check_access('sales_quotation_manage'); require_once 'modules/sales/quotations/print.php'; } 
        else { require_once 'modules/sales/quotations/index.php'; }
        break;

    case 'sales-so':    
        check_access('sales_view'); 
        if ($action == 'create' || $action == 'edit') { check_access('sales_so_manage'); require_once 'modules/sales/orders/form.php'; } 
        elseif ($action == 'delete') { check_access('sales_so_manage'); require_once 'modules/sales/orders/delete.php'; } 
        elseif ($action == 'print') { check_access('sales_so_manage'); require_once 'modules/sales/orders/print.php'; } 
        else { require_once 'modules/sales/orders/index.php'; }
        break;

    // --- GROUP: ENGINEERING ---
    case 'eng-items': 
        check_access('eng_items'); 
        if ($action == 'import_ajax') { require_once 'modules/engineering/items/import_ajax.php'; } 
        elseif ($action == 'create' || $action == 'edit') { require_once 'modules/engineering/items/form.php'; } 
        elseif ($action == 'delete') { require_once 'modules/engineering/items/delete.php'; } 
        else { require_once 'modules/engineering/items/index.php'; }
        break;

    case 'eng-machines':
        $redir = "index.php?page=admin-machines";
        if (!empty($action) && $action !== 'index') $redir .= "&action=" . urlencode($action);
        if (isset($_GET['id'])) $redir .= "&id=" . urlencode($_GET['id']);
        header("Location: $redir");
        exit;
    case 'admin-machines':
        check_access('eng_machine_view');
        if ($action == 'create' || $action == 'edit') { check_access('eng_machine_manage'); require_once 'modules/admin/machines/form.php'; } 
        elseif ($action == 'delete') { check_access('eng_machine_manage'); require_once 'modules/admin/machines/delete.php'; } 
        else { require_once 'modules/admin/machines/index.php'; }
        break;

    case 'eng-bom':   
        check_access('eng_bom'); 
        if ($action == 'create' || $action == 'edit') { require_once 'modules/engineering/boms/form.php'; } 
        elseif ($action == 'delete') { require_once 'modules/engineering/boms/delete.php'; } 
        else { require_once 'modules/engineering/boms/index.php'; }
        break;

    case 'eng-partlist':
        check_access('eng_view');
        if ($action == 'create' || $action == 'edit') { require_once 'modules/engineering/partlist/form.php'; } 
        elseif ($action == 'print') { require_once 'modules/engineering/partlist/print.php'; } 
        elseif ($action == 'approve') { require_once 'modules/engineering/partlist/index.php'; } 
        else { require_once 'modules/engineering/partlist/index.php'; }
        break;

    // --- GROUP: PPIC ---
    case 'ppic-spk':       
        check_access('ppic_spk_view');
        if ($action == 'create' || $action == 'edit') { check_access('ppic_spk_manage'); require_once 'modules/ppic/spk/form.php'; } 
        elseif ($action == 'delete') { check_access('ppic_spk_delete'); require_once 'modules/ppic/spk/delete.php'; } 
        elseif ($action == 'print') { require_once 'modules/ppic/spk/print.php'; } 
        else { require_once 'modules/ppic/spk/index.php'; }
        break;

    case 'ppic-pr':        
        check_access('ppic_pr_view');
        if ($action == 'create' || $action == 'edit') { check_access('ppic_pr_manage'); require_once 'modules/ppic/purchase_requests/form.php'; } 
        elseif ($action == 'delete') { check_access('ppic_pr_delete'); require_once 'modules/ppic/purchase_requests/delete.php'; } 
        elseif ($action == 'print') { require_once 'modules/ppic/purchase_requests/print.php'; } 
        else { require_once 'modules/ppic/purchase_requests/index.php'; }
        break;

    case 'ppic-mps': check_access('ppic_view'); require_once 'modules/ppic/mps/index.php'; break;
    case 'ppic-inventory': check_access('ppic_view'); if ($action == 'view') { require_once 'modules/ppic/inventory/view.php'; } else { require_once 'modules/ppic/inventory/index.php'; } break;

    // --- GROUP: PURCHASING ---
    case 'purch-po':     
        check_access('purch_po');     
        if ($action == 'create' || $action == 'edit') { check_access('purch_po_manage'); require_once 'modules/procurement/orders/form.php'; } 
        elseif ($action == 'delete') { check_access('purch_po_delete'); require_once 'modules/procurement/orders/delete.php'; } 
        elseif ($action == 'print') { require_once 'modules/procurement/orders/print.php'; } 
        else { require_once 'modules/procurement/orders/index.php'; }
        break;
        
    case 'purch-vendor': 
        check_access('purch_vendor_view'); 
        if ($action == 'import_ajax') { check_access('purch_vendor_manage'); require_once 'modules/procurement/suppliers/import_ajax.php'; }
        elseif ($action == 'create' || $action == 'edit') { check_access('purch_vendor_manage'); require_once 'modules/procurement/suppliers/form.php'; } 
        elseif ($action == 'delete') { check_access('purch_vendor_manage'); require_once 'modules/procurement/suppliers/delete.php'; } 
        else { require_once 'modules/procurement/suppliers/index.php'; }
        break;

    case 'purch-rfq':
        check_access('purch_po');
        if (!function_exists('mms_is_dev_feature_enabled') || !mms_is_dev_feature_enabled('purch_rfq')) {
            render_header("Halaman Tidak Ditemukan");
            echo '<div class="alert alert-warning m-4">Modul RFQ belum diaktifkan di Development Module Toggle.</div>';
            render_footer();
            break;
        }
        require_once 'modules/procurement/rfq/index.php';
        break;

    case 'purch-vendor-rating':
        check_access('purch_vendor_view');
        if (!function_exists('mms_is_dev_feature_enabled') || !mms_is_dev_feature_enabled('purch_vendor_rating')) {
            render_header("Halaman Tidak Ditemukan");
            echo '<div class="alert alert-warning m-4">Modul Vendor Rating belum diaktifkan di Development Module Toggle.</div>';
            render_footer();
            break;
        }
        require_once 'modules/procurement/vendor_rating/index.php';
        break;

    // --- GROUP: PRODUKSI ---
    case 'prod-task':   
        check_access('prod_view'); 
        if ($action == 'manage') { check_access('prod_task_manage'); require_once 'modules/production/tasks/manage.php'; } 
        else { require_once 'modules/production/tasks/index.php'; }
        break;

    case 'prod-operator': if (has_permission('prod_operator_access') || has_permission('prod_view')) { require_once 'modules/production/operator/index.php'; } else { check_access('prod_operator_access'); } break;
    case 'prod-scan': if (has_permission('prod_operator_access') || has_permission('prod_view')) { require_once 'modules/production/operator/index.php'; } else { render_construction("Operator Scanner"); } break;
    case 'prod-report': check_access('prod_view'); require_once 'modules/production/report/index.php'; break;

    // --- GROUP: QC ---
    case 'qc-incoming': check_access('qc_incoming_view'); if ($action == 'inspect') { check_access('qc_incoming_manage'); require_once 'modules/qc/incoming/form.php'; } elseif ($action == 'print') { require_once 'modules/qc/incoming/print.php'; } else { require_once 'modules/qc/incoming/index.php'; } break;
    case 'qc-production':
        check_access('qc_production_view');
        if ($action == 'inspect') {
            check_access('qc_production_manage');
            require_once 'modules/qc/production/form.php';
        } elseif ($action == 'print') {
            require_once 'modules/qc/production/print.php';
        } else {
            require_once 'modules/qc/production/index.php';
        }
        break;
    case 'qc-ncr': check_access('qc_ncr_view'); if ($action == 'create' || $action == 'edit') { check_access('qc_ncr_manage'); require_once 'modules/qc/ncr/form.php'; } elseif ($action == 'print') { require_once 'modules/qc/ncr/print.php'; } else { require_once 'modules/qc/ncr/index.php'; } break;

    // --- GROUP: WAREHOUSE ---
    case 'whse-receive': check_access('whse_view'); if ($action == 'create' || $action == 'edit') { check_access('whse_receive_manage'); require_once 'modules/warehouse/receive/form.php'; } elseif ($action == 'delete') { check_access('whse_receive_manage'); require_once 'modules/warehouse/receive/delete.php'; } else { require_once 'modules/warehouse/receive/index.php'; } break;
    case 'whse-issue': check_access('whse_stock'); if ($action == 'create') { require_once 'modules/warehouse/issue/form.php'; } elseif ($action == 'delete') { require_once 'modules/warehouse/issue/delete.php'; } elseif ($action == 'print') { require_once 'modules/warehouse/issue/print.php'; } else { require_once 'modules/warehouse/issue/index.php'; } break;
    case 'whse-sj': check_access('whse_sj_view'); if ($action == 'create' || $action == 'edit') { check_access('whse_sj_manage'); require_once 'modules/warehouse/delivery/form.php'; } elseif ($action == 'delete') { check_access('whse_sj_manage'); require_once 'modules/warehouse/delivery/delete.php'; } elseif ($action == 'print') { check_access('whse_sj_manage'); require_once 'modules/warehouse/delivery/print.php'; } else { require_once 'modules/warehouse/delivery/index.php'; } break;
    case 'whse-return': check_access('whse_view'); if ($action == 'create') { check_access('whse_stock'); require_once 'modules/warehouse/return/form.php'; } elseif ($action == 'approve') { check_access('whse_stock'); require_once 'modules/warehouse/return/index.php'; } else { require_once 'modules/warehouse/return/index.php'; } break;
    case 'whse-batch-expiry': check_access('whse_view'); require_once 'modules/warehouse/batch_expiry/index.php'; break;
    case 'whse-cycle-counting': check_access('whse_view'); require_once 'modules/warehouse/cycle_counting/index.php'; break;

    // --- GROUP: FINANCE ---
    case 'fin-ar': check_access('fin_ar_view'); if ($action == 'create' || $action == 'edit') { check_access('fin_ar_manage'); require_once 'modules/finance/ar/form.php'; } elseif ($action == 'post' || $action == 'unpost') { check_access('fin_ar_manage'); require_once 'modules/finance/ar/index.php'; } elseif ($action == 'pay') { check_access('fin_ar_manage'); require_once 'modules/finance/ar/payment.php'; } elseif ($action == 'print') { require_once 'modules/finance/ar/print.php'; } elseif ($action == 'print_tax') { require_once 'modules/finance/ar/print_tax.php'; } else { require_once 'modules/finance/ar/index.php'; } break;
    case 'fin-ap': check_access('fin_ap_view'); if ($action == 'create' || $action == 'edit') { check_access('fin_ap_manage'); require_once 'modules/finance/ap/form.php'; } elseif ($action == 'post' || $action == 'unpost') { check_access('fin_ap_manage'); require_once 'modules/finance/ap/index.php'; } elseif ($action == 'delete') { check_access('fin_ap_manage'); require_once 'modules/finance/ap/delete.php'; } elseif ($action == 'pay') { check_access('fin_ap_manage'); require_once 'modules/finance/ap/payment.php'; } else { require_once 'modules/finance/ap/index.php'; } break;
    case 'fin-cash': check_access('fin_view'); if ($action == 'create' || $action == 'edit') { check_access('fin_ap_manage'); require_once 'modules/finance/cash/form.php'; } elseif ($action == 'delete') { check_access('fin_ap_manage'); require_once 'modules/finance/cash/delete.php'; } elseif ($action == 'print') { require_once 'modules/finance/cash/print.php'; } else { require_once 'modules/finance/cash/index.php'; } break;
    case 'fin-tax': check_access('fin_ar_view'); require_once 'modules/finance/tax/index.php'; break;

    // --- GROUP: ACCOUNTING ---
    case 'acc-coa': check_access('acc_view'); if ($action == 'create' || $action == 'edit') { check_access('acc_coa_manage'); require_once 'modules/accounting/coa/form.php'; } elseif ($action == 'delete') { check_access('acc_coa_manage'); require_once 'modules/accounting/coa/delete.php'; } else { require_once 'modules/accounting/coa/index.php'; } break;
    case 'acc-journal': check_access('acc_view'); if ($action == 'create') { check_access('acc_journal_manage'); require_once 'modules/accounting/journal/form.php'; } else { require_once 'modules/accounting/journal/index.php'; } break;
    case 'acc-ledger': check_access('acc_view'); require_once 'modules/accounting/ledger/index.php'; break;
    case 'acc-report': check_access('acc_reports'); require_once 'modules/accounting/reports/index.php'; break;
    case 'acc-assets': check_access('acc_view'); if ($action == 'create' || $action == 'edit') { check_access('acc_asset_manage'); require_once 'modules/accounting/assets/form.php'; } elseif ($action == 'delete') { check_access('acc_asset_manage'); require_once 'modules/accounting/assets/delete.php'; } elseif ($action == 'depreciate') { check_access('acc_asset_manage'); require_once 'modules/accounting/assets/index.php'; } else { require_once 'modules/accounting/assets/index.php'; } break;

    // --- GROUP: HRD ---
    case 'hrd-attendance': check_access('hrd_attendance_view'); require_once 'modules/hrd/attendance/index.php'; break;
    case 'hrd-payroll': check_access('hrd_payroll_view'); if ($action == 'create' || $action == 'edit') { check_access('hrd_payroll_manage'); require_once 'modules/hrd/payroll/form.php'; } elseif ($action == 'delete') { check_access('hrd_payroll_manage'); require_once 'modules/hrd/payroll/delete.php'; } elseif ($action == 'pay') { require_once 'modules/hrd/payroll/index.php'; } elseif ($action == 'print') { require_once 'modules/hrd/payroll/print.php'; } else { require_once 'modules/hrd/payroll/index.php'; } break;
    case 'hrd-employees': check_access('hrd_view'); if ($action == 'create' || $action == 'edit') { check_access('hrd_employee_manage'); require_once 'modules/hrd/employees/form.php'; } elseif ($action == 'delete') { check_access('hrd_employee_manage'); require_once 'modules/hrd/employees/delete.php'; } else { require_once 'modules/hrd/employees/index.php'; } break;

    // --- GROUP: EXECUTIVE ---
    case 'exec-kpi': check_access('owner_kpi'); require_once 'modules/executive/dashboard/index.php'; break;
    case 'exec-logs': check_access('owner_logs'); require_once 'modules/executive/logs/index.php'; break;

    // --- GROUP: TV DASHBOARD ---
    case 'tv-lobby': require_once 'modules/tv/lobby.php'; break;
    case 'tv-exec': require_once 'modules/tv/executive.php'; break;
    case 'tv-prod': require_once 'modules/tv/production.php'; break;

    default:
        render_header("Halaman Tidak Ditemukan");
        echo '<div class="d-flex align-items-center justify-content-center" style="height: 60vh;"><div class="text-center"><h1 class="display-1 fw-bold text-primary">404</h1><p class="fs-3">Halaman tidak ditemukan.</p><a href="index.php" class="btn btn-primary">Dashboard</a></div></div>';
        render_footer();
        break;
}
?>
