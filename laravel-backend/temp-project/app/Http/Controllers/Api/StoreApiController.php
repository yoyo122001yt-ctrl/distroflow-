<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\Delivery;
use App\Services\StoreCartService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class StoreApiController extends Controller
{
    public function __construct(
        protected StoreCartService $cart
    ) {}

    public function products(Request $request)
    {
        $query = Product::where('is_active', true)->with('category');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")->orWhere('sku', 'like', "%{$s}%");
            });
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('min_price')) {
            $query->where('selling_price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('selling_price', '<=', $request->max_price);
        }

        $sortField = $request->sort_by ?? 'name';
        $sortDir = $request->sort_dir ?? 'asc';
        $products = $query->orderBy($sortField, $sortDir)->paginate($request->per_page ?? 20);

        return response()->json(['data' => $products, 'message' => 'Products retrieved']);
    }

    public function searchProducts(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2']);
        $products = Product::where('is_active', true)
            ->where(function ($q) use ($request) {
                $s = $request->q;
                $q->where('name', 'like', "%{$s}%")->orWhere('sku', 'like', "%{$s}%");
            })
            ->take(10)
            ->get();

        return response()->json(['data' => $products]);
    }

    public function categories()
    {
        $categories = ProductCategory::where('is_active', true)
            ->withCount('products')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $categories]);
    }

    public function popularProducts()
    {
        $products = Product::where('is_active', true)
            ->withSum('orderItems as total_sold', 'quantity')
            ->orderByDesc('total_sold')
            ->take(12)
            ->get();

        return response()->json(['data' => $products]);
    }

    public function recommendedProducts()
    {
        $user = auth()->user();
        $store = $user?->retailStore;

        $products = Product::where('is_active', true)
            ->whereDoesntHave('storePrices', fn($q) => $q->where('retail_store_id', $store?->id))
            ->inRandomOrder()
            ->take(8)
            ->get();

        return response()->json(['data' => $products]);
    }

    public function getCart()
    {
        return response()->json(['data' => $this->cart->getSummary()]);
    }

    public function updateCart(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:0|max:999',
        ]);

        $this->cart->clear();
        foreach ($request->items as $item) {
            if ($item['quantity'] > 0) {
                $this->cart->addItem($item['product_id'], $item['quantity']);
            }
        }

        return response()->json(['data' => $this->cart->getSummary(), 'message' => 'Cart updated']);
    }

    public function removeFromCart($id)
    {
        $this->cart->removeItem((int) $id);
        return response()->json(['data' => $this->cart->getSummary(), 'message' => 'Item removed']);
    }

    public function placeOrder(Request $request)
    {
        $request->validate([
            'delivery_date' => 'nullable|date|after:today',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        $store = $user->retailStore;

        if (!$store) {
            return response()->json(['message' => 'No store profile found'], 400);
        }

        $cart = $this->cart->getSummary();
        if (empty($cart['items'])) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        $order = SalesOrder::create([
            'retail_store_id' => $store->id,
            'user_id' => $user->id,
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'status' => 'pending',
            'order_date' => now(),
            'requested_delivery_date' => $request->delivery_date,
            'notes' => $request->notes,
            'total_amount' => $cart['total'],
        ]);

        foreach ($cart['items'] as $item) {
            $order->items()->create([
                'product_id' => $item['product_id'],
                'product_name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $item['subtotal'],
            ]);
        }

        $this->cart->clear();

        return response()->json([
            'data' => $order->load('items'),
            'message' => 'Order placed successfully',
        ], 201);
    }

    public function getOrders(Request $request)
    {
        $user = auth()->user();
        $orders = SalesOrder::where('retail_store_id', $user->retailStore?->id)
            ->withCount('items')
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json(['data' => $orders]);
    }

    public function getOrderDetail($id)
    {
        $user = auth()->user();
        $order = SalesOrder::where('retail_store_id', $user->retailStore?->id)
            ->with(['items.product', 'deliveries.driver'])
            ->findOrFail($id);

        return response()->json(['data' => $order]);
    }

    public function trackDelivery($id)
    {
        $user = auth()->user();
        $delivery = Delivery::where('retail_store_id', $user->retailStore?->id)
            ->with(['driver.driverProfile', 'driver.driverLocations' => function ($q) {
                $q->latest()->take(50);
            }, 'stops'])
            ->findOrFail($id);

        return response()->json(['data' => $delivery]);
    }
}
