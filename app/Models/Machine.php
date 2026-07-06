<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Machine extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'machine_code',
        'machine_name',
        'process_type',
        'status',
        'location',
        'capacity_per_hour',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'capacity_per_hour' => 'decimal:2',
        ];
    }
}
