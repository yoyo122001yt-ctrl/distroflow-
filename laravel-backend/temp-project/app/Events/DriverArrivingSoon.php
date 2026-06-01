<?php

namespace App\Events;

use App\Models\Delivery;
use App\Models\Driver;
use Illuminate\Foundation\Events\Dispatchable;

class DriverArrivingSoon
{
    use Dispatchable;

    public function __construct(
        public Delivery $delivery,
        public Driver $driver,
        public int $estimatedMinutes
    ) {}
}
