<?php

namespace App\Http\Controllers\Api;

use App\Models\Batch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Models\PickList;
use App\Models\PickListItem;
use App\Models\LoadList;
use App\Models\LoadListItem;
use App\Models\OrderItem;
use App\Models\WarehouseStockMovement;
use App\Services\FEFOService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseInventoryController extends Controller
{
    protected FEFOService $fefo;

    public function __construct(FEFOService $fefo)
    {
        $this->fefo = $fefo;
    }

    public function receive(Request $request)
    {
        try {
            $request->validate([
                'purchase_order_id' => 'required|exists:purchase_orders,id',
                'items' => 'required|array|min:1',
                'items.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.batch_number' => 'nullable|string|max:100',
                'items.*.manufacturing_date' => 'nullable|date',
                'items.*.expiry_date' => 'nullable|date',
                'items.*.cost_price' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string',
            ]);

            $result = DB::transaction(function () use ($request) {
                $purchaseOrder = PurchaseOrder::with('items')->findOrFail($request->purchase_order_id);
                $createdBatches = [];

                foreach ($request->items as $item) {
                    $poItem = PurchaseOrderItem::findOrFail($item['purchase_order_item_id']);

                    $newReceived = $poItem->quantity_received + $item['quantity'];

                    if ($newReceived > $poItem->quantity_ordered) {
                        throw new \RuntimeException(
                            "Quantity received ({$newReceived}) exceeds ordered quantity ({$poItem->quantity_ordered}) for item #{$poItem->id}"
                        );
                    }

                    $batch = Batch::create([
                        'product_id' => $poItem->product_id,
                        'warehouse_id' => $purchaseOrder->warehouse_id,
                        'batch_number' => $item['batch_number'] ?? ('BATCH-' . strtoupper(uniqid())),
                        'manufacturing_date' => $item['manufacturing_date'] ?? null,
                        'expiry_date' => $item['expiry_date'] ?? now()->addDays(60)->format('Y-m-d'),
                        'quantity' => $item['quantity'],
                        'available_quantity' => $item['quantity'],
                        'cost_price' => $item['cost_price'] ?? $poItem->unit_cost,
                        'supplier_id' => $purchaseOrder->supplier_id,
                        'received_date' => now(),
                        'purchase_order_id' => $purchaseOrder->id,
                        'status' => 'available',
                    ]);

                    $poItem->update([
                        'quantity_received' => $newReceived,
                    ]);

                    $createdBatches[] = $batch;
                }

                $purchaseOrder->load('items');

                $allReceived = $purchaseOrder->items->every(function ($item) {
                    return $item->quantity_received >= $item->quantity_ordered;
                });

                if ($allReceived) {
                    $purchaseOrder->update(['status' => 'received']);
                } else {
                    $purchaseOrder->update(['status' => 'partial']);
                }

                return $createdBatches;
            });

            return response()->json([
                'data' => $result,
                'message' => 'Stock received successfully',
            ], 201);
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
                'message' => 'Failed to receive stock',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function inventory(Request $request)
    {
        try {
            $query = Batch::with(['product', 'warehouse', 'supplier'])
                ->where('status', 'available')
                ->where('available_quantity', '>', 0);

            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            if ($request->filled('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('product', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('name_ar', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
                });
            }

            if ($request->filled('expiry_before')) {
                $query->where('expiry_date', '<=', $request->expiry_before);
            }

            $batches = $query->orderBy('expiry_date', 'asc')
                ->paginate($request->per_page ?? 20);

            return response()->json([
                'data' => $batches,
                'message' => 'Inventory retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve inventory',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function pick(Request $request)
    {
        try {
            $request->validate([
                'warehouse_id' => 'required|exists:warehouses,id',
                'route_id' => 'required|exists:routes,id',
                'items' => 'required|array|min:1',
                'items.*.order_item_id' => 'required|exists:order_items,id',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|numeric|min:0.01',
            ]);

            $result = DB::transaction(function () use ($request) {
                $pickList = PickList::create([
                    'pick_list_number' => 'PICK-' . strtoupper(uniqid()),
                    'warehouse_id' => $request->warehouse_id,
                    'route_id' => $request->route_id,
                    'status' => 'open',
                    'notes' => $request->notes,
                ]);

                $pickItems = [];

                foreach ($request->items as $item) {
                    $orderItem = OrderItem::findOrFail($item['order_item_id']);

                    $batches = $this->fefo->getPickingBatches($item['product_id'], $item['quantity']);

                    foreach ($batches as $pick) {
                        $pickListItem = PickListItem::create([
                            'pick_list_id' => $pickList->id,
                            'sales_order_id' => $orderItem->sales_order_id,
                            'order_item_id' => $orderItem->id,
                            'product_id' => $item['product_id'],
                            'batch_id' => $pick->batch_id,
                            'quantity' => $pick->quantity,
                            'status' => 'pending',
                        ]);

                        $pickItems[] = $pickListItem;
                    }

                    $orderItem->update([
                        'quantity_picked' => $item['quantity'],
                        'status' => 'picked',
                    ]);
                }

                $pickList->load('items.product', 'items.batch');

                return $pickList;
            });

            return response()->json([
                'data' => $result,
                'message' => 'Pick list generated',
            ], 201);
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
                'message' => 'Failed to generate pick list',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function load(Request $request)
    {
        try {
            $request->validate([
                'route_id' => 'required|exists:routes,id',
                'truck_id' => 'required|exists:trucks,id',
                'warehouse_id' => 'required|exists:warehouses,id',
                'pick_list_id' => 'required|exists:pick_lists,id',
                'items' => 'required|array|min:1',
                'items.*.pick_list_item_id' => 'required|exists:pick_list_items,id',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.batch_id' => 'required|exists:batches,id',
                'items.*.quantity' => 'required|numeric|min:0.01',
            ]);

            $result = DB::transaction(function () use ($request) {
                $loadList = LoadList::create([
                    'load_list_number' => 'LOAD-' . strtoupper(uniqid()),
                    'route_id' => $request->route_id,
                    'truck_id' => $request->truck_id,
                    'warehouse_id' => $request->warehouse_id,
                    'status' => 'loaded',
                    'loaded_by' => $request->user()->id,
                    'loaded_at' => now(),
                ]);

                foreach ($request->items as $item) {
                    LoadListItem::create([
                        'load_list_id' => $loadList->id,
                        'pick_list_item_id' => $item['pick_list_item_id'],
                        'product_id' => $item['product_id'],
                        'batch_id' => $item['batch_id'],
                        'quantity' => $item['quantity'],
                        'status' => 'loaded',
                    ]);

                    $this->fefo->consumeBatch($item['batch_id'], $item['quantity']);
                }

                PickList::where('id', $request->pick_list_id)->update(['status' => 'loaded']);

                $loadList->load('items.product', 'items.batch', 'truck');

                return $loadList;
            });

            return response()->json([
                'data' => $result,
                'message' => 'Truck loaded successfully',
            ], 201);
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
                'message' => 'Failed to load truck',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function adjust(Request $request)
    {
        try {
            $request->validate([
                'quantity' => 'required|numeric|min:0',
                'reason' => 'required|string',
                'type' => 'required|in:add,remove,damage',
            ]);

            $result = DB::transaction(function () use ($request) {
                $batch = Batch::findOrFail($request->route('id'));
                $oldQuantity = $batch->available_quantity;
                $adjustQty = $request->quantity;

                if ($request->type === 'add') {
                    $newQuantity = $oldQuantity + $adjustQty;
                    $movementType = 'addition';
                } else {
                    $newQuantity = max(0, $oldQuantity - $adjustQty);
                    $movementType = $request->type === 'damage' ? 'damage' : 'removal';
                }

                $batch->update([
                    'available_quantity' => $newQuantity,
                    'status' => $newQuantity > 0 ? 'available' : 'depleted',
                    'notes' => $request->reason,
                ]);

                WarehouseStockMovement::create([
                    'batch_id' => $batch->id,
                    'product_id' => $batch->product_id,
                    'movement_type' => $movementType,
                    'quantity' => $adjustQty,
                    'quantity_before' => $oldQuantity,
                    'quantity_after' => $newQuantity,
                    'reference_type' => 'adjustment',
                    'reference_id' => $batch->id,
                    'notes' => $request->reason,
                    'created_by' => $request->user()->id,
                ]);

                return $batch->fresh()->load('product', 'warehouse');
            });

            return response()->json([
                'data' => $result,
                'message' => 'Stock adjusted successfully',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to adjust stock',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function expiring(Request $request)
    {
        try {
            $days = $request->integer('days', 30);

            $expiring = $this->fefo->getExpiringBatches($days);

            return response()->json([
                'data' => $expiring,
                'meta' => [
                    'days' => $days,
                    'total_quantity' => $expiring->sum('available_quantity'),
                ],
                'message' => 'Expiring products retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve expiring products',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
