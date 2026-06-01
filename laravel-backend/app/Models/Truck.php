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

class TruckInventory extends Model
{
    protected $table = 'truck_inventory';
    protected $fillable = ['truck_id', 'product_id', 'batch_id', 'quantity', 'starting_quantity'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'starting_quantity' => 'decimal:2',
        ];
    }

    public function truck()
    {
        return $this->belongsTo(Truck::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}
