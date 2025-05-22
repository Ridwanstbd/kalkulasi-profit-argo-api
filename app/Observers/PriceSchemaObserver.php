<?php

namespace App\Observers;

use App\Models\PriceSchema;
use App\Models\Project;

class PriceSchemaObserver
{
    /**
     * Handle the PriceSchema "created" event.
     */
    public function created(PriceSchema $priceSchema): void
    {
        $this->updateprojectSellingPrice($priceSchema);
    }

    /**
     * Handle the PriceSchema "updated" event.
     */
    public function updated(PriceSchema $priceSchema): void
    {
        $this->updateprojectSellingPrice($priceSchema);
    }

    /**
     * Handle the PriceSchema "deleted" event.
     */
    public function deleted(PriceSchema $priceSchema): void
    {
        $this->updateprojectSellingPrice($priceSchema);
    }

    /**
     * Update the project's selling price based on the highest level order schema
     */
    private function updateprojectSellingPrice(PriceSchema $priceSchema): void
    {
        $projectId = $priceSchema->project_id;
        
        $highestLevelSchema = PriceSchema::where('project_id', $projectId)
            ->orderBy('level_order', 'desc')
            ->first();
        
        if ($highestLevelSchema) {
            Project::where('id', $projectId)->update([
                'selling_price' => $highestLevelSchema->selling_price
            ]);
        } else {
            $project = Project::find($projectId);
            if ($project) {
                $project->update([
                    'selling_price' => $project->hpp ?? null
                ]);
            }
        }
    }
}