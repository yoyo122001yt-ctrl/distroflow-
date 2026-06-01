<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorePrice extends Model
{
    protected $fillable = [
        'retail_store_id', 'product_id', 'price', 'discount_percent',
        'effective_from', 'effective_to', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function store()
    {
        return $this->belongsTo(RetailStore::class, 'retail_store_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
