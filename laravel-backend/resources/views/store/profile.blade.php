@extends('layouts.app')

@section('title', __('store.profile'))

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
        <h1>{{ __('store.profileSettings') }}</h1>
    </div>
</div>

<div class="container">
    <nav class="store-nav">
        <a href="{{ route('store.dashboard') }}">{{ __('store.dashboard') }}</a>
        <a href="{{ route('store.products') }}">{{ __('store.products') }}</a>
        <a href="{{ route('store.cart') }}">{{ __('store.cart') }}</a>
        <a href="{{ route('store.orders') }}">{{ __('store.orders') }}</a>
        <a href="{{ route('store.profile') }}" class="active">{{ __('store.profile') }}</a>
    </nav>

    @if(session('success'))
    <div style="background: #d1fae5; color: #065f46; padding: 0.75rem; border-radius: 0.375rem; margin: 1rem 0;">{{ session('success') }}</div>
    @endif

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="card">
            <h2 style="margin-bottom: 1rem;">{{ __('store.accountInformation') }}</h2>
            <form action="{{ route('store.profile.update') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label>{{ __('store.nameLabel') }}</label>
                    <input type="text" name="name" value="{{ $user->name }}" required>
                </div>
                <div class="form-group">
                    <label>{{ __('store.emailLabel') }}</label>
                    <input type="email" name="email" value="{{ $user->email }}" required>
                </div>
                <div class="form-group">
                    <label>{{ __('store.phoneLabel') }}</label>
                    <input type="text" name="phone" value="{{ $user->phone ?? $store->phone ?? '' }}">
                </div>
                <div class="form-group">
                    <label>{{ __('store.addressLabel') }}</label>
                    <textarea name="address">{{ $store->address ?? '' }}</textarea>
                </div>
                <button type="submit" class="btn-primary">{{ __('store.saveChanges') }}</button>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-bottom: 1rem;">{{ __('store.notificationPreferences') }}</h2>
            <form action="{{ route('store.profile.update') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label>{{ __('store.whatsappLabel') }}</label>
                    <input type="text" name="whatsapp_phone" value="{{ $store->whatsapp_phone ?? '' }}" placeholder="+201234567890">
                </div>
                <div class="form-group">
                    <label>{{ __('store.smsLabel') }}</label>
                    <input type="text" name="sms_phone" value="{{ $store->sms_phone ?? '' }}" placeholder="+201234567890">
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="notify_sms" value="1" {{ $store->notification_preferences ? (json_decode($store->notification_preferences, true)['sms'] ?? false ? 'checked' : '') : 'checked' }}>
                        {{ __('store.smsNotifications') }}
                    </label>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="notify_whatsapp" value="1" {{ $store->notification_preferences ? (json_decode($store->notification_preferences, true)['whatsapp'] ?? false ? 'checked' : '') : 'checked' }}>
                        {{ __('store.whatsappNotifications') }}
                    </label>
                </div>
                <button type="submit" class="btn-primary">{{ __('store.savePreferences') }}</button>
            </form>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-bottom: 1rem;">{{ __('store.storeInformation') }}</h2>
        @if($store)
        <dl style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
            <dt style="color: #6b7280;">{{ __('store.businessName') }}</dt><dd>{{ $store->business_name }}</dd>
            <dt style="color: #6b7280;">{{ __('store.storeType') }}</dt><dd>{{ ucfirst($store->store_type) }}</dd>
            <dt style="color: #6b7280;">{{ __('store.creditLimit') }}</dt><dd>{{ number_format($store->credit_limit ?? 0, 2) }} EGP</dd>
            <dt style="color: #6b7280;">{{ __('store.currentBalance') }}</dt><dd>{{ number_format($store->balance ?? 0, 2) }} EGP</dd>
        </dl>
        @else
        <p style="color: #6b7280;">{{ __('store.noStoreLinked') }}</p>
        @endif
    </div>
</div>
@endsection
