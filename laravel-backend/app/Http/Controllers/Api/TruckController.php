<?php

namespace App\Http\Controllers\Api;

use App\Models\Truck;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class TruckController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Truck::query();

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                      ->orWhere('plate_number', 'like', "%{$search}%")
                      ->orWhere('model', 'like', "%{$search}%");
                });
            }

            if ($request->filled('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $trucks = $query->orderBy('code')->paginate($request->per_page ?? 20);

            return response()->json([
                'data' => $trucks,
                'message' => 'Trucks retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve trucks',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'code' => 'required|string|max:50|unique:trucks,code',
                'plate_number' => 'required|string|max:20|unique:trucks,plate_number',
                'model' => 'required|string|max:100',
                'year' => 'nullable|integer|min:1990|max:2099',
                'capacity_weight' => 'nullable|numeric|min:0',
                'capacity_volume' => 'nullable|numeric|min:0',
                'status' => 'required|string|in:available,in_route,maintenance,out_of_service',
                'is_active' => 'boolean',
            ]);

            $truck = Truck::create($request->all());

            return response()->json([
                'data' => $truck,
                'message' => 'Truck created',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create truck',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $truck = Truck::with('inventory.product', 'inventory.batch')->findOrFail($id);

            return response()->json([
                'data' => $truck,
                'message' => 'Truck retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Truck not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $truck = Truck::findOrFail($id);

            $request->validate([
                'code' => 'sometimes|string|max:50|unique:trucks,code,' . $id,
                'plate_number' => 'sometimes|string|max:20|unique:trucks,plate_number,' . $id,
                'model' => 'sometimes|string|max:100',
                'year' => 'nullable|integer|min:1990|max:2099',
                'capacity_weight' => 'nullable|numeric|min:0',
                'capacity_volume' => 'nullable|numeric|min:0',
                'status' => 'sometimes|string|in:available,in_route,maintenance,out_of_service',
                'is_active' => 'boolean',
            ]);

            $truck->update($request->all());

            return response()->json([
                'data' => $truck->fresh(),
                'message' => 'Truck updated',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update truck',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $truck = Truck::findOrFail($id);

            if ($truck->inventory()->where('quantity', '>', 0)->exists()) {
                return response()->json([
                    'message' => 'Cannot delete truck with inventory',
                ], 409);
            }

            $truck->update(['is_active' => false]);

            return response()->json([
                'message' => 'Truck deactivated',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to deactivate truck',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
