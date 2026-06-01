<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number', 'sales_order_id', 'retail_store_id',
        'invoice_date', 'due_date', 'subtotal', 'discount', 'tax',
        'total', 'amount_paid', 'balance_due', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance_due' => 'decimal:2',
        ];
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function retailStore()
    {
        return $this->belongsTo(RetailStore::class, 'retail_store_id');
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_date->isPast() && $this->balance_due > 0;
    }

    public function daysOverdue(): int
    {
        return $this->due_date->diffInDays(now(), false);
    }
}

class InvoicePayment extends Model
{
    protected $table = 'invoice_payments';
    protected $fillable = [
        'invoice_id', 'amount', 'payment_method',
        'reference_number', 'payment_date', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
