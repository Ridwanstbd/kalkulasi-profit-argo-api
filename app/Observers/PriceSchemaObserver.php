<?php

namespace App\Observers;

use App\Models\PriceSchema;
use App\Models\Product;

class PriceSchemaObserver
{
    /**
     * Handle the PriceSchema "created" event.
     */
    public function created(PriceSchema $priceSchema): void
    {
        $this->updateProductSellingPrice($priceSchema);
    }

    /**
     * Handle the PriceSchema "updated" event.
     */
    public function updated(PriceSchema $priceSchema): void
    {
        $this->updateProductSellingPrice($priceSchema);
    }

    /**
     * Handle the PriceSchema "deleted" event.
     */
    public function deleted(PriceSchema $priceSchema): void
    {
        $this->updateProductSellingPrice($priceSchema);
    }   

    private function updateProductSellingPrice(PriceSchema $priceSchema): void
    {
        // Get the product ID
        $productId = $priceSchema->product_id;
        
        // Find the highest selling price for this product
        $highestSellingPrice = PriceSchema::where('product_id', $productId)
            ->max('selling_price');
        
        // Update the product's selling price
        if ($highestSellingPrice) {
            Product::where('id', $productId)->update([
                'selling_price' => $highestSellingPrice
            ]);
        }
    }
}
