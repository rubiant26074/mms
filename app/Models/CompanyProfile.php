<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    protected $table = 'company_profile';

    public $timestamps = false;

    protected $fillable = [
        'company_name',
        'address',
        'phone',
        'email',
        'npwp',
        'pkp_date',
        'website',
        'fonte_token',
        'ui_theme',
        'attendance_location_name',
        'attendance_latitude',
        'attendance_longitude',
        'attendance_radius_meters',
        'running_text',
        'logo_path',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'pkp_date' => 'date',
            'updated_at' => 'datetime',
            'attendance_latitude' => 'decimal:7',
            'attendance_longitude' => 'decimal:7',
            'attendance_radius_meters' => 'integer',
        ];
    }
}
