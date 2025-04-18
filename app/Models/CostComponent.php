<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CostComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'component_type',
    ];
    public const TYPES = [
        'direct_material',
        'direct_labor',
        'overhead',
        'packaging',
        'other'
    ];

    public function productCosts()
    {
        return $this->hasMany(ProductCost::class,'cost_component_id');
    }
}
