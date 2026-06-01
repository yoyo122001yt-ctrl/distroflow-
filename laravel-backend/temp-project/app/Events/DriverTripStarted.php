<?php

namespace App\Events;

use App\Models\Driver;
use App\Models\Delivery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;

class DriverTripStarted
{
    use Dispatchable;

    public function __construct(
        public Driver $driver,
        public Collection $deliveries
    ) {}
}
