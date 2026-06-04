<?php

namespace Tests\Unit\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\RetailStore;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Database\Factories\RetailStoreFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $service;
    private User $user;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvoiceService::class);
        $this->user = UserFactory::new()->create();
        $this->warehouse = Warehouse::create([
            'name' => 'Main WH', 'code' => 'WH-001',
            'latitude' => 0, 'longitude' => 0, 'is_active' => true,
        ]);
    }

    public function test_generate_invoice_creates_invoice_with_correct_totals()
    {
        $store = RetailStoreFactory::new()->create(['payment_terms' => 'net_30']);

        $order = SalesOrder::create([
            'retail_store_id' => $store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'order_number' => 'ORD-001',
            'order_date' => now(),
            'status' => 'approved',
            'source' => 'phone',
            'subtotal' => 1000.00,
            'discount' => 50.00,
            'tax' => 78.00,
            'total' => 1028.00,
        ]);

        $invoice = $this->service->generateInvoice($order->id);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals($order->id, $invoice->sales_order_id);
        $this->assertEquals($store->id, $invoice->retail_store_id);
        $this->assertEquals(1000.00, $invoice->subtotal);
        $this->assertEquals(78.00, $invoice->tax);
        $this->assertEquals(1028.00, $invoice->total);
        $this->assertEquals(0, $invoice->amount_paid);
        $this->assertEquals(1028.00, $invoice->balance_due);
        $this->assertEquals('unpaid', $invoice->status);
        $this->assertStringStartsWith('INV-', $invoice->invoice_number);
    }

    public function test_generate_invoice_calculates_due_date_based_on_payment_terms()
    {
        Carbon::setTestNow(Carbon::parse('2025-06-01'));

        $store = RetailStoreFactory::new()->create(['payment_terms' => 'net_15']);
        $order = SalesOrder::create([
            'retail_store_id' => $store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'order_number' => 'ORD-001',
            'order_date' => now(),
            'status' => 'approved',
            'source' => 'phone',
            'subtotal' => 500.00,
            'tax' => 41.25,
            'total' => 541.25,
        ]);
        $invoice = $this->service->generateInvoice($order->id);
        $this->assertEquals('2025-06-16', $invoice->due_date->format('Y-m-d'));

        $store2 = RetailStoreFactory::new()->create(['payment_terms' => 'net_45']);
        $order2 = SalesOrder::create([
            'retail_store_id' => $store2->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'order_number' => 'ORD-002',
            'order_date' => now(),
            'status' => 'approved',
            'source' => 'phone',
            'subtotal' => 300.00,
            'tax' => 24.75,
            'total' => 324.75,
        ]);
        $invoice2 = $this->service->generateInvoice($order2->id);
        $this->assertEquals('2025-07-16', $invoice2->due_date->format('Y-m-d'));

        Carbon::setTestNow();
    }

    public function test_generate_invoice_defaults_to_7_days_when_no_payment_terms()
    {
        Carbon::setTestNow(Carbon::parse('2025-06-01'));

        // payment_terms defaults to 'cod' from DB migration default, resulting in 7-day due date
        $store = RetailStoreFactory::new()->create(['payment_terms' => 'cod']);
        $order = SalesOrder::create([
            'retail_store_id' => $store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'order_number' => 'ORD-001',
            'order_date' => now(),
            'status' => 'approved',
            'source' => 'phone',
            'subtotal' => 200.00,
            'tax' => 16.50,
            'total' => 216.50,
        ]);
        $invoice = $this->service->generateInvoice($order->id);
        $this->assertEquals('2025-06-08', $invoice->due_date->format('Y-m-d'));

        Carbon::setTestNow();
    }

    public function test_generate_invoice_throws_for_nonexistent_order()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->generateInvoice(99999);
    }

    public function test_record_payment_creates_payment_and_updates_invoice()
    {
        $store = RetailStoreFactory::new()->create([
            'payment_terms' => 'net_30',
            'current_balance' => 5000.00,
        ]);

        $order = SalesOrder::create([
            'retail_store_id' => $store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'order_number' => 'ORD-001',
            'order_date' => now(),
            'status' => 'approved',
            'source' => 'phone',
            'subtotal' => 1000.00,
            'tax' => 82.50,
            'total' => 1082.50,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-001',
            'sales_order_id' => $order->id,
            'retail_store_id' => $store->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 1000.00,
            'tax' => 82.50,
            'total' => 1082.50,
            'amount_paid' => 0,
            'balance_due' => 1082.50,
            'status' => 'unpaid',
        ]);

        $payment = $this->service->recordPayment($invoice->id, 500.00, 'cash');

        $this->assertInstanceOf(InvoicePayment::class, $payment);
        $this->assertEquals($invoice->id, $payment->invoice_id);
        $this->assertEquals(500.00, $payment->amount);
        $this->assertEquals('cash', $payment->payment_method);

        $invoice->refresh();
        $this->assertEquals(500.00, $invoice->amount_paid);
        $this->assertEquals(582.50, $invoice->balance_due);
        $this->assertEquals('partial', $invoice->status);
    }

    public function test_record_payment_sets_status_to_partial_when_partial_payment()
    {
        $store = RetailStoreFactory::new()->create(['payment_terms' => 'net_30', 'current_balance' => 5000]);
        $order = SalesOrder::create([
            'retail_store_id' => $store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'order_number' => 'ORD-001',
            'order_date' => now(),
            'status' => 'approved',
            'source' => 'phone',
            'subtotal' => 500.00,
            'tax' => 41.25,
            'total' => 541.25,
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-002',
            'sales_order_id' => $order->id,
            'retail_store_id' => $store->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 500.00,
            'tax' => 41.25,
            'total' => 541.25,
            'amount_paid' => 0,
            'balance_due' => 541.25,
            'status' => 'unpaid',
        ]);

        $this->service->recordPayment($invoice->id, 200.00, 'credit_card');

        $invoice->refresh();
        $this->assertEquals(200.00, $invoice->amount_paid);
        $this->assertEquals(341.25, $invoice->balance_due);
        $this->assertEquals('partial', $invoice->status);
    }

    public function test_record_payment_sets_status_to_paid_when_balance_reaches_zero()
    {
        $store = RetailStoreFactory::new()->create(['payment_terms' => 'net_30', 'current_balance' => 5000]);
        $order = SalesOrder::create([
            'retail_store_id' => $store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'order_number' => 'ORD-001',
            'order_date' => now(),
            'status' => 'approved',
            'source' => 'phone',
            'subtotal' => 500.00,
            'tax' => 41.25,
            'total' => 541.25,
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-003',
            'sales_order_id' => $order->id,
            'retail_store_id' => $store->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 500.00,
            'tax' => 41.25,
            'total' => 541.25,
            'amount_paid' => 0,
            'balance_due' => 541.25,
            'status' => 'unpaid',
        ]);

        $this->service->recordPayment($invoice->id, 541.25, 'bank_transfer');

        $invoice->refresh();
        $this->assertEquals(541.25, $invoice->amount_paid);
        $this->assertEquals(0, $invoice->balance_due);
        $this->assertEquals('paid', $invoice->status);
    }

    public function test_record_payment_updates_store_current_balance()
    {
        $store = RetailStoreFactory::new()->create([
            'payment_terms' => 'net_30',
            'current_balance' => 10000.00,
        ]);
        $order = SalesOrder::create([
            'retail_store_id' => $store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'order_number' => 'ORD-001',
            'order_date' => now(),
            'status' => 'approved',
            'source' => 'phone',
            'subtotal' => 500.00,
            'tax' => 41.25,
            'total' => 541.25,
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-004',
            'sales_order_id' => $order->id,
            'retail_store_id' => $store->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 500.00,
            'tax' => 41.25,
            'total' => 541.25,
            'amount_paid' => 0,
            'balance_due' => 541.25,
            'status' => 'unpaid',
        ]);

        $this->service->recordPayment($invoice->id, 541.25, 'check');

        $store->refresh();
        $this->assertEquals(9458.75, $store->current_balance);
    }

    public function test_record_payment_accepts_extra_fields()
    {
        $store = RetailStoreFactory::new()->create(['payment_terms' => 'net_30', 'current_balance' => 5000]);
        $order = SalesOrder::create([
            'retail_store_id' => $store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'order_number' => 'ORD-001',
            'order_date' => now(),
            'status' => 'approved',
            'source' => 'phone',
            'subtotal' => 200.00,
            'tax' => 16.50,
            'total' => 216.50,
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-005',
            'sales_order_id' => $order->id,
            'retail_store_id' => $store->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 200.00,
            'tax' => 16.50,
            'total' => 216.50,
            'amount_paid' => 0,
            'balance_due' => 216.50,
            'status' => 'unpaid',
        ]);

        $payment = $this->service->recordPayment($invoice->id, 216.50, 'bank_transfer', [
            'reference_number' => 'REF-12345',
            'notes' => 'Payment for April',
        ]);

        $this->assertEquals('REF-12345', $payment->reference_number);
        $this->assertEquals('Payment for April', $payment->notes);
    }

    public function test_record_payment_throws_for_nonexistent_invoice()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->recordPayment(99999, 100, 'cash');
    }

    public function test_get_aging_report_returns_correct_buckets()
    {
        Carbon::setTestNow(Carbon::parse('2025-06-15'));

        $store = RetailStoreFactory::new()->create();

        Invoice::create([
            'invoice_number' => 'INV-current',
            'sales_order_id' => SalesOrder::create([
                'retail_store_id' => $store->id,
                'warehouse_id' => $this->warehouse->id,
                'created_by' => $this->user->id,
                'order_number' => 'ORD-current',
                'order_date' => now(),
                'status' => 'approved', 'source' => 'phone',
                'subtotal' => 100, 'tax' => 8.25, 'total' => 108.25,
            ])->id,
            'retail_store_id' => $store->id,
            'invoice_date' => now()->subDays(10),
            'due_date' => now()->addDays(5),
            'subtotal' => 100, 'tax' => 8.25, 'total' => 108.25,
            'amount_paid' => 0, 'balance_due' => 108.25, 'status' => 'unpaid',
        ]);

        Invoice::create([
            'invoice_number' => 'INV-1-30',
            'sales_order_id' => SalesOrder::create([
                'retail_store_id' => $store->id,
                'warehouse_id' => $this->warehouse->id,
                'created_by' => $this->user->id,
                'order_number' => 'ORD-1-30',
                'order_date' => now(), 'status' => 'approved', 'source' => 'phone',
                'subtotal' => 200, 'tax' => 16.50, 'total' => 216.50,
            ])->id,
            'retail_store_id' => $store->id,
            'invoice_date' => now()->subDays(40),
            'due_date' => now()->subDays(10),
            'subtotal' => 200, 'tax' => 16.50, 'total' => 216.50,
            'amount_paid' => 0, 'balance_due' => 216.50, 'status' => 'unpaid',
        ]);

        Invoice::create([
            'invoice_number' => 'INV-31-60',
            'sales_order_id' => SalesOrder::create([
                'retail_store_id' => $store->id,
                'warehouse_id' => $this->warehouse->id,
                'created_by' => $this->user->id,
                'order_number' => 'ORD-31-60',
                'order_date' => now(), 'status' => 'approved', 'source' => 'phone',
                'subtotal' => 300, 'tax' => 24.75, 'total' => 324.75,
            ])->id,
            'retail_store_id' => $store->id,
            'invoice_date' => now()->subDays(70),
            'due_date' => now()->subDays(40),
            'subtotal' => 300, 'tax' => 24.75, 'total' => 324.75,
            'amount_paid' => 0, 'balance_due' => 324.75, 'status' => 'unpaid',
        ]);

        Invoice::create([
            'invoice_number' => 'INV-61-90',
            'sales_order_id' => SalesOrder::create([
                'retail_store_id' => $store->id,
                'warehouse_id' => $this->warehouse->id,
                'created_by' => $this->user->id,
                'order_number' => 'ORD-61-90',
                'order_date' => now(), 'status' => 'approved', 'source' => 'phone',
                'subtotal' => 400, 'tax' => 33.00, 'total' => 433.00,
            ])->id,
            'retail_store_id' => $store->id,
            'invoice_date' => now()->subDays(100),
            'due_date' => now()->subDays(70),
            'subtotal' => 400, 'tax' => 33.00, 'total' => 433.00,
            'amount_paid' => 0, 'balance_due' => 433.00, 'status' => 'unpaid',
        ]);

        Invoice::create([
            'invoice_number' => 'INV-90+',
            'sales_order_id' => SalesOrder::create([
                'retail_store_id' => $store->id,
                'warehouse_id' => $this->warehouse->id,
                'created_by' => $this->user->id,
                'order_number' => 'ORD-90+',
                'order_date' => now(), 'status' => 'approved', 'source' => 'phone',
                'subtotal' => 500, 'tax' => 41.25, 'total' => 541.25,
            ])->id,
            'retail_store_id' => $store->id,
            'invoice_date' => now()->subDays(150),
            'due_date' => now()->subDays(120),
            'subtotal' => 500, 'tax' => 41.25, 'total' => 541.25,
            'amount_paid' => 0, 'balance_due' => 541.25, 'status' => 'unpaid',
        ]);

        $report = $this->service->getAgingReport();

        $this->assertEquals(108.25, $report['buckets']['current']);
        $this->assertEquals(216.50, $report['buckets']['1-30']);
        $this->assertEquals(324.75, $report['buckets']['31-60']);
        $this->assertEquals(433.00, $report['buckets']['61-90']);
        $this->assertEquals(541.25, $report['buckets']['90+']);
        $this->assertEquals(1623.75, $report['total_outstanding']);
        $this->assertCount(5, $report['details']);

        Carbon::setTestNow();
    }

    public function test_get_aging_report_excludes_paid_invoices()
    {
        $store = RetailStoreFactory::new()->create();
        $order = SalesOrder::create([
            'retail_store_id' => $store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'order_number' => 'ORD-paid',
            'order_date' => now()->subDays(60), 'status' => 'approved', 'source' => 'phone',
            'subtotal' => 100, 'tax' => 8.25, 'total' => 108.25,
        ]);
        Invoice::create([
            'invoice_number' => 'INV-paid',
            'sales_order_id' => $order->id,
            'retail_store_id' => $store->id,
            'invoice_date' => now()->subDays(60),
            'due_date' => now()->subDays(30),
            'subtotal' => 100, 'tax' => 8.25, 'total' => 108.25,
            'amount_paid' => 108.25, 'balance_due' => 0, 'status' => 'paid',
        ]);

        $report = $this->service->getAgingReport();

        $this->assertEquals(0, $report['total_outstanding']);
        $this->assertCount(0, $report['details']);
    }

    public function test_get_aging_report_returns_empty_buckets_when_no_invoices()
    {
        $report = $this->service->getAgingReport();

        foreach (['current', '1-30', '31-60', '61-90', '90+'] as $bucket) {
            $this->assertEquals(0, $report['buckets'][$bucket]);
        }
        $this->assertEquals(0, $report['total_outstanding']);
    }
}
