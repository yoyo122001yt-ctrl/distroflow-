<?php

namespace Database\Factories;

use App\Models\RetailStore;
use Illuminate\Database\Eloquent\Factories\Factory;

class RetailStoreFactory extends Factory
{
    protected $model = RetailStore::class;

    public function definition(): array
    {
        $types = ['grocery', 'convenience', 'pharmacy', 'restaurant', 'hotel'];
        $terms = ['cod', 'net_15', 'net_30', 'net_45'];

        return [
            'code' => 'STR-' . $this->faker->unique()->numerify('####'),
            'business_name' => $this->faker->company(),
            'store_type' => $this->faker->randomElement($types),
            'contact_person' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'zip' => $this->faker->postcode(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'credit_limit' => $this->faker->randomFloat(2, 5000, 100000),
            'current_balance' => $this->faker->randomFloat(2, 0, 50000),
            'payment_terms' => $this->faker->randomElement($terms),
            'status' => 'active',
        ];
    }
}
