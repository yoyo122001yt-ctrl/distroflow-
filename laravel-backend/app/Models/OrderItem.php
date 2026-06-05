<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $table = 'order_items';
    protected $fillable = [
        'sales_order_id', 'product_id', 'quantity_ordered',
        'quantity_picked', 'quantity_loaded', 'quantity_delivered',
        'quantity_returned', 'unit_price', 'total_price', 'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:2',
            'quantity_picked' => 'decimal:2',
            'quantity_loaded' => 'decimal:2',
            'quantity_delivered' => 'decimal:2',
            'quantity_returned' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
