<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    protected $fillable = [
        'product_id', 'warehouse_id', 'batch_number', 'manufacturing_date',
        'expiry_date', 'quantity', 'available_quantity', 'cost_price',
        'supplier_id', 'received_date', 'purchase_order_id', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'manufacturing_date' => 'date',
            'expiry_date' => 'date',
            'received_date' => 'date',
            'quantity' => 'decimal:2',
            'available_quantity' => 'decimal:2',
            'cost_price' => 'decimal:2',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available')->where('available_quantity', '>', 0);
    }

    public function scopeExpiringBefore($query, $date)
    {
        return $query->where('expiry_date', '<=', $date)->where('available_quantity', '>', 0);
    }

    public function scopeFefo($query, $productId, $quantityNeeded)
    {
        return $query->where('product_id', $productId)
            ->where('status', 'available')
            ->where('available_quantity', '>', 0)
            ->orderBy('expiry_date', 'asc');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date->isPast();
    }

    public function daysUntilExpiry(): int
    {
        return now()->diffInDays($this->expiry_date, false);
    }
}
