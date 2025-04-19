<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperationalExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'expense_category_id',
        'name',
        'quantity',
        'unit',
        'amount',
        'conversion_factor',
        'conversion_unit',
        'total_amount',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'amount' => 'decimal:2',
        'conversion_factor' => 'integer',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Get the category that this expense belongs to
     */
    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /**
     * Calculate total amount before saving
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($expense) {
            $expense->total_amount = $expense->quantity * $expense->amount * $expense->conversion_factor;
        });
    }

    /**
     * Get all expenses grouped by category
     */
    public static function getExpensesByCategory()
    {
        return static::with('category')
            ->orderBy('expense_category_id')
            ->get()
            ->groupBy('expense_category_id');
    }

    /**
     * Get all salary expenses
     */
    public static function getSalaryExpenses()
    {
        return static::with('category')
            ->whereHas('category', function ($query) {
                $query->where('is_salary', true);
            })
            ->get();
    }

    /**
     * Get all operational (non-salary) expenses
     */
    public static function getOperationalExpenses()
    {
        return static::with('category')
            ->whereHas('category', function ($query) {
                $query->where('is_salary', false);
            })
            ->get();
    }

    /**
     * Get total salary expenses
     */
    public static function getTotalSalaryExpenses()
    {
        return static::whereHas('category', function ($query) {
                $query->where('is_salary', true);
            })
            ->sum('total_amount');
    }

    /**
     * Get total operational (non-salary) expenses
     */
    public static function getTotalOperationalExpenses()
    {
        return static::whereHas('category', function ($query) {
                $query->where('is_salary', false);
            })
            ->sum('total_amount');
    }

    /**
     * Get grand total of all expenses
     */
    public static function getGrandTotal()
    {
        return static::sum('total_amount');
    }

    /**
     * Get complete summary of expenses
     */
    public static function getSummary()
    {
        $categories = ExpenseCategory::orderBy('order')->get();
        $expensesByCategory = static::getExpensesByCategory();
        
        $summary = [];
        $totalSalary = 0;
        $totalOperational = 0;
        $totalEmployees = 0;
        
        foreach ($categories as $category) {
            $categoryExpenses = $expensesByCategory->get($category->id, collect([]));
            $categoryTotal = $categoryExpenses->sum('total_amount');
            
            if ($category->is_salary) {
                $totalSalary += $categoryTotal;
                $totalEmployees += $categoryExpenses->sum('quantity');
            } else {
                $totalOperational += $categoryTotal;
            }
            
            $summary[] = [
                'category' => $category->toArray(),
                'expenses' => $categoryExpenses->toArray(),
                'total' => $categoryTotal
            ];
        }
        
        return [
            'details' => $summary,
            'total_salary' => $totalSalary,
            'total_operational' => $totalOperational,
            'grand_total' => $totalSalary + $totalOperational,
            'total_employees' => $totalEmployees
        ];
    }
}
