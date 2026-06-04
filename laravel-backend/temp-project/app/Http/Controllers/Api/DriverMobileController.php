<?php

namespace App\Http\Controllers\Api;

use App\Models\Delivery;
use App\Models\DeliveryStop;
use App\Models\DeliveryItem;
use App\Models\DeliveryPayment;
use App\Models\DeliveryReturn;
use App\Models\RouteAssignment;
use App\Models\RouteStop;
use App\Models\OrderItem;
use App\Models\SalesOrder;
use App\Models\TruckInventory;
use App\Models\Truck;
use App\Models\Batch;
use App\Services\DriverSettlementService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DriverMobileController extends Controller
{
    protected DriverSettlementService $settlementService;

    public function __construct(DriverSettlementService $settlementService)
    {
        $this->settlementService = $settlementService;
    }

    public function todayRoute(Request $request)
    {
        try {
            $driver = $request->user();

            $assignment = RouteAssignment::with(['route.stops.retailStore', 'route.warehouse', 'truck'])
                ->where('driver_id', $driver->id)
                ->whereDate('assignment_date', now())
                ->whereIn('status', ['scheduled', 'active'])
                ->first();

            if (!$assignment) {
                return response()->json([
                    'data' => null,
                    'message' => 'No route assigned for today',
                ], 200);
            }

            $delivery = Delivery::where('route_assignment_id', $assignment->id)
                ->whereDate('delivery_date', now())
                ->first();

            return response()->json([
                'data' => [
                    'assignment' => $assignment,
                    'delivery' => $delivery,
                    'stops_count' => $assignment->route->stops->count(),
                ],
                'message' => 'Today\'s route retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve today\'s route',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function routeStops(Request $request)
    {
        try {
            $driver = $request->user();

            $assignment = RouteAssignment::with(['route.stops.retailStore'])
                ->where('driver_id', $driver->id)
                ->whereDate('assignment_date', now())
                ->first();

            if (!$assignment) {
                return response()->json([
                    'data' => [],
                    'message' => 'No route assigned for today',
                ], 200);
            }

            $delivery = Delivery::where('route_assignment_id', $assignment->id)
                ->whereDate('delivery_date', now())
                ->first();

            $stops = $assignment->route->stops->map(function ($stop) use ($delivery) {
                $deliveryStop = null;
                if ($delivery) {
                    $deliveryStop = DeliveryStop::where('delivery_id', $delivery->id)
                        ->where('route_stop_id', $stop->id)
                        ->first();
                }

                return [
                    'route_stop_id' => $stop->id,
                    'stop_order' => $stop->stop_order,
                    'store' => $stop->retailStore,
                    'status' => $deliveryStop?->status ?? 'pending',
                    'arrived_at' => $deliveryStop?->arrived_at,
                    'departed_at' => $deliveryStop?->departed_at,
                ];
            });

            return response()->json([
                'data' => $stops,
                'message' => 'Route stops retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve route stops',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function stopDetail(Request $request, $stopId)
    {
        try {
            $driver = $request->user();

            $routeStop = RouteStop::with('retailStore')->findOrFail($stopId);

            $assignment = RouteAssignment::where('driver_id', $driver->id)
                ->whereDate('assignment_date', now())
                ->firstOrFail();

            $delivery = Delivery::where('route_assignment_id', $assignment->id)
                ->whereDate('delivery_date', now())
                ->first();

            $orders = SalesOrder::with(['items.product'])
                ->where('retail_store_id', $routeStop->retail_store_id)
                ->where('route_id', $assignment->route_id)
                ->whereIn('status', ['assigned', 'in_transit'])
                ->get();

            $deliveryStop = null;
            if ($delivery) {
                $deliveryStop = DeliveryStop::with('payments')
                    ->where('delivery_id', $delivery->id)
                    ->where('route_stop_id', $stopId)
                    ->first();
            }

            return response()->json([
                'data' => [
                    'route_stop' => $routeStop,
                    'store' => $routeStop->retailStore,
                    'orders' => $orders,
                    'delivery_stop' => $deliveryStop,
                    'delivery' => $delivery,
                ],
                'message' => 'Stop detail retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve stop detail',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function arrive(Request $request, $stopId)
    {
        try {
            $driver = $request->user();

            $routeStop = RouteStop::findOrFail($stopId);

            $assignment = RouteAssignment::where('driver_id', $driver->id)
                ->whereDate('assignment_date', now())
                ->firstOrFail();

            $delivery = Delivery::where('route_assignment_id', $assignment->id)
                ->whereDate('delivery_date', now())
                ->first();

            if (!$delivery) {
                $delivery = Delivery::create([
                    'delivery_number' => 'DEL-' . strtoupper(uniqid()),
                    'route_assignment_id' => $assignment->id,
                    'driver_id' => $driver->id,
                    'truck_id' => $assignment->truck_id,
                    'delivery_date' => now()->toDateString(),
                    'status' => 'in_transit',
                    'total_sales' => 0,
                    'total_collected' => 0,
                    'total_returns' => 0,
                ]);
            }

            if ($delivery->status === 'pending') {
                $delivery->update(['status' => 'in_transit', 'started_at' => now()]);
            }

            $deliveryStop = DeliveryStop::updateOrCreate(
                [
                    'delivery_id' => $delivery->id,
                    'route_stop_id' => $stopId,
                ],
                [
                    'retail_store_id' => $routeStop->retail_store_id,
                    'stop_order' => $routeStop->stop_order,
                    'status' => 'arrived',
                    'arrived_at' => now(),
                ]
            );

            if ($assignment->status === 'scheduled') {
                $assignment->update(['status' => 'active']);
            }

            return response()->json([
                'data' => $deliveryStop,
                'message' => 'Arrival recorded',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to record arrival',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deliver(Request $request, $stopId)
    {
        try {
            $request->validate([
                'items' => 'required|array|min:1',
                'items.*.order_item_id' => 'required|exists:order_items,id',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.batch_id' => 'required|exists:batches,id',
                'items.*.quantity_delivered' => 'required|numeric|min:0',
                'items.*.quantity_returned' => 'nullable|numeric|min:0',
                'items.*.return_reason' => 'nullable|string',
            ]);

            $driver = $request->user();

            $routeStop = RouteStop::findOrFail($stopId);

            $assignment = RouteAssignment::where('driver_id', $driver->id)
                ->whereDate('assignment_date', now())
                ->firstOrFail();

            $delivery = Delivery::where('route_assignment_id', $assignment->id)
                ->whereDate('delivery_date', now())
                ->firstOrFail();

            $deliveryStop = DeliveryStop::where('delivery_id', $delivery->id)
                ->where('route_stop_id', $stopId)
                ->firstOrFail();

            DB::transaction(function () use ($request, $delivery, $deliveryStop) {
                foreach ($request->items as $item) {
                    $orderItem = OrderItem::findOrFail($item['order_item_id']);

                    DeliveryItem::create([
                        'delivery_id' => $delivery->id,
                        'sales_order_id' => $orderItem->sales_order_id,
                        'order_item_id' => $orderItem->id,
                        'product_id' => $item['product_id'],
                        'batch_id' => $item['batch_id'],
                        'quantity_loaded' => $item['quantity_delivered'] + ($item['quantity_returned'] ?? 0),
                        'quantity_delivered' => $item['quantity_delivered'],
                        'quantity_returned' => $item['quantity_returned'] ?? 0,
                        'unit_price' => $orderItem->unit_price,
                        'total_price' => $item['quantity_delivered'] * $orderItem->unit_price,
                        'return_reason' => $item['return_reason'] ?? null,
                        'status' => 'delivered',
                    ]);

                    if (($item['quantity_returned'] ?? 0) > 0) {
                        DeliveryReturn::create([
                            'delivery_id' => $delivery->id,
                            'delivery_stop_id' => $deliveryStop->id,
                            'product_id' => $item['product_id'],
                            'batch_id' => $item['batch_id'],
                            'quantity' => $item['quantity_returned'],
                            'return_reason' => $item['return_reason'] ?? 'unknown',
                            'status' => 'returned',
                        ]);
                    }

                    $newDelivered = $orderItem->quantity_delivered + $item['quantity_delivered'];
                    $newReturned = $orderItem->quantity_returned + ($item['quantity_returned'] ?? 0);
                    $orderItem->update([
                        'quantity_delivered' => $newDelivered,
                        'quantity_returned' => $newReturned,
                        'status' => ($item['quantity_returned'] ?? 0) > 0 ? 'partial' : 'delivered',
                    ]);

                    $salesOrder = SalesOrder::find($orderItem->sales_order_id);
                    if ($salesOrder) {
                        $allDelivered = $salesOrder->items()->whereNotIn('status', ['delivered'])->count() === 0;
                        if ($allDelivered) {
                            $salesOrder->update(['status' => 'delivered']);
                        }
                    }
                }

                $deliveryStop->update(['status' => 'delivered']);
            });

            return response()->json([
                'message' => 'Delivery recorded successfully',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to record delivery',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function collectPayment(Request $request, $stopId)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'payment_method' => 'required|in:cash,check,bank_transfer,credit',
                'reference_number' => 'nullable|string',
                'check_number' => 'nullable|string',
                'check_bank' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            $driver = $request->user();

            $assignment = RouteAssignment::where('driver_id', $driver->id)
                ->whereDate('assignment_date', now())
                ->firstOrFail();

            $delivery = Delivery::where('route_assignment_id', $assignment->id)
                ->whereDate('delivery_date', now())
                ->firstOrFail();

            $deliveryStop = DeliveryStop::where('delivery_id', $delivery->id)
                ->where('route_stop_id', $stopId)
                ->firstOrFail();

            $payment = DB::transaction(function () use ($request, $delivery, $deliveryStop) {
                $salesOrderId = $delivery->items()->value('sales_order_id');

                $payment = DeliveryPayment::create([
                    'delivery_stop_id' => $deliveryStop->id,
                    'delivery_id' => $delivery->id,
                    'sales_order_id' => $salesOrderId,
                    'amount' => $request->amount,
                    'payment_method' => $request->payment_method,
                    'reference_number' => $request->reference_number,
                    'check_number' => $request->check_number,
                    'check_bank' => $request->check_bank,
                    'status' => 'collected',
                    'notes' => $request->notes,
                ]);

                $deliveryStop->increment('collected_amount', $request->amount);
                $delivery->increment('total_collected', $request->amount);

                return $payment;
            });

            return response()->json([
                'data' => $payment,
                'message' => 'Payment collected',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to collect payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function signature(Request $request, $stopId)
    {
        try {
            $request->validate([
                'signature' => 'required|string',
                'name' => 'nullable|string|max:255',
            ]);

            $driver = $request->user();

            $assignment = RouteAssignment::where('driver_id', $driver->id)
                ->whereDate('assignment_date', now())
                ->firstOrFail();

            $delivery = Delivery::where('route_assignment_id', $assignment->id)
                ->whereDate('delivery_date', now())
                ->firstOrFail();

            $deliveryStop = DeliveryStop::where('delivery_id', $delivery->id)
                ->where('route_stop_id', $stopId)
                ->firstOrFail();

            $deliveryStop->update([
                'signature' => $request->signature,
                'notes' => $request->name ?? $deliveryStop->notes,
            ]);

            return response()->json([
                'data' => $deliveryStop,
                'message' => 'Signature captured',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to capture signature',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function photos(Request $request, $stopId)
    {
        try {
            $request->validate([
                'photos' => 'required|array',
                'photos.*' => 'required|string',
                'photo_type' => 'nullable|string|in:delivery,return,proof',
            ]);

            $driver = $request->user();

            $assignment = RouteAssignment::where('driver_id', $driver->id)
                ->whereDate('assignment_date', now())
                ->firstOrFail();

            $delivery = Delivery::where('route_assignment_id', $assignment->id)
                ->whereDate('delivery_date', now())
                ->firstOrFail();

            $deliveryStop = DeliveryStop::where('delivery_id', $delivery->id)
                ->where('route_stop_id', $stopId)
                ->firstOrFail();

            $savedPhotos = [];
            foreach ($request->photos as $index => $photoData) {
                $filename = 'stop_' . $stopId . '_' . time() . '_' . $index . '.jpg';
                $path = 'delivery_photos/' . $filename;

                $savedPhotos[] = [
                    'path' => $path,
                    'type' => $request->photo_type ?? 'proof',
                ];
            }

            return response()->json([
                'data' => [
                    'delivery_stop_id' => $deliveryStop->id,
                    'photos' => $savedPhotos,
                    'count' => count($savedPhotos),
                ],
                'message' => 'Photos uploaded',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload photos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function truckInventory(Request $request)
    {
        try {
            $driver = $request->user();

            $assignment = RouteAssignment::with('truck')
                ->where('driver_id', $driver->id)
                ->whereDate('assignment_date', now())
                ->firstOrFail();

            $truck = $assignment->truck;
            $inventory = TruckInventory::with('product', 'batch')
                ->where('truck_id', $truck->id)
                ->where('quantity', '>', 0)
                ->get();

            return response()->json([
                'data' => [
                    'truck' => $truck,
                    'items' => $inventory,
                ],
                'message' => 'Truck inventory retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve truck inventory',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function syncInventory(Request $request)
    {
        try {
            $request->validate([
                'items' => 'required|array',
                'items.*.batch_id' => 'required|exists:batches,id',
                'items.*.quantity' => 'required|numeric|min:0',
                'items.*.product_id' => 'required|exists:products,id',
            ]);

            $driver = $request->user();

            $assignment = RouteAssignment::where('driver_id', $driver->id)
                ->whereDate('assignment_date', now())
                ->firstOrFail();

            $truckId = $assignment->truck_id;

            DB::transaction(function () use ($request, $truckId) {
                foreach ($request->items as $item) {
                    TruckInventory::updateOrCreate(
                        [
                            'truck_id' => $truckId,
                            'batch_id' => $item['batch_id'],
                            'product_id' => $item['product_id'],
                        ],
                        [
                            'quantity' => $item['quantity'],
                        ]
                    );
                }
            });

            return response()->json([
                'message' => 'Truck inventory synced',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to sync inventory',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function startShift(Request $request)
    {
        try {
            $driver = $request->user();

            $assignment = RouteAssignment::with('route')
                ->where('driver_id', $driver->id)
                ->whereDate('assignment_date', now())
                ->firstOrFail();

            $delivery = Delivery::where('route_assignment_id', $assignment->id)
                ->whereDate('delivery_date', now())
                ->first();

            if (!$delivery) {
                $delivery = Delivery::create([
                    'delivery_number' => 'DEL-' . strtoupper(uniqid()),
                    'route_assignment_id' => $assignment->id,
                    'driver_id' => $driver->id,
                    'truck_id' => $assignment->truck_id,
                    'delivery_date' => now()->toDateString(),
                    'status' => 'in_transit',
                    'started_at' => now(),
                    'total_sales' => 0,
                    'total_collected' => 0,
                    'total_returns' => 0,
                ]);
            }

            if ($delivery->status === 'pending') {
                $delivery->update(['status' => 'in_transit', 'started_at' => now()]);
            }

            $assignment->update(['status' => 'active']);

            if ($driver->driverProfile) {
                $driver->driverProfile->update(['status' => 'busy']);
            }

            return response()->json([
                'data' => [
                    'delivery' => $delivery,
                    'assignment' => $assignment,
                ],
                'message' => 'Shift started',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to start shift',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function endShift(Request $request)
    {
        try {
            $request->validate([
                'actual_cash' => 'required|numeric|min:0',
                'notes' => 'nullable|string',
            ]);

            $driver = $request->user();

            $assignment = RouteAssignment::where('driver_id', $driver->id)
                ->whereDate('assignment_date', now())
                ->firstOrFail();

            $delivery = Delivery::where('route_assignment_id', $assignment->id)
                ->whereDate('delivery_date', now())
                ->firstOrFail();

            $result = DB::transaction(function () use ($request, $delivery, $driver, $assignment) {
                $delivery->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'notes' => $request->notes,
                ]);

                $settlement = $this->settlementService->calculateSettlement($delivery->id);

                $delivery->update([
                    'total_sales' => $settlement->total_sales,
                    'total_returns' => $settlement->total_returns,
                    'total_collected' => $delivery->payments()->sum('amount'),
                ]);

                $settlement->update([
                    'actual_cash' => $request->actual_cash,
                    'cash_variance' => $settlement->expected_cash - $request->actual_cash,
                    'status' => abs($settlement->expected_cash - $request->actual_cash) > 10.00 ? 'flagged' : 'pending',
                    'notes' => $request->notes,
                ]);

                $assignment->update(['status' => 'completed']);

                if ($driver->driverProfile) {
                    $driver->driverProfile->update(['status' => 'available']);
                }

                return [
                    'delivery' => $delivery->fresh(),
                    'settlement' => $settlement->fresh(),
                ];
            });

            return response()->json([
                'data' => $result,
                'message' => 'Shift ended successfully',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to end shift',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
