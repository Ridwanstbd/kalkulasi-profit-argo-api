<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'duration',
        'features',
        'max_products',
    ];

    public function userSubscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }
}
