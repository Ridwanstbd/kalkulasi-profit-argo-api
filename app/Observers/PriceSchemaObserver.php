<?php

namespace App\Observers;

use App\Models\PriceSchema;
use App\Models\Service;

class PriceSchemaObserver
{
    /**
     * Handle the PriceSchema "created" event.
     */
    public function created(PriceSchema $priceSchema): void
    {
        $this->updateServiceSellingPrice($priceSchema);
    }

    /**
     * Handle the PriceSchema "updated" event.
     */
    public function updated(PriceSchema $priceSchema): void
    {
        $this->updateServiceSellingPrice($priceSchema);
    }

    /**
     * Handle the PriceSchema "deleted" event.
     */
    public function deleted(PriceSchema $priceSchema): void
    {
        $this->updateServiceSellingPrice($priceSchema);
    }

    /**
     * Update the project's selling price based on the highest level order schema
     */
    private function updateServiceSellingPrice(PriceSchema $priceSchema): void
    {
        $serviceId = $priceSchema->service_id;
        
        $highestLevelSchema = PriceSchema::where('service_id', $serviceId)
            ->orderBy('level_order', 'desc')
            ->first();
        
        if ($highestLevelSchema) {
            Service::where('id', $serviceId)->update([
                'selling_price' => $highestLevelSchema->selling_price
            ]);
        } else {
            $project = Service::find($serviceId);
            if ($project) {
                $project->update([
                    'selling_price' => $project->hpp ?? null
                ]);
            }
        }
    }
}