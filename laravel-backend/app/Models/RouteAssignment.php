<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
