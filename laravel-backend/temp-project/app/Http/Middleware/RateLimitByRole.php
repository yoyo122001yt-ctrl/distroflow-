<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitByRole
{
    protected array $limits = [
        'admin' => ['max' => 1000, 'decay' => 60],
        'warehouse_manager' => ['max' => 500, 'decay' => 60],
        'manager' => ['max' => 500, 'decay' => 60],
        'driver' => ['max' => 120, 'decay' => 60],
        'store' => ['max' => 60, 'decay' => 60],
    ];

    public function handle(Request $request, Closure $next, ?string $customLimit = null)
    {
        $user = $request->user();
        $role = $user?->role ?? 'guest';
        $limit = $this->limits[$role] ?? ['max' => 30, 'decay' => 60];

        if ($customLimit && str_contains($customLimit, ':')) {
            [$max, $decay] = explode(':', $customLimit);
            $limit = ['max' => (int) $max, 'decay' => (int) ($decay ?? 60)];
        }

        $key = 'rate-limit:' . ($user?->id ?? $request->ip()) . ':' . $role;

        $executed = RateLimiter::attempt(
            $key,
            $limit['max'],
            fn() => true,
            $limit['decay']
        );

        if (!$executed) {
            $retryAfter = RateLimiter::availableIn($key);
            return response()->json([
                'message' => 'Too many requests. Please try again.',
                'retry_after' => $retryAfter,
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $limit['max'],
                'X-RateLimit-Remaining' => RateLimiter::remaining($key, $limit['max']),
                'Retry-After' => $retryAfter,
            ]);
        }

        $response = $next($request);

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $response->headers->set('X-RateLimit-Limit', $limit['max']);
            $response->headers->set('X-RateLimit-Remaining', RateLimiter::remaining($key, $limit['max']));
        }

        return $response;
    }
}
