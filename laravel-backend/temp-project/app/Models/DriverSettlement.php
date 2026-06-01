<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverSettlement extends Model
{
    protected $table = 'driver_settlements';
    protected $fillable = [
        'driver_id', 'route_assignment_id', 'delivery_id', 'settlement_date',
        'status', 'total_sales', 'total_returns', 'expected_cash', 'actual_cash',
        'cash_variance', 'starting_inventory_value', 'loaded_value',
        'sales_value', 'returns_value', 'expected_end_inventory_value',
        'actual_end_inventory_value', 'inventory_variance',
        'notes', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'settlement_date' => 'date',
            'approved_at' => 'datetime',
            'total_sales' => 'decimal:2',
            'total_returns' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'actual_cash' => 'decimal:2',
            'cash_variance' => 'decimal:2',
            'starting_inventory_value' => 'decimal:2',
            'loaded_value' => 'decimal:2',
            'sales_value' => 'decimal:2',
            'returns_value' => 'decimal:2',
            'expected_end_inventory_value' => 'decimal:2',
            'actual_end_inventory_value' => 'decimal:2',
            'inventory_variance' => 'decimal:2',
        ];
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function routeAssignment()
    {
        return $this->belongsTo(RouteAssignment::class);
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function hasVariance(): bool
    {
        return abs($this->cash_variance) > 0.01 || abs($this->inventory_variance) > 0.01;
    }
}
