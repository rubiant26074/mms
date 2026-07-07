<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierBill extends Model
{
    protected $fillable = [
        'bill_number',
        'supplier_inv_number',
        'purchase_order_id',
        'supplier_id',
        'bill_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'grand_total',
        'paid_amount',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'float',
            'tax_amount' => 'float',
            'discount_amount' => 'float',
            'grand_total' => 'float',
            'paid_amount' => 'float',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierBillItem::class, 'bill_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class, 'bill_id');
    }
}
