<?php

namespace Database\Factories;

use App\Models\CostComponent;
use App\Models\Service;
use App\Models\ServiceCost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServiceCost>
 */
class ServiceCostFactory extends Factory
{
    protected $model = ServiceCost::class;

    public function definition(): array
    {
        $unitPrice = $this->faker->randomFloat(2, 100, 1000); // Contoh harga satuan
        $quantity = $this->faker->randomFloat(2, 1, 100);
        $conversionQty = $this->faker->randomFloat(2, 1, 10);
        $amount = $unitPrice * $quantity;

        return [
            'service_id' => Service::factory(),
            'cost_component_id' => CostComponent::factory(),
            'unit' => $this->faker->randomElement(['pcs', 'kg', 'ltr', 'box']),
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'conversion_qty' => $conversionQty,
            'amount' => $amount,
        ];
    }
}
