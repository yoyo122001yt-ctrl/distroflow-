<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use App\Models\Product;
use App\Models\Batch;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class DemoProductsSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::first();

        // Categories
        $categories = [
            ['name' => 'Beverages', 'slug' => 'beverages'],
            ['name' => 'Snacks', 'slug' => 'snacks'],
            ['name' => 'Dairy', 'slug' => 'dairy'],
            ['name' => 'Frozen', 'slug' => 'frozen'],
            ['name' => 'Grocery', 'slug' => 'grocery'],
        ];

        foreach ($categories as $cat) {
            ProductCategory::create($cat);
        }

        // Supplier
        $suppliers = [
            ['code' => 'SUP-001', 'business_name' => 'Coca-Cola Distributors', 'contact_person' => 'Mike Cola', 'phone' => '+1555200001', 'email' => 'orders@cocacola.com', 'city' => 'Atlanta', 'state' => 'GA'],
            ['code' => 'SUP-002', 'business_name' => 'PepsiCo Foods', 'contact_person' => 'Sarah Chips', 'phone' => '+1555200002', 'email' => 'orders@pepsico.com', 'city' => 'Dallas', 'state' => 'TX'],
            ['code' => 'SUP-003', 'business_name' => 'Dairy Fresh Inc', 'contact_person' => 'Tom Milk', 'phone' => '+1555200003', 'email' => 'orders@dairyfresh.com', 'city' => 'Madison', 'state' => 'WI'],
            ['code' => 'SUP-004', 'business_name' => 'Frozen Foods Co', 'contact_person' => 'Lisa Frost', 'phone' => '+1555200004', 'email' => 'orders@frozenfoods.com', 'city' => 'Minneapolis', 'state' => 'MN'],
        ];

        foreach ($suppliers as $s) {
            Supplier::create($s);
        }

        // Products
        $products = [
            ['Coca-Cola 12oz Can (24pk)', 'BEV-001', 'beverages', 8.50, 12.00, true, 180, 100],
            ['Pepsi 12oz Can (24pk)', 'BEV-002', 'beverages', 8.25, 11.50, true, 180, 100],
            ['Spring Water 16.9oz (24pk)', 'BEV-003', 'beverages', 4.00, 6.50, true, 365, 200],
            ['Orange Juice 64oz', 'BEV-004', 'beverages', 3.25, 5.49, true, 60, 50],
            ['Lays Classic Chips (48pk)', 'SNK-001', 'snacks', 12.00, 18.50, true, 120, 80],
            ['Doritos Nacho Cheese (48pk)', 'SNK-002', 'snacks', 12.50, 18.99, true, 120, 80],
            ['Oreo Cookies (36pk)', 'SNK-003', 'snacks', 10.00, 15.50, true, 180, 60],
            ['Whole Milk Gallon', 'DRY-001', 'dairy', 2.50, 4.29, true, 14, 40],
            ['Greek Yogurt 32oz', 'DRY-002', 'dairy', 3.00, 5.49, true, 30, 30],
            ['Cheddar Cheese Block 16oz', 'DRY-003', 'dairy', 3.75, 5.99, true, 90, 30],
            ['Frozen Pizza 12"', 'FRZ-001', 'frozen', 4.00, 7.99, true, 180, 60],
            ['Ice Cream Vanilla 1gal', 'FRZ-002', 'frozen', 5.00, 8.99, true, 180, 40],
            ['Frozen Vegetables (5lb)', 'FRZ-003', 'frozen', 6.00, 9.99, true, 365, 50],
            ['White Bread (20pk)', 'GRC-001', 'grocery', 1.50, 3.29, true, 14, 80],
            ['Cooking Oil 1gal', 'GRC-002', 'grocery', 8.00, 12.99, true, 365, 40],
            ['Rice 20lb Bag', 'GRC-003', 'grocery', 10.00, 15.99, true, 730, 30],
            ['Pasta Spaghetti 1lb (24pk)', 'GRC-004', 'grocery', 7.00, 11.50, true, 730, 50],
        ];

        $catMap = ProductCategory::pluck('id', 'slug');

        foreach ($products as $p) {
            $product = Product::create([
                'category_id' => $catMap[$p[2]],
                'name' => $p[0],
                'sku' => $p[1],
                'barcode' => 'BAR-' . $p[1],
                'unit' => 'case',
                'cost_price' => $p[3],
                'selling_price' => $p[4],
                'is_expiry_tracked' => $p[5],
                'shelf_life_days' => $p[6],
                'min_stock_level' => $p[7],
                'max_stock_level' => $p[7] * 3,
                'is_active' => true,
            ]);

            // Create batches with varying expiry dates
            for ($i = 0; $i < 3; $i++) {
                $expiryDate = now()->addDays($p[6] / 3 * ($i + 1));
                $qty = rand(50, 200);

                Batch::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'batch_number' => "BATCH-{$p[1]}-" . str_pad(($i + 1), 3, '0', STR_PAD_LEFT),
                    'manufacturing_date' => now()->subDays($p[6] - ($p[6] / 3 * ($i + 1))),
                    'expiry_date' => $expiryDate,
                    'quantity' => $qty,
                    'available_quantity' => $qty,
                    'cost_price' => $p[3],
                    'supplier_id' => rand(1, 4),
                    'received_date' => now()->subDays($i * 30),
                    'status' => $expiryDate->isPast() ? 'expired' : 'available',
                ]);
            }
        }

        $this->command->info(count($products) . ' products created with batches.');
    }
}
