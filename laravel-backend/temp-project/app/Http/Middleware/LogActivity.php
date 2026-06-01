<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $resourceType = $this->getResourceType($request->path());
            $resourceId = $request->route('id')
                ?? $request->route($resourceType)
                ?? null;

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => $request->method() === 'DELETE' ? 'delete' : ($request->method() === 'POST' ? 'create' : 'update'),
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'description' => $request->method() . ' ' . $request->path(),
                'details' => [
                    'payload' => $request->except(['password', 'password_confirmation']),
                    'response_status' => $response->getStatusCode(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return $response;
    }

    private function getResourceType(string $path): string
    {
        $parts = explode('/', $path);
        $apiIndex = array_search('api', $parts);
        if ($apiIndex !== false && isset($parts[$apiIndex + 1])) {
            return $parts[$apiIndex + 1];
        }
        return $parts[0] ?? 'unknown';
    }
}
