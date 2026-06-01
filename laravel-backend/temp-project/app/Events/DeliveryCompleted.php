<?php

namespace App\Events;

use App\Models\Delivery;
use Illuminate\Foundation\Events\Dispatchable;

class DeliveryCompleted
{
    use Dispatchable;

    public function __construct(
        public Delivery $delivery
    ) {}
}
