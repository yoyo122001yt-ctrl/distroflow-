<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseZone extends Model
{
    protected $table = 'warehouse_zones';
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
