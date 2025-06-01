<?php

namespace App\Http\Controllers\Feature;

use App\Http\Controllers\Controller;
use App\Models\OperationalExpense;
use App\Models\SalesRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class StatsController extends Controller
{
    public function stats(Request $request)
    {
        $year = $request->input('year', Carbon::now()->year);
        $month = $request->input('month', Carbon::now()->month);
        
        $validationResult = $this->validateIndexParams($year, $month);
        if ($validationResult !== true) {
            return $validationResult;
        }
        
        $year = (int) $year;
        $month = $month ? (int) $month : null;
        
        $availableYears = SalesRecord::selectRaw('DISTINCT year')
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();
        
        $availableMonths = SalesRecord::where('year', $year)
            ->selectRaw('DISTINCT month')
            ->orderBy('month', 'asc')
            ->pluck('month')
            ->toArray();
        
        $salesRecords = SalesRecord::where('year', $year)
            ->where('month', $month)
            ->get();
        
        $totalSales = $salesRecords->sum(function ($record) {
            return $record->selling_price;
        });
        
        $totalVariableCost = $salesRecords->sum(function ($record) {
            return $record->hpp;
        });
        
        $totalOperationalCost = OperationalExpense::getTotalOperationalExpenses($year, $month);
        
        $totalSalaryExpenses = OperationalExpense::getTotalSalaryExpenses($year, $month);
        
        $totalCost = $totalVariableCost + $totalOperationalCost + $totalSalaryExpenses;
        $grossProfit = $totalSales - $totalVariableCost;
        $netProfit = $grossProfit - $totalOperationalCost - $totalSalaryExpenses;
        
        return response()->json([
            'success' => true,
            'data' => [
                'total_sales' => round($totalSales, 2),
                'total_cost' => round($totalCost, 2),
                'total_variable_cost' => round($totalVariableCost, 2),
                'total_operational_cost' => round($totalOperationalCost, 2),
                'total_salary_expenses' => round($totalSalaryExpenses, 2),
                'gross_profit' => round($grossProfit, 2),
                'net_profit' => round($netProfit, 2),
                'year' => $year,
                'month' => $month,
                'availableYears' => $availableYears,
                'availableMonths' => $availableMonths
            ]
        ]);
    }

    private function validateIndexParams($year, $month)
    {
        $validator = Validator::make([
            'year' => $year,
            'month' => $month,
        ], [
            'year' => 'required|integer|min:2000|max:2900',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        return true;
    }
}