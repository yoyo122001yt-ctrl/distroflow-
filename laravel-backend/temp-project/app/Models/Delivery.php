<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = [
        'delivery_number', 'route_assignment_id', 'driver_id', 'truck_id',
        'delivery_date', 'status', 'total_sales', 'total_collected',
        'total_returns', 'started_at', 'completed_at', 'notes', 'tracking_token',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_sales' => 'decimal:2',
            'total_collected' => 'decimal:2',
            'total_returns' => 'decimal:2',
        ];
    }

    public function routeAssignment()
    {
        return $this->belongsTo(RouteAssignment::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function truck()
    {
        return $this->belongsTo(Truck::class);
    }

    public function stops()
    {
        return $this->hasMany(DeliveryStop::class)->orderBy('stop_order');
    }

    public function items()
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function payments()
    {
        return $this->hasMany(DeliveryPayment::class);
    }

    public function returns()
    {
        return $this->hasMany(DeliveryReturn::class);
    }

    public function settlement()
    {
        return $this->hasOne(DriverSettlement::class);
    }

    public function trip()
    {
        return $this->hasOne(DriverTrip::class);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('delivery_date', today());
    }

    public function scopeForDriver($query, $driverId)
    {
        return $query->where('driver_id', $driverId);
    }
}
