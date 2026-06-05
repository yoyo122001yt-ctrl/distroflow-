@extends('layouts.app')

@section('title', __('store.orderNumber') . ' #' . $order->order_number)

@push('styles')
<style>
    .store-header { background: #2563eb; color: #fff; padding: 1.5rem 0; }
    .store-nav { display: flex; gap: 1rem; margin: 1rem 0; flex-wrap: wrap; }
    .store-nav a { background: #fff; padding: 0.5rem 1rem; border-radius: 0.5rem; color: #374151; font-weight: 500; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .store-nav a:hover { background: #f3f4f6; text-decoration: none; }
    .card { background: #fff; border-radius: 0.5rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin: 1rem 0; }
    .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .detail-grid dt { color: #6b7280; font-size: 0.875rem; }
    .detail-grid dd { font-weight: 600; margin-bottom: 0.75rem; }
    .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
    .badge-pending { background: #fef3c7; color: #92400e; }
    .badge-confirmed { background: #dbeafe; color: #1e40af; }
    .badge-shipped, .badge-in_transit { background: #e0e7ff; color: #3730a3; }
    .badge-delivered { background: #d1fae5; color: #065f46; }
    .badge-cancelled { background: #fee2e2; color: #991b1b; }
    .items-table { width: 100%; border-collapse: collapse; }
    .items-table th { text-align: left; padding: 0.5rem 0.75rem; border-bottom: 2px solid #f3f4f6; font-size: 0.875rem; color: #6b7280; }
    .items-table td { padding: 0.75rem; border-bottom: 1px solid #f3f4f6; }
    .total-row td { font-weight: 700; font-size: 1.1rem; border-bottom: none; padding-top: 1rem; }
</style>
@endpush

@section('content')
<div class="store-header">
    <div class="container">
        <h1>{{ __('store.orderNumber') }} {{ $order->order_number }}</h1>
    </div>
</div>

<div class="container">
    <nav class="store-nav">
        <a href="{{ route('store.dashboard') }}">{{ __('store.dashboard') }}</a>
        <a href="{{ route('store.products') }}">{{ __('store.products') }}</a>
        <a href="{{ route('store.cart') }}">{{ __('store.cart') }}</a>
        <a href="{{ route('store.orders') }}">{{ __('store.orders') }}</a>
        <a href="{{ route('store.profile') }}">{{ __('store.profile') }}</a>
    </nav>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
            <h2>{{ __('store.orderDetails') }}</h2>
            <span class="badge badge-{{ $order->status }}">{{ ucfirst($order->status) }}</span>
        </div>

        <dl class="detail-grid">
            <div><dt>{{ __('store.orderNumber') }}</dt><dd>{{ $order->order_number }}</dd></div>
            <div><dt>{{ __('store.orderDate') }}</dt><dd>{{ $order->order_date->format('d M Y, h:i A') }}</dd></div>
            <div><dt>{{ __('store.totalAmount') }}</dt><dd>{{ number_format($order->total_amount, 2) }} EGP</dd></div>
            <div><dt>{{ __('store.requestedDelivery') }}</dt><dd>{{ $order->requested_delivery_date ? $order->requested_delivery_date->format('d M Y') : __('store.notSpecified') }}</dd></div>
            @if($order->notes)
            <div style="grid-column: 1 / -1;"><dt>{{ __('store.notesLabel') }}</dt><dd>{{ $order->notes }}</dd></div>
            @endif
        </dl>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 1rem;">{{ __('store.items') }}</h3>
        <table class="items-table">
            <thead>
                <tr><th>{{ __('store.product') }}</th><th>{{ __('store.quantity') }}</th><th>{{ __('store.unitPrice') }}</th><th>{{ __('store.subtotal') }}</th></tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format($item->unit_price, 2) }} EGP</td>
                    <td>{{ number_format($item->subtotal, 2) }} EGP</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3" style="text-align: right;">{{ __('store.totalColon') }}</td>
                    <td>{{ number_format($order->total_amount, 2) }} EGP</td>
                </tr>
            </tbody>
        </table>
    </div>

    @if($order->deliveries->count())
    <div class="card">
        <h3 style="margin-bottom: 1rem;">{{ __('store.deliveries') }}</h3>
        @foreach($order->deliveries as $delivery)
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid #f3f4f6;">
            <div>
                <strong>{{ __('store.deliveryNo', ['id' => $delivery->id]) }}</strong>
                <div style="font-size: 0.875rem; color: #6b7280;">{{ $delivery->created_at->format('d M Y') }}</div>
            </div>
            <div style="text-align: right;">
                <span class="badge badge-{{ $delivery->status }}">{{ ucfirst($delivery->status) }}</span>
                <div style="margin-top: 0.25rem;">
                    <a href="{{ route('store.track', $delivery->id) }}" style="font-size: 0.875rem;">{{ __('store.track') }}</a>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <div style="margin: 1rem 0;">
        <a href="{{ route('store.orders') }}" style="color: #2563eb;">{{ __('store.backToOrders') }}</a>
    </div>
</div>
@endsection
