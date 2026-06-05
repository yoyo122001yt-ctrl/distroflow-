<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    protected array $headers = [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(self)',
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        foreach ($this->headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        $csp = $this->buildCsp();
        $response->headers->set('Content-Security-Policy', $csp);

        if ($request->isSecure() || env('APP_ENV') === 'production') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }

    protected function buildCsp(): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://*.googleapis.com https://*.pusher.com",
            "style-src 'self' 'unsafe-inline' https://*.googleapis.com",
            "img-src 'self' data: blob: https://*.googleapis.com https://*.openstreetmap.org",
            "font-src 'self' https://*.gstatic.com",
            "connect-src 'self' https://*.pusher.com ws://*.pusher.com wss://*.pusher.com ws://localhost:*",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        return implode('; ', $directives);
    }
}
