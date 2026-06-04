<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Batch;
use App\Models\StorePrice;
use App\Models\RetailStore;
use App\Models\Warehouse;
use Database\Factories\ProductFactory;
use Database\Factories\RetailStoreFactory;

class ProductTest extends TestCase
{
    public function test_scope_active_filters_active_products(): void
    {
        ProductCategory::create(['name' => 'General', 'slug' => 'general']);
        $active = ProductFactory::new()->create(['is_active' => true]);
        $inactive = ProductFactory::new()->create(['is_active' => false]);

        $products = Product::active()->get();

        $this->assertTrue($products->contains($active));
        $this->assertFalse($products->contains($inactive));
    }

    public function test_category_relationship(): void
    {
        $category = ProductCategory::create(['name' => 'Beverages', 'slug' => 'beverages']);
        $product = ProductFactory::new()->create(['category_id' => $category->id]);

        $this->assertInstanceOf(ProductCategory::class, $product->category);
        $this->assertEquals($category->id, $product->category->id);
    }

    public function test_batches_relationship(): void
    {
        $warehouse = Warehouse::create(['name' => 'WH', 'code' => 'WH-01']);
        $category = ProductCategory::create(['name' => 'Dairy', 'slug' => 'dairy']);
        $product = ProductFactory::new()->create(['category_id' => $category->id]);

        Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'B-001',
            'quantity' => 100,
            'available_quantity' => 80,
            'cost_price' => 10.00,
            'expiry_date' => '2026-12-31',
            'received_date' => today(),
            'status' => 'available',
        ]);
        Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'B-002',
            'quantity' => 50,
            'available_quantity' => 50,
            'cost_price' => 12.00,
            'expiry_date' => '2026-12-31',
            'received_date' => today(),
            'status' => 'available',
        ]);

        $this->assertCount(2, $product->batches);
    }

    public function test_store_prices_relationship(): void
    {
        $category = ProductCategory::create(['name' => 'Snacks', 'slug' => 'snacks']);
        $product = ProductFactory::new()->create(['category_id' => $category->id]);
        $store = RetailStoreFactory::new()->create();

        StorePrice::create([
            'retail_store_id' => $store->id,
            'product_id' => $product->id,
            'price' => 15.00,
            'is_active' => true,
        ]);

        $this->assertCount(1, $product->storePrices);
        $this->assertEquals(15.00, (float)$product->storePrices->first()->price);
    }

    public function test_available_stock_attribute_sums_batch_available_quantity(): void
    {
        $warehouse = Warehouse::create(['name' => 'WH', 'code' => 'WH-01']);
        $category = ProductCategory::create(['name' => 'Frozen', 'slug' => 'frozen']);
        $product = ProductFactory::new()->create(['category_id' => $category->id]);

        Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'B-001',
            'quantity' => 100,
            'available_quantity' => 80,
            'cost_price' => 5.00,
            'expiry_date' => '2026-12-31',
            'received_date' => today(),
            'status' => 'available',
        ]);
        Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'B-002',
            'quantity' => 60,
            'available_quantity' => 30,
            'cost_price' => 6.00,
            'expiry_date' => '2026-12-31',
            'received_date' => today(),
            'status' => 'available',
        ]);

        $expected = 80 + 30;
        $this->assertEquals($expected, $product->available_stock);
    }

    public function test_available_stock_attribute_returns_zero_when_no_batches(): void
    {
        $category = ProductCategory::create(['name' => 'Empty', 'slug' => 'empty']);
        $product = ProductFactory::new()->create(['category_id' => $category->id]);

        $this->assertEquals(0, $product->available_stock);
    }

    public function test_cost_price_is_cast_to_decimal(): void
    {
        $category = ProductCategory::create(['name' => 'Test', 'slug' => 'test']);
        $product = ProductFactory::new()->create([
            'category_id' => $category->id,
            'cost_price' => 49.99,
        ]);

        $this->assertIsNumeric($product->cost_price);
        $this->assertEquals(49.99, (float)$product->cost_price);
    }

    public function test_selling_price_is_cast_to_decimal(): void
    {
        $category = ProductCategory::create(['name' => 'Test', 'slug' => 'test']);
        $product = ProductFactory::new()->create([
            'category_id' => $category->id,
            'selling_price' => 79.95,
        ]);

        $this->assertIsNumeric($product->selling_price);
        $this->assertEquals(79.95, (float)$product->selling_price);
    }

    public function test_is_active_is_cast_to_boolean(): void
    {
        $category = ProductCategory::create(['name' => 'Test', 'slug' => 'test']);
        $product = ProductFactory::new()->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $this->assertIsBool($product->is_active);
        $this->assertTrue($product->is_active);
    }
}
