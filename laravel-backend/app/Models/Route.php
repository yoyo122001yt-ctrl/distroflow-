<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'warehouse_id', 'status',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stops()
    {
        return $this->hasMany(RouteStop::class)->orderBy('stop_order');
    }

    public function assignments()
    {
        return $this->hasMany(RouteAssignment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}

class RouteStop extends Model
{
    protected $table = 'route_stops';
    protected $fillable = [
        'route_id', 'retail_store_id', 'stop_order',
        'latitude', 'longitude', 'estimated_arrival', 'estimated_departure',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function retailStore()
    {
        return $this->belongsTo(RetailStore::class, 'retail_store_id');
    }
}

class RouteAssignment extends Model
{
    protected $table = 'route_assignments';
    protected $fillable = [
        'route_id', 'driver_id', 'truck_id', 'assignment_date', 'status',
    ];

    protected function casts(): array
    {
        return ['assignment_date' => 'date'];
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function truck()
    {
        return $this->belongsTo(Truck::class);
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }

    public function settlements()
    {
        return $this->hasMany(DriverSettlement::class);
    }
}
