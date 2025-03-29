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
        
    }
}
