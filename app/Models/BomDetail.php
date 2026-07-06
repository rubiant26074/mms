<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomDetail extends Model
{
    protected $table = 'bom_details';

    public $timestamps = false;

    protected $fillable = ['bom_id', 'material_id', 'qty', 'unit', 'waste_percent', 'notes'];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'material_id');
    }
}
