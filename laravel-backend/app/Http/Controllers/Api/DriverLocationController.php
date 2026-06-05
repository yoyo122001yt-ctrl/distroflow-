<?php

namespace App\Http\Controllers\Api;

use App\Models\DriverLocation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class DriverLocationController extends Controller
{
    public function updateLocation(Request $request)
    {
        $driver = Auth::user();

        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed_kmh' => 'nullable|numeric|min:0|max:999',
            'accuracy' => 'nullable|integer|min:0|max:99999',
        ]);

        $trip = $driver->activeTrip()->first();

        if (!$trip) {
            return response()->json(['message' => 'No active trip'], 403);
        }

        $recent = DriverLocation::where('driver_id', $driver->id)
            ->where('recorded_at', '>=', now()->subSeconds(10))
            ->exists();

        if ($recent) {
            return response()->json(['message' => 'Too frequent'], 429);
        }

        $location = DriverLocation::create([
            'driver_id' => $driver->id,
            'trip_id' => $trip->id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'speed_kmh' => $validated['speed_kmh'] ?? null,
            'accuracy' => $validated['accuracy'] ?? null,
            'recorded_at' => now(),
        ]);

        return response()->json(['message' => 'Location updated', 'id' => $location->id]);
    }
}
