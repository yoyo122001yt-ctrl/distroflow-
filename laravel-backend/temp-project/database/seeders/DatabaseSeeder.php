<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DefaultRolesSeeder::class,
            DemoWarehouseSeeder::class,
            DemoProductsSeeder::class,
            DemoStoresSeeder::class,
            DemoRoutesSeeder::class,
            DemoOrdersSeeder::class,
        ]);
    }
}
