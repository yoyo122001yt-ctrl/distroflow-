<?php

namespace App\Events;

use App\Models\SalesOrder;
use Illuminate\Foundation\Events\Dispatchable;

class OrderConfirmed
{
    use Dispatchable;

    public function __construct(
        public SalesOrder $order
    ) {}
}
