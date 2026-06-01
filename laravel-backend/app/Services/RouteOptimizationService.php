<?php

namespace App\Services;

use App\Models\Route;
use App\Models\RouteStop;
use Illuminate\Support\Collection;

class RouteOptimizationService
{
    const float EARTH_RADIUS_KM = 6371.0;

    public function optimize(int $routeId): Collection
    {
        $stops = RouteStop::with('retailStore')
            ->where('route_id', $routeId)
            ->where('is_active', true)
            ->get();

        if ($stops->isEmpty()) {
            return collect();
        }

        $route = Route::with('warehouse')->findOrFail($routeId);
        $warehouseCoords = [
            'lat' => (float) ($route->warehouse?->latitude ?? 0),
            'lng' => (float) ($route->warehouse?->longitude ?? 0),
        ];

        $points = $stops->map(function ($stop) {
            return [
                'id' => $stop->id,
                'store_id' => $stop->retail_store_id,
                'lat' => (float) ($stop->latitude ?? $stop->retailStore?->latitude ?? 0),
                'lng' => (float) ($stop->longitude ?? $stop->retailStore?->longitude ?? 0),
            ];
        })->toArray();

        $ordered = $this->nearestNeighbor($warehouseCoords, $points);

        $result = collect();
        $order = 1;
        foreach ($ordered as $point) {
            $result->push([
                'stop_id' => $point['id'],
                'stop_order' => $order++,
            ]);
        }

        return $result;
    }

    private function nearestNeighbor(array $start, array $points): array
    {
        if (empty($points)) return [];

        $unvisited = $points;
        $ordered = [];
        $current = $start;

        while (!empty($unvisited)) {
            $nearestIdx = null;
            $nearestDist = PHP_FLOAT_MAX;

            foreach ($unvisited as $idx => $point) {
                $dist = $this->haversine(
                    $current['lat'], $current['lng'],
                    $point['lat'], $point['lng']
                );

                if ($dist < $nearestDist) {
                    $nearestDist = $dist;
                    $nearestIdx = $idx;
                }
            }

            if ($nearestIdx !== null) {
                $ordered[] = $unvisited[$nearestIdx];
                $current = $unvisited[$nearestIdx];
                array_splice($unvisited, $nearestIdx, 1);
            }
        }

        return $ordered;
    }

    public function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }

    public function calculateTotalDistance(array $stops): float
    {
        $total = 0;
        for ($i = 0; $i < count($stops) - 1; $i++) {
            $total += $this->haversine(
                $stops[$i]['lat'], $stops[$i]['lng'],
                $stops[$i + 1]['lat'], $stops[$i + 1]['lng']
            );
        }
        return $total;
    }
}
