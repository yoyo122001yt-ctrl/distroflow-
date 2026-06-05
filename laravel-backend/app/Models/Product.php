<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'name', 'sku', 'barcode', 'description', 'unit',
        'cost_price', 'selling_price', 'weight', 'image',
        'is_expiry_tracked', 'shelf_life_days',
        'min_stock_level', 'max_stock_level', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_expiry_tracked' => 'boolean',
            'is_active' => 'boolean',
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
        ];
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }

    public function storePrices()
    {
        return $this->hasMany(StorePrice::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getAvailableStockAttribute()
    {
        return $this->batches()->sum('available_quantity');
    }
}

