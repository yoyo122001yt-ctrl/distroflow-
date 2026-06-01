<?php

namespace Database\Seeders;

use App\Models\SalesOrder;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RetailStore;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoOrdersSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();
        $stores = RetailStore::all();
        $users = User::whereIn('email', ['admin@distroflow.com', 'manager@distroflow.com'])->get();
        $statuses = ['pending', 'approved', 'shipped', 'delivered'];

        if ($products->isEmpty() || $stores->isEmpty()) {
            $this->command->warn('Products or stores missing, skipping orders seeder');
            return;
        }

        $orderCount = max(5, $stores->count() * 2);
        $invoiceService = app(InvoiceService::class);

        for ($i = 0; $i < $orderCount; $i++) {
            $store = $stores->random();
            $status = $statuses[array_rand($statuses)];
            $itemCount = rand(1, 4);

            DB::transaction(function () use ($products, $store, $users, $status, $itemCount, $i, $orderCount, $invoiceService) {
                $selectedProducts = $products->random(min($itemCount, $products->count()));
                $subtotal = 0;
                $items = [];

                foreach ($selectedProducts as $product) {
                    $qty = rand(1, 10);
                    $price = (float) $product->selling_price;
                    $total = $qty * $price;
                    $subtotal += $total;

                    $items[] = [
                        'product_id' => $product->id,
                        'quantity_ordered' => $qty,
                        'unit_price' => $price,
                        'total_price' => $total,
                    ];
                }

                $discount = round($subtotal * (rand(0, 10) / 100), 2);
                $taxable = $subtotal - $discount;
                $tax = round($taxable * 0.14, 2);
                $total = round($taxable + $tax, 2);

                $order = SalesOrder::create([
                    'order_number' => 'SO-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                    'retail_store_id' => $store->id,
                    'warehouse_id' => 1,
                    'created_by' => $users->random()->id,
                    'order_date' => now()->subDays(rand(1, 30)),
                    'status' => $status,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'tax' => $tax,
                    'total' => $total,
                    'balance_due' => in_array($status, ['pending', 'approved']) ? $total : 0,
                ]);

                foreach ($items as $item) {
                    $order->items()->create($item);
                }

                // Generate invoices for ~half the approved/delivered orders
                if (in_array($status, ['approved', 'delivered']) && rand(0, 1) === 0) {
                    try {
                        $invoiceService->generateInvoice($order->id);
                    } catch (\Exception $e) {
                        // skip
                    }
                }
            });
        }
    }
}
