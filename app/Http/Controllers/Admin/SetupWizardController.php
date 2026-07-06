<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SetupWizardController extends Controller
{
    public function index(Request $request): View
    {
        DB::statement("CREATE TABLE IF NOT EXISTS system_settings (setting_key VARCHAR(50) NOT NULL PRIMARY KEY, setting_value VARCHAR(255) DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $step = max(1, min(3, $request->integer('step', 1)));

        return view('admin.setup.index', [
            'step' => $step,
            'companyData' => CompanyProfile::query()->find(1) ?? new CompanyProfile(['id' => 1]),
            'settings' => DB::table('system_settings')->pluck('setting_value', 'setting_key'),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        DB::statement("CREATE TABLE IF NOT EXISTS system_settings (setting_key VARCHAR(50) NOT NULL PRIMARY KEY, setting_value VARCHAR(255) DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $step = (int) $request->input('current_step', 1);

        if ($step === 1) {
            $data = $request->validate([
                'company_name' => ['required', 'string', 'max:100'],
                'address' => ['nullable', 'string'],
                'phone' => ['nullable', 'string', 'max:50'],
                'email' => ['nullable', 'email', 'max:100'],
                'website' => ['nullable', 'string', 'max:100'],
                'npwp' => ['nullable', 'string', 'max:25'],
                'pkp_date' => ['nullable', 'date'],
            ]);
            CompanyProfile::query()->updateOrCreate(['id' => 1], $data);
            $this->putSetting('setup_wizard_step1_done_at', now()->toDateTimeString());

            return redirect()->route('admin.setup.index', ['step' => 2])->with('success', 'Company Profile tersimpan.');
        }

        if ($step === 2) {
            foreach (['fiscal_year_start_month', 'base_currency', 'lock_backdate_days', 'opening_date', 'opening_capital_amount'] as $key) {
                $this->putSetting($key, (string) $request->input($key, ''));
            }
            $this->putSetting('setup_wizard_step2_done_at', now()->toDateTimeString());

            return redirect()->route('admin.setup.index', ['step' => 3])->with('success', 'Accounting & Fiscal Setup tersimpan.');
        }

        foreach (['tax_ppn_rate', 'tax_pph23_rate', 'tax_pph21_rate', 'tax_pph_final_rate', 'tax_invoice_prefix'] as $key) {
            $this->putSetting($key, (string) $request->input($key, ''));
        }
        $this->putSetting('setup_wizard_completed_at', now()->toDateTimeString());
        $this->putSetting('setup_wizard_completed_by', (string) auth()->id());

        return redirect()->route('admin.setup.index', ['step' => 3])->with('success', 'Tax Configuration tersimpan. Wizard setup selesai.');
    }

    private function putSetting(string $key, string $value): void
    {
        DB::table('system_settings')->updateOrInsert(['setting_key' => $key], ['setting_value' => $value]);
    }
}
