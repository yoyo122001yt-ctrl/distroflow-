<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LoadingController extends Controller
{
    public function index()
    {
        return response()->json(['data' => [], 'message' => 'Load lists retrieved']);
    }

    public function store(Request $request)
    {
        return response()->json(['data' => null, 'message' => 'Load list created'], 201);
    }

    public function start($id)
    {
        return response()->json(['data' => null, 'message' => 'Load list not found'], 404);
    }

    public function complete($id)
    {
        return response()->json(['data' => null, 'message' => 'Load list not found'], 404);
    }
}
