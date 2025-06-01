<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'service_id',
        'month',
        'year',
        'date',
        'hpp',
        'selling_price'
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
