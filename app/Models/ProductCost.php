<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCost extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'cost_component_id',
        'amount',
        'description',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function costComponent()
    {
        return $this->belongsTo(CostComponent::class, 'cost_component_id');
    }
}
