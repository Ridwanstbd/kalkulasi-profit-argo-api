<?php

namespace Database\Factories;

use App\Models\PriceSchema;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PriceSchema>
 */
class PriceSchemaFactory extends Factory
{
    protected $model = PriceSchema::class;

    public function definition(): array
    {
        $purchasePrice = $this->faker->randomFloat(2, 1000, 5000);
        $discountPercentage = $this->faker->randomFloat(2, 0, 50);
        $sellingPrice = $purchasePrice * (1 + (100 - $discountPercentage) / 100);
        $profitAmount = $sellingPrice - $purchasePrice;

        return [
            'service_id' => Service::factory(),
            'level_name' => $this->faker->randomElement(['Grosir', 'Reseller', 'Agen', 'Distributor']),
            'level_order' => $this->faker->numberBetween(1, 5),
            'discount_percentage' => $discountPercentage,
            'purchase_price' => $purchasePrice,
            'selling_price' => $sellingPrice,
            'profit_amount' => $profitAmount,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
