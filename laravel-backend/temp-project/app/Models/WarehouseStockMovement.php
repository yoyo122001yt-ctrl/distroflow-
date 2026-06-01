<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseStockMovement extends Model
{
    protected $fillable = [
        'batch_id', 'product_id', 'warehouse_location_id', 'movement_type',
        'quantity', 'quantity_before', 'quantity_after',
        'reference_type', 'reference_id', 'notes', 'created_by',
    ];
}
