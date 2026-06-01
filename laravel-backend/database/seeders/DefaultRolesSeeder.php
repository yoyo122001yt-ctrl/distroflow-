<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\DriverProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultRolesSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@distroflow.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => '+1555000001',
            'is_active' => true,
        ]);

        $manager = User::create([
            'name' => 'Warehouse Manager',
            'email' => 'manager@distroflow.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'phone' => '+1555000002',
            'is_active' => true,
        ]);

        $accountant = User::create([
            'name' => 'Accountant User',
            'email' => 'accountant@distroflow.com',
            'password' => Hash::make('password'),
            'role' => 'accountant',
            'phone' => '+1555000003',
            'is_active' => true,
        ]);

        $salesRep = User::create([
            'name' => 'Sales Representative',
            'email' => 'sales@distroflow.com',
            'password' => Hash::make('password'),
            'role' => 'sales',
            'phone' => '+1555000004',
            'is_active' => true,
        ]);

        // Drivers
        $driver1 = User::create([
            'name' => 'John Driver',
            'email' => 'driver1@distroflow.com',
            'password' => Hash::make('password'),
            'role' => 'driver',
            'phone' => '+1555000010',
            'is_active' => true,
        ]);
        DriverProfile::create([
            'user_id' => $driver1->id,
            'license_number' => 'DL-1001',
            'license_expiry' => '2026-12-31',
            'status' => 'available',
        ]);

        $driver2 = User::create([
            'name' => 'Jane Driver',
            'email' => 'driver2@distroflow.com',
            'password' => Hash::make('password'),
            'role' => 'driver',
            'phone' => '+1555000011',
            'is_active' => true,
        ]);
        DriverProfile::create([
            'user_id' => $driver2->id,
            'license_number' => 'DL-1002',
            'license_expiry' => '2026-12-31',
            'status' => 'available',
        ]);

        $this->command->info('Default users created:');
        $this->command->info('  admin@distroflow.com / password (admin)');
        $this->command->info('  manager@distroflow.com / password (manager)');
        $this->command->info('  driver1@distroflow.com / password (driver)');
        $this->command->info('  driver2@distroflow.com / password (driver)');
    }
}
