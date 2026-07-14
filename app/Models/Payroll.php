<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    protected $table = 'payrolls';

    public const UPDATED_AT = null;

    protected $fillable = [
        'payroll_code',
        'period_start',
        'period_end',
        'user_id',
        'basic_salary',
        'allowance_total',
        'allowance_details',
        'deduction_total',
        'deduction_details',
        'total_attendance',
        'attendance_mode',
        'net_salary',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'basic_salary' => 'decimal:2',
            'allowance_total' => 'decimal:2',
            'allowance_details' => 'array',
            'deduction_total' => 'decimal:2',
            'deduction_details' => 'array',
            'net_salary' => 'decimal:2',
            'total_attendance' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
