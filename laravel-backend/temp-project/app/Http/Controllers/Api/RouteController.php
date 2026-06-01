<?php

namespace App\Http\Controllers\Api;

use App\Models\Route;
use App\Models\RouteStop;
use App\Models\RouteAssignment;
use App\Models\RetailStore;
use App\Models\User;
use App\Models\Truck;
use App\Services\RouteOptimizationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RouteController extends Controller
{
    protected RouteOptimizationService $optimizationService;

    public function __construct(RouteOptimizationService $optimizationService)
    {
        $this->optimizationService = $optimizationService;
    }

    public function index(Request $request)
    {
        try {
            $query = Route::with('warehouse');

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            }

            $routes = $query->orderBy('name')->paginate($request->per_page ?? 20);

            return response()->json([
                'data' => $routes,
                'message' => 'Routes retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve routes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'code' => 'required|string|max:50|unique:routes,code',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'warehouse_id' => 'required|exists:warehouses,id',
                'status' => 'required|string|in:active,inactive',
            ]);

            $route = Route::create($request->all());

            return response()->json([
                'data' => $route->load('warehouse'),
                'message' => 'Route created',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create route',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $route = Route::with([
                'warehouse',
                'stops.retailStore',
                'assignments.driver',
                'assignments.truck',
            ])->findOrFail($id);

            return response()->json([
                'data' => $route,
                'message' => 'Route retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Route not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $route = Route::findOrFail($id);

            $request->validate([
                'code' => 'sometimes|string|max:50|unique:routes,code,' . $id,
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'warehouse_id' => 'sometimes|exists:warehouses,id',
                'status' => 'sometimes|string|in:active,inactive',
            ]);

            $route->update($request->all());

            return response()->json([
                'data' => $route->fresh()->load('warehouse'),
                'message' => 'Route updated',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update route',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $route = Route::findOrFail($id);

            if ($route->stops()->exists()) {
                return response()->json([
                    'message' => 'Cannot delete route with existing stops',
                ], 409);
            }

            $route->delete();

            return response()->json([
                'message' => 'Route deleted',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete route',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function stops($id)
    {
        try {
            $route = Route::findOrFail($id);

            $stops = $route->stops()->with('retailStore')->orderBy('stop_order')->get();

            return response()->json([
                'data' => $stops,
                'message' => 'Route stops retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve stops',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function optimize($id)
    {
        try {
            $route = Route::findOrFail($id);
            $optimized = $this->optimizationService->optimize($route->id);

            if ($optimized->isEmpty()) {
                return response()->json([
                    'message' => 'No stops to optimize',
                ], 200);
            }

            DB::transaction(function () use ($optimized) {
                foreach ($optimized as $stop) {
                    RouteStop::where('id', $stop['stop_id'])->update([
                        'stop_order' => $stop['stop_order'],
                    ]);
                }
            });

            $stops = $route->stops()->with('retailStore')->orderBy('stop_order')->get();

            return response()->json([
                'data' => $stops,
                'message' => 'Route optimized successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to optimize route',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function assign(Request $request, $id)
    {
        try {
            $request->validate([
                'driver_id' => 'required|exists:users,id',
                'truck_id' => 'required|exists:trucks,id',
                'assignment_date' => 'required|date',
            ]);

            $route = Route::findOrFail($id);
            $driver = User::findOrFail($request->driver_id);
            $truck = Truck::findOrFail($request->truck_id);

            if (!$driver->isDriver()) {
                return response()->json([
                    'message' => 'Selected user is not a driver',
                ], 400);
            }

            $assignment = DB::transaction(function () use ($route, $request) {
                return RouteAssignment::create([
                    'route_id' => $route->id,
                    'driver_id' => $request->driver_id,
                    'truck_id' => $request->truck_id,
                    'assignment_date' => $request->assignment_date,
                    'status' => 'scheduled',
                ]);
            });

            return response()->json([
                'data' => $assignment->load('driver', 'truck', 'route'),
                'message' => 'Driver and truck assigned to route',
            ], 201);
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

    public function manifest($id)
    {
        try {
            $route = Route::with([
                'warehouse',
                'stops.retailStore',
                'assignments.driver',
                'assignments.truck',
            ])->findOrFail($id);

            $stops = $route->stops->map(function ($stop) {
                $store = $stop->retailStore;
                return [
                    'stop_order' => $stop->stop_order,
                    'store_name' => $store?->business_name,
                    'store_code' => $store?->code,
                    'address' => $store?->address,
                    'city' => $store?->city,
                    'phone' => $store?->phone,
                    'contact_person' => $store?->contact_person,
                ];
            });

            $assignment = $route->assignments->first();

            $manifest = [
                'route' => $route,
                'driver' => $assignment?->driver,
                'truck' => $assignment?->truck,
                'warehouse' => $route->warehouse,
                'stops' => $stops,
                'total_stops' => $stops->count(),
                'generated_at' => now()->toDateTimeString(),
            ];

            return response()->json([
                'data' => $manifest,
                'message' => 'Route manifest generated',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate manifest',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
