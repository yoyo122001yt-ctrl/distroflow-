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
