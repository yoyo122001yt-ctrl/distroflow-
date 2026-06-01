<?php

namespace Database\Factories;

use App\Models\SalesOrder;
use App\Models\OrderItem;
use App\Models\RetailStore;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    public function definition(): array
    {
        $store = RetailStore::inRandomOrder()->first();
        $user = User::inRandomOrder()->first();
        $warehouse = Warehouse::first();

        $subtotal = $this->faker->randomFloat(2, 50, 5000);
        $tax = $subtotal * 0.0825;
        $total = $subtotal + $tax;

        return [
            'order_number' => 'ORD-' . strtoupper($this->faker->unique()->bothify('??####')),
            'retail_store_id' => $store->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'order_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'status' => $this->faker->randomElement(['pending', 'approved', 'assigned', 'delivered', 'cancelled']),
            'source' => $this->faker->randomElement(['phone', 'app', 'driver', 'walk-in']),
            'subtotal' => $subtotal,
            'discount' => 0,
            'tax' => $tax,
            'total' => $total,
            'balance_due' => $total,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (SalesOrder $order) {
            $productCount = rand(2, 8);
            $products = Product::inRandomOrder()->limit($productCount)->get();
            $total = 0;

            foreach ($products as $product) {
                $qty = rand(2, 50);
                $price = $order->retailStore->getPriceForProduct($product->id);
                $lineTotal = $qty * $price;
                $total += $lineTotal;

                OrderItem::create([
                    'sales_order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity_ordered' => $qty,
                    'quantity_picked' => 0,
                    'quantity_loaded' => 0,
                    'quantity_delivered' => 0,
                    'quantity_returned' => 0,
                    'unit_price' => $price,
                    'total_price' => $lineTotal,
                    'status' => 'pending',
                ]);
            }

            $order->update([
                'subtotal' => $total,
                'total' => $total + ($total * 0.0825),
                'balance_due' => $total + ($total * 0.0825),
            ]);
        });
    }
}
