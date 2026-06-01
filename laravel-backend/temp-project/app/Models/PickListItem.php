<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PickListItem extends Model
{
    protected $table = 'pick_list_items';
    protected $fillable = [
        'pick_list_id', 'sales_order_id', 'order_item_id', 'product_id',
        'batch_id', 'warehouse_location_id', 'quantity', 'status',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    public function pickList()
    {
        return $this->belongsTo(PickList::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function location()
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }
}
