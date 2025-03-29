<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'start_date',
        'end_date',
        'status',
        'payment_status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where('end_date', '>=', now()->format('Y-m-d'));
    }

    public function isActive()
    {
        return $this->status === 'active' && $this->end_date >= now();
    }

    public function hasFeatureAccess($feature)
    {
        if(!$this->isActive()){
            return false;
        }
        $features = json_decode($this->subscriptionPlan->features, true);
        return isset($features[$feature]) && $features[$feature] === true;
    }
}
