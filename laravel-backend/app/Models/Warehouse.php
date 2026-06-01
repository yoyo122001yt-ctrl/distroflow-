<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = [
        'name', 'code', 'address', 'city', 'state', 'zip', 'phone', 'email',
        'latitude', 'longitude', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function zones()
    {
        return $this->hasMany(WarehouseZone::class);
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function routes()
    {
        return $this->hasMany(Route::class);
    }
}

class WarehouseZone extends Model
{
    protected $fillable = ['warehouse_id', 'name', 'code', 'type', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function locations()
    {
        return $this->hasMany(WarehouseLocation::class);
    }
}

class WarehouseLocation extends Model
{
    protected $fillable = ['warehouse_zone_id', 'rack', 'shelf', 'bin', 'barcode', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function zone()
    {
        return $this->belongsTo(WarehouseZone::class, 'warehouse_zone_id');
    }
}
