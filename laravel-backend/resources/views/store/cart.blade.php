@extends('layouts.app')

@section('title', __('store.cart'))

@push('styles')
<style>
    .store-header { background: #2563eb; color: #fff; padding: 1.5rem 0; }
    .store-nav { display: flex; gap: 1rem; margin: 1rem 0; flex-wrap: wrap; }
    .store-nav a { background: #fff; padding: 0.5rem 1rem; border-radius: 0.5rem; color: #374151; font-weight: 500; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .store-nav a:hover { background: #f3f4f6; text-decoration: none; }
    .store-nav a.active { background: #2563eb; color: #fff; }
    .cart-badge { background: #ef4444; color: #fff; border-radius: 9999px; padding: 0.125rem 0.5rem; font-size: 0.75rem; margin-left: 0.25rem; }
    .cart-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 0.5rem; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .cart-table th { background: #f9fafb; padding: 0.75rem 1rem; text-align: left; font-size: 0.875rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
    .cart-table td { padding: 1rem; border-top: 1px solid #f3f4f6; }
    .cart-table input[type=number] { width: 70px; padding: 0.375rem; border: 1px solid #d1d5db; border-radius: 0.375rem; }
    .cart-table .remove { color: #ef4444; cursor: pointer; background: none; border: none; font-size: 0.875rem; }
    .cart-summary { background: #fff; border-radius: 0.5rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-top: 1rem; }
    .cart-summary .total { font-size: 1.5rem; font-weight: 700; color: #2563eb; }
    .btn-primary { background: #2563eb; color: #fff; border: none; padding: 0.75rem 2rem; border-radius: 0.375rem; cursor: pointer; font-size: 1rem; font-weight: 600; }
    .btn-primary:hover { background: #1d4ed8; }
    .btn-secondary { background: #6b7280; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 0.375rem; cursor: pointer; }
    .empty-cart { text-align: center; padding: 3rem; color: #6b7280; }
</style>
@endpush

@section('content')
<div class="store-header">
    <div class="container">
        <h1>{{ __('store.shoppingCart') }}</h1>
    </div>
</div>

<div class="container">
    <nav class="store-nav">
        <a href="{{ route('store.dashboard') }}">{{ __('store.dashboard') }}</a>
        <a href="{{ route('store.products') }}">{{ __('store.products') }}</a>
        <a href="{{ route('store.cart') }}" class="active">{{ __('store.cart') }} @if($cart['count'] > 0)<span class="cart-badge">{{ $cart['count'] }}</span>@endif</a>
        <a href="{{ route('store.orders') }}">{{ __('store.orders') }}</a>
        <a href="{{ route('store.profile') }}">{{ __('store.profile') }}</a>
    </nav>

    @if(session('success'))
    <div style="background: #d1fae5; color: #065f46; padding: 0.75rem; border-radius: 0.375rem; margin: 1rem 0;">{{ session('success') }}</div>
    @endif

    @if(count($cart['items']))
    <form action="{{ route('store.order.place') }}" method="POST">
        @csrf
        <table class="cart-table">
            <thead>
                <tr>
                    <th>{{ __('store.product') }}</th>
                    <th>{{ __('store.price') }}</th>
                    <th>{{ __('store.quantity') }}</th>
                    <th>{{ __('store.subtotal') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($cart['items'] as $item)
                <tr>
                    <td>
                        <strong>{{ $item['name'] }}</strong>
                        <div style="color: #6b7280; font-size: 0.875rem;">{{ $item['sku'] }}</div>
                    </td>
                    <td>{{ number_format($item['unit_price'], 2) }} EGP</td>
                    <td>
                        <form action="{{ route('store.cart.update') }}" method="POST" style="display: flex; gap: 0.25rem; align-items: center;">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                            <input type="number" name="quantity" value="{{ $item['quantity'] }}" min="0" max="999" onchange="this.form.submit()">
                        </form>
                    </td>
                    <td>{{ number_format($item['subtotal'], 2) }} EGP</td>
                    <td>
                        <form action="{{ route('store.cart.remove') }}" method="POST">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                            <button class="remove" type="submit">{{ __('store.remove') }}</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="cart-summary">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-size: 0.875rem; color: #6b7280;">{{ __('store.total') }}</div>
                    <div class="total">{{ number_format($cart['total'], 2) }} EGP</div>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <div>
                        <label style="font-size: 0.875rem; color: #6b7280;">{{ __('store.deliveryDateOptional') }}</label>
                        <input type="date" name="delivery_date" min="{{ date('Y-m-d', strtotime('+1 day')) }}" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                    </div>
                    <div>
                        <label style="font-size: 0.875rem; color: #6b7280;">{{ __('store.notes') }}</label>
                        <input type="text" name="notes" placeholder="{{ __('store.optionalNotes') }}" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                    </div>
                    <button type="submit" class="btn-primary">{{ __('store.placeOrder') }}</button>
                </div>
            </div>
        </div>
    </form>
    @else
    <div class="empty-cart">
        <h2>{{ __('store.cartEmpty') }}</h2>
        <p>{{ __('store.cartEmptyHint') }}</p>
        <a href="{{ route('store.products') }}" class="btn-primary" style="display: inline-block; margin-top: 1rem; text-decoration: none;">{{ __('store.browseProducts') }}</a>
    </div>
    @endif
</div>
@endsection
