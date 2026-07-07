<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = [
        'customer_id',
        'item_code',
        'item_name',
        'item_type',
        'qc_type',
        'ownership',
        'unit',
        'base_price',
        'current_stock',
        'min_stock',
        'description',
        'drawing_file',
    ];

    public function warehouseBatches(): HasMany
    {
        return $this->hasMany(WarehouseBatch::class);
    }

    public function cycleCountSessionItems(): HasMany
    {
        return $this->hasMany(CycleCountSessionItem::class);
    }
}
