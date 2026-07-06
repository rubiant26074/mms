<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends Model
{
    protected $fillable = [
        'pr_number',
        'pr_date',
        'required_date',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'current_approval_level',
        'last_approval_date',
    ];

    protected function casts(): array
    {
        return [
            'pr_date' => 'date',
            'required_date' => 'date',
            'approved_at' => 'datetime',
            'last_approval_date' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
