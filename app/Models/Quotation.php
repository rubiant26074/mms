<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    protected $fillable = [
        'revision_of',
        'quote_number',
        'revision_version',
        'customer_id',
        'quote_date',
        'payment_terms',
        'validity',
        'ppn_percent',
        'tax_included',
        'subtotal',
        'discount_amount',
        'discount_type',
        'discount_value',
        'tax_amount',
        'status',
        'approved_by',
        'sent_to_client_at',
        'sent_to_client_by',
        'grand_total',
        'notes',
        'attachment',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quote_date' => 'date',
            'sent_to_client_at' => 'datetime',
            'tax_included' => 'boolean',
            'ppn_percent' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'discount_type' => 'string',
            'discount_value' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
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
