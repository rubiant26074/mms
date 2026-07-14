<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollSetting extends Model
{
    protected $table = 'payroll_settings';

    protected $fillable = [
        'working_hour_mon_thu_start',
        'working_hour_mon_thu_end',
        'working_hour_fri_start',
        'working_hour_fri_end',
        
        'ot_workday_hour1_start',
        'ot_workday_hour1_end',
        'ot_workday_hour2_start',
        'ot_workday_hour2_end',
        'ot_workday_rest_start',
        'ot_workday_rest_end',
        'ot_workday_rate',
        
        'ot_holiday_shift1_start',
        'ot_holiday_shift1_end',
        'ot_holiday_shift1_rest_start',
        'ot_holiday_shift1_rest_end',
        
        'ot_holiday_shift2_start',
        'ot_holiday_shift2_end',
        'ot_holiday_shift2_rest_start',
        'ot_holiday_shift2_rest_end',
        'ot_holiday_rate',
    ];

    protected function casts(): array
    {
        return [
            'ot_workday_rate' => 'decimal:2',
            'ot_holiday_rate' => 'decimal:2',
        ];
    }
}
