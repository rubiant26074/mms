<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    protected $fillable = [
        'so_number',
        'quotation_id',
        'customer_id',
        'so_date',
        'cust_po_number',
        'cust_po_date',
        'delivery_date',
        'subtotal',
        'payment_terms',
        'fulfillment_source',
        'status',
        'grand_total',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'sent_to_client_at',
        'sent_to_client_by',
        'ppn_percent',
        'tax_included',
        'discount_amount',
        'tax_amount',
    ];

    protected function casts(): array
    {
        return [
            'so_date' => 'date',
            'cust_po_date' => 'date',
            'delivery_date' => 'date',
            'approved_at' => 'datetime',
            'sent_to_client_at' => 'datetime',
            'tax_included' => 'boolean',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
