<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreLicense extends Model
{
    protected $table = 'store_licenses';
    protected $fillable = [
        'retail_store_id', 'license_type', 'license_number',
        'issued_date', 'expiry_date', 'issuing_authority', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function store()
    {
        return $this->belongsTo(RetailStore::class, 'retail_store_id');
    }
}
