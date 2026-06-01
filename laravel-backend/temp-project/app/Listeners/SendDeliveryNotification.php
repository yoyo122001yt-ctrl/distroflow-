<?php

namespace App\Listeners;

use App\Events\DeliveryCompleted;
use App\Events\DriverArrivingSoon;
use App\Events\DriverTripStarted;
use App\Events\OrderConfirmed;
use App\Models\RetailStore;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendDeliveryNotification implements ShouldQueue
{
    public int $delay = 0;

    public function __construct(
        protected NotificationService $notifier
    ) {}

    public function handleDriverTripStarted(DriverTripStarted $event): void
    {
        foreach ($event->deliveries as $delivery) {
            $store = $delivery->retailStore ?? RetailStore::find($delivery->retail_store_id);
            if (!$store) continue;

            $this->notifier->send($store, 'trip_started', [
                'driver_name' => $event->driver->name,
                'driver_phone' => $event->driver->phone,
                'vehicle_plate' => $event->driver->driverProfile?->vehicle_plate ?? 'N/A',
            ], $delivery->id);
        }
    }

    public function handleDriverArrivingSoon(DriverArrivingSoon $event): void
    {
        $store = $event->delivery->retailStore ?? RetailStore::find($event->delivery->retail_store_id);
        if (!$store) return;

        $this->notifier->send($store, 'arrival_soon', [
            'driver_name' => $event->driver->name,
            'estimated_minutes' => (string) $event->estimatedMinutes,
            'vehicle_plate' => $event->driver->driverProfile?->vehicle_plate ?? 'N/A',
        ], $event->delivery->id);
    }

    public function handleDeliveryCompleted(DeliveryCompleted $event): void
    {
        $store = $event->delivery->retailStore ?? RetailStore::find($event->delivery->retail_store_id);
        if (!$store) return;

        $invoiceNumber = $event->delivery->salesOrder?->invoice?->invoice_number ?? $event->delivery->id;

        $this->notifier->send($store, 'delivered', [
            'driver_name' => $event->driver?->name ?? 'N/A',
            'invoice_number' => (string) $invoiceNumber,
            'delivery_id' => (string) $event->delivery->id,
        ], $event->delivery->id);
    }

    public function handleOrderConfirmed(OrderConfirmed $event): void
    {
        $store = $event->order->retailStore ?? RetailStore::find($event->order->retail_store_id);
        if (!$store) return;

        $this->notifier->send($store, 'order_confirmed', [
            'order_number' => (string) ($event->order->order_number ?? $event->order->id),
            'store_name' => $store->business_name,
        ]);
    }

    public function subscribe(): array
    {
        return [
            DriverTripStarted::class => 'handleDriverTripStarted',
            DriverArrivingSoon::class => 'handleDriverArrivingSoon',
            DeliveryCompleted::class => 'handleDeliveryCompleted',
            OrderConfirmed::class => 'handleOrderConfirmed',
        ];
    }
}
