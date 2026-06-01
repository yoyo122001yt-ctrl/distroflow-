<?php

namespace App\Http\Controllers\Api;

use App\Models\SalesOrder;
use App\Models\OrderItem;
use App\Models\RetailStore;
use App\Http\Requests\SalesOrderRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrderController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = SalesOrder::with('retailStore', 'createdBy', 'route', 'items.product');

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('retail_store_id')) {
                $query->where('retail_store_id', $request->retail_store_id);
            }

            if ($request->filled('route_id')) {
                $query->where('route_id', $request->route_id);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('order_number', 'like', "%{$search}%");
            }

            if ($request->filled('date_from')) {
                $query->where('order_date', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('order_date', '<=', $request->date_to);
            }

            $orders = $query->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 20);

            return response()->json([
                'data' => $orders,
                'message' => 'Sales orders retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve sales orders',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(SalesOrderRequest $request)
    {
        try {
            $order = DB::transaction(function () use ($request) {
                $orderNumber = 'ORD-' . strtoupper(uniqid());
                $store = RetailStore::findOrFail($request->retail_store_id);

                $subtotal = collect($request->items)->sum(fn($i) => $i['quantity_ordered'] * $i['unit_price']);
                $total = $subtotal;

                $order = SalesOrder::create([
                    'order_number' => $orderNumber,
                    'retail_store_id' => $request->retail_store_id,
                    'warehouse_id' => $request->warehouse_id,
                    'created_by' => $request->user()->id,
                    'order_date' => $request->order_date,
                    'status' => 'pending',
                    'source' => $request->source,
                    'subtotal' => $subtotal,
                    'discount' => 0,
                    'tax' => 0,
                    'total' => $total,
                    'balance_due' => $total,
                    'notes' => $request->notes,
                ]);

                foreach ($request->items as $item) {
                    OrderItem::create([
                        'sales_order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'quantity_ordered' => $item['quantity_ordered'],
                        'quantity_picked' => 0,
                        'quantity_loaded' => 0,
                        'quantity_delivered' => 0,
                        'quantity_returned' => 0,
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['quantity_ordered'] * $item['unit_price'],
                        'status' => 'pending',
                    ]);
                }

                return $order->load('items.product', 'retailStore', 'createdBy');
            });

            return response()->json([
                'data' => $order,
                'message' => 'Sales order created',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create sales order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $order = SalesOrder::with([
                'retailStore',
                'items.product',
                'createdBy',
                'approvedBy',
                'route',
                'invoices',
            ])->findOrFail($id);

            return response()->json([
                'data' => $order,
                'message' => 'Sales order retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Sales order not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function update(SalesOrderRequest $request, $id)
    {
        try {
            $order = SalesOrder::findOrFail($id);

            if ($order->status !== 'pending') {
                return response()->json([
                    'message' => 'Cannot modify order after it has been processed',
                ], 400);
            }

            $updated = DB::transaction(function () use ($request, $order) {
                $subtotal = collect($request->items)->sum(fn($i) => $i['quantity_ordered'] * $i['unit_price']);
                $total = $subtotal;

                $order->update([
                    'retail_store_id' => $request->retail_store_id,
                    'warehouse_id' => $request->warehouse_id,
                    'order_date' => $request->order_date,
                    'source' => $request->source,
                    'subtotal' => $subtotal,
                    'total' => $total,
                    'balance_due' => $total,
                    'notes' => $request->notes,
                ]);

                $order->items()->delete();

                foreach ($request->items as $item) {
                    OrderItem::create([
                        'sales_order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'quantity_ordered' => $item['quantity_ordered'],
                        'quantity_picked' => 0,
                        'quantity_loaded' => 0,
                        'quantity_delivered' => 0,
                        'quantity_returned' => 0,
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['quantity_ordered'] * $item['unit_price'],
                        'status' => 'pending',
                    ]);
                }

                return $order->fresh()->load('items.product', 'retailStore');
            });

            return response()->json([
                'data' => $updated,
                'message' => 'Sales order updated',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update sales order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $order = SalesOrder::findOrFail($id);

            if (!in_array($order->status, ['pending', 'cancelled'])) {
                return response()->json([
                    'message' => 'Cannot delete order in current status',
                ], 400);
            }

            $order->items()->delete();
            $order->delete();

            return response()->json([
                'message' => 'Sales order deleted',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete sales order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function approve(Request $request, $id)
    {
        try {
            $request->validate([
                'notes' => 'nullable|string',
            ]);

            $order = SalesOrder::with('retailStore', 'items')->findOrFail($id);

            if ($order->status !== 'pending') {
                return response()->json([
                    'message' => 'Order is not in pending status',
                ], 400);
            }

            $store = $order->retailStore;
            $creditCheck = $store->checkCreditLimit($order->total);

            if (!$creditCheck['approved']) {
                return response()->json([
                    'message' => 'Order exceeds store credit limit',
                    'data' => $creditCheck,
                ], 400);
            }

            $order->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'approval_notes' => $request->notes,
            ]);

            $store->increment('current_balance', $order->total);

            return response()->json([
                'data' => $order->fresh()->load('retailStore', 'items.product', 'approvedBy'),
                'message' => 'Sales order approved',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to approve order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function assignRoute(Request $request, $id)
    {
        try {
            $request->validate([
                'route_id' => 'required|exists:routes,id',
            ]);

            $order = SalesOrder::findOrFail($id);

            if (!in_array($order->status, ['approved', 'assigned'])) {
                return response()->json([
                    'message' => 'Order must be approved before assigning a route',
                ], 400);
            }

            $order->update([
                'route_id' => $request->route_id,
                'status' => 'assigned',
            ]);

            return response()->json([
                'data' => $order->fresh()->load('route'),
                'message' => 'Route assigned to order',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to assign route',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function cancel(Request $request, $id)
    {
        try {
            $request->validate([
                'reason' => 'required|string',
            ]);

            $order = SalesOrder::findOrFail($id);

            if (in_array($order->status, ['delivered', 'cancelled'])) {
                return response()->json([
                    'message' => 'Cannot cancel order in current status',
                ], 400);
            }

            if ($order->status === 'approved') {
                $store = $order->retailStore;
                $store->decrement('current_balance', $order->total);
            }

            $order->update([
                'status' => 'cancelled',
                'notes' => $request->reason,
            ]);

            return response()->json([
                'data' => $order->fresh(),
                'message' => 'Sales order cancelled',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to cancel order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
