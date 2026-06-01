<?php

namespace App\Events;

use App\Models\Delivery;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class DeliveryStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Delivery $delivery
    ) {}

    public function broadcastOn(): array
    {
        if (!$this->delivery->retail_store_id) {
            return [];
        }
        return [
            new Channel('store.' . $this->delivery->retail_store_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'delivery_id' => $this->delivery->id,
            'status' => $this->delivery->status,
            'order_id' => $this->delivery->sales_order_id,
            'driver_name' => $this->delivery->driver?->name,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'delivery.status_changed';
    }
}
