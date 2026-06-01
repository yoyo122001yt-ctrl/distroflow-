@extends('layouts.app')

@section('title', 'Login')

@section('content')
<style>
    .login-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #1e40af, #3b82f6); padding: 1rem; }
    .login-card { background: #fff; border-radius: 16px; padding: 2.5rem; width: 100%; max-width: 400px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
    .login-card h1 { font-size: 1.5rem; font-weight: 700; text-align: center; margin-bottom: 0.25rem; color: #111827; }
    .login-card p { text-align: center; color: #6b7280; margin-bottom: 2rem; font-size: 0.875rem; }
    .form-group { margin-bottom: 1.25rem; }
    .form-group label { display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.375rem; }
    .form-group input { width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; transition: border-color 0.15s; }
    .form-group input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
    .btn-login { width: 100%; padding: 0.75rem; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.15s; }
    .btn-login:hover { background: #1d4ed8; }
    .error { background: #fef2f2; color: #dc2626; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.875rem; border: 1px solid #fecaca; }
    .logo { text-align: center; margin-bottom: 1.5rem; }
    .logo .icon { width: 48px; height: 48px; background: #2563eb; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; }
    .logo .icon span { color: #fff; font-weight: 800; font-size: 1.25rem; }
    .demo-hint { text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb; font-size: 0.75rem; color: #9ca3af; }
    .demo-hint code { background: #f3f4f6; padding: 0.125rem 0.375rem; border-radius: 4px; font-size: 0.7rem; }
</style>
<div class="login-page">
    <div class="login-card">
        <div class="logo">
            <div class="icon"><span>DF</span></div>
        </div>
        <h1>Welcome to DistroFlow</h1>
        <p>Sign in to continue</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first('email') }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus placeholder="driver@distroflow.com">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required placeholder="Enter password">
            </div>
            <button type="submit" class="btn-login">Sign In</button>
        </form>

        <div class="demo-hint">
            Demo driver: <code>driver1@distroflow.com</code> / <code>password</code><br>
            Demo manager: <code>admin@distroflow.com</code> / <code>password</code>
        </div>
    </div>
</div>
@endsection
