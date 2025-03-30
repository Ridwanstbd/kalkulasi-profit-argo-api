<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingSimulation extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'name',
        'base_hpp',
        'margin_type',
        'margin_value',
        'discount_type',
        'discount_value',
        'price_before_discount',
        'retail_price',
        'profit',
        'profit_percentage',
        'market_position',
        'notes',
        'is_applied',
    ];

    protected $casts = [
        'base_hpp' => 'float',
        'margin_value' => 'float',
        'discount_value' => 'float',
        'price_before_discount' => 'float',
        'retail_price' => 'float',
        'profit' => 'float',
        'profit_percentage' => 'float',
        'competitor_price' => 'float',
        'is_applied' => 'boolean',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function getFormattedMarginAttribute()
    {
        if ($this->margin_type === 'percentage') {
            return $this->margin_value . '%';
        } else {
            return 'Rp ' . number_format($this->margin_value, 0, ',', '.');
        }
    }
    public function getFormattedDiscountAttribute()
    {
        if (!$this->discount_type || $this->discount_value <= 0) {
            return '-';
        }
        
        if ($this->discount_type === 'percentage') {
            return $this->discount_value . '%';
        } else {
            return 'Rp ' . number_format($this->discount_value, 0, ',', '.');
        }
    }
    public function getApplicationStatusAttribute()
    {
        return $this->is_applied ? 'Diterapkan' : 'Simulasi';
    }
}
