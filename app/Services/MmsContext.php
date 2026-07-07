<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\MmsNotification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class MmsContext
{
    public function company(): CompanyProfile
    {
        return CompanyProfile::query()->find(1) ?? new CompanyProfile([
            'company_name' => 'MMS System',
            'ui_theme' => 'original',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function notifications(User $user): array
    {
        return MmsNotification::query()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhere('target_role', $user->role?->role_slug ?? '');
            })
            ->latest('id')
            ->limit(5)
            ->get()
            ->all();
    }

    public function unreadNotificationCount(User $user): int
    {
        return MmsNotification::query()
            ->where('is_read', 0)
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhere('target_role', $user->role?->role_slug ?? '');
            })
            ->count();
    }

    public function activeTheme(CompanyProfile $company): array
    {
        $slug = strtolower(trim((string) ($company->ui_theme ?: 'original')));
        $path = $slug !== 'original' ? "assets/css/theme-{$slug}.css" : '';

        if ($path !== '' && ! is_file(public_path($path))) {
            $slug = 'original';
            $path = '';
        }

        return [
            'slug' => $slug,
            'label' => $this->themeLabel($slug),
            'css_path' => $path,
            'body_class' => $slug !== 'original' ? "theme-{$slug}" : '',
        ];
    }

    public function logoUrl(CompanyProfile $company): ?string
    {
        $path = trim((string) $company->logo_path);

        return $path !== '' ? asset($path) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sidebarMenus(?User $user = null): array
    {
        $user ??= Auth::user();
        if (! $user instanceof User) {
            return [];
        }

        $can = fn (string $permission): bool => $user->hasPermission($permission);

        $menus = [];
        if ($can('dashboard_view')) {
            $menus[] = ['label' => 'Dashboard', 'url' => route('dashboard'), 'icon' => 'bi-speedometer2'];
        }
        if ($can('user_view') || $can('role_manage')) {
            $menus[] = [
                'id' => 'adminMenu',
                'label' => 'Administrator',
                'icon' => 'bi-shield-lock',
                'submenu' => array_values(array_filter([
                    $can('user_view') ? ['label' => 'Users', 'url' => route('admin.users.index'), 'icon' => 'bi-people'] : null,
                    $can('role_manage') ? ['label' => 'Roles', 'url' => route('admin.roles.index'), 'icon' => 'bi-person-lock'] : null,
                    $can('role_manage') ? ['label' => 'Permissions', 'url' => route('admin.roles.permissions'), 'icon' => 'bi-key'] : null,
                    $can('admin_company_manage') ? ['label' => 'Company', 'url' => route('admin.company.edit'), 'icon' => 'bi-building'] : null,
                    $can('eng_machine_view') ? ['label' => 'Machines', 'url' => route('admin.machines.index'), 'icon' => 'bi-hdd-rack'] : null,
                    $can('admin_menu_manage') ? ['label' => 'Menu Custom', 'url' => route('admin.menu.index'), 'icon' => 'bi-grid'] : null,
                    $can('admin_reset_db') ? ['label' => 'Setup Wizard', 'url' => route('admin.setup.index'), 'icon' => 'bi-magic'] : null,
                    $can('admin_reset_db') ? ['label' => 'Backup', 'url' => route('admin.backup.index'), 'icon' => 'bi-database-down'] : null,
                    $can('admin_reset_db') ? ['label' => 'Reset', 'url' => route('admin.reset.index'), 'icon' => 'bi-exclamation-triangle'] : null,
                    $can('admin_reset_db') ? ['label' => 'WA Logs', 'url' => route('admin.wa_logs.index'), 'icon' => 'bi-whatsapp'] : null,
                ])),
            ];
        }
        if ($can('sales_customer_view') || $can('sales_quotation_view') || $can('sales_view')) {
            $menus[] = [
                'id' => 'salesMenu',
                'label' => 'Sales',
                'icon' => 'bi-graph-up-arrow',
                'submenu' => array_values(array_filter([
                    $can('sales_customer_view') ? ['label' => 'Customers', 'url' => route('sales.customers.index'), 'icon' => 'bi-person-vcard'] : null,
                    $can('sales_quotation_view') ? ['label' => 'Quotations', 'url' => route('sales.quotations.index'), 'icon' => 'bi-file-earmark-text'] : null,
                    $can('sales_view') ? ['label' => 'Sales Order', 'url' => route('sales.orders.index'), 'icon' => 'bi-receipt'] : null,
                ])),
            ];
        }
        if ($can('eng_items') || $can('eng_bom') || $can('eng_view')) {
            $menus[] = [
                'id' => 'engineeringMenu',
                'label' => 'Engineering',
                'icon' => 'bi-tools',
                'submenu' => array_values(array_filter([
                    $can('eng_items') ? ['label' => 'Items', 'url' => route('engineering.items.index'), 'icon' => 'bi-box'] : null,
                    $can('eng_bom') ? ['label' => 'BOM', 'url' => route('engineering.boms.index'), 'icon' => 'bi-diagram-3'] : null,
                    $can('eng_view') ? ['label' => 'Part List', 'url' => route('engineering.partlists.index'), 'icon' => 'bi-list-check'] : null,
                ])),
            ];
        }
        if ($can('ppic_view') || $can('ppic_spk_view') || $can('ppic_pr_view')) {
            $menus[] = [
                'id' => 'ppicMenu',
                'label' => 'PPIC',
                'icon' => 'bi-calendar2-week',
                'submenu' => [
                    ['label' => 'SPK', 'url' => route('ppic.spk.index'), 'icon' => 'bi-clipboard-check'],
                    ['label' => 'MPS', 'url' => route('ppic.mps.index'), 'icon' => 'bi-calendar-week'],
                    ['label' => 'Purchase Request', 'url' => route('ppic.purchase_requests.index'), 'icon' => 'bi-cart-plus'],
                    ['label' => 'Inventory', 'url' => route('ppic.inventory.index'), 'icon' => 'bi-box-seam'],
                ],
            ];
        }
        if ($can('prod_view') || $can('prod_task_manage') || $can('prod_operator_access')) {
            $menus[] = [
                'id' => 'productionMenu',
                'label' => 'Production',
                'icon' => 'bi-kanban',
                'submenu' => array_values(array_filter([
                    $can('prod_view') ? ['label' => 'Task Assignment', 'url' => route('production.tasks.index'), 'icon' => 'bi-list-task'] : null,
                    ($can('prod_operator_access') || $can('prod_view')) ? ['label' => 'Operator Panel', 'url' => route('production.operator.index'), 'icon' => 'bi-phone'] : null,
                    $can('prod_view') ? ['label' => 'Laporan Harian', 'url' => route('production.reports.index'), 'icon' => 'bi-file-bar-graph'] : null,
                ])),
            ];
        }
        if ($can('purch_po_view') || $can('purch_vendor_view')) {
            $menus[] = [
                'id' => 'procurementMenu',
                'label' => 'Procurement',
                'icon' => 'bi-bag-check',
                'submenu' => array_values(array_filter([
                    $can('purch_vendor_view') ? ['label' => 'Suppliers', 'url' => route('procurement.suppliers.index'), 'icon' => 'bi-truck'] : null,
                    $can('purch_vendor_view') ? ['label' => 'Vendor Rating', 'url' => route('procurement.vendor_ratings.index'), 'icon' => 'bi-star-half'] : null,
                    $can('purch_po_view') ? ['label' => 'RFQ', 'url' => route('procurement.rfqs.index'), 'icon' => 'bi-clipboard2-data'] : null,
                    $can('purch_po_view') ? ['label' => 'Purchase Orders', 'url' => route('procurement.orders.index'), 'icon' => 'bi-file-earmark-text'] : null,
                ])),
            ];
        }
        if ($can('whse_receive_view') || $can('whse_view') || $can('whse_stock') || $can('whse_sj_view')) {
            $menus[] = [
                'id' => 'warehouseMenu',
                'label' => 'Warehouse',
                'icon' => 'bi-box-seam',
                'submenu' => array_values(array_filter([
                    ($can('whse_receive_view') || $can('whse_view')) ? ['label' => 'Penerimaan Barang', 'url' => route('warehouse.receipts.index'), 'icon' => 'bi-box-arrow-in-down'] : null,
                    $can('whse_stock') ? ['label' => 'Material Issue', 'url' => route('warehouse.material_issues.index'), 'icon' => 'bi-box-arrow-up'] : null,
                    $can('whse_sj_view') ? ['label' => 'Surat Jalan', 'url' => route('warehouse.delivery_notes.index'), 'icon' => 'bi-truck'] : null,
                    ($can('whse_view') || $can('whse_stock')) ? ['label' => 'Material Return', 'url' => route('warehouse.material_returns.index'), 'icon' => 'bi-arrow-return-left'] : null,
                    $can('whse_view') ? ['label' => 'Batch Expiry', 'url' => route('warehouse.batch_expiry.index'), 'icon' => 'bi-calendar-x'] : null,
                    $can('whse_view') ? ['label' => 'Cycle Counting', 'url' => route('warehouse.cycle_counting.index'), 'icon' => 'bi-clipboard-data'] : null,
                ])),
            ];
        }
        if ($can('qc_incoming_view') || $can('qc_production_view') || $can('qc_ncr_view') || $can('qc_view')) {
            $menus[] = [
                'id' => 'qcMenu',
                'label' => 'Quality Control',
                'icon' => 'bi-shield-check',
                'submenu' => array_values(array_filter([
                    ($can('qc_incoming_view') || $can('qc_view')) ? ['label' => 'Incoming QC', 'url' => route('qc.incoming.index'), 'icon' => 'bi-box-arrow-in-down'] : null,
                    ($can('qc_production_view') || $can('qc_view')) ? ['label' => 'Production QC', 'url' => route('qc.production.index'), 'icon' => 'bi-clipboard-check'] : null,
                    ($can('qc_ncr_view') || $can('qc_view')) ? ['label' => 'NCR', 'url' => route('qc.ncr.index'), 'icon' => 'bi-exclamation-octagon'] : null,
                ])),
            ];
        }
        if ($can('fin_view') || $can('fin_ar_view') || $can('fin_ap_view')) {
            $menus[] = [
                'id' => 'financeMenu',
                'label' => 'Finance',
                'icon' => 'bi-cash-coin',
                'submenu' => array_values(array_filter([
                    $can('fin_ar_view') ? ['label' => 'Accounts Receivable', 'url' => route('finance.ar.index'), 'icon' => 'bi-receipt'] : null,
                    $can('fin_ap_view') ? ['label' => 'Accounts Payable', 'url' => route('finance.ap.index'), 'icon' => 'bi-wallet2'] : null,
                    $can('fin_view') ? ['label' => 'Cash / Bank', 'url' => 'index.php?page=fin-cash', 'icon' => 'bi-bank'] : null,
                    $can('fin_ar_view') ? ['label' => 'Perpajakan', 'url' => route('finance.tax.index'), 'icon' => 'bi-receipt-cutoff'] : null,
                ])),
            ];
        }
        if ($can('acc_view') || $can('acc_reports')) {
            $menus[] = [
                'id' => 'accountingMenu',
                'label' => 'Accounting',
                'icon' => 'bi-journal-richtext',
                'submenu' => array_values(array_filter([
                    $can('acc_view') ? ['label' => 'Chart of Accounts', 'url' => route('accounting.coa.index'), 'icon' => 'bi-list-columns-reverse'] : null,
                    $can('acc_view') ? ['label' => 'Jurnal Umum', 'url' => route('accounting.journal.index'), 'icon' => 'bi-journal-text'] : null,
                    $can('acc_view') ? ['label' => 'Buku Besar', 'url' => route('accounting.ledger.index'), 'icon' => 'bi-book'] : null,
                    $can('acc_reports') ? ['label' => 'Laporan Keuangan', 'url' => route('accounting.reports.index'), 'icon' => 'bi-file-earmark-bar-graph'] : null,
                    $can('acc_view') ? ['label' => 'Fixed Assets', 'url' => route('accounting.assets.index'), 'icon' => 'bi-building-gear'] : null,
                ])),
            ];
        }
        if ($can('owner_kpi') || $can('owner_logs')) {
            $menus[] = [
                'id' => 'executiveMenu',
                'label' => 'Executive',
                'icon' => 'bi-bar-chart-line',
                'submenu' => array_values(array_filter([
                    $can('owner_kpi') ? ['label' => 'KPI Dashboard', 'url' => route('executive.kpi.index'), 'icon' => 'bi-trophy'] : null,
                    $can('owner_logs') ? ['label' => 'System Logs', 'url' => route('executive.logs.index'), 'icon' => 'bi-terminal'] : null,
                ])),
            ];
        }

        return $menus;
    }

    public function syncLegacySession(User $user): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;
        $_SESSION['fullname'] = $user->fullname;
        $_SESSION['role'] = $user->role?->role_slug ?? '';
        $_SESSION['user_role'] = $_SESSION['role'];
        $_SESSION['role_name'] = $user->role?->role_name ?? '';
        $_SESSION['permissions'] = $user->role
            ? $user->role->permissions()->pluck('permission_slug')->all()
            : [];
    }

    private function themeLabel(string $slug): string
    {
        return [
            'original' => 'Original (Standar)',
            'pro' => 'Pro (Modern)',
            'aurora' => 'Aurora (Cerah)',
            'emerald' => 'Emerald (Hijau)',
            'slate' => 'Slate (Abu)',
            'nocturne' => 'Nocturne (Gelap)',
            'obsidian' => 'Obsidian (Hitam)',
        ][$slug] ?? ucwords(str_replace(['-', '_'], ' ', $slug));
    }
}
