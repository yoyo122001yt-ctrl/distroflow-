<?php

namespace Tests\Unit\Services;

use App\Services\CacheService;
use Database\Factories\ProductFactory;
use Database\Factories\RetailStoreFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CacheServiceTest extends TestCase
{
    use RefreshDatabase;

    private CacheService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CacheService::class);

        if (!Schema::hasColumn('retail_stores', 'is_active')) {
            Schema::table('retail_stores', function ($table) {
                $table->boolean('is_active')->default(true);
            });
        }
    }

    protected function tearDown(): void
    {
        Cache::store('array')->flush();
        parent::tearDown();
    }

    public function test_remember_stores_and_retrieves_data()
    {
        $result = $this->service->remember('test-key', 'stored-value', 60);

        $this->assertEquals('stored-value', $result);
        $this->assertTrue(Cache::store('array')->has('test-key'));
        $this->assertEquals('stored-value', Cache::store('array')->get('test-key'));
    }

    public function test_remember_uses_default_ttl_when_not_provided()
    {
        $result = $this->service->remember('ttl-test', 'ttl-value');

        $this->assertEquals('ttl-value', $result);
    }

    public function test_remember_with_tag_stores_tagged_data()
    {
        $result = $this->service->remember('tagged-key', 'tagged-value', 60, 'products');

        $this->assertEquals('tagged-value', $result);
    }

    public function test_remember_uses_callable()
    {
        $result = $this->service->remember('callable-key', function () {
            return 'computed-value';
        }, 60);

        $this->assertEquals('computed-value', $result);
    }

    public function test_remember_returns_cached_value_on_subsequent_calls()
    {
        $counter = 0;

        $result1 = $this->service->remember('counter-key', function () use (&$counter) {
            $counter++;
            return "count-{$counter}";
        }, 60);

        $result2 = $this->service->remember('counter-key', function () use (&$counter) {
            $counter++;
            return "count-{$counter}";
        }, 60);

        $this->assertEquals('count-1', $result1);
        $this->assertEquals('count-1', $result2);
        $this->assertEquals(1, $counter);
    }

    public function test_get_returns_stored_value()
    {
        Cache::store('array')->put('get-test', 'hello', 60);

        $result = $this->service->get('get-test');

        $this->assertEquals('hello', $result);
    }

    public function test_get_returns_default_when_key_missing()
    {
        $result = $this->service->get('non-existent-key', 'default-value');

        $this->assertEquals('default-value', $result);
    }

    public function test_get_returns_null_default_when_not_specified()
    {
        $result = $this->service->get('non-existent-key');

        $this->assertNull($result);
    }

    public function test_put_stores_value()
    {
        $this->service->put('put-key', 'put-value', 60);

        $this->assertTrue(Cache::store('array')->has('put-key'));
        $this->assertEquals('put-value', Cache::store('array')->get('put-key'));
    }

    public function test_put_overwrites_existing_value()
    {
        Cache::store('array')->put('overwrite-key', 'old', 60);
        $this->service->put('overwrite-key', 'new', 60);

        $this->assertEquals('new', Cache::store('array')->get('overwrite-key'));
    }

    public function test_forget_removes_cached_item()
    {
        Cache::store('array')->put('forget-key', 'value', 60);

        $this->service->forget('forget-key');

        $this->assertFalse(Cache::store('array')->has('forget-key'));
    }

    public function test_forget_does_not_error_when_key_missing()
    {
        $this->service->forget('non-existent-key');

        $this->assertFalse(Cache::store('array')->has('non-existent-key'));
    }

    public function test_flush_clears_tagged_cache()
    {
        $this->service->remember('product-1', 'product-data', 60, 'products');
        $this->service->remember('store-1', 'store-data', 60, 'stores');

        $this->service->flush('products');

        $this->assertFalse(Cache::store('array')->tags(['products'])->has('product-1'));
    }

    public function test_flush_does_not_affect_other_tags()
    {
        $this->service->remember('route-data', 'route-value', 60, 'routes');
        $this->service->remember('store-data', 'store-value', 60, 'stores');

        $this->service->flush('routes');

        $this->assertFalse(Cache::store('array')->tags(['routes', 'drivers'])->has('route-data'));
        $this->assertTrue(Cache::store('array')->tags(['stores'])->has('store-data'));
    }

    public function test_flush_all_clears_all_tags()
    {
        $this->service->remember('product-1', 'data', 60, 'products');
        $this->service->remember('store-1', 'data', 60, 'stores');
        $this->service->remember('route-1', 'data', 60, 'routes');

        $this->service->flushAll();

        $this->assertFalse(Cache::store('array')->tags(['products'])->has('product-1'));
        $this->assertFalse(Cache::store('array')->tags(['stores'])->has('store-1'));
        $this->assertFalse(Cache::store('array')->tags(['routes'])->has('route-1'));
    }

    public function test_get_cached_products_returns_products()
    {
        $product = ProductFactory::new()->create(['is_active' => true]);

        $cached = $this->service->getCachedProducts();

        $this->assertCount(1, $cached);
        $this->assertEquals($product->id, $cached[0]->id);
    }

    public function test_get_cached_products_filters_by_active()
    {
        ProductFactory::new()->create(['is_active' => false]);

        $cached = $this->service->getCachedProducts(['active' => true]);

        $this->assertCount(0, $cached);
    }

    public function test_get_cached_stores_returns_active_stores()
    {
        $store = RetailStoreFactory::new()->create(['status' => 'active']);

        $cached = $this->service->getCachedStores();

        $this->assertCount(1, $cached);
        $this->assertEquals($store->id, $cached[0]->id);
    }

    public function test_get_cached_driver_route_returns_deliveries()
    {
        $user = UserFactory::new()->create(['role' => 'driver']);

        $cached = $this->service->getCachedDriverRoute($user->id);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $cached);
    }

    public function test_get_cached_products_is_cached()
    {
        ProductFactory::new()->create(['is_active' => true, 'name' => 'First Product']);

        $this->service->getCachedProducts();

        ProductFactory::new()->create(['is_active' => true, 'name' => 'Second Product']);

        $cached = $this->service->getCachedProducts();

        $this->assertCount(1, $cached);
    }

    public function test_clear_product_cache_flushes_products_tag()
    {
        Cache::store('array')->tags(['products'])->put('products:test', 'value', 60);

        $this->service->clearProductCache();

        $this->assertFalse(Cache::store('array')->tags(['products'])->has('products:test'));
    }

    public function test_clear_store_cache_flushes_stores_tag()
    {
        Cache::store('array')->tags(['stores'])->put('stores:all', 'value', 60);

        $this->service->clearStoreCache();

        $this->assertFalse(Cache::store('array')->tags(['stores'])->has('stores:all'));
    }

    public function test_clear_route_cache_flushes_routes_tag()
    {
        Cache::store('array')->tags(['routes'])->put('route:driver:1', 'value', 60);

        $this->service->clearRouteCache();

        $this->assertFalse(Cache::store('array')->tags(['routes'])->has('route:driver:1'));
    }

    public function test_flush_handles_unknown_tag()
    {
        $this->service->remember('key-1', 'val', 60, 'unknown');

        $this->service->flush('unknown');

        $this->assertFalse(Cache::store('array')->tags(['unknown'])->has('key-1'));
    }
}
