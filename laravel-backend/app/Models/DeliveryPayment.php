<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryPayment extends Model
{
    protected $table = 'delivery_payments';
    protected $fillable = [
        'delivery_stop_id', 'delivery_id', 'sales_order_id', 'amount',
        'payment_method', 'reference_number', 'check_number', 'check_bank',
        'status', 'notes',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function deliveryStop()
    {
        return $this->belongsTo(DeliveryStop::class);
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }
}
