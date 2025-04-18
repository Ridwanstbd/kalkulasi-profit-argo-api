<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_salary',
        'order',
        'user_id'
    ];

    protected $casts = [
        'is_salary' => 'boolean',
        'order' => 'integer'
    ];

    /**
     * Get all operational expenses belonging to this category
     */
    public function operationalExpenses()
    {
        return $this->hasMany(OperationalExpense::class);
    }

    /**
     * Get the total amount for all expenses in this category
     */
    public function getTotalAmountAttribute()
    {
        return $this->operationalExpenses()->sum('total_amount');
    }
    
    /**
     * Get the total employee count for salary categories
     */
    public function getTotalEmployeeCountAttribute()
    {
        if (!$this->is_salary) {
            return 0;
        }
        
        return $this->operationalExpenses()->sum('quantity');
    }
}
