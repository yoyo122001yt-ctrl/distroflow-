<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetailStore extends Model
{
    protected $fillable = [
        'code', 'business_name', 'trade_name', 'store_type', 'contact_person',
        'phone', 'email', 'address', 'city', 'state', 'zip',
        'latitude', 'longitude', 'credit_limit', 'current_balance',
        'payment_terms', 'tax_id', 'status', 'notes', 'warehouse_id',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function prices()
    {
        return $this->hasMany(StorePrice::class);
    }

    public function orders()
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function licenses()
    {
        return $this->hasMany(StoreLicense::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('store_type', $type);
    }

    public function getPriceForProduct($productId)
    {
        $storePrice = $this->prices()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            })
            ->first();

        if ($storePrice) {
            return $storePrice->price;
        }

        return Product::find($productId)?->selling_price ?? 0;
    }

    public function checkCreditLimit(float $orderAmount): array
    {
        $newBalance = $this->current_balance + $orderAmount;
        return [
            'approved' => $newBalance <= $this->credit_limit,
            'current_balance' => $this->current_balance,
            'credit_limit' => $this->credit_limit,
            'new_balance' => $newBalance,
            'remaining_credit' => $this->credit_limit - $newBalance,
        ];
    }
}
