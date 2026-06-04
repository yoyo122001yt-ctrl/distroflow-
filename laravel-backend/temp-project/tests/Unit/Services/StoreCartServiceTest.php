<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\StoreCartService;
use App\Models\Product;
use App\Models\ProductCategory;
use Database\Factories\ProductFactory;
use Illuminate\Support\Facades\Session;

class StoreCartServiceTest extends TestCase
{
    private StoreCartService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Session::forget('store_cart');
        $this->service = new StoreCartService();
    }

    public function test_get_cart_returns_empty_collection_for_new_cart(): void
    {
        $cart = $this->service->getCart();

        $this->assertTrue($cart->isEmpty());
    }

    public function test_add_item_adds_new_item_to_cart(): void
    {
        ProductCategory::create(['name' => 'General', 'slug' => 'general']);
        $product = ProductFactory::new()->create([
            'selling_price' => 25.00,
            'name' => 'Test Product',
            'sku' => 'TST-001',
        ]);

        $summary = $this->service->addItem($product->id, 3);

        $this->assertCount(1, $summary['items']);
        $this->assertEquals($product->id, $summary['items'][0]['product_id']);
        $this->assertEquals('Test Product', $summary['items'][0]['name']);
        $this->assertEquals(3, $summary['items'][0]['quantity']);
        $this->assertEquals(25.00, $summary['items'][0]['unit_price']);
        $this->assertEquals(75.00, $summary['items'][0]['subtotal']);
        $this->assertEquals(75.00, $summary['total']);
        $this->assertEquals(3, $summary['count']);
    }

    public function test_add_item_updates_quantity_for_existing_item(): void
    {
        ProductCategory::create(['name' => 'General', 'slug' => 'general']);
        $product = ProductFactory::new()->create(['selling_price' => 10.00]);

        $this->service->addItem($product->id, 2);
        $summary = $this->service->addItem($product->id, 3);

        $this->assertCount(1, $summary['items']);
        $this->assertEquals(5, $summary['items'][0]['quantity']);
        $this->assertEquals(50.00, $summary['items'][0]['subtotal']);
        $this->assertEquals(50.00, $summary['total']);
        $this->assertEquals(5, $summary['count']);
    }

    public function test_update_quantity_updates_existing_item(): void
    {
        ProductCategory::create(['name' => 'General', 'slug' => 'general']);
        $product = ProductFactory::new()->create(['selling_price' => 20.00]);

        $this->service->addItem($product->id, 2);
        $summary = $this->service->updateQuantity($product->id, 5);

        $this->assertEquals(5, $summary['items'][0]['quantity']);
        $this->assertEquals(100.00, $summary['items'][0]['subtotal']);
    }

    public function test_update_quantity_removes_item_when_quantity_is_zero_or_negative(): void
    {
        ProductCategory::create(['name' => 'General', 'slug' => 'general']);
        $product = ProductFactory::new()->create(['selling_price' => 15.00]);

        $this->service->addItem($product->id, 2);
        $summary = $this->service->updateQuantity($product->id, 0);

        $this->assertCount(0, $summary['items']);
        $this->assertEquals(0, $summary['total']);
        $this->assertEquals(0, $summary['count']);
    }

    public function test_remove_item_removes_specific_item(): void
    {
        ProductCategory::create(['name' => 'General', 'slug' => 'general']);
        $product1 = ProductFactory::new()->create(['selling_price' => 10.00, 'sku' => 'A-001']);
        $product2 = ProductFactory::new()->create(['selling_price' => 20.00, 'sku' => 'A-002']);

        $this->service->addItem($product1->id, 1);
        $this->service->addItem($product2->id, 2);

        $summary = $this->service->removeItem($product1->id);

        $this->assertCount(1, $summary['items']);
        $this->assertEquals($product2->id, $summary['items'][0]['product_id']);
        $this->assertEquals(40.00, $summary['total']);
    }

    public function test_get_summary_returns_items_total_and_count(): void
    {
        ProductCategory::create(['name' => 'General', 'slug' => 'general']);
        $product = ProductFactory::new()->create(['selling_price' => 30.00]);

        $this->service->addItem($product->id, 4);
        $summary = $this->service->getSummary();

        $this->assertArrayHasKey('items', $summary);
        $this->assertArrayHasKey('total', $summary);
        $this->assertArrayHasKey('count', $summary);
        $this->assertCount(1, $summary['items']);
        $this->assertEquals(120.00, $summary['total']);
        $this->assertEquals(4, $summary['count']);
    }

    public function test_clear_empties_the_cart(): void
    {
        ProductCategory::create(['name' => 'General', 'slug' => 'general']);
        $product = ProductFactory::new()->create(['selling_price' => 10.00]);

        $this->service->addItem($product->id, 2);
        $this->service->clear();

        $cart = $this->service->getCart();
        $summary = $this->service->getSummary();

        $this->assertTrue($cart->isEmpty());
        $this->assertEquals(0, $summary['count']);
        $this->assertEquals(0, $summary['total']);
    }

    public function test_merge_guest_cart_merges_new_items_from_saved_cart(): void
    {
        ProductCategory::create(['name' => 'General', 'slug' => 'general']);
        $product = ProductFactory::new()->create(['selling_price' => 10.00, 'sku' => 'M-001']);

        $savedCart = [
            ['product_id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'unit_price' => 10.00, 'quantity' => 3, 'subtotal' => 30.00],
        ];

        $this->service->mergeGuestCart($savedCart);

        $summary = $this->service->getSummary();
        $this->assertCount(1, $summary['items']);
        $this->assertEquals(3, $summary['items'][0]['quantity']);
    }

    public function test_merge_guest_cart_keeps_max_quantity_for_duplicate_items(): void
    {
        ProductCategory::create(['name' => 'General', 'slug' => 'general']);
        $product = ProductFactory::new()->create(['selling_price' => 10.00, 'sku' => 'M-001']);

        $this->service->addItem($product->id, 2);

        $savedCart = [
            ['product_id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'unit_price' => 10.00, 'quantity' => 5, 'subtotal' => 50.00],
        ];

        $this->service->mergeGuestCart($savedCart);

        $summary = $this->service->getSummary();
        $this->assertEquals(5, $summary['items'][0]['quantity']);
    }

    public function test_add_item_throws_exception_for_nonexistent_product(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->addItem(99999);
    }
}
