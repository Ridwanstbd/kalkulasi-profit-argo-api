<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'sku',
        'description',
        'production_capacity',
        'unit',
        'hpp',
        'selling_price',
        'min_stock',
        'current_stock',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function costs()
    {
        return $this->hasMany(ProductCost::class);
    }

    public function materials()
    {
        return $this->belongsToMany(Material::class, 'product_materials')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }
    
    public function getHppBreakdownAttribute()
    {
        $directMaterial = 0;
        $directLabor = 0;
        $overhead = 0;
        $packaging = 0;
        $other = 0;

        foreach ($this->costs as $cost) {
            switch ($cost->costComponent->component_type){
                case 'direct_material':
                    $directMaterial += $cost->amount;
                    break;
                case 'direct_labor':
                    $directLabor += $cost->amount;
                    break;
                case 'overhead':
                    $overhead += $cost->amount;
                    break;
                case 'packaging':
                    $packaging += $cost->amount;
                    break;
                case 'other':
                    $other += $cost->amount;
                    break;

            }
        }
        $rawMaterialCost = 0;
        foreach($this->materials as $material){
            $rawMaterialCost += $material->pivot->quantity * $material->price_per_unit; 
        }
        $totalDirectMaterial = $directMaterial + $rawMaterialCost;
        $totalHpp = $totalDirectMaterial + $directLabor + $overhead + $packaging + $other;

        $percentageDirectMaterial = $totalHpp > 0 ? ($totalDirectMaterial / $totalHpp) * 100 : 0;
        $percentageDirectLabor = $totalHpp > 0 ? ($directLabor / $totalHpp) * 100 : 0;
        $percentageOverhead = $totalHpp ? ($overhead / $totalHpp) * 100 : 0;
        $percentagePackaging = $totalHpp > 0 ? ($packaging / $totalHpp) : 0;
        $percentageOther = $totalHpp > 0 ? ($other / $totalHpp) * 100 : 0;

        return [
            'direct_material' => [
                'amount' => round($totalDirectMaterial,2),
                'percentage' => round($percentageDirectMaterial,2)
            ],
            'direct_labor' => [
                'amount' => round($directLabor,2),
                'percentage' => round($percentageDirectLabor,2)
            ],
            'overhead' => [
                'amount' => round($overhead,2),
                'percentage' => round($percentageOverhead,2)
            ],
            'packaging' => [
                'amount' => round($packaging,2),
                'percentage' => round($percentagePackaging,2)
            ],
            'other' => [
                'amount' => round($other,2),
                'percentage' => round($percentageOther,2)
            ],
            'total' => round($totalHpp,2)
        ];
    }
}
