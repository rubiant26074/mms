<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRfqQuote extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'rfq_id',
        'item_id',
        'item_name',
        'specification',
        'qty',
        'unit',
        'supplier_id',
        'unit_price',
        'lead_time_days',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_price' => 'decimal:2',
        ];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(PurchaseRfq::class, 'rfq_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
