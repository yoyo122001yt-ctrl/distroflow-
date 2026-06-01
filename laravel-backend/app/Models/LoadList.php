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

class LoadListItem extends Model
{
    protected $table = 'load_list_items';
    protected $fillable = [
        'load_list_id', 'pick_list_item_id', 'product_id', 'batch_id',
        'quantity', 'status',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    public function loadList()
    {
        return $this->belongsTo(LoadList::class);
    }

    public function pickListItem()
    {
        return $this->belongsTo(PickListItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}
