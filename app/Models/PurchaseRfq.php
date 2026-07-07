<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRfq extends Model
{
    protected $fillable = [
        'rfq_number',
        'rfq_date',
        'due_date',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rfq_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(PurchaseRfqQuote::class, 'rfq_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
