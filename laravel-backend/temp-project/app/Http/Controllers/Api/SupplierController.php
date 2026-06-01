<?php

namespace App\Http\Controllers\Api;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Supplier::query();

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('business_name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $suppliers = $query->orderBy('business_name')->paginate($request->per_page ?? 20);

            return response()->json([
                'data' => $suppliers,
                'message' => 'Suppliers retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve suppliers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'code' => 'required|string|max:50|unique:suppliers,code',
                'business_name' => 'required|string|max:255',
                'contact_person' => 'nullable|string|max:255',
                'phone' => 'required|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'tax_id' => 'nullable|string|max:100',
                'payment_terms' => 'nullable|string|max:50',
                'status' => 'required|string|in:active,inactive',
                'notes' => 'nullable|string',
            ]);

            $supplier = Supplier::create($request->all());

            return response()->json([
                'data' => $supplier,
                'message' => 'Supplier created',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create supplier',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $supplier = Supplier::with('purchaseOrders')->findOrFail($id);

            return response()->json([
                'data' => $supplier,
                'message' => 'Supplier retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Supplier not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $supplier = Supplier::findOrFail($id);

            $request->validate([
                'code' => 'sometimes|string|max:50|unique:suppliers,code,' . $id,
                'business_name' => 'sometimes|string|max:255',
                'contact_person' => 'nullable|string|max:255',
                'phone' => 'sometimes|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'tax_id' => 'nullable|string|max:100',
                'payment_terms' => 'nullable|string|max:50',
                'status' => 'sometimes|string|in:active,inactive',
                'notes' => 'nullable|string',
            ]);

            $supplier->update($request->all());

            return response()->json([
                'data' => $supplier->fresh(),
                'message' => 'Supplier updated',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update supplier',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $supplier = Supplier::findOrFail($id);

            if ($supplier->purchaseOrders()->whereIn('status', ['pending', 'partial'])->exists()) {
                return response()->json([
                    'message' => 'Cannot delete supplier with active purchase orders',
                ], 409);
            }

            $supplier->update(['status' => 'inactive']);

            return response()->json([
                'message' => 'Supplier deactivated',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to deactivate supplier',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
