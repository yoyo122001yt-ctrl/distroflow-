<?php

namespace App\Http\Controllers\Api;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = PurchaseOrder::with('supplier', 'warehouse', 'createdBy');

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
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
                'message' => 'Purchase orders retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve purchase orders',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'supplier_id' => 'required|exists:suppliers,id',
                'warehouse_id' => 'required|exists:warehouses,id',
                'order_date' => 'required|date',
                'expected_date' => 'nullable|date',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity_ordered' => 'required|numeric|min:0.01',
                'items.*.unit_cost' => 'required|numeric|min:0',
                'items.*.expiry_date' => 'nullable|date',
                'subtotal' => 'required|numeric|min:0',
                'tax' => 'nullable|numeric|min:0',
                'total' => 'required|numeric|min:0',
                'notes' => 'nullable|string',
            ]);

            $order = DB::transaction(function () use ($request) {
                $orderNumber = 'PO-' . strtoupper(uniqid());

                $order = PurchaseOrder::create([
                    'order_number' => $orderNumber,
                    'supplier_id' => $request->supplier_id,
                    'warehouse_id' => $request->warehouse_id,
                    'created_by' => $request->user()->id,
                    'order_date' => $request->order_date,
                    'expected_date' => $request->expected_date,
                    'status' => 'pending',
                    'subtotal' => $request->subtotal,
                    'tax' => $request->tax ?? 0,
                    'total' => $request->total,
                    'notes' => $request->notes,
                ]);

                foreach ($request->items as $item) {
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'quantity_ordered' => $item['quantity_ordered'],
                        'quantity_received' => 0,
                        'unit_cost' => $item['unit_cost'],
                        'total_cost' => $item['quantity_ordered'] * $item['unit_cost'],
                        'expiry_date' => $item['expiry_date'] ?? null,
                    ]);
                }

                return $order->load('items.product', 'supplier', 'warehouse');
            });

            return response()->json([
                'data' => $order,
                'message' => 'Purchase order created',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create purchase order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $order = PurchaseOrder::with([
                'supplier',
                'warehouse',
                'createdBy',
                'items.product',
                'batches',
            ])->findOrFail($id);

            return response()->json([
                'data' => $order,
                'message' => 'Purchase order retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Purchase order not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $order = PurchaseOrder::findOrFail($id);

            if (!in_array($order->status, ['pending', 'partial'])) {
                return response()->json([
                    'message' => 'Cannot modify purchase order in current status',
                ], 400);
            }

            $request->validate([
                'supplier_id' => 'sometimes|exists:suppliers,id',
                'warehouse_id' => 'sometimes|exists:warehouses,id',
                'order_date' => 'sometimes|date',
                'expected_date' => 'nullable|date',
                'notes' => 'nullable|string',
            ]);

            $order->update($request->all());

            return response()->json([
                'data' => $order->fresh()->load('items.product', 'supplier'),
                'message' => 'Purchase order updated',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update purchase order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $order = PurchaseOrder::findOrFail($id);

            if ($order->status !== 'pending') {
                return response()->json([
                    'message' => 'Cannot delete purchase order that has been processed',
                ], 400);
            }

            $order->items()->delete();
            $order->delete();

            return response()->json([
                'message' => 'Purchase order deleted',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete purchase order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function receive(Request $request, $id)
    {
        try {
            $request->validate([
                'items' => 'required|array|min:1',
                'items.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.batch_number' => 'nullable|string|max:100',
                'items.*.manufacturing_date' => 'nullable|date',
                'items.*.expiry_date' => 'nullable|date',
                'items.*.cost_price' => 'nullable|numeric|min:0',
            ]);

            $result = DB::transaction(function () use ($request, $id) {
                $purchaseOrder = PurchaseOrder::with('items')->findOrFail($id);

                if ($purchaseOrder->status === 'received') {
                    throw new \RuntimeException('Purchase order is already fully received');
                }

                $createdBatches = [];

                foreach ($request->items as $item) {
                    $poItem = PurchaseOrderItem::findOrFail($item['purchase_order_item_id']);

                    $newReceived = $poItem->quantity_received + $item['quantity'];

                    if ($newReceived > $poItem->quantity_ordered) {
                        throw new \RuntimeException(
                            "Quantity received ({$newReceived}) exceeds ordered quantity ({$poItem->quantity_ordered})"
                        );
                    }

                    $batch = Batch::create([
                        'product_id' => $poItem->product_id,
                        'warehouse_id' => $purchaseOrder->warehouse_id,
                        'batch_number' => $item['batch_number'] ?? ('BATCH-' . strtoupper(uniqid())),
                        'manufacturing_date' => $item['manufacturing_date'] ?? null,
                        'expiry_date' => $item['expiry_date'] ?? null,
                        'quantity' => $item['quantity'],
                        'available_quantity' => $item['quantity'],
                        'cost_price' => $item['cost_price'] ?? $poItem->unit_cost,
                        'supplier_id' => $purchaseOrder->supplier_id,
                        'received_date' => now(),
                        'purchase_order_id' => $purchaseOrder->id,
                        'status' => 'available',
                    ]);

                    $poItem->update(['quantity_received' => $newReceived]);

                    $createdBatches[] = $batch;
                }

                $allReceived = $purchaseOrder->items->every(function ($item) {
                    return $item->quantity_received >= $item->quantity_ordered;
                });

                $purchaseOrder->update([
                    'status' => $allReceived ? 'received' : 'partial',
                ]);

                return [
                    'purchase_order' => $purchaseOrder->fresh()->load('items', 'batches'),
                    'batches' => $createdBatches,
                ];
            });

            return response()->json([
                'data' => $result,
                'message' => 'Purchase order received successfully',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to receive purchase order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
