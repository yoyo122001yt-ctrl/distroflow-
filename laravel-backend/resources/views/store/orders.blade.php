@extends('layouts.app')

@section('title', __('store.myOrders'))

@push('styles')
<style>
    .store-header { background: #2563eb; color: #fff; padding: 1.5rem 0; }
    .store-nav { display: flex; gap: 1rem; margin: 1rem 0; flex-wrap: wrap; }
    .store-nav a { background: #fff; padding: 0.5rem 1rem; border-radius: 0.5rem; color: #374151; font-weight: 500; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .store-nav a:hover { background: #f3f4f6; text-decoration: none; }
    .store-nav a.active { background: #2563eb; color: #fff; }
    .cart-badge { background: #ef4444; color: #fff; border-radius: 9999px; padding: 0.125rem 0.5rem; font-size: 0.75rem; margin-left: 0.25rem; }
    .order-card { background: #fff; border-radius: 0.5rem; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin: 1rem 0; }
    .order-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
    .order-card-header h3 { font-size: 1.1rem; }
    .order-meta { display: flex; gap: 1rem; font-size: 0.875rem; color: #6b7280; }
    .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
    .badge-pending { background: #fef3c7; color: #92400e; }
    .badge-confirmed { background: #dbeafe; color: #1e40af; }
    .badge-shipped { background: #e0e7ff; color: #3730a3; }
    .badge-delivered { background: #d1fae5; color: #065f46; }
    .badge-cancelled { background: #fee2e2; color: #991b1b; }
    .pagination { display: flex; justify-content: center; gap: 0.5rem; margin: 2rem 0; }
    .pagination a, .pagination span { padding: 0.5rem 0.75rem; border-radius: 0.375rem; background: #fff; border: 1px solid #d1d5db; color: #374151; }
    .pagination a:hover { background: #f3f4f6; text-decoration: none; }
</style>
@endpush

@section('content')
<div class="store-header">
    <div class="container">
        <h1>{{ __('store.myOrders') }}</h1>
    </div>
</div>

<div class="container">
    <nav class="store-nav">
        <a href="{{ route('store.dashboard') }}">{{ __('store.dashboard') }}</a>
        <a href="{{ route('store.products') }}">{{ __('store.products') }}</a>
        <a href="{{ route('store.cart') }}">{{ __('store.cart') }}</a>
        <a href="{{ route('store.orders') }}" class="active">{{ __('store.orders') }}</a>
        <a href="{{ route('store.profile') }}">{{ __('store.profile') }}</a>
    </nav>

    @if($orders->count())
    @foreach($orders as $order)
    <a href="{{ route('store.order.detail', $order->id) }}" style="display: block; text-decoration: none; color: inherit;">
        <div class="order-card">
            <div class="order-card-header">
                <h3>{{ $order->order_number }}</h3>
                <span class="badge badge-{{ $order->status }}">{{ ucfirst($order->status) }}</span>
            </div>
            <div class="order-meta">
                <span>{{ $order->order_date->format('d M Y, h:i A') }}</span>
                <span>{{ $order->items_count }} item(s)</span>
                <span style="font-weight: 600; color: #111827;">{{ number_format($order->total_amount, 2) }} EGP</span>
            </div>
        </div>
    </a>
    @endforeach

    <div class="pagination">
        {{ $orders->links() }}
    </div>
    @else
    <p style="text-align: center; color: #6b7280; margin: 3rem 0;">{!! __('store.noOrdersHint', ['link' => '<a href="'.route('store.products').'">'.__('store.browseProductsLink').'</a>']) !!}</p>
    @endif
</div>
@endsection
