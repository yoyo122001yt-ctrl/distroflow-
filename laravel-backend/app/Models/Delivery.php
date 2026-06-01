<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = [
        'delivery_number', 'route_assignment_id', 'driver_id', 'truck_id',
        'delivery_date', 'status', 'total_sales', 'total_collected',
        'total_returns', 'started_at', 'completed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_sales' => 'decimal:2',
            'total_collected' => 'decimal:2',
            'total_returns' => 'decimal:2',
        ];
    }

    public function routeAssignment()
    {
        return $this->belongsTo(RouteAssignment::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function truck()
    {
        return $this->belongsTo(Truck::class);
    }

    public function stops()
    {
        return $this->hasMany(DeliveryStop::class)->orderBy('stop_order');
    }

    public function items()
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function payments()
    {
        return $this->hasMany(DeliveryPayment::class);
    }

    public function returns()
    {
        return $this->hasMany(DeliveryReturn::class);
    }

    public function settlement()
    {
        return $this->hasOne(DriverSettlement::class);
    }
}

class DeliveryStop extends Model
{
    protected $table = 'delivery_stops';
    protected $fillable = [
        'delivery_id', 'route_stop_id', 'retail_store_id', 'stop_order',
        'status', 'arrived_at', 'departed_at', 'collected_amount',
        'payment_method', 'notes', 'signature',
    ];

    protected function casts(): array
    {
        return [
            'arrived_at' => 'datetime',
            'departed_at' => 'datetime',
            'collected_amount' => 'decimal:2',
        ];
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function routeStop()
    {
        return $this->belongsTo(RouteStop::class);
    }

    public function retailStore()
    {
        return $this->belongsTo(RetailStore::class, 'retail_store_id');
    }

    public function payments()
    {
        return $this->hasMany(DeliveryPayment::class);
    }
}

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
