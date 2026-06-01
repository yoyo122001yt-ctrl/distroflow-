@extends('layouts.app')

@section('title', 'Track Delivery')

@push('styles')
<style>
    .store-header { background: #2563eb; color: #fff; padding: 1.5rem 0; }
    .store-nav { display: flex; gap: 1rem; margin: 1rem 0; flex-wrap: wrap; }
    .store-nav a { background: #fff; padding: 0.5rem 1rem; border-radius: 0.5rem; color: #374151; font-weight: 500; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .store-nav a:hover { background: #f3f4f6; text-decoration: none; }
    .card { background: #fff; border-radius: 0.5rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin: 1rem 0; }
    #map { height: 400px; border-radius: 0.5rem; margin: 1rem 0; }
    .driver-info { display: flex; align-items: center; gap: 1rem; padding: 1rem; background: #f9fafb; border-radius: 0.5rem; margin: 1rem 0; }
    .driver-info .avatar { width: 48px; height: 48px; border-radius: 50%; background: #2563eb; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.25rem; }
    .driver-info .name { font-weight: 600; }
    .driver-info .phone { color: #6b7280; font-size: 0.875rem; }
    .status-timeline { list-style: none; padding: 0; }
    .status-timeline li { display: flex; gap: 1rem; padding: 0.75rem 0; border-left: 2px solid #e5e7eb; padding-left: 1rem; margin-left: 0.5rem; position: relative; }
    .status-timeline li::before { content: ''; width: 10px; height: 10px; border-radius: 50%; background: #d1d5db; position: absolute; left: -6px; top: 1rem; }
    .status-timeline li.completed { border-left-color: #10b981; }
    .status-timeline li.completed::before { background: #10b981; }
    .status-timeline li.active { border-left-color: #2563eb; }
    .status-timeline li.active::before { background: #2563eb; }
    .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
</style>
@endpush

@section('content')
<div class="store-header">
    <div class="container">
        <h1>Track Delivery</h1>
        <p>Delivery #{{ $delivery->id }}</p>
    </div>
</div>

<div class="container">
    <nav class="store-nav">
        <a href="{{ route('store.dashboard') }}">Dashboard</a>
        <a href="{{ route('store.products') }}">Products</a>
        <a href="{{ route('store.cart') }}">Cart</a>
        <a href="{{ route('store.orders') }}">Orders</a>
        <a href="{{ route('store.profile') }}">Profile</a>
    </nav>

    @if($delivery->driver)
    <div class="driver-info">
        <div class="avatar">{{ strtoupper(substr($delivery->driver->name, 0, 1)) }}</div>
        <div>
            <div class="name">{{ $delivery->driver->name }}</div>
            <div class="phone">{{ $delivery->driver->phone ?? 'No phone' }}</div>
        </div>
    </div>
    @endif

    <div id="map">
        <div style="display: flex; height: 100%; align-items: center; justify-content: center; background: #f3f4f6; color: #6b7280;">
            Map will display here. Ensure Google Maps or Leaflet is configured.
        </div>
    </div>
    <div style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">
        @if($driverLocation)
        Last updated: {{ $driverLocation->created_at->diffForHumans() }}
        @else
        No location data available yet.
        @endif
    </div>

    <div class="card">
        <h3>Delivery Status</h3>
        <ul class="status-timeline">
            <li class="{{ in_array($delivery->status, ['assigned','in_transit','completed']) ? 'completed' : ($delivery->status === 'pending' ? 'active' : '') }}">
                <div>
                    <strong>Order Placed</strong>
                    <div style="font-size: 0.875rem; color: #6b7280;">{{ $delivery->created_at->format('d M Y h:i A') }}</div>
                </div>
            </li>
            <li class="{{ in_array($delivery->status, ['in_transit','completed']) ? 'completed' : ($delivery->status === 'assigned' ? 'active' : '') }}">
                <div>
                    <strong>Driver Assigned</strong>
                    @if($delivery->assigned_at)
                    <div style="font-size: 0.875rem; color: #6b7280;">{{ $delivery->assigned_at->format('d M Y h:i A') }}</div>
                    @endif
                </div>
            </li>
            <li class="{{ $delivery->status === 'completed' ? 'completed' : ($delivery->status === 'in_transit' ? 'active' : '') }}">
                <div>
                    <strong>In Transit</strong>
                    @if($delivery->started_at)
                    <div style="font-size: 0.875rem; color: #6b7280;">{{ $delivery->started_at->format('d M Y h:i A') }}</div>
                    @endif
                </div>
            </li>
            <li class="{{ $delivery->status === 'completed' ? 'completed' : '' }}">
                <div>
                    <strong>Delivered</strong>
                    @if($delivery->completed_at)
                    <div style="font-size: 0.875rem; color: #6b7280;">{{ $delivery->completed_at->format('d M Y h:i A') }}</div>
                    @endif
                </div>
            </li>
        </ul>
    </div>

    @if($delivery->stops->count())
    <div class="card">
        <h3>Route Stops</h3>
        <ol style="margin: 0; padding-left: 1.25rem;">
            @foreach($delivery->stops as $stop)
            <li style="padding: 0.5rem 0;">
                {{ $stop->address ?? 'Stop #' . $stop->id }}
                <span class="badge badge-{{ $stop->status }}">{{ ucfirst($stop->status) }}</span>
            </li>
            @endforeach
        </ol>
    </div>
    @endif

    <div style="margin: 1rem 0;">
        <a href="{{ route('store.order.detail', $delivery->sales_order_id) }}" style="color: #2563eb;">&larr; Back to Order</a>
    </div>
</div>
@endsection
