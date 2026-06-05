<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return redirect('http://localhost:5173/login');
    }

    public function login(Request $request)
    {
        return redirect('http://localhost:5173/login');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('http://localhost:5173/login')->setStatusCode(303);
    }
}
