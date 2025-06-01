<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceSchema extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'level_name',
        'level_order',
        'discount_percentage',
        'purchase_price',
        'selling_price',
        'profit_amount',
        'notes',
    ];

    protected $casts = [
        'discount_percentage' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'profit_amount' => 'decimal:2',
        'level_order' => 'integer',
    ];


    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function getProfitMarginPercentageAttribute()
    {
        if ($this->selling_price > 0) {
            return round(($this->profit_amount / $this->selling_price) * 100, 2);
        }
        
        return 0;
    }
}
