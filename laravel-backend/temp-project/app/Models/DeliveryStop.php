<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryStop extends Model
{
    protected $table = 'delivery_stops';
    protected $fillable = [
        'delivery_id', 'route_stop_id', 'retail_store_id', 'stop_order',
        'status', 'arrived_at', 'departed_at', 'collected_amount',
        'payment_method', 'notes', 'signature',
    ];

    protected function casts(): array
    {
        return [
            'arrived_at' => 'datetime',
            'departed_at' => 'datetime',
            'collected_amount' => 'decimal:2',
        ];
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function routeStop()
    {
        return $this->belongsTo(RouteStop::class);
    }

    public function retailStore()
    {
        return $this->belongsTo(RetailStore::class, 'retail_store_id');
    }

    public function payments()
    {
        return $this->hasMany(DeliveryPayment::class);
    }
}
