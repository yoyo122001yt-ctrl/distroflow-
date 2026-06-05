<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    protected int $defaultTtl = 3600;

    protected array $tagMap = [
        'products' => ['products', 'stores'],
        'stores' => ['stores'],
        'routes' => ['routes', 'drivers'],
    ];

    public function remember(string $key, mixed $data, ?int $ttl = null, ?string $tag = null): mixed
    {
        $ttl = $ttl ?? $this->defaultTtl;

        if ($tag && method_exists(Cache::store(), 'tags')) {
            $tags = $this->tagMap[$tag] ?? [$tag];
            return Cache::tags($tags)->remember($key, $ttl, fn() => value($data));
        }

        return Cache::remember($key, $ttl, fn() => value($data));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($key, $default);
    }

    public function put(string $key, mixed $value, ?int $ttl = null): void
    {
        Cache::put($key, $value, $ttl ?? $this->defaultTtl);
    }

    public function forget(string $key): void
    {
        Cache::forget($key);
    }

    public function flush(string $tag): void
    {
        $tags = $this->tagMap[$tag] ?? [$tag];
        if (method_exists(Cache::store(), 'tags')) {
            Cache::tags($tags)->flush();
        }
    }

    public function flushAll(): void
    {
        if (method_exists(Cache::store(), 'tags')) {
            foreach ($this->tagMap as $tags) {
                Cache::tags($tags)->flush();
            }
        }
    }

    public function getCachedProducts(array $filters = []): mixed
    {
        $key = 'products:' . md5(json_encode($filters));
        return $this->remember($key, function () use ($filters) {
            $query = \App\Models\Product::query();
            if (!empty($filters['active'])) $query->where('is_active', true);
            if (!empty($filters['category_id'])) $query->where('category_id', $filters['category_id']);
            return $query->get();
        }, 1800, 'products');
    }

    public function getCachedStores(): mixed
    {
        return $this->remember('stores:all', function () {
            return \App\Models\RetailStore::where('is_active', true)->get();
        }, 3600, 'stores');
    }

    public function getCachedDriverRoute(int $driverId): mixed
    {
        return $this->remember("route:driver:{$driverId}", function () use ($driverId) {
            return \App\Models\Delivery::where('driver_id', $driverId)
                ->whereIn('status', ['assigned', 'in_transit'])
                ->with(['stops', 'salesOrder.retailStore'])
                ->get();
        }, 300, 'routes');
    }

    public function clearProductCache(): void
    {
        $this->flush('products');
    }

    public function clearStoreCache(): void
    {
        $this->flush('stores');
    }

    public function clearRouteCache(): void
    {
        $this->flush('routes');
    }
}
