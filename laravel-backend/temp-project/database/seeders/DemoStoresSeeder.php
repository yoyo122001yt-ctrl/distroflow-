<?php

namespace Database\Seeders;

use App\Models\RetailStore;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class DemoStoresSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::first();

        $stores = [
            ['STR-001', 'Mega Mart Chicago', 'grocery', 'John Manager', '+13125550001', 'orders@megamart.com', '100 N Michigan Ave', 'Chicago', 'IL', '60601', 41.8826, -87.6246, 50000, 'net_30'],
            ['STR-002', 'Quick Stop Convenience', 'convenience', 'Sarah Owner', '+13125550002', 'quickstop@email.com', '2500 W Chicago Ave', 'Chicago', 'IL', '60622', 41.8958, -87.6905, 10000, 'cod'],
            ['STR-003', 'Family Pharmacy', 'pharmacy', 'Dr. Wilson', '+13125550003', 'rx@familypharmacy.com', '3505 N Lincoln Ave', 'Chicago', 'IL', '60657', 41.9440, -87.6718, 25000, 'net_30'],
            ['STR-004', 'Corner Bistro', 'restaurant', 'Chef Marco', '+13125550004', 'marco@cornerbistro.com', '800 S Halsted St', 'Chicago', 'IL', '60607', 41.8719, -87.6475, 15000, 'net_15'],
            ['STR-005', 'Downtown Hotel Supply', 'hotel', 'Robert Frontdesk', '+13125550005', 'purchasing@dthotel.com', '200 N Columbus Dr', 'Chicago', 'IL', '60601', 41.8865, -87.6208, 75000, 'net_45'],
            ['STR-006', 'Fresh Grocers', 'grocery', 'Maria Fresh', '+13125550006', 'maria@freshgrocers.com', '4500 W Diversey Ave', 'Chicago', 'IL', '60639', 41.9325, -87.7435, 30000, 'net_30'],
            ['STR-007', 'Express Gas & Go', 'convenience', 'Ali Khan', '+13125550007', 'ali@expressgas.com', '5600 S Cicero Ave', 'Chicago', 'IL', '60638', 41.7900, -87.7417, 8000, 'cod'],
            ['STR-008', 'Hyde Park Pharmacy', 'pharmacy', 'Dr. Brown', '+13125550008', 'brown@hydeparkrx.com', '1500 E 55th St', 'Chicago', 'IL', '60615', 41.7945, -87.5887, 20000, 'net_30'],
            ['STR-009', 'Pizza Roma', 'restaurant', 'Giuseppe Romano', '+13125550009', 'giuseppe@pizzaroma.com', '2800 S Western Ave', 'Chicago', 'IL', '60608', 41.8415, -87.6850, 12000, 'net_15'],
            ['STR-010', 'Lakeview Inn', 'hotel', 'Nancy Frontdesk', '+13125550010', 'nancy@lakeviewinn.com', '3200 N Lake Shore Dr', 'Chicago', 'IL', '60657', 41.9400, -87.6422, 40000, 'net_30'],
        ];

        foreach ($stores as $s) {
            RetailStore::create([
                'code' => $s[0],
                'business_name' => $s[1],
                'store_type' => $s[2],
                'contact_person' => $s[3],
                'phone' => $s[4],
                'email' => $s[5],
                'address' => $s[6],
                'city' => $s[7],
                'state' => $s[8],
                'zip' => $s[9],
                'latitude' => $s[10],
                'longitude' => $s[11],
                'credit_limit' => $s[12],
                'current_balance' => rand(0, (int)($s[12] * 0.6)),
                'payment_terms' => $s[13],
                'warehouse_id' => $warehouse->id,
                'status' => 'active',
            ]);
        }

        $this->command->info(count($stores) . ' retail stores created.');
    }
}
