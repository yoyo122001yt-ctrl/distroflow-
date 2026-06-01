<?php

namespace App\Http\Controllers\Api;

use App\Models\DriverSettlement;
use App\Services\DriverSettlementService;
use App\Http\Requests\SettlementRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettlementController extends Controller
{
    protected DriverSettlementService $settlementService;

    public function __construct(DriverSettlementService $settlementService)
    {
        $this->settlementService = $settlementService;
    }

    public function index(Request $request)
    {
        try {
            $query = DriverSettlement::with('driver', 'routeAssignment.route', 'delivery');

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('driver_id')) {
                $query->where('driver_id', $request->driver_id);
            }

            if ($request->filled('date_from')) {
                $query->where('settlement_date', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('settlement_date', '<=', $request->date_to);
            }

            if ($request->boolean('flagged_only')) {
                $query->where('status', 'flagged');
            }

            $settlements = $query->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 20);

            return response()->json([
                'data' => $settlements,
                'message' => 'Settlements retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve settlements',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(SettlementRequest $request)
    {
        try {
            $request->validate([
                'delivery_id' => 'required|exists:deliveries,id',
            ]);

            $settlement = $this->settlementService->calculateSettlement($request->delivery_id);

            if ($request->has('actual_cash')) {
                $settlement->update([
                    'actual_cash' => $request->actual_cash,
                    'cash_variance' => $settlement->expected_cash - $request->actual_cash,
                ]);
            }

            return response()->json([
                'data' => $settlement->load('driver', 'routeAssignment.route', 'delivery'),
                'message' => 'Settlement created',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create settlement',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $settlement = DriverSettlement::with([
                'driver',
                'routeAssignment.route',
                'routeAssignment.driver',
                'delivery',
                'approvedBy',
            ])->findOrFail($id);

            return response()->json([
                'data' => $settlement,
                'message' => 'Settlement retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Settlement not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $settlement = DriverSettlement::findOrFail($id);

            if ($settlement->status === 'approved') {
                return response()->json([
                    'message' => 'Cannot modify an approved settlement',
                ], 400);
            }

            $request->validate([
                'actual_cash' => 'sometimes|numeric|min:0',
                'notes' => 'nullable|string',
            ]);

            $updateData = $request->only(['actual_cash', 'notes']);

            if ($request->has('actual_cash')) {
                $updateData['cash_variance'] = $settlement->expected_cash - $request->actual_cash;
            }

            $settlement->update($updateData);

            return response()->json([
                'data' => $settlement->fresh(),
                'message' => 'Settlement updated',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update settlement',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $settlement = DriverSettlement::findOrFail($id);

            if ($settlement->status === 'approved') {
                return response()->json([
                    'message' => 'Cannot delete an approved settlement',
                ], 400);
            }

            $settlement->delete();

            return response()->json([
                'message' => 'Settlement deleted',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete settlement',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function approve(Request $request, $id)
    {
        try {
            $settlement = DriverSettlement::findOrFail($id);

            if ($settlement->status === 'approved') {
                return response()->json([
                    'message' => 'Settlement is already approved',
                ], 400);
            }

            $settlement = $this->settlementService->approveSettlement($id, $request->user()->id);

            return response()->json([
                'data' => $settlement->fresh()->load('approvedBy'),
                'message' => 'Settlement approved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to approve settlement',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function reject(Request $request, $id)
    {
        try {
            $settlement = DriverSettlement::findOrFail($id);

            if ($settlement->status === 'rejected') {
                return response()->json([
                    'message' => 'Settlement is already rejected',
                ], 400);
            }

            $settlement->update([
                'status' => 'rejected',
                'notes' => $request->reason ?? 'Rejected',
            ]);

            return response()->json([
                'data' => $settlement->fresh(),
                'message' => 'Settlement rejected',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reject settlement',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function generate(Request $request)
    {
        try {
            return response()->json([
                'data' => ['count' => 0],
                'message' => 'Settlement generation not yet implemented',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate settlements',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function unreconciled(Request $request)
    {
        try {
            $query = DriverSettlement::with('driver', 'routeAssignment.route')
                ->whereIn('status', ['pending', 'flagged']);

            if ($request->filled('driver_id')) {
                $query->where('driver_id', $request->driver_id);
            }

            $settlements = $query->orderBy('settlement_date', 'desc')->get();

            return response()->json([
                'data' => $settlements,
                'meta' => [
                    'total_count' => $settlements->count(),
                    'flagged_count' => $settlements->where('status', 'flagged')->count(),
                    'pending_count' => $settlements->where('status', 'pending')->count(),
                ],
                'message' => 'Unreconciled settlements retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve unreconciled settlements',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
