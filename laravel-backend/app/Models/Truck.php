<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Truck extends Model
{
    protected $fillable = [
        'code', 'plate_number', 'model', 'year',
        'capacity_weight', 'capacity_volume', 'status', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity_weight' => 'decimal:2',
            'capacity_volume' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function inventory()
    {
        return $this->hasMany(TruckInventory::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }
}

