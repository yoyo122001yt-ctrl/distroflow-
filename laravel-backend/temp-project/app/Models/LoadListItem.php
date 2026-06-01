<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoadListItem extends Model
{
    protected $table = 'load_list_items';
    protected $fillable = [
        'load_list_id', 'pick_list_item_id', 'product_id', 'batch_id',
        'quantity', 'status',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    public function loadList()
    {
        return $this->belongsTo(LoadList::class);
    }

    public function pickListItem()
    {
        return $this->belongsTo(PickListItem::class);
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
