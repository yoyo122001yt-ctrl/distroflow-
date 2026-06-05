<?php

namespace Database\Seeders;

use App\Models\SalesOrder;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\DriverSettlement;
use App\Models\RetailStore;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoFinanceSeeder extends Seeder
{
    public function run(): void
    {
        $deliveredOrders = SalesOrder::whereIn('status', ['delivered', 'completed'])->get();
        $deliveries = Delivery::where('status', 'delivered')->get();
        $approvedOrders = SalesOrder::whereIn('status', ['approved', 'processing'])->get();

        $invoiceCount = 0;
        $settlementCount = 0;

        // ────────────────────────────────────
        // INVOICES for delivered orders
        // ────────────────────────────────────
        foreach ($deliveredOrders as $order) {
            $store = RetailStore::find($order->retail_store_id);

            $invoice = Invoice::create([
                'invoice_number' => 'INV-' . str_pad($invoiceCount + 1, 4, '0', STR_PAD_LEFT),
                'sales_order_id' => $order->id,
                'retail_store_id' => $order->retail_store_id,
                'invoice_date' => now()->subDays(rand(0, 5)),
                'due_date' => now()->addDays(rand(15, 45)),
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'tax' => $order->tax,
                'total' => $order->total,
                'amount_paid' => $order->total,
                'balance_due' => 0,
                'status' => 'paid',
                'notes' => 'Invoice for ' . $order->order_number,
            ]);

            InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'amount' => $order->total,
                'payment_method' => 'cash',
                'payment_date' => now()->subDays(rand(0, 2)),
                'notes' => 'Paid in full upon delivery',
            ]);

            $invoiceCount++;
        }

        // ────────────────────────────────────
        // PENDING INVOICES for approved/processing orders
        // ────────────────────────────────────
        foreach ($approvedOrders as $order) {
            Invoice::create([
                'invoice_number' => 'INV-' . str_pad($invoiceCount + 1, 4, '0', STR_PAD_LEFT),
                'sales_order_id' => $order->id,
                'retail_store_id' => $order->retail_store_id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30),
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'tax' => $order->tax,
                'total' => $order->total,
                'amount_paid' => 0,
                'balance_due' => $order->total,
                'status' => 'unpaid',
                'notes' => 'Pending payment',
            ]);

            $invoiceCount++;
        }
        $this->command->info("$invoiceCount invoices created with payments.");

        // ────────────────────────────────────
        // DRIVER SETTLEMENTS
        // ────────────────────────────────────
        foreach ($deliveries as $delivery) {
            $driver = User::find($delivery->driver_id);
            if (!$driver) continue;

            DriverSettlement::create([
                'driver_id' => $delivery->driver_id,
                'route_assignment_id' => $delivery->route_assignment_id,
                'delivery_id' => $delivery->id,
                'settlement_date' => now()->toDateString(),
                'status' => 'approved',
                'total_sales' => $delivery->total_sales,
                'total_returns' => $delivery->total_returns,
                'expected_cash' => $delivery->total_collected,
                'actual_cash' => $delivery->total_collected,
                'cash_variance' => 0,
                'starting_inventory_value' => $delivery->total_sales * 1.2,
                'loaded_value' => $delivery->total_sales,
                'sales_value' => $delivery->total_sales,
                'returns_value' => 0,
                'expected_end_inventory_value' => 0,
                'actual_end_inventory_value' => 0,
                'inventory_variance' => 0,
                'notes' => 'Auto-settlement for driver ' . ($driver->name ?? 'Unknown'),
                'approved_by' => User::where('role', 'manager')->first()?->id,
                'approved_at' => now()->subHours(rand(1, 6)),
            ]);

            $settlementCount++;
        }
        $this->command->info("$settlementCount driver settlements created.");
    }
}
