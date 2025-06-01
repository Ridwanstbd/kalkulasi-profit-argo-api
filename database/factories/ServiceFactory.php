<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'sku' => $this->faker->unique()->ean8(),
            'description' => $this->faker->sentence(),
            'hpp' => $this->faker->numberBetween(10000, 50000),
            'selling_price' => $this->faker->numberBetween(50000, 100000),
        ];
    }
}