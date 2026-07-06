<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Spk extends Model
{
    protected $table = 'spk';

    public const UPDATED_AT = null;

    protected $fillable = [
        'spk_number',
        'sales_order_id',
        'project_name',
        'spk_date',
        'deadline_date',
        'required_processes',
        'status',
        'notes',
        'drawing_link',
        'created_by',
        'approved_by_eng',
        'approved_at_eng',
        'approved_by_mgr',
        'approved_at_mgr',
        'approved_by_spv',
        'approved_at_spv',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'spk_date' => 'date',
            'deadline_date' => 'date',
            'approved_at_eng' => 'datetime',
            'approved_at_mgr' => 'datetime',
            'approved_at_spv' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function partlists(): HasMany
    {
        return $this->hasMany(SpkPartlist::class, 'spk_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(SpkMaterial::class, 'spk_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
