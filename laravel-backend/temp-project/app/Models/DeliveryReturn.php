<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryReturn extends Model
{
    protected $table = 'delivery_returns';
    protected $fillable = [
        'delivery_id', 'delivery_stop_id', 'product_id', 'batch_id',
        'quantity', 'return_reason', 'condition', 'notes', 'status',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function deliveryStop()
    {
        return $this->belongsTo(DeliveryStop::class);
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
