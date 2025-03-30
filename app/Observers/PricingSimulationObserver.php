<?php

namespace App\Observers;

use App\Models\PricingSimulation;
use Illuminate\Support\Facades\DB;

class PricingSimulationObserver
{
    public function saving(PricingSimulation $pricingSimulation)
    {
        if($pricingSimulation->is_applied){
            DB::table('pricing_simulations')
                ->where('product_id', $pricingSimulation->product_id)
                ->where('id','!=', $pricingSimulation->id)
                ->where('is_applied',true)
                ->update(['is_applied' => false]);
        }
    }
}
