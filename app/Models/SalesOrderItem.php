<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sales_order_id',
        'item_id',
        'item_code_manual',
        'item_name_manual',
        'material_manual',
        'unit_manual',
        'qty',
        'unit_price',
        'subtotal',
        'notes',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
