<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;

class DeliveryStatusUpdated
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $deliveryId,
        public string $status,
        public ?int $storeId = null
    ) {}
}
