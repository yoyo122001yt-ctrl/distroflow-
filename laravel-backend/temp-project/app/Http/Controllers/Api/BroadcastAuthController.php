<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BroadcastAuthController extends Controller
{
    public function authenticate(Request $request)
    {
        $request->validate([
            'channel_name' => 'required|string',
            'socket_id' => 'required|string',
        ]);

        $user = $request->user();

        $channelName = $request->channel_name;

        if (str_starts_with($channelName, 'private-store.')) {
            $storeId = explode('.', $channelName)[1] ?? null;
            if (!$storeId || $user->retailStore?->id != $storeId) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        if (str_starts_with($channelName, 'private-driver.')) {
            $driverId = explode('.', $channelName)[1] ?? null;
            if (!$driverId || $user->id != $driverId) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $broadcast = \Illuminate\Support\Facades\Broadcast::auth($request);

        return $broadcast;
    }
}
