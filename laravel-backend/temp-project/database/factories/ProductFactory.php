<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $categories = ProductCategory::pluck('id')->toArray();

        return [
            'category_id' => $this->faker->randomElement($categories),
            'name' => $this->faker->unique()->words(3, true),
            'name_ar' => $this->faker->boolean(50) ? $this->faker->words(3, true) : null,
            'sku' => 'SKU-' . strtoupper($this->faker->unique()->bothify('??###')),
            'barcode' => $this->faker->unique()->ean13(),
            'description' => $this->faker->sentence(),
            'unit' => $this->faker->randomElement(['case', 'piece', 'box', 'pallet']),
            'cost_price' => $this->faker->randomFloat(2, 1, 50),
            'selling_price' => $this->faker->randomFloat(2, 2, 80),
            'weight' => $this->faker->randomFloat(2, 0.1, 50),
            'is_expiry_tracked' => $this->faker->boolean(80),
            'shelf_life_days' => $this->faker->randomElement([30, 60, 90, 180, 365]),
            'min_stock_level' => $this->faker->numberBetween(10, 100),
            'max_stock_level' => $this->faker->numberBetween(200, 1000),
            'is_active' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }
}
