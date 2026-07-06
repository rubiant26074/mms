<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ResetController extends Controller
{
    public function index(): View
    {
        return view('admin.reset.index');
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'admin_password' => ['required', 'string'],
            'confirm_text' => ['required', 'in:RESET'],
        ]);

        if (! Hash::check($request->input('admin_password'), auth()->user()->password)) {
            return back()->withErrors('Password Admin salah. Tindakan dibatalkan.');
        }

        $tables = [
            'quotations', 'quotation_items', 'sales_orders', 'sales_order_items', 'spk', 'spk_materials',
            'purchase_requests', 'purchase_request_items', 'production_assignments', 'production_logs',
            'purchase_orders', 'purchase_order_items', 'goods_receipts', 'goods_receipt_items',
            'material_issues', 'material_issue_items', 'delivery_notes', 'delivery_note_items',
            'qc_incoming', 'qc_incoming_items', 'qc_production', 'ncr', 'invoices', 'invoice_payments',
            'supplier_bills', 'supplier_payments', 'journals', 'journal_items', 'attendance', 'payrolls',
            'system_logs',
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            if (DB::selectOne("SHOW TABLES LIKE '{$table}'")) {
                DB::statement("TRUNCATE TABLE `{$table}`");
            }
        }
        if ($request->boolean('reset_items')) {
            if (DB::selectOne("SHOW TABLES LIKE 'items'")) DB::table('items')->update(['current_stock' => 0]);
            if (DB::selectOne("SHOW TABLES LIKE 'coa'")) DB::statement('UPDATE coa SET current_balance = opening_balance');
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        return back()->with('success', 'Database transaksi berhasil di-reset bersih.');
    }
}
