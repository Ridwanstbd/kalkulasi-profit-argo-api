<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'month',
        'year',
        'number_of_sales',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
