<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'sku',
        'description',
        'unit',
        'hpp',
        'selling_price',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function costs()
    {
        return $this->hasMany(ProductCost::class);
    }
    public function priceSchemas()
    {
        return $this->hasMany(PriceSchema::class);
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
        
        $totalDirectMaterial = $directMaterial; 
        $totalHpp = $totalDirectMaterial + $directLabor + $overhead + $packaging + $other;

        $percentageDirectMaterial = $totalHpp > 0 ? ($totalDirectMaterial / $totalHpp) * 100 : 0;
        $percentageDirectLabor = $totalHpp > 0 ? ($directLabor / $totalHpp) * 100 : 0;
        $percentageOverhead = $totalHpp > 0 ? ($overhead / $totalHpp) * 100 : 0;
        $percentagePackaging = $totalHpp > 0 ? ($packaging / $totalHpp) * 100 : 0; 
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
