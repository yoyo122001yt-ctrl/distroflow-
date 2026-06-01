<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PickList extends Model
{
    protected $fillable = [
        'pick_list_number', 'warehouse_id', 'route_id', 'status',
        'picked_by', 'picked_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'picked_at' => 'datetime',
        ];
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function pickedBy()
    {
        return $this->belongsTo(User::class, 'picked_by');
    }

    public function items()
    {
        return $this->hasMany(PickListItem::class);
    }
}
