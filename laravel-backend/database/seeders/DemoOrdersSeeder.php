<?php

namespace Database\Seeders;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrder;
use App\Models\OrderItem;
use App\Models\StorePrice;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\RetailStore;
use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoOrdersSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::first();
        $products = Product::all();
        $suppliers = Supplier::all();
        $stores = RetailStore::all();
        $manager = User::where('role', 'manager')->first();
        $sales = User::where('role', 'sales')->first();

        if (!$warehouse || $products->isEmpty() || $suppliers->isEmpty() || $stores->isEmpty()) {
            $this->command->error('Missing prerequisite data. Run base seeders first.');
            return;
        }

        $userId = $manager?->id ?? User::first()->id;
        $salesId = $sales?->id ?? $userId;

        // ────────────────────────────────────
        // STORE PRICES
        // ────────────────────────────────────
        $storePricesCreated = 0;
        foreach ($stores as $store) {
            foreach ($products->random(min(12, $products->count())) as $product) {
                $markup = rand(5, 30) / 100;
                StorePrice::firstOrCreate([
                    'retail_store_id' => $store->id,
                    'product_id' => $product->id,
                ], [
                    'price' => round($product->selling_price * (1 + $markup), 2),
                    'is_active' => true,
                ]);
                $storePricesCreated++;
            }
        }
        $this->command->info("$storePricesCreated store prices created.");

        // ────────────────────────────────────
        // PURCHASE ORDERS
        // ────────────────────────────────────
        $poStatuses = ['draft', 'pending', 'approved', 'completed'];
        $poCount = 0;

        foreach ($suppliers as $supplier) {
            for ($i = 0; $i < 2; $i++) {
                $status = $poStatuses[array_rand($poStatuses)];
                $items = $products->random(rand(3, 6));
                $subtotal = 0;
                $poItems = [];

                foreach ($items as $product) {
                    $qty = rand(20, 100);
                    $cost = $product->cost_price;
                    $total = round($qty * $cost, 2);
                    $subtotal += $total;
                    $poItems[] = [
                        'product_id' => $product->id,
                        'quantity_ordered' => $qty,
                        'quantity_received' => $status === 'completed' ? $qty : ($status === 'approved' ? rand(0, $qty) : 0),
                        'unit_cost' => $cost,
                        'total_cost' => $total,
                    ];
                }

                $tax = round($subtotal * 0.10, 2);
                $total = round($subtotal + $tax, 2);

                $po = PurchaseOrder::create([
                    'order_number' => 'PO-' . str_pad($poCount + 1, 4, '0', STR_PAD_LEFT),
                    'supplier_id' => $supplier->id,
                    'warehouse_id' => $warehouse->id,
                    'created_by' => $userId,
                    'order_date' => now()->subDays(rand(1, 30)),
                    'expected_date' => now()->addDays(rand(1, 15)),
                    'status' => $status,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    'notes' => $status === 'completed' ? 'All items received' : 'Pending receipt',
                ]);

                foreach ($poItems as $pi) {
                    PurchaseOrderItem::create(array_merge($pi, ['purchase_order_id' => $po->id]));
                }
                $poCount++;
            }
        }
        $this->command->info("$poCount purchase orders created with items.");

        // ────────────────────────────────────
        // SALES ORDERS
        // ────────────────────────────────────
        $soStatuses = ['pending', 'approved', 'processing', 'delivered', 'cancelled'];
        $soCount = 0;

        foreach ($stores as $store) {
            for ($i = 0; $i < 3; $i++) {
                $status = $soStatuses[array_rand($soStatuses)];
                $items = $products->random(rand(2, 5));
                $subtotal = 0;
                $soItems = [];

                foreach ($items as $product) {
                    $qty = rand(5, 30);
                    $storePrice = StorePrice::where('retail_store_id', $store->id)
                        ->where('product_id', $product->id)->first();
                    $price = $storePrice ? $storePrice->price : $product->selling_price;
                    $total = round($qty * $price, 2);
                    $subtotal += $total;
                    $soItems[] = [
                        'product_id' => $product->id,
                        'quantity_ordered' => $qty,
                        'unit_price' => $price,
                        'total_price' => $total,
                        'status' => $status === 'delivered' ? 'delivered' : 'pending',
                    ];
                }

                $discount = rand(0, 1) ? round($subtotal * (rand(1, 10) / 100), 2) : 0;
                $afterDiscount = round($subtotal - $discount, 2);
                $tax = round($afterDiscount * 0.10, 2);
                $total = round($afterDiscount + $tax, 2);

                $so = SalesOrder::create([
                    'order_number' => 'SO-' . str_pad($soCount + 1, 4, '0', STR_PAD_LEFT),
                    'retail_store_id' => $store->id,
                    'warehouse_id' => $warehouse->id,
                    'created_by' => $salesId,
                    'order_date' => now()->subDays(rand(0, 14)),
                    'status' => $status,
                    'source' => 'web',
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'tax' => $tax,
                    'total' => $total,
                    'balance_due' => $status === 'delivered' ? 0 : $total,
                    'notes' => $status === 'cancelled' ? 'Cancelled by customer' : '',
                ]);

                foreach ($soItems as $si) {
                    OrderItem::create(array_merge($si, ['sales_order_id' => $so->id]));
                }
                $soCount++;
            }
        }
        $this->command->info("$soCount sales orders created with items.");
    }
}
