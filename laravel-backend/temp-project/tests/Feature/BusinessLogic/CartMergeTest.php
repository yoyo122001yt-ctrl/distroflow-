<?php

namespace Tests\Feature\BusinessLogic;

use App\Models\Product;
use App\Models\RetailStore;
use App\Models\User;
use App\Services\StoreCartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartMergeTest extends TestCase
{
    private StoreCartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/');
        $this->cartService = app(StoreCartService::class);
        $this->cartService->clear();
    }

    protected function tearDown(): void
    {
        $this->cartService->clear();
        parent::tearDown();
    }

    public function test_guest_adds_items_logs_in_and_cart_merges_with_max_quantities(): void
    {
        $productA = Product::create([
            'name' => 'Product A',
            'sku' => 'PRD-A',
            'selling_price' => 15.00,
            'cost_price' => 8.00,
        ]);

        $productB = Product::create([
            'name' => 'Product B',
            'sku' => 'PRD-B',
            'selling_price' => 25.00,
            'cost_price' => 12.00,
        ]);

        $user = User::create([
            'name' => 'Store Owner',
            'email' => 'store@example.com',
            'role' => 'store',
            'password' => 'password',
        ]);

        $this->actingAs($user);

        $currentCart = [
            ['product_id' => $productA->id, 'name' => 'Product A', 'sku' => 'PRD-A', 'unit_price' => 15.00, 'quantity' => 1, 'subtotal' => 15.00, 'image_url' => null],
        ];

        $guestCart = [
            ['product_id' => $productA->id, 'name' => 'Product A', 'sku' => 'PRD-A', 'unit_price' => 15.00, 'quantity' => 2, 'subtotal' => 30.00, 'image_url' => null],
            ['product_id' => $productB->id, 'name' => 'Product B', 'sku' => 'PRD-B', 'unit_price' => 25.00, 'quantity' => 3, 'subtotal' => 75.00, 'image_url' => null],
        ];

        session()->put('store_cart', $currentCart);
        $this->cartService->mergeGuestCart($guestCart);

        $merged = $this->cartService->getCart();

        $itemA = $merged->firstWhere('product_id', $productA->id);
        $itemB = $merged->firstWhere('product_id', $productB->id);

        $this->assertNotNull($itemA, 'Product A should exist after merge');
        $this->assertNotNull($itemB, 'Product B should exist after merge');
        $this->assertEquals(2, $itemA['quantity'], 'Product A should take max(1,2) = 2');
        $this->assertEquals(3, $itemB['quantity'], 'Product B should come from guest cart');
        $this->assertCount(2, $merged, 'Cart should have 2 items after merge');
    }

    public function test_cart_persists_across_multiple_service_calls(): void
    {
        $product = Product::create([
            'name' => 'Persist Product',
            'sku' => 'PRD-PERSIST',
            'selling_price' => 10.00,
            'cost_price' => 5.00,
        ]);

        $this->cartService->addItem($product->id, 3);

        $summary = $this->cartService->getSummary();
        $this->assertEquals(3, $summary['count']);

        $retrieved = $this->cartService->getCart();
        $this->assertCount(1, $retrieved);
        $this->assertEquals(3, $retrieved->first()['quantity']);

        $summary2 = $this->cartService->getSummary();
        $this->assertEquals(3, $summary2['count']);
    }

    public function test_adding_duplicate_item_increases_quantity(): void
    {
        $product = Product::create([
            'name' => 'Duplicate Test',
            'sku' => 'PRD-DUP',
            'selling_price' => 20.00,
            'cost_price' => 10.00,
        ]);

        $this->cartService->addItem($product->id, 2);

        $this->cartService->addItem($product->id, 3);

        $cart = $this->cartService->getCart();
        $item = $cart->firstWhere('product_id', $product->id);

        $this->assertEquals(5, $item['quantity']);
        $this->assertCount(1, $cart);
    }

    public function test_cart_summary_calculates_correct_totals(): void
    {
        $productA = Product::create([
            'name' => 'Calc Product A',
            'sku' => 'PRD-CALC-A',
            'selling_price' => 10.00,
            'cost_price' => 4.00,
        ]);

        $productB = Product::create([
            'name' => 'Calc Product B',
            'sku' => 'PRD-CALC-B',
            'selling_price' => 20.00,
            'cost_price' => 8.00,
        ]);

        $this->cartService->addItem($productA->id, 3);
        $this->cartService->addItem($productB->id, 2);

        $summary = $this->cartService->getSummary();

        $this->assertEquals(5, $summary['count']);
        $this->assertEquals(70.00, $summary['total']);
        $this->assertCount(2, $summary['items']);
    }

    public function test_removing_items_from_cart_works(): void
    {
        $productA = Product::create([
            'name' => 'Remove Product A',
            'sku' => 'PRD-REM-A',
            'selling_price' => 10.00,
            'cost_price' => 4.00,
        ]);

        $productB = Product::create([
            'name' => 'Remove Product B',
            'sku' => 'PRD-REM-B',
            'selling_price' => 15.00,
            'cost_price' => 6.00,
        ]);

        $this->cartService->addItem($productA->id, 2);
        $this->cartService->addItem($productB->id, 1);

        $this->assertEquals(3, $this->cartService->count());

        $this->cartService->removeItem($productA->id);

        $cart = $this->cartService->getCart();
        $this->assertCount(1, $cart);
        $this->assertNull($cart->firstWhere('product_id', $productA->id));
        $this->assertNotNull($cart->firstWhere('product_id', $productB->id));
        $this->assertEquals(1, $this->cartService->count());
    }

    public function test_update_quantity_to_zero_removes_item(): void
    {
        $product = Product::create([
            'name' => 'Zero Qty Product',
            'sku' => 'PRD-ZERO',
            'selling_price' => 10.00,
            'cost_price' => 4.00,
        ]);

        $this->cartService->addItem($product->id, 5);
        $this->assertEquals(1, $this->cartService->getCart()->count());

        $this->cartService->updateQuantity($product->id, 0);

        $this->assertEquals(0, $this->cartService->getCart()->count());
    }
}
