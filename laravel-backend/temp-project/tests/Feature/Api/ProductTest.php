<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Batch;
use App\Models\Warehouse;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = new User;
        $this->adminUser->name = 'Admin';
        $this->adminUser->email = 'admin@test.com';
        $this->adminUser->password = bcrypt('password');
        $this->adminUser->role = 'admin';
        $this->adminUser->save();

        $this->category = new ProductCategory;
        $this->category->name = 'Beverages';
        $this->category->slug = 'beverages';
        $this->category->is_active = true;
        $this->category->save();
    }

    private function createProduct(array $overrides = []): Product
    {
        $product = new Product;
        $product->category_id = $this->category->id;
        $product->name = $overrides['name'] ?? 'Test Product';
        $product->sku = $overrides['sku'] ?? 'SKU-' . uniqid();
        $product->barcode = $overrides['barcode'] ?? null;
        $product->description = $overrides['description'] ?? 'Test description';
        $product->unit = $overrides['unit'] ?? 'piece';
        $product->cost_price = $overrides['cost_price'] ?? 5.00;
        $product->selling_price = $overrides['selling_price'] ?? 12.99;
        $product->weight = $overrides['weight'] ?? 1.5;
        $product->is_expiry_tracked = $overrides['is_expiry_tracked'] ?? true;
        $product->shelf_life_days = $overrides['shelf_life_days'] ?? 90;
        $product->min_stock_level = $overrides['min_stock_level'] ?? 10;
        $product->max_stock_level = $overrides['max_stock_level'] ?? 500;
        $product->is_active = $overrides['is_active'] ?? true;
        $product->save();
        return $product;
    }

    public function test_index_lists_products(): void
    {
        $this->createProduct(['name' => 'Product A', 'sku' => 'SKU-A']);
        $this->createProduct(['name' => 'Product B', 'sku' => 'SKU-B']);
        $this->createProduct(['name' => 'Product C', 'sku' => 'SKU-C']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [['id', 'name', 'sku', 'category']],
                    'current_page',
                    'total',
                ],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Products retrieved']);
    }

    public function test_index_can_filter_by_search(): void
    {
        $this->createProduct(['name' => 'Unique Product Name', 'sku' => 'UNQ-001']);
        $this->createProduct(['name' => 'Other Product', 'sku' => 'OTH-001']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/products?search=Unique');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    }

    public function test_store_creates_product(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/products', [
                'category_id' => $this->category->id,
                'name' => 'New Product',
                'sku' => 'SKU-NEW-001',
                'barcode' => '1234567890123',
                'description' => 'A brand new product',
                'unit' => 'piece',
                'cost_price' => 5.00,
                'selling_price' => 12.99,
                'weight' => 1.5,
                'is_expiry_tracked' => true,
                'shelf_life_days' => 90,
                'min_stock_level' => 10,
                'max_stock_level' => 500,
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'sku', 'category', 'selling_price', 'cost_price'],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Product created'])
            ->assertJsonFragment(['name' => 'New Product']);

        $this->assertDatabaseHas('products', [
            'sku' => 'SKU-NEW-001',
            'name' => 'New Product',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/products', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'sku', 'unit', 'cost_price', 'selling_price']);
    }

    public function test_store_validates_unique_sku(): void
    {
        $this->createProduct(['sku' => 'SKU-EXISTING']);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/products', [
                'name' => 'Duplicate SKU Product',
                'sku' => 'SKU-EXISTING',
                'unit' => 'piece',
                'cost_price' => 5.00,
                'selling_price' => 10.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    }

    public function test_show_returns_product(): void
    {
        $product = $this->createProduct();

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'sku', 'category'],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Product retrieved'])
            ->assertJsonFragment(['id' => $product->id]);
    }

    public function test_show_returns_404_for_non_existent_product(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/products/9999');

        $response->assertStatus(404)
            ->assertJsonFragment(['message' => 'Product not found']);
    }

    public function test_update_modifies_product(): void
    {
        $product = $this->createProduct([
            'name' => 'Original Name',
            'sku' => 'SKU-ORIGINAL',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/products/{$product->id}", [
                'name' => 'Updated Name',
                'selling_price' => 25.00,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'sku', 'category'],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Product updated'])
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Name',
            'selling_price' => 25.00,
        ]);
    }

    public function test_update_validates_unique_sku(): void
    {
        $productA = $this->createProduct(['sku' => 'SKU-A']);
        $this->createProduct(['sku' => 'SKU-B']);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/products/{$productA->id}", [
                'sku' => 'SKU-B',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    }

    public function test_update_allows_same_sku_for_same_product(): void
    {
        $product = $this->createProduct(['sku' => 'SKU-SAME']);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/products/{$product->id}", [
                'sku' => 'SKU-SAME',
                'name' => 'Renamed',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Product updated']);
    }

    public function test_destroy_deactivates_product(): void
    {
        $product = $this->createProduct(['is_active' => true]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Product deactivated']);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_active' => false,
        ]);
    }

    public function test_destroy_rejects_product_with_existing_stock(): void
    {
        $warehouse = new Warehouse;
        $warehouse->name = 'Test Warehouse';
        $warehouse->code = 'WH-TEST';
        $warehouse->is_active = true;
        $warehouse->save();

        $product = $this->createProduct();

        $batch = new Batch;
        $batch->product_id = $product->id;
        $batch->warehouse_id = $warehouse->id;
        $batch->batch_number = 'BATCH-STOCK-001';
        $batch->quantity = 100;
        $batch->available_quantity = 50;
        $batch->cost_price = 5.00;
        $batch->received_date = now();
        $batch->expiry_date = now()->addYear();
        $batch->status = 'available';
        $batch->save();

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(409)
            ->assertJsonFragment(['message' => 'Cannot delete product with existing stock']);
    }

    public function test_batches_returns_product_batches(): void
    {
        $warehouse = new Warehouse;
        $warehouse->name = 'Test Warehouse';
        $warehouse->code = 'WH-BATCH';
        $warehouse->is_active = true;
        $warehouse->save();

        $supplier = new Supplier;
        $supplier->code = 'SUP-001';
        $supplier->business_name = 'Test Supplier';
        $supplier->status = 'active';
        $supplier->save();

        $product = $this->createProduct();

        $batch = new Batch;
        $batch->product_id = $product->id;
        $batch->warehouse_id = $warehouse->id;
        $batch->batch_number = 'BATCH-001';
        $batch->quantity = 100;
        $batch->available_quantity = 80;
        $batch->cost_price = 5.00;
        $batch->supplier_id = $supplier->id;
        $batch->received_date = now();
        $batch->expiry_date = now()->addYear();
        $batch->status = 'available';
        $batch->save();

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/products/{$product->id}/batches");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [['id', 'product_id', 'warehouse', 'batch_number', 'available_quantity']],
                    'current_page',
                    'total',
                ],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Product batches retrieved']);
    }

    public function test_batches_returns_error_for_non_existent_product(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/products/9999/batches');

        $response->assertStatus(500)
            ->assertJsonFragment(['message' => 'Failed to retrieve batches']);
    }

    public function test_import_products_from_csv(): void
    {
        $csvContent = "name,sku,category,selling_price,cost_price,stock_quantity,unit,description,is_active\n" .
            "Imported Product A,IMP-SKU-001,Beverages,15.00,8.00,100,piece,Imported item A,1\n" .
            "Imported Product B,IMP-SKU-002,Beverages,25.00,12.00,50,box,Imported item B,1";

        $file = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $response = $this->actingAs($this->adminUser)
            ->post('/api/products/import', [
                'file' => $file,
            ], ['Accept' => 'application/json']);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => ['imported', 'failed', 'errors'],
            ])
            ->assertJsonFragment(['message' => 'Import completed']);

        $this->assertDatabaseHas('products', ['sku' => 'IMP-SKU-001']);
        $this->assertDatabaseHas('products', ['sku' => 'IMP-SKU-002']);
    }

    public function test_import_validates_file_required(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post('/api/products/import', [], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_download_import_template(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get('/api/products/import/template');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="product-import-template.csv"');
    }

    public function test_unauthenticated_user_cannot_access_products(): void
    {
        $this->getJson('/api/products')->assertStatus(401);
        $this->postJson('/api/products', [])->assertStatus(401);
        $this->getJson('/api/products/1')->assertStatus(401);
        $this->putJson('/api/products/1', [])->assertStatus(401);
        $this->deleteJson('/api/products/1')->assertStatus(401);
    }
}
