<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\RetailStore;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckStoreCredit
{
    public function handle(Request $request, Closure $next): Response
    {
        $storeId = $request->route('id')
            ?? $request->input('retail_store_id')
            ?? $request->input('store_id');

        $orderAmount = $request->input('total')
            ?? $request->input('amount')
            ?? 0;

        if (!$storeId) {
            return $next($request);
        }

        $store = RetailStore::find($storeId);
        if (!$store) {
            return $next($request);
        }

        $check = $store->checkCreditLimit((float) $orderAmount);

        if (!$check['approved']) {
            return response()->json([
                'message' => 'Order exceeds credit limit',
                'credit_check' => $check,
                'requires_manager_approval' => true,
            ], 422);
        }

        $request->merge(['credit_check' => $check]);

        return $next($request);
    }
}
