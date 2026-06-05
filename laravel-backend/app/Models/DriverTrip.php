<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverTrip extends Model
{
    protected $table = 'driver_trips';

    protected $fillable = [
        'driver_id', 'delivery_id', 'started_at', 'ended_at',
        'status', 'total_stops', 'completed_stops',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'total_stops' => 'integer',
            'completed_stops' => 'integer',
        ];
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function locations()
    {
        return $this->hasMany(DriverLocation::class, 'trip_id');
    }

    public function lastLocation()
    {
        return $this->hasOne(DriverLocation::class, 'trip_id')->latest('recorded_at');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
