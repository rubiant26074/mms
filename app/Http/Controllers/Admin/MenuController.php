<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureSchema();
        $editUser = $request->integer('edit_user') ? User::query()->with('role')->find($request->integer('edit_user')) : null;
        $mode = DB::table('system_settings')->where('setting_key', 'menu_mode')->value('setting_value') ?: 'role';
        $access = $editUser
            ? DB::table('user_custom_menus')->where('user_id', $editUser->id)->pluck('menu_slug')->all()
            : [];
        if (in_array('eng-items', $access, true)) {
            $access[] = 'whse-items';
        }

        return view('admin.menu.index', [
            'mode' => $mode,
            'users' => User::query()->with('role')->orderBy('fullname')->get(),
            'editUser' => $editUser,
            'menuTree' => $this->menuTree(),
            'access' => $access,
        ]);
    }

    public function saveMode(Request $request): RedirectResponse
    {
        $this->ensureSchema();
        $mode = $request->validate(['menu_mode' => ['required', 'in:role,custom']])['menu_mode'];
        DB::table('system_settings')->updateOrInsert(['setting_key' => 'menu_mode'], ['setting_value' => $mode]);

        return back()->with('success', 'Mode Menu Berhasil Diubah!');
    }

    public function saveUserMenu(Request $request, User $user): RedirectResponse
    {
        $this->ensureSchema();
        $menus = array_map(
            fn (string $menu): string => $menu === 'eng-items' ? 'whse-items' : $menu,
            array_values(array_unique(array_filter((array) $request->input('menus', []))))
        );

        DB::transaction(function () use ($user, $menus): void {
            DB::table('user_custom_menus')->where('user_id', $user->id)->delete();
            foreach ($menus as $slug) {
                DB::table('user_custom_menus')->insertOrIgnore(['user_id' => $user->id, 'menu_slug' => $slug]);
            }
        });

        return redirect()->route('admin.menu.index', ['edit_user' => $user->id])->with('success', 'Akses menu tersimpan!');
    }

    private function ensureSchema(): void
    {
        DB::statement("CREATE TABLE IF NOT EXISTS system_settings (setting_key VARCHAR(50) NOT NULL PRIMARY KEY, setting_value VARCHAR(255) DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        DB::statement("CREATE TABLE IF NOT EXISTS user_custom_menus (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, menu_slug VARCHAR(50) NOT NULL, UNIQUE KEY uniq_user_menu (user_id, menu_slug), KEY idx_ucm_user (user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function menuTree(): array
    {
        return [
            ['type' => 'single', 'slug' => 'dashboard', 'label' => 'Dashboard'],
            ['type' => 'group', 'label' => 'Administrator', 'children' => [
                ['slug' => 'users', 'label' => 'Users'],
                ['slug' => 'roles', 'label' => 'Roles'],
                ['slug' => 'admin-company', 'label' => 'Company'],
                ['slug' => 'admin-machines', 'label' => 'Machines'],
                ['slug' => 'admin-menu', 'label' => 'Menu Custom'],
                ['slug' => 'admin-wa-logs', 'label' => 'WA Logs'],
            ]],
            ['type' => 'group', 'label' => 'Sales & Marketing', 'children' => [
                ['slug' => 'sales-customers', 'label' => 'Master Customer'],
                ['slug' => 'sales-quote', 'label' => 'Penawaran'],
                ['slug' => 'sales-so', 'label' => 'Sales Order'],
            ]],
            ['type' => 'group', 'label' => 'HRD', 'children' => [
                ['slug' => 'hrd-attendance', 'label' => 'Absensi'],
                ['slug' => 'hrd-payroll', 'label' => 'Payroll'],
                ['slug' => 'hrd-employees', 'label' => 'Karyawan'],
            ]],
            ['type' => 'group', 'label' => 'Engineering', 'children' => [
                ['slug' => 'eng-bom', 'label' => 'BOM'],
                ['slug' => 'eng-partlist', 'label' => 'Partlist & Drawing'],
            ]],
            ['type' => 'group', 'label' => 'PPIC', 'children' => [
                ['slug' => 'ppic-spk', 'label' => 'SPK Produksi'],
                ['slug' => 'ppic-mps', 'label' => 'Jadwal MPS'],
                ['slug' => 'ppic-pr', 'label' => 'Purchase Request'],
                ['slug' => 'ppic-inventory', 'label' => 'Inventory'],
            ]],
            ['type' => 'group', 'label' => 'Purchasing', 'children' => [
                ['slug' => 'purch-po', 'label' => 'Purchase Order'],
                ['slug' => 'purch-vendor', 'label' => 'Vendor List'],
            ]],
            ['type' => 'group', 'label' => 'Produksi', 'children' => [
                ['slug' => 'prod-task', 'label' => 'Task Assignment'],
                ['slug' => 'prod-scan', 'label' => 'Operator Scan'],
                ['slug' => 'prod-report', 'label' => 'Laporan Harian'],
            ]],
            ['type' => 'group', 'label' => 'Warehouse', 'children' => [
                ['slug' => 'whse-items', 'label' => 'Master Barang'],
                ['slug' => 'whse-receive', 'label' => 'Penerimaan Barang'],
                ['slug' => 'whse-issue', 'label' => 'Material Issue'],
                ['slug' => 'whse-sj', 'label' => 'Surat Jalan'],
                ['slug' => 'whse-return', 'label' => 'Material Return'],
                ['slug' => 'whse-expiry', 'label' => 'Batch Expiry'],
                ['slug' => 'whse-counting', 'label' => 'Cycle Counting'],
            ]],
            ['type' => 'group', 'label' => 'QC', 'children' => [
                ['slug' => 'qc-incoming', 'label' => 'QC Incoming'],
                ['slug' => 'qc-production', 'label' => 'QC Production'],
                ['slug' => 'qc-ncr', 'label' => 'NCR Form'],
            ]],
            ['type' => 'group', 'label' => 'Finance & Accounting', 'children' => [
                ['slug' => 'fin-ar', 'label' => 'AR / Invoice'],
                ['slug' => 'fin-ap', 'label' => 'AP / Payment'],
                ['slug' => 'fin-cash', 'label' => 'Cash'],
                ['slug' => 'acc-coa', 'label' => 'COA'],
                ['slug' => 'acc-journal', 'label' => 'Jurnal Umum'],
                ['slug' => 'acc-report', 'label' => 'Laporan Keuangan'],
            ]],
            ['type' => 'group', 'label' => 'TV Dashboard', 'children' => [
                ['slug' => 'tv-lobby', 'label' => 'TV Lobby'],
                ['slug' => 'tv-exec', 'label' => 'TV Executive'],
                ['slug' => 'tv-prod', 'label' => 'TV Production'],
            ]],
        ];
    }
}
