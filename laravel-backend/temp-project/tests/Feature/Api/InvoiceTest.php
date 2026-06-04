<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\SalesOrder;
use App\Models\RetailStore;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private RetailStore $store;
    private SalesOrder $salesOrder;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = new User;
        $this->adminUser->name = 'Admin';
        $this->adminUser->email = 'admin@test.com';
        $this->adminUser->password = bcrypt('password');
        $this->adminUser->role = 'admin';
        $this->adminUser->save();

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH01',
            'is_active' => true,
        ]);

        $this->store = new RetailStore;
        $this->store->code = 'STR-001';
        $this->store->business_name = 'Test Store';
        $this->store->store_type = 'grocery';
        $this->store->contact_person = 'John';
        $this->store->phone = '0123456789';
        $this->store->email = 'store@test.com';
        $this->store->address = '123 Test St';
        $this->store->city = 'Test City';
        $this->store->state = 'Test State';
        $this->store->latitude = 30.0444;
        $this->store->longitude = 31.2357;
        $this->store->credit_limit = 50000;
        $this->store->current_balance = 0;
        $this->store->payment_terms = 'net_30';
        $this->store->status = 'active';
        $this->store->save();

        $this->product = new Product;
        $this->product->name = 'Test Product';
        $this->product->sku = 'SKU-TEST-001';
        $this->product->barcode = '1234567890123';
        $this->product->cost_price = 5.00;
        $this->product->selling_price = 50.00;
        $this->product->unit = 'piece';
        $this->product->is_active = true;
        $this->product->save();

        $this->salesOrder = SalesOrder::create([
            'order_number' => 'ORD-INV-001',
            'retail_store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->adminUser->id,
            'order_date' => now(),
            'status' => 'approved',
            'subtotal' => 500.00,
            'discount' => 0,
            'tax' => 41.25,
            'total' => 541.25,
            'balance_due' => 541.25,
        ]);

        OrderItem::create([
            'sales_order_id' => $this->salesOrder->id,
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'unit_price' => 50.00,
            'total_price' => 500.00,
            'status' => 'pending',
        ]);
    }

    public function test_index_lists_invoices(): void
    {
        Invoice::create([
            'invoice_number' => 'INV-001',
            'sales_order_id' => $this->salesOrder->id,
            'retail_store_id' => $this->store->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 500.00,
            'total' => 541.25,
            'amount_paid' => 0,
            'balance_due' => 541.25,
            'status' => 'unpaid',
        ]);

        Invoice::create([
            'invoice_number' => 'INV-002',
            'sales_order_id' => $this->salesOrder->id,
            'retail_store_id' => $this->store->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 300.00,
            'total' => 324.75,
            'amount_paid' => 324.75,
            'balance_due' => 0,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/invoices');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['data' => [['id', 'invoice_number', 'status', 'retail_store', 'sales_order']], 'current_page', 'total'],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Invoices retrieved']);
    }

    public function test_index_filters_by_status(): void
    {
        Invoice::create([
            'invoice_number' => 'INV-FILTER-001',
            'sales_order_id' => $this->salesOrder->id,
            'retail_store_id' => $this->store->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 100.00,
            'total' => 108.25,
            'amount_paid' => 0,
            'balance_due' => 108.25,
            'status' => 'unpaid',
        ]);

        Invoice::create([
            'invoice_number' => 'INV-FILTER-002',
            'sales_order_id' => $this->salesOrder->id,
            'retail_store_id' => $this->store->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 200.00,
            'total' => 216.50,
            'amount_paid' => 216.50,
            'balance_due' => 0,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/invoices?status=unpaid');

        $response->assertStatus(200);
        $statuses = collect($response->json('data.data'))->pluck('status')->unique();
        $this->assertEquals(['unpaid'], $statuses->values()->all());
    }

    public function test_show_returns_invoice_with_payments(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-SHOW-001',
            'sales_order_id' => $this->salesOrder->id,
            'retail_store_id' => $this->store->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 500.00,
            'total' => 541.25,
            'amount_paid' => 0,
            'balance_due' => 541.25,
            'status' => 'unpaid',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/invoices/{$invoice->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'invoice_number', 'status', 'retail_store', 'sales_order', 'payments'], 'message'])
            ->assertJsonFragment(['message' => 'Invoice retrieved']);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/invoices/9999');

        $response->assertStatus(404);
    }

    public function test_send_returns_success_message(): void
    {
        $this->store->email = 'store@test.com';
        $this->store->save();

        $invoice = Invoice::create([
            'invoice_number' => 'INV-SEND-001',
            'sales_order_id' => $this->salesOrder->id,
            'retail_store_id' => $this->store->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 500.00,
            'total' => 541.25,
            'amount_paid' => 0,
            'balance_due' => 541.25,
            'status' => 'unpaid',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/invoices/{$invoice->id}/send");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Invoice sent successfully']);
    }

    public function test_send_fails_when_store_has_no_email(): void
    {
        $this->store->email = null;
        $this->store->save();

        $invoice = Invoice::create([
            'invoice_number' => 'INV-NOMAIL',
            'sales_order_id' => $this->salesOrder->id,
            'retail_store_id' => $this->store->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 500.00,
            'total' => 541.25,
            'amount_paid' => 0,
            'balance_due' => 541.25,
            'status' => 'unpaid',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/invoices/{$invoice->id}/send");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Store has no email address']);
    }

    public function test_pdf_returns_placeholder(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-PDF-001',
            'sales_order_id' => $this->salesOrder->id,
            'retail_store_id' => $this->store->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 500.00,
            'total' => 541.25,
            'amount_paid' => 0,
            'balance_due' => 541.25,
            'status' => 'unpaid',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/invoices/{$invoice->id}/pdf");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'PDF generation placeholder']);
    }

    public function test_record_payment_records_full_payment_and_updates_status_to_paid(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-PAY-001',
            'sales_order_id' => $this->salesOrder->id,
            'retail_store_id' => $this->store->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 500.00,
            'total' => 541.25,
            'amount_paid' => 0,
            'balance_due' => 541.25,
            'status' => 'unpaid',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/invoices/{$invoice->id}/record-payment", [
                'amount' => 541.25,
                'payment_method' => 'bank_transfer',
                'reference_number' => 'REF-001',
                'payment_date' => now()->format('Y-m-d'),
                'notes' => 'Full payment',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'amount', 'payment_method'], 'message'])
            ->assertJsonFragment(['message' => 'Payment recorded successfully']);

        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoice->id,
            'amount' => 541.25,
            'payment_method' => 'bank_transfer',
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'amount_paid' => 541.25,
            'balance_due' => 0,
            'status' => 'paid',
        ]);
    }

    public function test_record_payment_records_partial_payment_and_updates_status_to_partial(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-PARTIAL',
            'sales_order_id' => $this->salesOrder->id,
            'retail_store_id' => $this->store->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 500.00,
            'total' => 541.25,
            'amount_paid' => 0,
            'balance_due' => 541.25,
            'status' => 'unpaid',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/invoices/{$invoice->id}/record-payment", [
                'amount' => 200.00,
                'payment_method' => 'cash',
                'payment_date' => now()->format('Y-m-d'),
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'amount_paid' => 200.00,
            'balance_due' => 341.25,
            'status' => 'partial',
        ]);
    }

    public function test_record_payment_validates_required_fields(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-VALIDATE',
            'sales_order_id' => $this->salesOrder->id,
            'retail_store_id' => $this->store->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 500.00,
            'total' => 541.25,
            'amount_paid' => 0,
            'balance_due' => 541.25,
            'status' => 'unpaid',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/invoices/{$invoice->id}/record-payment", [
                'amount' => 0,
                'payment_method' => 'invalid_method',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'payment_method']);
    }
}
