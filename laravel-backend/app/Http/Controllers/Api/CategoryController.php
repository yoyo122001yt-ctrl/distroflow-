<?php

namespace App\Http\Controllers\Api;

use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index()
    {
        try {
            $categories = ProductCategory::where('is_active', true)
                ->withCount('products')
                ->orderBy('name')
                ->get();

            return response()->json([
                'data' => $categories,
                'message' => 'Categories retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve categories',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'name_ar' => 'nullable|string|max:255',
            ]);

            $slug = Str::slug($request->name);

            if (ProductCategory::where('slug', $slug)->exists()) {
                $slug = $slug . '-' . uniqid();
            }

            $category = ProductCategory::create([
                'name' => $request->name,
                'name_ar' => $request->name_ar,
                'slug' => $slug,
                'is_active' => true,
            ]);

            return response()->json([
                'data' => $category,
                'message' => 'Category created',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create category',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
