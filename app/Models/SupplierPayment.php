<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPayment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['bill_id', 'payment_date', 'amount', 'method', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'amount' => 'float', 'created_at' => 'datetime'];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(SupplierBill::class, 'bill_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
