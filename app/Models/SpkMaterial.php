<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpkMaterial extends Model
{
    protected $table = 'spk_materials';

    public $timestamps = false;

    protected $fillable = ['spk_id', 'item_id', 'qty_required', 'qty_allocated', 'notes'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
