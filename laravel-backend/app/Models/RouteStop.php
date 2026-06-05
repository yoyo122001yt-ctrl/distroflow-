<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
