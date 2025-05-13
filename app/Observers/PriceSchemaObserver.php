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

    /**
     * Update the product's selling price based on the highest level order schema
     */
    private function updateProductSellingPrice(PriceSchema $priceSchema): void
    {
        $productId = $priceSchema->product_id;
        
        $highestLevelSchema = PriceSchema::where('product_id', $productId)
            ->orderBy('level_order', 'desc')
            ->first();
        
        if ($highestLevelSchema) {
            Product::where('id', $productId)->update([
                'selling_price' => $highestLevelSchema->selling_price
            ]);
        } else {
            $product = Product::find($productId);
            if ($product) {
                $product->update([
                    'selling_price' => $product->hpp ?? null
                ]);
            }
        }
    }
}