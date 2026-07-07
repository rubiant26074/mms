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
        'deduction_total',
        'net_salary',
        'total_attendance',
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
            'deduction_total' => 'decimal:2',
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
