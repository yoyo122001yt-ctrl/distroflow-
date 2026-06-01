<?php

namespace App\Events;

use App\Models\DriverLocation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class DriverLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public DriverLocation $location
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('driver.' . $this->location->driver_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'driver_id' => $this->location->driver_id,
            'lat' => $this->location->latitude,
            'lng' => $this->location->longitude,
            'heading' => $this->location->heading ?? 0,
            'speed' => $this->location->speed ?? 0,
            'updated_at' => $this->location->created_at->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'location.updated';
    }
}
