<?php

namespace App\Http\Controllers\Api;

use App\Models\Warehouse;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Warehouse::where('is_active', true);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            }

            $warehouses = $query->orderBy('name')->paginate($request->per_page ?? 50);

            return response()->json([
                'data' => $warehouses,
                'message' => 'Warehouses retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve warehouses',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
