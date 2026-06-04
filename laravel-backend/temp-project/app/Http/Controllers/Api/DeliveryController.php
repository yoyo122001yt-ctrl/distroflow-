<?php

namespace App\Http\Controllers\Api;

use App\Models\Delivery;
use App\Models\DeliveryStop;
use App\Models\DeliveryItem;
use App\Models\DeliveryPayment;
use App\Models\DeliveryReturn;
use App\Models\RouteAssignment;
use App\Models\OrderItem;
use App\Models\SalesOrder;
use App\Services\DriverSettlementService;
use App\Http\Requests\DeliveryRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryController extends Controller
{
    protected DriverSettlementService $settlementService;

    public function __construct(DriverSettlementService $settlementService)
    {
        $this->settlementService = $settlementService;
    }

    public function index(Request $request)
    {
        try {
            $query = Delivery::with('driver', 'truck', 'routeAssignment.route');

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('driver_id')) {
                $query->where('driver_id', $request->driver_id);
            }

            if ($request->filled('truck_id')) {
                $query->where('truck_id', $request->truck_id);
            }

            if ($request->filled('date_from')) {
                $query->where('delivery_date', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('delivery_date', '<=', $request->date_to);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('delivery_number', 'like', "%{$search}%");
            }

            $deliveries = $query->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 20);

            return response()->json([
                'data' => $deliveries,
                'message' => 'Deliveries retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve deliveries',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'route_assignment_id' => 'required|exists:route_assignments,id',
                'delivery_date' => 'required|date',
            ]);

            $assignment = RouteAssignment::with('route', 'driver', 'truck')
                ->findOrFail($request->route_assignment_id);

            $delivery = DB::transaction(function () use ($request, $assignment) {
                $deliveryNumber = 'DEL-' . strtoupper(uniqid());

                return Delivery::create([
                    'delivery_number' => $deliveryNumber,
                    'route_assignment_id' => $assignment->id,
                    'driver_id' => $assignment->driver_id,
                    'truck_id' => $assignment->truck_id,
                    'delivery_date' => $request->delivery_date,
                    'status' => 'pending',
                    'total_sales' => 0,
                    'total_collected' => 0,
                    'total_returns' => 0,
                ]);
            });

            return response()->json([
                'data' => $delivery->load('driver', 'truck', 'routeAssignment.route'),
                'message' => 'Delivery created',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create delivery',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $delivery = Delivery::with([
                'driver',
                'truck',
                'routeAssignment.route',
                'stops.retailStore',
                'stops.payments',
                'items.product',
                'items.batch',
                'items.salesOrder',
                'payments',
                'returns.product',
                'returns.batch',
                'settlement',
            ])->findOrFail($id);

            return response()->json([
                'data' => $delivery,
                'message' => 'Delivery retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Delivery not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $delivery = Delivery::findOrFail($id);

            if ($delivery->status !== 'pending') {
                return response()->json([
                    'message' => 'Cannot modify delivery that has started',
                ], 400);
            }

            $request->validate([
                'delivery_date' => 'sometimes|date',
                'notes' => 'nullable|string',
            ]);

            $delivery->update($request->only(['delivery_date', 'notes']));

            return response()->json([
                'data' => $delivery->fresh(),
                'message' => 'Delivery updated',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update delivery',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $delivery = Delivery::findOrFail($id);

            if ($delivery->status !== 'pending') {
                return response()->json([
                    'message' => 'Cannot delete delivery that has started',
                ], 400);
            }

            $delivery->delete();

            return response()->json([
                'message' => 'Delivery deleted',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete delivery',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function stops($id)
    {
        try {
            $delivery = Delivery::findOrFail($id);

            $stops = $delivery->stops()->with([
                'retailStore',
                'payments',
            ])->orderBy('stop_order')->get();

            return response()->json([
                'data' => $stops,
                'message' => 'Delivery stops retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve stops',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function complete(DeliveryRequest $request, $id)
    {
        try {
            $delivery = Delivery::findOrFail($id);

            if ($delivery->status === 'completed') {
                return response()->json([
                    'message' => 'Delivery is already completed',
                ], 400);
            }

            $result = DB::transaction(function () use ($request, $delivery) {
                $totalSales = 0;
                $totalCollected = 0;
                $totalReturns = 0;

                $firstDeliveryStop = $delivery->stops()->first();

                foreach ($request->items as $item) {
                    $orderItem = OrderItem::findOrFail($item['order_item_id']);

                    $deliveryItem = DeliveryItem::create([
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

                    $totalSales += $deliveryItem->total_price;

                    if (($item['quantity_returned'] ?? 0) > 0) {
                        $return = DeliveryReturn::create([
                            'delivery_id' => $delivery->id,
                            'product_id' => $item['product_id'],
                            'batch_id' => $item['batch_id'],
                            'quantity' => $item['quantity_returned'],
                            'return_reason' => $item['return_reason'] ?? 'unknown',
                            'status' => 'returned',
                        ]);

                        $batch = \App\Models\Batch::find($item['batch_id']);
                        $totalReturns += $return->quantity * ($batch?->cost_price ?? $deliveryItem->unit_price);
                    }

                    $orderItem->update([
                        'quantity_delivered' => $item['quantity_delivered'],
                        'quantity_returned' => $item['quantity_returned'] ?? 0,
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

                if ($request->has('payments')) {
                    foreach ($request->payments as $payment) {
                        DeliveryPayment::create([
                            'delivery_id' => $delivery->id,
                            'delivery_stop_id' => $firstDeliveryStop?->id,
                            'sales_order_id' => $request->items[0] ? OrderItem::find($request->items[0]['order_item_id'])?->sales_order_id : null,
                            'amount' => $payment['amount'],
                            'payment_method' => $payment['payment_method'],
                            'reference_number' => $payment['reference_number'] ?? null,
                            'notes' => $payment['notes'] ?? null,
                            'status' => 'collected',
                        ]);

                        $totalCollected += $payment['amount'];
                    }
                }

                $delivery->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'total_sales' => $totalSales,
                    'total_collected' => $totalCollected,
                    'total_returns' => $totalReturns,
                    'notes' => $request->notes,
                ]);

                $settlement = $this->settlementService->calculateSettlement($delivery->id);

                return [
                    'delivery' => $delivery->fresh()->load(
                        'items.product', 'items.batch', 'payments', 'returns'
                    ),
                    'settlement' => $settlement,
                ];
            });

            return response()->json([
                'data' => $result,
                'message' => 'Delivery completed successfully',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to complete delivery',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
