<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialReturnItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'return_id',
        'type',
        'item_id',
        'item_name_manual',
        'qty',
        'unit',
        'condition_notes',
    ];

    public function materialReturn(): BelongsTo
    {
        return $this->belongsTo(MaterialReturn::class, 'return_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
