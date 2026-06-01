<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\DriverProfile;
use App\Models\DriverSettlement;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DriverController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = User::where('role', 'driver')
                ->with('driverProfile');

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            if ($request->filled('status')) {
                $query->whereHas('driverProfile', function ($q) use ($request) {
                    $q->where('status', $request->status);
                });
            }

            if ($request->filled('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $drivers = $query->orderBy('name')->paginate($request->per_page ?? 20);

            return response()->json([
                'data' => $drivers,
                'message' => 'Drivers retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve drivers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:8',
                'phone' => 'nullable|string|max:20',
                'warehouse_id' => 'nullable|exists:warehouses,id',
                'license_number' => 'nullable|string|max:100',
                'license_expiry' => 'nullable|date',
                'date_of_birth' => 'nullable|date',
                'emergency_contact' => 'nullable|string|max:255',
                'emergency_phone' => 'nullable|string|max:20',
                'status' => 'required|string|in:available,busy,off_duty,terminated',
            ]);

            $driver = DB::transaction(function () use ($request) {
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => $request->password,
                    'phone' => $request->phone,
                    'role' => 'driver',
                    'is_active' => true,
                    'warehouse_id' => $request->warehouse_id,
                ]);

                DriverProfile::create([
                    'user_id' => $user->id,
                    'license_number' => $request->license_number,
                    'license_expiry' => $request->license_expiry,
                    'date_of_birth' => $request->date_of_birth,
                    'emergency_contact' => $request->emergency_contact,
                    'emergency_phone' => $request->emergency_phone,
                    'status' => $request->status,
                ]);

                return $user->load('driverProfile');
            });

            return response()->json([
                'data' => $driver,
                'message' => 'Driver created',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create driver',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $driver = User::where('role', 'driver')
                ->with('driverProfile', 'warehouse')
                ->findOrFail($id);

            return response()->json([
                'data' => $driver,
                'message' => 'Driver retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Driver not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $driver = User::where('role', 'driver')->findOrFail($id);

            $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $id,
                'phone' => 'nullable|string|max:20',
                'warehouse_id' => 'nullable|exists:warehouses,id',
                'is_active' => 'boolean',
                'license_number' => 'nullable|string|max:100',
                'license_expiry' => 'nullable|date',
                'date_of_birth' => 'nullable|date',
                'emergency_contact' => 'nullable|string|max:255',
                'emergency_phone' => 'nullable|string|max:20',
                'status' => 'sometimes|string|in:available,busy,off_duty,terminated',
            ]);

            DB::transaction(function () use ($request, $driver) {
                $userData = $request->only(['name', 'email', 'phone', 'warehouse_id', 'is_active']);
                if (!empty($userData)) {
                    $driver->update($userData);
                }

                if ($driver->driverProfile) {
                    $profileData = $request->only([
                        'license_number', 'license_expiry', 'date_of_birth',
                        'emergency_contact', 'emergency_phone', 'status',
                    ]);
                    if (!empty($profileData)) {
                        $driver->driverProfile->update($profileData);
                    }
                }
            });

            return response()->json([
                'data' => $driver->fresh()->load('driverProfile'),
                'message' => 'Driver updated',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update driver',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $driver = User::where('role', 'driver')->findOrFail($id);

            $driver->update(['is_active' => false]);

            if ($driver->driverProfile) {
                $driver->driverProfile->update(['status' => 'terminated']);
            }

            return response()->json([
                'message' => 'Driver deactivated',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to deactivate driver',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function settlements($id, Request $request)
    {
        try {
            $driver = User::where('role', 'driver')->findOrFail($id);

            $settlements = DriverSettlement::where('driver_id', $driver->id)
                ->with(['routeAssignment.route', 'delivery'])
                ->orderBy('settlement_date', 'desc')
                ->paginate($request->per_page ?? 20);

            return response()->json([
                'data' => $settlements,
                'message' => 'Driver settlements retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve settlements',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
