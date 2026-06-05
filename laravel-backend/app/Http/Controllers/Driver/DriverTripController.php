<?php

namespace App\Http\Controllers\Driver;

use App\Models\DriverTrip;
use App\Models\Delivery;
use App\Models\DeliveryStop;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class DriverTripController extends Controller
{
    public function showTripPage()
    {
        $driver = Auth::user();
        $activeTrip = $driver->activeTrip()->with(['delivery.stops.retailStore'])->first();
        $todayDeliveries = Delivery::forDriver($driver->id)->today()
            ->with(['stops.retailStore', 'routeAssignment.route.routeStops.retailStore'])
            ->orderBy('delivery_date')->get();

        return view('driver.trip', compact('driver', 'activeTrip', 'todayDeliveries'));
    }

    public function startTrip(Request $request)
    {
        $driver = Auth::user();

        $existingActive = $driver->activeTrip()->first();
        if ($existingActive) {
            return response()->json(['message' => 'You already have an active trip'], 422);
        }

        $deliveryId = $request->input('delivery_id');
        $delivery = null;

        if ($deliveryId) {
            $delivery = Delivery::forDriver($driver->id)->findOrFail($deliveryId);
        } else {
            $delivery = Delivery::forDriver($driver->id)->today()->where('status', 'pending')->first();
        }

        if (!$delivery) {
            return response()->json(['message' => 'No delivery assigned for today'], 404);
        }

        $totalStops = $delivery->stops()->count();

        $trip = DriverTrip::create([
            'driver_id' => $driver->id,
            'delivery_id' => $delivery->id,
            'started_at' => now(),
            'status' => 'active',
            'total_stops' => $totalStops,
            'completed_stops' => $delivery->stops()->where('status', 'completed')->count(),
        ]);

        $delivery->update(['status' => 'in_transit', 'started_at' => now()]);

        return response()->json([
            'message' => 'Trip started successfully',
            'trip' => $trip->load('delivery.stops.retailStore'),
        ]);
    }

    public function endTrip(Request $request)
    {
        $driver = Auth::user();
        $trip = $driver->activeTrip()->first();

        if (!$trip) {
            return response()->json(['message' => 'No active trip found'], 404);
        }

        $trip->update([
            'ended_at' => now(),
            'status' => 'completed',
        ]);

        if ($trip->delivery) {
            $trip->delivery->update(['status' => 'completed', 'completed_at' => now()]);
        }

        return response()->json(['message' => 'Trip ended successfully']);
    }

    public function getMyDeliveries()
    {
        $driver = Auth::user();
        $deliveries = Delivery::forDriver($driver->id)->today()
            ->with(['stops.retailStore', 'items'])
            ->orderBy('delivery_date')
            ->get();

        return response()->json(['data' => $deliveries]);
    }

    public function markStopComplete(Request $request, $stopId)
    {
        $driver = Auth::user();
        $trip = $driver->activeTrip()->first();

        if (!$trip) {
            return response()->json(['message' => 'No active trip'], 404);
        }

        $stop = DeliveryStop::where('delivery_id', $trip->delivery_id)
            ->findOrFail($stopId);

        $stop->update([
            'status' => 'completed',
            'arrived_at' => $stop->arrived_at ?? now(),
            'departed_at' => now(),
            'notes' => $request->input('notes'),
        ]);

        $completedCount = $trip->delivery->stops()->where('status', 'completed')->count();
        $trip->update(['completed_stops' => $completedCount]);

        return response()->json([
            'message' => 'Stop marked as completed',
            'completed_stops' => $completedCount,
            'total_stops' => $trip->total_stops,
        ]);
    }
}
