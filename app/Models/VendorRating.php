<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorRating extends Model
{
    protected $fillable = [
        'supplier_id',
        'rating_period',
        'lead_time_score',
        'quality_score',
        'price_score',
        'total_score',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'lead_time_score' => 'decimal:2',
            'quality_score' => 'decimal:2',
            'price_score' => 'decimal:2',
            'total_score' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
