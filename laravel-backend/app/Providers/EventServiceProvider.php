<?php

namespace App\Providers;

use App\Events\DeliveryCompleted;
use App\Events\DriverArrivingSoon;
use App\Events\DriverTripStarted;
use App\Events\OrderConfirmed;
use App\Listeners\SendDeliveryNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        DriverTripStarted::class => [SendDeliveryNotification::class],
        DriverArrivingSoon::class => [SendDeliveryNotification::class],
        DeliveryCompleted::class => [SendDeliveryNotification::class],
        OrderConfirmed::class => [SendDeliveryNotification::class],
    ];
}
