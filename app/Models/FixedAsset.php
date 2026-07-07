<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    protected $table = 'fixed_assets';

    public const UPDATED_AT = null;

    protected $fillable = [
        'asset_code',
        'asset_name',
        'category',
        'acquisition_date',
        'acquisition_cost',
        'salvage_value',
        'useful_life_years',
        'monthly_depreciation',
        'accumulated_depreciation',
        'book_value',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'salvage_value' => 'decimal:2',
            'monthly_depreciation' => 'decimal:2',
            'accumulated_depreciation' => 'decimal:2',
            'book_value' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }
}
