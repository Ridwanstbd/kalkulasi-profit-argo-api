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
        return $this->belongsTo(ProductCategory::class);
    }

    public function productCosts()
    {
        return $this->hasMany(ProductCost::class);
    }

    public function productMaterials()
    {
        return $this->belongsToMany(Material::class, 'product_materials')->withPivot('quantity');
    }
}
