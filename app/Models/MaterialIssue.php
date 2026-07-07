<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialIssue extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'itr_number',
        'spk_id',
        'itr_date',
        'issued_by',
        'received_by',
        'notes',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'itr_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function spk(): BelongsTo
    {
        return $this->belongsTo(Spk::class, 'spk_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialIssueItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
