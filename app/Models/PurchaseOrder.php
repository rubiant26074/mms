<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'po_number',
        'purchase_request_id',
        'supplier_id',
        'po_date',
        'delivery_date',
        'subtotal',
        'payment_terms',
        'ppn_percent',
        'discount_amount',
        'tax_amount',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'grand_total',
        'finance_approved_by',
        'finance_approved_at',
    ];

    protected function casts(): array
    {
        return [
            'po_date' => 'date',
            'delivery_date' => 'date',
            'approved_at' => 'datetime',
            'finance_approved_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function supplierBills(): HasMany
    {
        return $this->hasMany(SupplierBill::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function financeApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_approved_by');
    }
}
