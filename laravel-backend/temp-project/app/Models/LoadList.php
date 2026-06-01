<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoadList extends Model
{
    protected $fillable = [
        'load_list_number', 'route_id', 'truck_id', 'warehouse_id',
        'status', 'loaded_by', 'loaded_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['loaded_at' => 'datetime'];
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function truck()
    {
        return $this->belongsTo(Truck::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function loadedBy()
    {
        return $this->belongsTo(User::class, 'loaded_by');
    }

    public function items()
    {
        return $this->hasMany(LoadListItem::class);
    }
}
