<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\PayrollSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollSettingController extends Controller
{
    public function edit(): View
    {
        // Get the first settings row or create it with defaults
        $setting = PayrollSetting::query()->firstOrCreate(['id' => 1], [
            'working_hour_mon_thu_start' => '08:00:00',
            'working_hour_mon_thu_end' => '17:00:00',
            'working_hour_fri_start' => '08:00:00',
            'working_hour_fri_end' => '17:30:00',
            
            'ot_workday_hour1_start' => '17:00:00',
            'ot_workday_hour1_end' => '18:00:00',
            'ot_workday_hour2_start' => '19:00:00',
            'ot_workday_hour2_end' => null,
            'ot_workday_rest_start' => '18:00:00',
            'ot_workday_rest_end' => '19:00:00',
            'ot_workday_rate' => 10000.00,
            
            'ot_holiday_shift1_start' => '08:00:00',
            'ot_holiday_shift1_end' => '16:00:00',
            'ot_holiday_shift1_rest_start' => '12:00:00',
            'ot_holiday_shift1_rest_end' => '13:00:00',
            
            'ot_holiday_shift2_start' => '16:00:00',
            'ot_holiday_shift2_end' => null,
            'ot_holiday_shift2_rest_start' => '18:00:00',
            'ot_holiday_shift2_rest_end' => '19:00:00',
            'ot_holiday_rate' => 10000.00,
        ]);

        return view('hrd.payroll-settings.form', compact('setting'));
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = PayrollSetting::query()->firstOrCreate(['id' => 1]);

        $data = $request->validate([
            'working_hour_mon_thu_start' => ['required', 'date_format:H:i'],
            'working_hour_mon_thu_end' => ['required', 'date_format:H:i'],
            'working_hour_fri_start' => ['required', 'date_format:H:i'],
            'working_hour_fri_end' => ['required', 'date_format:H:i'],
            
            'ot_workday_hour1_start' => ['required', 'date_format:H:i'],
            'ot_workday_hour1_end' => ['required', 'date_format:H:i'],
            'ot_workday_hour2_start' => ['required', 'date_format:H:i'],
            'ot_workday_hour2_end' => ['nullable', 'date_format:H:i'],
            'ot_workday_rest_start' => ['required', 'date_format:H:i'],
            'ot_workday_rest_end' => ['required', 'date_format:H:i'],
            'ot_workday_rate' => ['required', 'string'],
            
            'ot_holiday_shift1_start' => ['required', 'date_format:H:i'],
            'ot_holiday_shift1_end' => ['required', 'date_format:H:i'],
            'ot_holiday_shift1_rest_start' => ['required', 'date_format:H:i'],
            'ot_holiday_shift1_rest_end' => ['required', 'date_format:H:i'],
            
            'ot_holiday_shift2_start' => ['required', 'date_format:H:i'],
            'ot_holiday_shift2_end' => ['nullable', 'date_format:H:i'],
            'ot_holiday_shift2_rest_start' => ['required', 'date_format:H:i'],
            'ot_holiday_shift2_rest_end' => ['required', 'date_format:H:i'],
            'ot_holiday_rate' => ['required', 'string'],
        ]);

        // Format rates (remove thousand separator)
        $data['ot_workday_rate'] = (float) str_replace(['.', ','], '', $data['ot_workday_rate']);
        $data['ot_holiday_rate'] = (float) str_replace(['.', ','], '', $data['ot_holiday_rate']);

        $setting->update($data);

        return redirect()->route('hrd.payroll_settings.edit')->with('success', 'Pengaturan Payroll berhasil disimpan!');
    }
}
