<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'code',
        'name',
        'address',
        'phone',
        'email',
        'contact_person',
        'bank_name',
        'bank_number',
    ];

    public function vendorRatings(): HasMany
    {
        return $this->hasMany(VendorRating::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(SupplierBill::class);
    }
}
