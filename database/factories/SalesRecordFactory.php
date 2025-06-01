<?php

namespace Database\Factories;

use App\Models\SalesRecord;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class SalesRecordFactory extends Factory
{
    protected $model = SalesRecord::class;

    public function definition()
    {
        $hpp = $this->faker->numberBetween(50000, 200000);
        $sellingPrice = $hpp + $this->faker->numberBetween(10000, 50000);

        return [
            'service_id' => Service::factory(),
            'name' => $this->faker->words(3, true), 
            'month' => $this->faker->numberBetween(1, 12),
            'year' => $this->faker->numberBetween(Carbon::now()->year - 2, Carbon::now()->year),
            'date' => $this->faker->numberBetween(1, 28),
            'hpp' => $hpp,
            'selling_price' => $sellingPrice,
        ];
    }
}