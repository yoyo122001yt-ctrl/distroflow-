<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use App\Models\WarehouseZone;
use App\Models\WarehouseLocation;
use App\Models\Truck;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class DemoWarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-001',
            'address' => '123 Industrial Blvd',
            'city' => 'Chicago',
            'state' => 'IL',
            'zip' => '60601',
            'phone' => '+15551234567',
            'email' => 'warehouse@distroflow.com',
            'latitude' => 41.8781,
            'longitude' => -87.6298,
            'is_active' => true,
        ]);

        $zones = ['A', 'B', 'C', 'D', 'Returns'];
        foreach ($zones as $zoneName) {
            $zone = WarehouseZone::create([
                'warehouse_id' => $warehouse->id,
                'name' => "Zone {$zoneName}",
                'code' => "Z-{$zoneName}",
                'type' => $zoneName === 'Returns' ? 'returns' : 'storage',
                'is_active' => true,
            ]);

            for ($rack = 1; $rack <= 5; $rack++) {
                for ($shelf = 1; $shelf <= 4; $shelf++) {
                    WarehouseLocation::create([
                        'warehouse_zone_id' => $zone->id,
                        'rack' => "R-{$zoneName}-{$rack}",
                        'shelf' => "S-{$shelf}",
                        'bin' => "B-{$zoneName}-{$rack}-{$shelf}",
                        'barcode' => "LOC-{$zoneName}-{$rack}-{$shelf}",
                        'is_active' => true,
                    ]);
                }
            }
        }

        // Trucks
        $trucks = [
            ['code' => 'TRK-001', 'plate' => 'ABC-1001', 'model' => 'Ford Transit 350'],
            ['code' => 'TRK-002', 'plate' => 'ABC-1002', 'model' => 'Mercedes Sprinter'],
            ['code' => 'TRK-003', 'plate' => 'ABC-1003', 'model' => 'RAM ProMaster 3500'],
        ];

        foreach ($trucks as $t) {
            Truck::create([
                'code' => $t['code'],
                'plate_number' => $t['plate'],
                'model' => $t['model'],
                'year' => 2024,
                'capacity_weight' => 3500.00,
                'capacity_volume' => 500.00,
                'status' => 'available',
                'is_active' => true,
            ]);
        }

        // Settings
        SystemSetting::create(['key' => 'company_name', 'value' => 'DistroFlow Distribution', 'group' => 'general']);
        SystemSetting::create(['key' => 'company_address', 'value' => '123 Industrial Blvd, Chicago, IL 60601', 'group' => 'general']);
        SystemSetting::create(['key' => 'company_phone', 'value' => '+15551234567', 'group' => 'general']);
        SystemSetting::create(['key' => 'currency', 'value' => 'USD', 'group' => 'general']);
        SystemSetting::create(['key' => 'tax_rate', 'value' => '8.25', 'group' => 'general']);
        SystemSetting::create(['key' => 'variance_threshold_cash', 'value' => '10.00', 'group' => 'settlement']);
        SystemSetting::create(['key' => 'variance_threshold_inventory', 'value' => '0.01', 'group' => 'settlement']);

        $this->command->info("Warehouse, zones, locations, trucks, and settings created.");
    }
}
