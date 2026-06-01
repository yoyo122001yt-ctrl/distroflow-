<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthenticateByQueryToken
{
    public function handle(Request $request, Closure $next)
    {
        if ($token = $request->query('token')) {
            $accessToken = PersonalAccessToken::findToken($token);
            if ($accessToken) {
                $user = $accessToken->tokenable;
                if ($user) {
                    auth()->login($user);
                }
            }
        }

        return $next($request);
    }
}
