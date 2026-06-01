<?php

namespace App\Http\Controllers\Warehouse;

use App\Models\User;
use App\Models\DriverTrip;
use App\Models\DeliveryStop;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class TrackingController extends Controller
{
    public function showTrackingDashboard()
    {
        return view('warehouse.tracking');
    }

    public function getActiveDrivers()
    {
        $drivers = User::drivers()->with(['activeTrip.delivery', 'driverLocations' => function ($q) {
            $q->where('recorded_at', '>=', now()->subMinutes(5));
        }])->get();

        $result = $drivers->map(function ($driver) {
            $activeTrip = $driver->activeTrip;

            $lastLocation = $driver->driverLocations->sortByDesc('recorded_at')->first();

            $isActive = $activeTrip && $lastLocation && $lastLocation->recorded_at->gt(now()->subMinutes(30));

            $currentStop = null;
            $nextStop = null;
            $progress = null;

            if ($activeTrip && $activeTrip->delivery) {
                $stops = $activeTrip->delivery->stops()->orderBy('stop_order')->get();
                $completedStops = $stops->where('status', 'completed')->count();
                $progress = [
                    'completed' => $completedStops,
                    'total' => $stops->count(),
                ];

                $currentStop = $stops->where('status', '!=', 'completed')->first();
                $nextStop = $stops->where('status', 'pending')->first();
            }

            $lastSeen = $lastLocation ? $lastLocation->recorded_at : ($activeTrip ? $activeTrip->started_at : null);

            return [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'is_active' => $isActive,
                'has_active_trip' => (bool) $activeTrip,
                'trip_id' => $activeTrip?->id,
                'delivery_id' => $activeTrip?->delivery_id,
                'last_latitude' => $lastLocation?->latitude,
                'last_longitude' => $lastLocation?->longitude,
                'speed_kmh' => $lastLocation?->speed_kmh,
                'accuracy' => $lastLocation?->accuracy,
                'last_seen' => $lastSeen?->diffForHumans(),
                'last_seen_at' => $lastSeen,
                'current_stop' => $currentStop ? [
                    'id' => $currentStop->id,
                    'stop_order' => $currentStop->stop_order,
                    'store_name' => $currentStop->retailStore?->business_name ?? $currentStop->retailStore?->trade_name ?? 'Unknown',
                    'address' => $currentStop->retailStore?->address,
                    'latitude' => $currentStop->retailStore?->latitude,
                    'longitude' => $currentStop->retailStore?->longitude,
                ] : null,
                'next_stop' => $nextStop ? [
                    'id' => $nextStop->id,
                    'stop_order' => $nextStop->stop_order,
                    'store_name' => $nextStop->retailStore?->business_name ?? $nextStop->retailStore?->trade_name ?? 'Unknown',
                    'address' => $nextStop->retailStore?->address,
                    'latitude' => $nextStop->retailStore?->latitude,
                    'longitude' => $nextStop->retailStore?->longitude,
                ] : null,
                'progress' => $progress,
                'trip_started_at' => $activeTrip?->started_at?->diffForHumans(),
            ];
        });

        return response()->json([
            'drivers' => $result,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function getDriverRoute($driverId)
    {
        $driver = User::findOrFail($driverId);

        $trip = DriverTrip::where('driver_id', $driverId)
            ->with(['delivery.stops.retailStore', 'delivery.stops' => function ($q) {
                $q->orderBy('stop_order');
            }])
            ->latest()
            ->first();

        if (!$trip || !$trip->delivery) {
            return response()->json(['message' => 'No route found for this driver'], 404);
        }

        $stops = $trip->delivery->stops->map(function ($stop) {
            return [
                'id' => $stop->id,
                'stop_order' => $stop->stop_order,
                'status' => $stop->status,
                'store_name' => $stop->retailStore?->business_name ?? $stop->retailStore?->trade_name ?? 'Unknown',
                'address' => $stop->retailStore?->address,
                'latitude' => $stop->retailStore?->latitude,
                'longitude' => $stop->retailStore?->longitude,
                'arrived_at' => $stop->arrived_at,
                'departed_at' => $stop->departed_at,
            ];
        });

        $locations = $trip->locations()->orderBy('recorded_at')->get()->map(function ($loc) {
            return [
                'latitude' => $loc->latitude,
                'longitude' => $loc->longitude,
                'speed_kmh' => $loc->speed_kmh,
                'recorded_at' => $loc->recorded_at,
            ];
        });

        return response()->json([
            'driver' => ['id' => $driver->id, 'name' => $driver->name],
            'trip' => [
                'id' => $trip->id,
                'started_at' => $trip->started_at,
                'ended_at' => $trip->ended_at,
                'status' => $trip->status,
            ],
            'stops' => $stops,
            'locations' => $locations,
        ]);
    }
}
