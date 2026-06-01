<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryItem extends Model
{
    protected $table = 'delivery_items';
    protected $fillable = [
        'delivery_id', 'sales_order_id', 'order_item_id', 'product_id',
        'batch_id', 'quantity_loaded', 'quantity_delivered',
        'quantity_returned', 'unit_price', 'total_price',
        'return_reason', 'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity_loaded' => 'decimal:2',
            'quantity_delivered' => 'decimal:2',
            'quantity_returned' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
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
