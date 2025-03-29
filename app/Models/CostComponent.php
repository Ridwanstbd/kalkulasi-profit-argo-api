<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CostComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'component_type',
        'is_fixed',
    ];

    public function productCosts()
    {
        return $this->hasMany(ProductCost::class);
    }
}
