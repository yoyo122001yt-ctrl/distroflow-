<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Delivery;
use App\Services\StoreCartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StorePortalController extends Controller
{
    public function __construct(
        protected StoreCartService $cart
    ) {
        $this->middleware(['auth', 'role:store']);
    }

    public function dashboard()
    {
        $user = Auth::user();
        $store = $user->retailStore;

        $recentOrders = SalesOrder::where('retail_store_id', $store?->id)
            ->latest()
            ->take(5)
            ->get();

        $popularProducts = Product::where('is_active', true)
            ->withSum('orderItems as total_sold', 'quantity')
            ->orderByDesc('total_sold')
            ->take(8)
            ->get();

        $cartCount = $this->cart->count();

        return view('store.dashboard', compact('store', 'recentOrders', 'popularProducts', 'cartCount'));
    }

    public function products(Request $request)
    {
        $query = Product::where('is_active', true);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $request->category));
        }

        $products = $query->paginate(20);
        $cartCount = $this->cart->count();

        return view('store.products', compact('products', 'cartCount'));
    }

    public function cart()
    {
        $cart = $this->cart->getSummary();
        return view('store.cart', compact('cart'));
    }

    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:999',
            'price' => 'nullable|numeric|min:0',
        ]);

        $summary = $this->cart->addItem(
            $request->product_id,
            $request->quantity,
            $request->price
        );

        if ($request->wantsJson()) {
            return response()->json($summary);
        }

        return back()->with('success', 'Product added to cart');
    }

    public function removeFromCart(Request $request)
    {
        $request->validate(['product_id' => 'required|integer']);
        $this->cart->removeItem($request->product_id);

        if ($request->wantsJson()) {
            return response()->json($this->cart->getSummary());
        }

        return back()->with('success', 'Product removed from cart');
    }

    public function updateCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer',
            'quantity' => 'required|integer|min:0|max:999',
        ]);

        $this->cart->updateQuantity($request->product_id, $request->quantity);

        if ($request->wantsJson()) {
            return response()->json($this->cart->getSummary());
        }

        return back()->with('success', 'Cart updated');
    }

    public function placeOrder(Request $request)
    {
        $request->validate([
            'delivery_date' => 'nullable|date|after:today',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        $store = $user->retailStore;
        $cart = $this->cart->getSummary();

        if (empty($cart['items'])) {
            return back()->with('error', 'Cart is empty');
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

        if ($request->wantsJson()) {
            return response()->json(['order_id' => $order->id, 'message' => 'Order placed'], 201);
        }

        return redirect()->route('store.order.detail', $order->id)
            ->with('success', 'Order placed successfully');
    }

    public function orders()
    {
        $user = Auth::user();
        $orders = SalesOrder::where('retail_store_id', $user->retailStore?->id)
            ->latest()
            ->paginate(15);

        return view('store.orders', compact('orders'));
    }

    public function orderDetail($id)
    {
        $user = Auth::user();
        $order = SalesOrder::where('retail_store_id', $user->retailStore?->id)
            ->with(['items.product', 'deliveries'])
            ->findOrFail($id);

        return view('store.order-detail', compact('order'));
    }

    public function trackDelivery($deliveryId)
    {
        $user = Auth::user();
        $delivery = Delivery::where('retail_store_id', $user->retailStore?->id)
            ->with(['driver', 'salesOrder', 'stops'])
            ->findOrFail($deliveryId);

        $driverLocation = $delivery->driver?->driverLocations()
            ->latest()
            ->first();

        return view('store.track', compact('delivery', 'driverLocation'));
    }

    public function profile()
    {
        $user = Auth::user();
        $store = $user->retailStore;
        return view('store.profile', compact('user', 'store'));
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $store = $user->retailStore;

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'whatsapp_phone' => 'nullable|string|max:20',
            'sms_phone' => 'nullable|string|max:20',
            'notification_preferences' => 'nullable|json',
        ]);

        $user->update($request->only(['name', 'email', 'phone']));

        if ($store) {
            $store->update($request->only([
                'address', 'whatsapp_phone', 'sms_phone', 'notification_preferences'
            ]));
        }

        return back()->with('success', 'Profile updated successfully');
    }
}
