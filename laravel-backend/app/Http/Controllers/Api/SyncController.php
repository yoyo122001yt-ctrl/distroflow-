<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class SyncController extends Controller
{
    public function sync(Request $request)
    {
        $request->validate([
            'actions' => 'required|array|max:100',
            'actions.*.type' => 'required|string|in:location,delivery_status,truck_inventory,payment',
            'actions.*.payload' => 'required|array',
        ]);

        $results = [];
        foreach ($request->actions as $action) {
            $result = match ($action['type']) {
                'location' => $this->syncLocation($action['payload']),
                'delivery_status' => $this->syncDeliveryStatus($action['payload']),
                'truck_inventory' => $this->syncTruckInventory($action['payload']),
                'payment' => $this->syncPayment($action['payload']),
                default => ['status' => 'error', 'message' => 'Unknown action type'],
            };
            $results[] = array_merge(['action_id' => $action['id'] ?? null], $result);
        }

        return response()->json([
            'data' => $results,
            'message' => 'Sync processed',
            'synced' => count(array_filter($results, fn($r) => ($r['status'] ?? '') === 'ok')),
            'failed' => count(array_filter($results, fn($r) => ($r['status'] ?? '') !== 'ok')),
        ]);
    }

    protected function syncLocation(array $payload): array
    {
        $validator = Validator::make($payload, [
            'driver_id' => 'required|exists:drivers,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'heading' => 'nullable|numeric|between:0,360',
            'speed' => 'nullable|numeric|min:0',
            'recorded_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        try {
            $location = \App\Models\DriverLocation::create($validator->validated());
            return ['status' => 'ok', 'location_id' => $location->id];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function syncDeliveryStatus(array $payload): array
    {
        $validator = Validator::make($payload, [
            'delivery_id' => 'required|exists:deliveries,id',
            'status' => 'required|string|in:pending,assigned,in_transit,completed,failed',
            'notes' => 'nullable|string|max:500',
            'completed_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        try {
            $delivery = \App\Models\Delivery::findOrFail($payload['delivery_id']);
            $delivery->update([
                'status' => $payload['status'],
                'notes' => $payload['notes'] ?? $delivery->notes,
                'completed_at' => $payload['completed_at'] ?? ($payload['status'] === 'completed' ? now() : $delivery->completed_at),
            ]);
            return ['status' => 'ok', 'delivery_id' => $delivery->id];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function syncTruckInventory(array $payload): array
    {
        $validator = Validator::make($payload, [
            'truck_id' => 'required|exists:trucks,id',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        try {
            foreach ($payload['items'] as $item) {
                \App\Models\TruckInventory::updateOrCreate(
                    ['truck_id' => $payload['truck_id'], 'product_id' => $item['product_id']],
                    ['quantity' => $item['quantity']]
                );
            }
            return ['status' => 'ok'];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function syncPayment(array $payload): array
    {
        $validator = Validator::make($payload, [
            'delivery_id' => 'required|exists:deliveries,id',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|string|in:cash,card,bank_transfer,wallet',
            'reference' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        try {
            $payment = \App\Models\DeliveryPayment::create($validator->validated());
            return ['status' => 'ok', 'payment_id' => $payment->id];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
