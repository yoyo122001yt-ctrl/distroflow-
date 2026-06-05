<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Product::with('category')->where('is_active', true);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('name_ar', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%")
                      ->orWhere('barcode', 'like', "%{$search}%");
                });
            }

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->filled('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $products = $query->orderBy('name')->paginate($request->per_page ?? 20);

            return response()->json([
                'data' => $products,
                'message' => 'Products retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve products',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'category_id' => 'nullable|exists:product_categories,id',
                'name' => 'required|string|max:255',
                'name_ar' => 'nullable|string|max:255',
                'sku' => 'required|string|max:100|unique:products,sku',
                'barcode' => 'nullable|string|max:100',
                'description' => 'nullable|string',
                'description_ar' => 'nullable|string',
                'unit' => 'required|string|max:50',
                'cost_price' => 'required|numeric|min:0',
                'selling_price' => 'required|numeric|min:0',
                'weight' => 'nullable|numeric|min:0',
                'image' => 'nullable|string',
                'is_expiry_tracked' => 'boolean',
                'shelf_life_days' => 'nullable|integer|min:0',
                'min_stock_level' => 'nullable|numeric|min:0',
                'max_stock_level' => 'nullable|numeric|min:0',
                'is_active' => 'boolean',
            ]);

            $product = Product::create($request->all());

            return response()->json([
                'data' => $product->load('category'),
                'message' => 'Product created',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $product = Product::with('category')->findOrFail($id);

            return response()->json([
                'data' => $product,
                'message' => 'Product retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Product not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);

            $request->validate([
                'category_id' => 'nullable|exists:product_categories,id',
                'name' => 'sometimes|string|max:255',
                'name_ar' => 'nullable|string|max:255',
                'sku' => 'sometimes|string|max:100|unique:products,sku,' . $id,
                'barcode' => 'nullable|string|max:100',
                'description' => 'nullable|string',
                'description_ar' => 'nullable|string',
                'unit' => 'sometimes|string|max:50',
                'cost_price' => 'sometimes|numeric|min:0',
                'selling_price' => 'sometimes|numeric|min:0',
                'weight' => 'nullable|numeric|min:0',
                'image' => 'nullable|string',
                'is_expiry_tracked' => 'boolean',
                'shelf_life_days' => 'nullable|integer|min:0',
                'min_stock_level' => 'nullable|numeric|min:0',
                'max_stock_level' => 'nullable|numeric|min:0',
                'is_active' => 'boolean',
            ]);

            $product->update($request->all());

            return response()->json([
                'data' => $product->fresh()->load('category'),
                'message' => 'Product updated',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);
            $product->update(['is_active' => false]);

            return response()->json([
                'message' => 'Product deactivated',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to deactivate product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function batches($id)
    {
        try {
            $product = Product::findOrFail($id);

            $batches = $product->batches()
                ->with('warehouse', 'supplier')
                ->orderBy('expiry_date', 'asc')
                ->paginate(20);

            return response()->json([
                'data' => $batches,
                'message' => 'Product batches retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve batches',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
