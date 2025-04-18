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
        'unit', // satuan koversi contoh 'm', 'pcs'
        'unit_price', // harga per satuan beli
        'quantity', // kebutuhan untuk produk (misal 3m, 1pc)
        'conversion_qty' // jumlah konversi dari satuan beli (misal 90m dalam 1 roll)
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function costComponent()
    {
        return $this->belongsTo(CostComponent::class, 'cost_component_id');
    }

    public function scopeByComponentType($query,$type)
    {
        return $query->whereHas('costComponent', function ($q) use ($type) {
            $q->where('component_type', $type);
        });
    }

    public function getCalculatedAmountAttribute()
    {
        if ($this->conversion_qty && $this->conversion_qty > 0) {
            return ($this->unit_price / $this->conversion_qty) * $this->quantity;
        }

        return $this->amount;
    }

}
