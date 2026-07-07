<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialReturn extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'ret_number',
        'spk_id',
        'ret_date',
        'returned_by',
        'received_by',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'ret_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function spk(): BelongsTo
    {
        return $this->belongsTo(Spk::class, 'spk_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialReturnItem::class, 'return_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
