<?php

namespace App\Http\Controllers\Api;

use App\Models\RetailStore;
use App\Models\StorePrice;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class RetailStoreController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = RetailStore::query();

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('business_name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('city')) {
                $query->where('city', $request->city);
            }

            $stores = $query->orderBy('business_name')->paginate($request->per_page ?? 20);

            return response()->json([
                'data' => $stores,
                'message' => 'Stores retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve stores',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'code' => 'required|string|max:50|unique:retail_stores,code',
                'business_name' => 'required|string|max:255',
                'trade_name' => 'nullable|string|max:255',
                'store_type' => 'required|string|max:50',
                'contact_person' => 'nullable|string|max:255',
                'phone' => 'required|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'required|string',
                'city' => 'required|string|max:100',
                'state' => 'nullable|string|max:100',
                'zip' => 'nullable|string|max:20',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'credit_limit' => 'required|numeric|min:0',
                'current_balance' => 'nullable|numeric|min:0',
                'payment_terms' => 'nullable|string|max:50',
                'tax_id' => 'nullable|string|max:100',
                'status' => 'required|string|in:active,inactive,on_hold',
                'notes' => 'nullable|string',
                'warehouse_id' => 'nullable|exists:warehouses,id',
            ]);

            $store = RetailStore::create($request->all());

            return response()->json([
                'data' => $store,
                'message' => 'Store created',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create store',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $store = RetailStore::with(['prices.product', 'licenses'])->findOrFail($id);

            return response()->json([
                'data' => $store,
                'message' => 'Store retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Store not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $store = RetailStore::findOrFail($id);

            $request->validate([
                'code' => 'sometimes|string|max:50|unique:retail_stores,code,' . $id,
                'business_name' => 'sometimes|string|max:255',
                'trade_name' => 'nullable|string|max:255',
                'store_type' => 'sometimes|string|max:50',
                'contact_person' => 'nullable|string|max:255',
                'phone' => 'sometimes|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'sometimes|string',
                'city' => 'sometimes|string|max:100',
                'state' => 'nullable|string|max:100',
                'zip' => 'nullable|string|max:20',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'credit_limit' => 'sometimes|numeric|min:0',
                'payment_terms' => 'nullable|string|max:50',
                'tax_id' => 'nullable|string|max:100',
                'status' => 'sometimes|string|in:active,inactive,on_hold',
                'notes' => 'nullable|string',
                'warehouse_id' => 'nullable|exists:warehouses,id',
            ]);

            $store->update($request->all());

            return response()->json([
                'data' => $store->fresh(),
                'message' => 'Store updated',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update store',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $store = RetailStore::findOrFail($id);

            $store->orders()->whereIn('status', ['pending', 'approved'])->update(['status' => 'cancelled']);

            $store->delete();

            return response()->json([
                'message' => 'Store deleted',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete store',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function orders($id, Request $request)
    {
        try {
            $store = RetailStore::findOrFail($id);

            $orders = $store->orders()
                ->with('items.product', 'createdBy', 'route')
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 20);

            return response()->json([
                'data' => $orders,
                'message' => 'Store orders retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve orders',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function balance($id)
    {
        try {
            $store = RetailStore::findOrFail($id);

            $availableCredit = $store->credit_limit - $store->current_balance;

            return response()->json([
                'data' => [
                    'credit_limit' => (float) $store->credit_limit,
                    'current_balance' => (float) $store->current_balance,
                    'available_credit' => max(0, (float) $availableCredit),
                    'credit_used_percent' => $store->credit_limit > 0
                        ? round(($store->current_balance / $store->credit_limit) * 100, 2)
                        : 0,
                ],
                'message' => 'Balance retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve balance',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateCreditLimit(Request $request, $id)
    {
        try {
            $store = RetailStore::findOrFail($id);

            $request->validate([
                'credit_limit' => 'required|numeric|min:0',
            ]);

            $store->update(['credit_limit' => $request->credit_limit]);

            return response()->json([
                'data' => $store,
                'message' => 'Credit limit updated',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update credit limit',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function setPrices(Request $request, $id)
    {
        try {
            $store = RetailStore::findOrFail($id);

            $request->validate([
                'prices' => 'required|array|min:1',
                'prices.*.product_id' => 'required|exists:products,id',
                'prices.*.price' => 'required|numeric|min:0',
                'prices.*.discount_percent' => 'nullable|numeric|min:0|max:100',
                'prices.*.effective_from' => 'nullable|date',
                'prices.*.effective_to' => 'nullable|date',
            ]);

            $saved = [];

            foreach ($request->prices as $priceData) {
                $price = StorePrice::updateOrCreate(
                    [
                        'retail_store_id' => $store->id,
                        'product_id' => $priceData['product_id'],
                    ],
                    [
                        'price' => $priceData['price'],
                        'discount_percent' => $priceData['discount_percent'] ?? 0,
                        'effective_from' => $priceData['effective_from'] ?? now(),
                        'effective_to' => $priceData['effective_to'] ?? null,
                        'is_active' => true,
                    ]
                );

                $saved[] = $price;
            }

            return response()->json([
                'data' => $saved,
                'message' => 'Store prices updated',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update prices',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
