<?php

namespace App\Http\Controllers\Feature;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SalesRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class RevenueTargetController extends Controller
{
    /**
     * Get monthly average profit percentage
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function monthlyAverageProfitPercentage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:2900',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = JWTAuth::user();
            $year = $request->year;
            
            // Initialize result array with all months
            $monthlyData = [];
            for ($month = 1; $month <= 12; $month++) {
                $monthlyData[$month] = [
                    'month' => $month,
                    'total_sales' => 0,
                    'total_profit' => 0,
                    'profit_percentage' => 0,
                    'product_count' => 0,
                    'products_with_sales' => 0
                ];
            }
            
            // Get all sales records for the year
            $salesRecords = SalesRecord::where('user_id', $user->id)
                ->where('year', $year)
                ->with('product') // eager load products
                ->get();
                
            // Calculate monthly totals
            foreach ($salesRecords as $record) {
                $month = $record->month;
                $product = $record->product;
                
                if ($product) {
                    $salesAmount = $product->selling_price * $record->number_of_sales;
                    $profitAmount = ($product->selling_price - $product->hpp) * $record->number_of_sales;
                    
                    $monthlyData[$month]['total_sales'] += $salesAmount;
                    $monthlyData[$month]['total_profit'] += $profitAmount;
                    $monthlyData[$month]['products_with_sales']++;
                }
            }
            
            // Get total product count for reference
            $totalProducts = Product::where('user_id', $user->id)->count();
            
            // Calculate percentages and add product counts
            foreach ($monthlyData as $month => &$data) {
                $data['profit_percentage'] = $data['total_sales'] > 0 ? 
                    round(($data['total_profit'] / $data['total_sales']) * 100, 2) : 0;
                $data['product_count'] = $totalProducts;
                
                // Format month name
                $monthName = date('F', mktime(0, 0, 0, $month, 1));
                $data['month_name'] = $monthName;
            }
            
            // Calculate yearly average
            $yearlyTotalSales = array_sum(array_column($monthlyData, 'total_sales'));
            $yearlyTotalProfit = array_sum(array_column($monthlyData, 'total_profit'));
            $yearlyAveragePercentage = $yearlyTotalSales > 0 ? 
                round(($yearlyTotalProfit / $yearlyTotalSales) * 100, 2) : 0;
                
            // Calculate averages from months with sales only
            $monthsWithSales = array_filter($monthlyData, function($item) {
                return $item['total_sales'] > 0;
            });
            
            $averageProfitPercentage = count($monthsWithSales) > 0 ? 
                round(array_sum(array_column($monthsWithSales, 'profit_percentage')) / count($monthsWithSales), 2) : 0;
            
            return response()->json([
                'success' => true,
                'monthly_data' => array_values($monthlyData),
                'summary' => [
                    'year' => $year,
                    'yearly_total_sales' => $yearlyTotalSales,
                    'yearly_total_profit' => $yearlyTotalProfit, 
                    'yearly_profit_percentage' => $yearlyAveragePercentage,
                    'average_monthly_profit_percentage' => $averageProfitPercentage,
                    'total_products' => $totalProducts,
                    'months_with_sales' => count($monthsWithSales)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
}
