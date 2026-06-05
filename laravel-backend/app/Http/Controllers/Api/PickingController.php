<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PickingController extends Controller
{
    public function index()
    {
        return response()->json(['data' => [], 'message' => 'Pick lists retrieved']);
    }

    public function show($id)
    {
        return response()->json(['data' => null, 'message' => 'Pick list not found'], 404);
    }

    public function generate()
    {
        return response()->json(['data' => null, 'message' => 'No orders to generate pick lists']);
    }

    public function start($id)
    {
        return response()->json(['data' => null, 'message' => 'Pick list not found'], 404);
    }

    public function pickItem($pickId, $itemId)
    {
        return response()->json(['data' => null, 'message' => 'Pick item not found'], 404);
    }

    public function complete($id)
    {
        return response()->json(['data' => null, 'message' => 'Pick list not found'], 404);
    }
}
