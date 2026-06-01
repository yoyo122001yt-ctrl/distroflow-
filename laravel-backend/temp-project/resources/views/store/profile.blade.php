@extends('layouts.app')

@section('title', 'Profile')

@push('styles')
<style>
    .store-header { background: #2563eb; color: #fff; padding: 1.5rem 0; }
    .store-nav { display: flex; gap: 1rem; margin: 1rem 0; flex-wrap: wrap; }
    .store-nav a { background: #fff; padding: 0.5rem 1rem; border-radius: 0.5rem; color: #374151; font-weight: 500; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .store-nav a:hover { background: #f3f4f6; text-decoration: none; }
    .store-nav a.active { background: #2563eb; color: #fff; }
    .card { background: #fff; border-radius: 0.5rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin: 1rem 0; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-size: 0.875rem; color: #374151; font-weight: 500; margin-bottom: 0.375rem; }
    .form-group input, .form-group textarea { width: 100%; padding: 0.625rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.9rem; }
    .form-group textarea { resize: vertical; min-height: 80px; }
    .btn-primary { background: #2563eb; color: #fff; border: none; padding: 0.625rem 1.5rem; border-radius: 0.375rem; cursor: pointer; font-weight: 600; }
    .btn-primary:hover { background: #1d4ed8; }
    .notification-toggle { display: flex; align-items: center; gap: 0.5rem; }
    .notification-toggle input[type=checkbox] { width: 1.25rem; height: 1.25rem; }
</style>
@endpush

@section('content')
<div class="store-header">
    <div class="container">
        <h1>Profile Settings</h1>
    </div>
</div>

<div class="container">
    <nav class="store-nav">
        <a href="{{ route('store.dashboard') }}">Dashboard</a>
        <a href="{{ route('store.products') }}">Products</a>
        <a href="{{ route('store.cart') }}">Cart</a>
        <a href="{{ route('store.orders') }}">Orders</a>
        <a href="{{ route('store.profile') }}" class="active">Profile</a>
    </nav>

    @if(session('success'))
    <div style="background: #d1fae5; color: #065f46; padding: 0.75rem; border-radius: 0.375rem; margin: 1rem 0;">{{ session('success') }}</div>
    @endif

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="card">
            <h2 style="margin-bottom: 1rem;">Account Information</h2>
            <form action="{{ route('store.profile.update') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" value="{{ $user->name }}" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="{{ $user->email }}" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" value="{{ $user->phone ?? $store->phone ?? '' }}">
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address">{{ $store->address ?? '' }}</textarea>
                </div>
                <button type="submit" class="btn-primary">Save Changes</button>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-bottom: 1rem;">Notification Preferences</h2>
            <form action="{{ route('store.profile.update') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label>WhatsApp Number (for delivery updates)</label>
                    <input type="text" name="whatsapp_phone" value="{{ $store->whatsapp_phone ?? '' }}" placeholder="+201234567890">
                </div>
                <div class="form-group">
                    <label>SMS Number (for delivery updates)</label>
                    <input type="text" name="sms_phone" value="{{ $store->sms_phone ?? '' }}" placeholder="+201234567890">
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="notify_sms" value="1" {{ $store->notification_preferences ? (json_decode($store->notification_preferences, true)['sms'] ?? false ? 'checked' : '') : 'checked' }}>
                        SMS Notifications
                    </label>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="notify_whatsapp" value="1" {{ $store->notification_preferences ? (json_decode($store->notification_preferences, true)['whatsapp'] ?? false ? 'checked' : '') : 'checked' }}>
                        WhatsApp Notifications
                    </label>
                </div>
                <button type="submit" class="btn-primary">Save Preferences</button>
            </form>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-bottom: 1rem;">Store Information</h2>
        @if($store)
        <dl style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
            <dt style="color: #6b7280;">Business Name</dt><dd>{{ $store->business_name }}</dd>
            <dt style="color: #6b7280;">Store Type</dt><dd>{{ ucfirst($store->store_type) }}</dd>
            <dt style="color: #6b7280;">Credit Limit</dt><dd>{{ number_format($store->credit_limit ?? 0, 2) }} EGP</dd>
            <dt style="color: #6b7280;">Current Balance</dt><dd>{{ number_format($store->balance ?? 0, 2) }} EGP</dd>
        </dl>
        @else
        <p style="color: #6b7280;">No store profile linked.</p>
        @endif
    </div>
</div>
@endsection
