@extends('layouts.app')

@section('title', 'Store Dashboard')

@push('styles')
<style>
    .store-header { background: #2563eb; color: #fff; padding: 1.5rem 0; }
    .store-header h1 { font-size: 1.5rem; }
    .store-nav { display: flex; gap: 1rem; margin: 1rem 0; flex-wrap: wrap; }
    .store-nav a { background: #fff; padding: 0.5rem 1rem; border-radius: 0.5rem; color: #374151; font-weight: 500; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .store-nav a:hover { background: #f3f4f6; text-decoration: none; }
    .store-nav a.active { background: #2563eb; color: #fff; }
    .cart-badge { background: #ef4444; color: #fff; border-radius: 9999px; padding: 0.125rem 0.5rem; font-size: 0.75rem; margin-left: 0.25rem; }
    .card { background: #fff; border-radius: 0.5rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1rem; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin: 1rem 0; }
    .stat-card { background: #fff; border-radius: 0.5rem; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .stat-card h3 { color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem; }
    .stat-card .value { font-size: 1.75rem; font-weight: 700; }
    .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem; }
    .product-card { background: #fff; border-radius: 0.5rem; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center; }
    .product-card img { width: 100%; height: 140px; object-fit: cover; border-radius: 0.375rem; }
    .product-card h4 { font-size: 0.9rem; margin: 0.5rem 0; }
    .product-card .price { color: #2563eb; font-weight: 700; font-size: 1.1rem; }
    .order-list { list-style: none; }
    .order-list li { display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #f3f4f6; }
    .order-list li:last-child { border-bottom: none; }
    .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
    .badge-pending { background: #fef3c7; color: #92400e; }
    .badge-confirmed { background: #dbeafe; color: #1e40af; }
    .badge-shipped { background: #e0e7ff; color: #3730a3; }
    .badge-delivered { background: #d1fae5; color: #065f46; }
</style>
@endpush

@section('content')
<div class="store-header">
    <div class="container">
        <h1>Welcome, {{ Auth::user()->name }}</h1>
        <p>{{ $store?->business_name ?? 'Store' }}</p>
    </div>
</div>

<div class="container">
    <nav class="store-nav">
        <a href="{{ route('store.dashboard') }}" class="active">Dashboard</a>
        <a href="{{ route('store.products') }}">Products</a>
        <a href="{{ route('store.cart') }}">Cart @if($cartCount > 0)<span class="cart-badge">{{ $cartCount }}</span>@endif</a>
        <a href="{{ route('store.orders') }}">Orders</a>
        <a href="{{ route('store.profile') }}">Profile</a>
    </nav>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Orders</h3>
            <div class="value">{{ $recentOrders->count() }}</div>
        </div>
        <div class="stat-card">
            <h3>Pending</h3>
            <div class="value">{{ $recentOrders->where('status', 'pending')->count() }}</div>
        </div>
        <div class="stat-card">
            <h3>Delivered</h3>
            <div class="value">{{ $recentOrders->where('status', 'delivered')->count() }}</div>
        </div>
        <div class="stat-card">
            <h3>Cart Items</h3>
            <div class="value">{{ $cartCount }}</div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="card">
            <h2 style="margin-bottom: 1rem;">Recent Orders</h2>
            @if($recentOrders->count())
            <ul class="order-list">
                @foreach($recentOrders as $order)
                <li>
                    <div>
                        <strong>{{ $order->order_number }}</strong>
                        <div style="color: #6b7280; font-size: 0.875rem;">{{ $order->order_date->format('d M Y') }}</div>
                    </div>
                    <div style="text-align: right;">
                        <span class="badge badge-{{ $order->status }}">{{ ucfirst($order->status) }}</span>
                        <div style="font-weight: 600; margin-top: 0.25rem;">{{ number_format($order->total_amount, 2) }} EGP</div>
                    </div>
                </li>
                @endforeach
            </ul>
            <a href="{{ route('store.orders') }}" style="display: inline-block; margin-top: 0.5rem;">View All Orders &rarr;</a>
            @else
            <p style="color: #6b7280;">No orders yet.</p>
            @endif
        </div>

        <div class="card">
            <h2 style="margin-bottom: 1rem;">Popular Products</h2>
            @if($popularProducts->count())
            <div class="product-grid">
                @foreach($popularProducts as $product)
                <div class="product-card">
                    @if($product->image_url)
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}">
                    @endif
                    <h4>{{ \Illuminate\Support\Str::limit($product->name, 30) }}</h4>
                    <div class="price">{{ number_format($product->selling_price, 2) }} EGP</div>
                    <form action="{{ route('store.cart.add') }}" method="POST" style="margin-top: 0.5rem;">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <input type="hidden" name="quantity" value="1">
                        <button type="submit" style="background: #2563eb; color: #fff; border: none; padding: 0.375rem 1rem; border-radius: 0.375rem; cursor: pointer; font-size: 0.875rem;">Add to Cart</button>
                    </form>
                </div>
                @endforeach
            </div>
            @else
            <p style="color: #6b7280;">No products available yet.</p>
            @endif
        </div>
    </div>
</div>
@endsection
