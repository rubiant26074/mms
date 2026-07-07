<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChartOfAccount extends Model
{
    protected $table = 'coa';

    public const UPDATED_AT = null;

    protected $fillable = [
        'account_code',
        'account_name',
        'account_type',
        'normal_balance',
        'opening_balance',
        'current_balance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
