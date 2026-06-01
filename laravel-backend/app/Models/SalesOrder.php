<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    protected $fillable = [
        'order_number', 'retail_store_id', 'warehouse_id', 'created_by',
        'route_id', 'order_date', 'status', 'source',
        'subtotal', 'discount', 'tax', 'total', 'balance_due',
        'notes', 'approval_notes', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'approved_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'balance_due' => 'decimal:2',
        ];
    }

    public function retailStore()
    {
        return $this->belongsTo(RetailStore::class, 'retail_store_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}

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
