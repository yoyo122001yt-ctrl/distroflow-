<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\DriverProfile;
use App\Models\DriverSettlement;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
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
                'email' => 'nullable|email|unique:users,email',
                'phone' => 'nullable|string|max:20',
                'license_number' => 'nullable|string|max:100',
                'vehicle_plate' => 'nullable|string|max:50',
                'pay_rate' => 'nullable|numeric|min:0',
                'pay_type' => 'nullable|string|in:per_delivery,hourly,salary',
                'status' => 'required|string|in:active,inactive',
            ]);

            $driver = DB::transaction(function () use ($request) {
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email ?? $request->name . '@driver.local',
                    'password' => Hash::make('password123'),
                    'phone' => $request->phone,
                    'role' => 'driver',
                    'is_active' => $request->status === 'active',
                ]);

                DriverProfile::create([
                    'user_id' => $user->id,
                    'license_number' => $request->license_number,
                    'vehicle_plate' => $request->vehicle_plate,
                    'pay_rate' => $request->pay_rate,
                    'pay_type' => $request->pay_type,
                    'status' => $request->status === 'active' ? 'available' : 'off_duty',
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
                'email' => 'nullable|email|unique:users,email,' . $id,
                'phone' => 'nullable|string|max:20',
                'license_number' => 'nullable|string|max:100',
                'vehicle_plate' => 'nullable|string|max:50',
                'pay_rate' => 'nullable|numeric|min:0',
                'pay_type' => 'nullable|string|in:per_delivery,hourly,salary',
                'status' => 'sometimes|string|in:active,inactive',
            ]);

            DB::transaction(function () use ($request, $driver) {
                $userData = $request->only(['name', 'email', 'phone']);
                if ($request->has('status')) {
                    $userData['is_active'] = $request->status === 'active';
                }
                if (!empty($userData)) {
                    $driver->update($userData);
                }

                if ($driver->driverProfile) {
                    $profileData = $request->only([
                        'license_number', 'vehicle_plate', 'pay_rate', 'pay_type',
                    ]);
                    if ($request->has('status')) {
                        $profileData['status'] = $request->status === 'active' ? 'available' : 'off_duty';
                    }
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
