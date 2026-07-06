<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bom extends Model
{
    protected $table = 'boms';

    protected $fillable = ['bom_code', 'item_id', 'qty_result', 'notes', 'description', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['qty_result' => 'decimal:2'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(BomDetail::class, 'bom_id');
    }
}
