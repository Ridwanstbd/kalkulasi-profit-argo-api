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
    ];
    public const TYPES = [
        'direct_material',
        'indirect_material',
        'direct_labor',
        'overhead',
        'packaging',
        'other'
    ];

    public function serviceCosts()
    {
        return $this->hasMany(ServiceCost::class,'cost_component_id');
    }
}
