<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\SalesOrder;
use App\Models\RetailStore;
use App\Models\Warehouse;
use App\Models\User;
use Database\Factories\RetailStoreFactory;
use Database\Factories\UserFactory;

class InvoiceTest extends TestCase
{
    public function test_sales_order_relationship(): void
    {
        $warehouse = Warehouse::create(['name' => 'WH', 'code' => 'WH-01']);
        $store = RetailStoreFactory::new()->create();
        $user = UserFactory::new()->create();
        $salesOrder = SalesOrder::create([
            'order_number' => 'ORD-001',
            'retail_store_id' => $store->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'order_date' => today(),
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-001',
            'sales_order_id' => $salesOrder->id,
            'retail_store_id' => $store->id,
            'invoice_date' => today(),
            'due_date' => today()->addDays(30),
            'total' => 500.00,
            'balance_due' => 500.00,
        ]);

        $this->assertInstanceOf(SalesOrder::class, $invoice->salesOrder);
        $this->assertEquals($salesOrder->id, $invoice->salesOrder->id);
    }

    public function test_retail_store_relationship(): void
    {
        $warehouse = Warehouse::create(['name' => 'WH', 'code' => 'WH-01']);
        $store = RetailStoreFactory::new()->create();
        $user = UserFactory::new()->create();
        $salesOrder = SalesOrder::create([
            'order_number' => 'ORD-002',
            'retail_store_id' => $store->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'order_date' => today(),
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-002',
            'sales_order_id' => $salesOrder->id,
            'retail_store_id' => $store->id,
            'invoice_date' => today(),
            'due_date' => today()->addDays(30),
            'total' => 250.00,
            'balance_due' => 250.00,
        ]);

        $this->assertInstanceOf(RetailStore::class, $invoice->retailStore);
        $this->assertEquals($store->id, $invoice->retailStore->id);
    }

    public function test_payments_relationship(): void
    {
        $warehouse = Warehouse::create(['name' => 'WH', 'code' => 'WH-01']);
        $store = RetailStoreFactory::new()->create();
        $user = UserFactory::new()->create();
        $salesOrder = SalesOrder::create([
            'order_number' => 'ORD-003',
            'retail_store_id' => $store->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'order_date' => today(),
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-003',
            'sales_order_id' => $salesOrder->id,
            'retail_store_id' => $store->id,
            'invoice_date' => today(),
            'due_date' => today()->addDays(30),
            'total' => 1000.00,
            'balance_due' => 1000.00,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'amount' => 500.00,
            'payment_method' => 'cash',
            'payment_date' => today(),
        ]);

        $this->assertCount(1, $invoice->payments);
        $this->assertEquals(500.00, (float)$invoice->payments->first()->amount);
    }

    public function test_is_overdue_returns_true_when_due_date_is_past_and_balance_due_is_positive(): void
    {
        $warehouse = Warehouse::create(['name' => 'WH', 'code' => 'WH-01']);
        $store = RetailStoreFactory::new()->create();
        $user = UserFactory::new()->create();
        $salesOrder = SalesOrder::create([
            'order_number' => 'ORD-004',
            'retail_store_id' => $store->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'order_date' => today()->subDays(60),
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-004',
            'sales_order_id' => $salesOrder->id,
            'retail_store_id' => $store->id,
            'invoice_date' => today()->subDays(60),
            'due_date' => today()->subDays(30),
            'total' => 300.00,
            'balance_due' => 300.00,
        ]);

        $this->assertTrue($invoice->isOverdue());
    }

    public function test_is_overdue_returns_false_when_due_date_is_in_future(): void
    {
        $warehouse = Warehouse::create(['name' => 'WH', 'code' => 'WH-01']);
        $store = RetailStoreFactory::new()->create();
        $user = UserFactory::new()->create();
        $salesOrder = SalesOrder::create([
            'order_number' => 'ORD-005',
            'retail_store_id' => $store->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'order_date' => today(),
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-005',
            'sales_order_id' => $salesOrder->id,
            'retail_store_id' => $store->id,
            'invoice_date' => today(),
            'due_date' => today()->addDays(30),
            'total' => 200.00,
            'balance_due' => 200.00,
        ]);

        $this->assertFalse($invoice->isOverdue());
    }

    public function test_is_overdue_returns_false_when_balance_due_is_zero(): void
    {
        $warehouse = Warehouse::create(['name' => 'WH', 'code' => 'WH-01']);
        $store = RetailStoreFactory::new()->create();
        $user = UserFactory::new()->create();
        $salesOrder = SalesOrder::create([
            'order_number' => 'ORD-006',
            'retail_store_id' => $store->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'order_date' => today()->subDays(60),
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-006',
            'sales_order_id' => $salesOrder->id,
            'retail_store_id' => $store->id,
            'invoice_date' => today()->subDays(60),
            'due_date' => today()->subDays(30),
            'total' => 200.00,
            'balance_due' => 0.00,
        ]);

        $this->assertFalse($invoice->isOverdue());
    }

    public function test_days_overdue_returns_positive_integer_for_overdue_invoice(): void
    {
        $warehouse = Warehouse::create(['name' => 'WH', 'code' => 'WH-01']);
        $store = RetailStoreFactory::new()->create();
        $user = UserFactory::new()->create();
        $salesOrder = SalesOrder::create([
            'order_number' => 'ORD-007',
            'retail_store_id' => $store->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'order_date' => today()->subDays(35),
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-007',
            'sales_order_id' => $salesOrder->id,
            'retail_store_id' => $store->id,
            'invoice_date' => today()->subDays(35),
            'due_date' => today()->subDays(5),
            'total' => 100.00,
            'balance_due' => 100.00,
        ]);

        $this->assertEquals(5, $invoice->daysOverdue());
    }

    public function test_days_overdue_returns_negative_integer_for_future_due_date(): void
    {
        $warehouse = Warehouse::create(['name' => 'WH', 'code' => 'WH-01']);
        $store = RetailStoreFactory::new()->create();
        $user = UserFactory::new()->create();
        $salesOrder = SalesOrder::create([
            'order_number' => 'ORD-008',
            'retail_store_id' => $store->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'order_date' => today(),
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-008',
            'sales_order_id' => $salesOrder->id,
            'retail_store_id' => $store->id,
            'invoice_date' => today(),
            'due_date' => today()->addDays(10),
            'total' => 100.00,
            'balance_due' => 100.00,
        ]);

        $this->assertLessThan(0, $invoice->daysOverdue());
    }

    public function test_total_is_cast_to_decimal(): void
    {
        $warehouse = Warehouse::create(['name' => 'WH', 'code' => 'WH-01']);
        $store = RetailStoreFactory::new()->create();
        $user = UserFactory::new()->create();
        $salesOrder = SalesOrder::create([
            'order_number' => 'ORD-009',
            'retail_store_id' => $store->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'order_date' => today(),
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-009',
            'sales_order_id' => $salesOrder->id,
            'retail_store_id' => $store->id,
            'invoice_date' => today(),
            'due_date' => today()->addDays(15),
            'total' => 1234.56,
            'balance_due' => 1234.56,
        ]);

        $this->assertIsNumeric($invoice->total);
        $this->assertEquals(1234.56, (float)$invoice->total);
    }

    public function test_balance_due_is_cast_to_decimal(): void
    {
        $warehouse = Warehouse::create(['name' => 'WH', 'code' => 'WH-01']);
        $store = RetailStoreFactory::new()->create();
        $user = UserFactory::new()->create();
        $salesOrder = SalesOrder::create([
            'order_number' => 'ORD-010',
            'retail_store_id' => $store->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'order_date' => today(),
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-010',
            'sales_order_id' => $salesOrder->id,
            'retail_store_id' => $store->id,
            'invoice_date' => today(),
            'due_date' => today()->addDays(15),
            'total' => 500.00,
            'balance_due' => 250.50,
        ]);

        $this->assertIsNumeric($invoice->balance_due);
        $this->assertEquals(250.50, (float)$invoice->balance_due);
    }
}
