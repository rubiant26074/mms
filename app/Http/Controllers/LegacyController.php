<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MmsContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class LegacyController extends Controller
{
    public function handle(Request $request, MmsContext $context, ?string $path = null): Response
    {
        if (($redirect = $this->redirectMigratedPage($request, $path)) !== null) {
            return $redirect;
        }

        $legacyRoot = base_path('legacy');
        $target = $this->resolveTarget($legacyRoot, $path);

        if ($target === null) {
            abort(404);
        }

        $_SERVER['REQUEST_METHOD'] = $request->method();
        $_SERVER['REQUEST_URI'] = $request->getRequestUri();
        $_SERVER['SCRIPT_NAME'] = '/' . ($path ?: 'index.php');
        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
        $_GET = $request->query->all();
        $_POST = $request->request->all();
        $_REQUEST = array_merge($_GET, $_POST);
        $GLOBALS['pdo'] = DB::connection()->getPdo();

        if (Auth::check() && Auth::user() instanceof User) {
            $context->syncLegacySession(Auth::user()->loadMissing('role.permissions'));
        }

        $previousDirectory = getcwd();
        chdir(dirname($target));

        ob_start();
        try {
            require $target;
            $content = ob_get_clean();
        } finally {
            chdir($previousDirectory ?: base_path());
        }

        return response($content);
    }

    private function redirectMigratedPage(Request $request, ?string $path): ?Response
    {
        $normalizedPath = trim((string) $path, '/');
        if ($normalizedPath !== 'index.php' && $normalizedPath !== '') {
            return null;
        }

        $page = (string) $request->query('page', '');
        $action = (string) $request->query('action', 'index');
        $id = $request->query('id');
        $roleId = $request->query('role_id');

        $route = match ($page) {
            'dashboard' => route('dashboard'),
            'users' => match ($action) {
                'create' => route('admin.users.create'),
                'edit' => $id ? route('admin.users.edit', $id) : route('admin.users.index'),
                default => route('admin.users.index'),
            },
            'roles' => match ($action) {
                'create' => route('admin.roles.create'),
                'edit' => $id ? route('admin.roles.edit', $id) : route('admin.roles.index'),
                default => route('admin.roles.index'),
            },
            'user-settings' => route('user_settings.edit'),
            'role-permissions' => $roleId ? route('admin.roles.permissions', $roleId) : route('admin.roles.permissions'),
            'admin-company' => route('admin.company.edit'),
            'admin-backup' => route('admin.backup.index'),
            'admin-reset' => route('admin.reset.index'),
            'admin-setup-wizard' => route('admin.setup.index'),
            'admin-wa-logs' => route('admin.wa_logs.index'),
            'admin-menu' => route('admin.menu.index', $request->only(['edit_user'])),
            'admin-machines' => match ($action) {
                'create' => route('admin.machines.create'),
                'edit' => $id ? route('admin.machines.edit', $id) : route('admin.machines.index'),
                default => route('admin.machines.index', $request->only(['status', 'search'])),
            },
            'sales-customers' => match ($action) {
                'create' => route('sales.customers.create'),
                'edit' => $id ? route('sales.customers.edit', $id) : route('sales.customers.index'),
                default => route('sales.customers.index', $request->only(['search'])),
            },
            'sales-quote' => match ($action) {
                'create' => route('sales.quotations.create'),
                'edit' => $id ? route('sales.quotations.edit', $id) : route('sales.quotations.index'),
                'print' => $id ? route('sales.quotations.print', $id) : route('sales.quotations.index'),
                default => route('sales.quotations.index', $request->only(['status', 'search'])),
            },
            'sales-so' => match ($action) {
                'create' => route('sales.orders.create', $request->only(['quote_id'])),
                'edit' => $id ? route('sales.orders.edit', $id) : route('sales.orders.index'),
                'print' => $id ? route('sales.orders.print', $id) : route('sales.orders.index'),
                default => route('sales.orders.index', $request->only(['status', 'search'])),
            },
            'eng-items' => match ($action) {
                'create' => route('engineering.items.create'),
                'edit' => $id ? route('engineering.items.edit', $id) : route('engineering.items.index'),
                default => route('engineering.items.index', $request->only(['filter_type', 'search'])),
            },
            'eng-machines' => match ($action) {
                'create' => route('admin.machines.create'),
                'edit' => $id ? route('admin.machines.edit', $id) : route('admin.machines.index'),
                default => route('admin.machines.index', $request->only(['status', 'search'])),
            },
            'eng-bom' => match ($action) {
                'create' => route('engineering.boms.create'),
                'edit' => $id ? route('engineering.boms.edit', $id) : route('engineering.boms.index'),
                default => route('engineering.boms.index', $request->only(['search'])),
            },
            'eng-partlist' => match ($action) {
                'create', 'edit' => route('engineering.partlists.create', $request->only(['spk_id'])),
                'print' => $id ? route('engineering.partlists.print', $id) : route('engineering.partlists.index'),
                default => route('engineering.partlists.index', $request->only(['view', 'status', 'search'])),
            },
            'ppic-spk' => match ($action) {
                'create' => route('ppic.spk.create', $request->only(['so_id'])),
                'edit' => $id ? route('ppic.spk.edit', $id) : route('ppic.spk.index'),
                'print' => $id ? route('ppic.spk.print', $id) : route('ppic.spk.index'),
                default => route('ppic.spk.index', $request->only(['status', 'search'])),
            },
            'ppic-pr' => match ($action) {
                'create' => route('ppic.purchase_requests.create', $request->only(['spk_id'])),
                'edit' => $id ? route('ppic.purchase_requests.edit', $id) : route('ppic.purchase_requests.index'),
                'print' => $id ? route('ppic.purchase_requests.print', $id) : route('ppic.purchase_requests.index'),
                default => route('ppic.purchase_requests.index', $request->only(['status', 'search'])),
            },
            'ppic-mps' => route('ppic.mps.index', $request->only(['month', 'year'])),
            'ppic-inventory' => match ($action) {
                'view' => $id ? route('ppic.inventory.show', $id) : route('ppic.inventory.index'),
                default => route('ppic.inventory.index', $request->only(['type', 'search'])),
            },
            'purch-vendor' => match ($action) {
                'create' => route('procurement.suppliers.create'),
                'edit' => $id ? route('procurement.suppliers.edit', $id) : route('procurement.suppliers.index'),
                default => route('procurement.suppliers.index', $request->only(['search'])),
            },
            'purch-po' => match ($action) {
                'create' => route('procurement.orders.create', $request->only(['pr_id'])),
                'edit' => $id ? route('procurement.orders.edit', $id) : route('procurement.orders.index'),
                'print' => $id ? route('procurement.orders.print', $id) : route('procurement.orders.index'),
                default => route('procurement.orders.index', $request->only(['status', 'search'])),
            },
            'whse-receive' => match ($action) {
                'create' => route('warehouse.receipts.create', $request->only(['po_id'])),
                'edit' => $id ? route('warehouse.receipts.edit', $id) : route('warehouse.receipts.index'),
                default => route('warehouse.receipts.index', $request->only(['status', 'search'])),
            },
            'qc-incoming' => match ($action) {
                'inspect' => route('qc.incoming.inspect', $request->only(['gr_id'])),
                'print' => $id ? route('qc.incoming.print', $id) : route('qc.incoming.index'),
                default => route('qc.incoming.index'),
            },
            'exec-kpi' => route('executive.kpi.index'),
            'exec-logs' => route('executive.logs.index', $request->only(['module', 'search'])),
            'fin-tax' => $action === 'print'
                ? route('finance.tax.print', $request->only(['month', 'year']))
                : route('finance.tax.index', $request->only(['month', 'year'])),
            'acc-coa' => match ($action) {
                'create' => route('accounting.coa.create'),
                'edit' => $id ? route('accounting.coa.edit', $id) : route('accounting.coa.index'),
                default => route('accounting.coa.index', $request->only(['type', 'search'])),
            },
            'acc-journal' => match ($action) {
                'create' => route('accounting.journal.create'),
                'print' => route('accounting.journal.print', $request->only(['search', 'start_date', 'end_date'])),
                default => route('accounting.journal.index', $request->only(['search', 'start_date', 'end_date'])),
            },
            'acc-ledger' => $action === 'print'
                ? route('accounting.ledger.print', $request->only(['start_date', 'end_date', 'coa_id']))
                : route('accounting.ledger.index', $request->only(['start_date', 'end_date', 'coa_id'])),
            default => null,
        };

        return $route ? redirect($route) : null;
    }

    private function resolveTarget(string $legacyRoot, ?string $path): ?string
    {
        $path = trim((string) $path, '/');

        if ($path === '' || $path === 'index.php') {
            $path = 'index.php';
        }

        if ($path === 'logout.php') {
            $path = 'logout.php';
        }

        if (! str_ends_with($path, '.php')) {
            return null;
        }

        $candidate = realpath($legacyRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        $root = realpath($legacyRoot);

        if ($candidate === false || $root === false) {
            return null;
        }

        if (! str_starts_with($candidate, $root . DIRECTORY_SEPARATOR) && $candidate !== $root . DIRECTORY_SEPARATOR . 'index.php') {
            return null;
        }

        return is_file($candidate) ? $candidate : null;
    }
}
