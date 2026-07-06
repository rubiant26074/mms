<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'gr_number',
        'receipt_type',
        'purchase_order_id',
        'customer_id',
        'gr_date',
        'delivery_note_number',
        'driver_name',
        'vehicle_number',
        'received_by',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'gr_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
