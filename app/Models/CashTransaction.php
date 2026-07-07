<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransaction extends Model
{
    protected $table = 'finance_cash_expenses';

    protected $fillable = [
        'expense_number',
        'transaction_type',
        'coa_id',
        'cash_coa_id',
        'expense_date',
        'category',
        'description',
        'amount',
        'payment_method',
        'reference_no',
        'vendor_name',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function counterCoa(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id');
    }

    public function cashCoa(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'cash_coa_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
