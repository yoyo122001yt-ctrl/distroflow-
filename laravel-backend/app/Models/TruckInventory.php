<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TruckInventory extends Model
{
    protected $table = 'truck_inventory';
    protected $fillable = ['truck_id', 'product_id', 'batch_id', 'quantity', 'starting_quantity'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'starting_quantity' => 'decimal:2',
        ];
    }

    public function truck()
    {
        return $this->belongsTo(Truck::class);
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
