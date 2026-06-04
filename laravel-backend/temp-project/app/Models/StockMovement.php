<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $table = 'warehouse_stock_movements';

    protected $fillable = [
        'batch_id', 'product_id', 'warehouse_location_id', 'movement_type',
        'quantity', 'quantity_before', 'quantity_after', 'unit_cost',
        'reference_type', 'reference_id', 'notes', 'created_by',
    ];
}
