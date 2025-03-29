<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'unit',
        'price_per_unit',
        'current_stock',
        'min_stock',
        'description',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(MaterialCategory::class);
    }

    public function supplierMaterials()
    {
        return $this->hasMany(SupplierMaterial::class);
    }

    public function productMaterials()
    {
        return $this->belongsToMany(Product::class, 'product_materials')->withPivot('quantity');
    }
}
