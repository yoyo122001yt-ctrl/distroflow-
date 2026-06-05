@extends('layouts.app')

@section('title', __('store.products'))

@push('styles')
<style>
    .store-header { background: #2563eb; color: #fff; padding: 1.5rem 0; }
    .store-nav { display: flex; gap: 1rem; margin: 1rem 0; flex-wrap: wrap; }
    .store-nav a { background: #fff; padding: 0.5rem 1rem; border-radius: 0.5rem; color: #374151; font-weight: 500; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .store-nav a:hover { background: #f3f4f6; text-decoration: none; }
    .store-nav a.active { background: #2563eb; color: #fff; }
    .cart-badge { background: #ef4444; color: #fff; border-radius: 9999px; padding: 0.125rem 0.5rem; font-size: 0.75rem; margin-left: 0.25rem; }
    .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.5rem; margin: 1.5rem 0; }
    .product-card { background: #fff; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; transition: box-shadow 0.2s; }
    .product-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    .product-card img { width: 100%; height: 180px; object-fit: cover; }
    .product-info { padding: 1rem; }
    .product-info h3 { font-size: 1rem; margin-bottom: 0.25rem; }
    .product-info .sku { font-size: 0.8rem; color: #6b7280; }
    .product-info .price { font-size: 1.25rem; font-weight: 700; color: #2563eb; margin: 0.5rem 0; }
    .product-info form { display: flex; gap: 0.5rem; }
    .product-info input[type=number] { width: 60px; padding: 0.375rem; border: 1px solid #d1d5db; border-radius: 0.375rem; }
    .product-info button { background: #2563eb; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 0.375rem; cursor: pointer; flex: 1; }
    .search-bar { display: flex; gap: 0.5rem; margin: 1rem 0; }
    .search-bar input { flex: 1; padding: 0.625rem; border: 1px solid #d1d5db; border-radius: 0.375rem; }
    .search-bar button { background: #2563eb; color: #fff; border: none; padding: 0.625rem 1.25rem; border-radius: 0.375rem; cursor: pointer; }
    .pagination { display: flex; justify-content: center; gap: 0.5rem; margin: 2rem 0; }
    .pagination a, .pagination span { padding: 0.5rem 0.75rem; border-radius: 0.375rem; background: #fff; border: 1px solid #d1d5db; color: #374151; }
    .pagination a:hover { background: #f3f4f6; text-decoration: none; }
</style>
@endpush

@section('content')
<div class="store-header">
    <div class="container">
        <h1>{{ __('store.products') }}</h1>
        <p>{{ __('store.productSubtitle') }}</p>
    </div>
</div>

<div class="container">
    <nav class="store-nav">
        <a href="{{ route('store.dashboard') }}">{{ __('store.dashboard') }}</a>
        <a href="{{ route('store.products') }}" class="active">{{ __('store.products') }}</a>
        <a href="{{ route('store.cart') }}">{{ __('store.cart') }} @if($cartCount > 0)<span class="cart-badge">{{ $cartCount }}</span>@endif</a>
        <a href="{{ route('store.orders') }}">{{ __('store.orders') }}</a>
        <a href="{{ route('store.profile') }}">{{ __('store.profile') }}</a>
    </nav>

    <form class="search-bar" method="GET" action="{{ route('store.products') }}">
        <input type="text" name="search" placeholder="{{ __('store.searchProducts') }}" value="{{ request('search') }}">
        <button type="submit">{{ __('store.search') }}</button>
    </form>

    @if(session('success'))
    <div style="background: #d1fae5; color: #065f46; padding: 0.75rem; border-radius: 0.375rem; margin: 1rem 0;">{{ session('success') }}</div>
    @endif

    @if($products->count())
    <div class="products-grid">
        @foreach($products as $product)
        <div class="product-card">
            @if($product->image_url)
            <img src="{{ $product->image_url }}" alt="{{ $product->name }}">
            @endif
            <div class="product-info">
                <h3>{{ $product->name }}</h3>
                <div class="sku">{{ $product->sku }}</div>
                <div class="price">{{ number_format($product->selling_price, 2) }} EGP</div>
                <form action="{{ route('store.cart.add') }}" method="POST">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="number" name="quantity" value="1" min="1" max="999">
                    <button type="submit">{{ __('store.addToCart') }}</button>
                </form>
            </div>
        </div>
        @endforeach
    </div>

    <div class="pagination">
        {{ $products->links() }}
    </div>
    @else
    <p style="text-align: center; color: #6b7280; margin: 3rem 0;">{{ __('store.noProductsFound') }}</p>
    @endif
</div>
@endsection
