<?php

namespace App\Http\Controllers\Feature;

use App\Http\Controllers\Controller;
use App\Models\SalesRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class SalesRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = JWTAuth::user();
        $query = SalesRecord::join('products', 'sales_records.product_id', '=', 'products.id')
            ->where('user_id', $user->id)
            ->where('sales_records.year', $request->year);

        if ($request->has('month') && $request->month) {
            $query->where('sales_records.month', $request->month);
        }

        $salesData = $query->select(
            'products.name as produk',
            'products.hpp as hpp_per_satuan',
            'products.selling_price as harga_jual',
            DB::raw('(products.selling_price - products.hpp) as profit_per_satuan'),
            DB::raw('ROUND(((products.selling_price - products.hpp) / products.selling_price) * 100, 0) as profit_percentage'),
            'sales_records.number_of_sales as qty',
            DB::raw('(products.selling_price * sales_records.number_of_sales) as sub_total'),
            DB::raw('((products.selling_price - products.hpp) * sales_records.number_of_sales) as profit')
        )->get();

        // Calculate totals
        $totalSales = $salesData->sum('sub_total');
        $totalProfit = $salesData->sum('profit');
        $totalProfitPercentage = $totalSales > 0 ? round(($totalProfit / $totalSales) * 100, 2) : 0;

        // Calculate profit contribution percentages
        $salesData = $salesData->map(function ($item) use ($totalProfit) {
            $item['profit_contribution_percentage'] = $totalProfit > 0 ? 
                round(($item['profit'] / $totalProfit) * 100, 2) : 0;
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $salesData,
            'summary' => [
                'total_sales' => $totalSales,
                'total_profit' => $totalProfit,
                'total_profit_percentage' => $totalProfitPercentage
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'number_of_sales' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = JWTAuth::user();
        // Check if record for this product, month, and year already exists
        $existingRecord = SalesRecord::where('product_id', $request->product_id)
            ->where('user_id',$user->id)
            ->where('month', $request->month)
            ->where('year', $request->year)
            ->first();

        if ($existingRecord) {
            // Update existing record
            $existingRecord->number_of_sales = $request->number_of_sales;
            $existingRecord->save();
            $record = $existingRecord;
        } else {
            // Create new record
            $record = SalesRecord::create([
                'user_id' => $user->id,
                'month' => $request['month'],
                'year' => $request['year'],
                'number_of_sales' => $request['number_of_sales']
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Penjualan berhasil disimpan',
            'data' => $record
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = JWTAuth::user();
        $salesRecord = SalesRecord::with('product')
        ->where('user_id',$user->id)
        ->find($id);
        
        if (!$salesRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Penjualan Tidak ditemukan!'
            ], 404);
        }
        
        // Get additional calculated fields
        $product = $salesRecord->product;
        $profitPerUnit = $product->selling_price - $product->hpp;
        $profitPercentage = $product->selling_price > 0 ? 
            round(($profitPerUnit / $product->selling_price) * 100, 2) : 0;
        $subTotal = $product->selling_price * $salesRecord->number_of_sales;
        $totalProfit = $profitPerUnit * $salesRecord->number_of_sales;
        
        $data = $salesRecord->toArray();
        $data['product_name'] = $product->name;
        $data['hpp_per_satuan'] = $product->hpp;
        $data['harga_jual'] = $product->selling_price;
        $data['profit_per_satuan'] = $profitPerUnit;
        $data['profit_percentage'] = $profitPercentage;
        $data['sub_total'] = $subTotal;
        $data['total_profit'] = $totalProfit;
        
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = JWTAuth::user();
        $salesRecord = SalesRecord::where('user_id',$user->id)->find($id);
        
        if (!$salesRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Penjualan tidak ditemukan'
            ], 404);
        }
        
        $validator = Validator::make($request->all(), [
            'product_id' => 'sometimes|required|exists:products,id',
            'month' => 'sometimes|required|integer|min:1|max:12',
            'year' => 'sometimes|required|integer|min:2000|max:2100',
            'number_of_sales' => 'sometimes|required|integer|min:1',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check if updating to a combination that already exists
        if (($request->has('product_id') || $request->has('month') || $request->has('year')) && 
            SalesRecord::where('id', '!=', $id)
                ->where('product_id', $request->product_id ?? $salesRecord->product_id)
                ->where('month', $request->month ?? $salesRecord->month)
                ->where('year', $request->year ?? $salesRecord->year)
                ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Penjualan untuk produk ini sudah ada di bulan yang diminta'
            ], 422);
        }
        
        $salesRecord->update($request->all());
        
        return response()->json([
            'success' => true,
            'message' => 'Penjualan berhasil diperbarui',
            'data' => $salesRecord
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = JWTAuth::user();
        $salesRecord = SalesRecord::where('user_id',$user->id)->find($id);
        
        if (!$salesRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Penjualan tidak ditemukan'
            ], 404);
        }
        
        $salesRecord->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Penjualan berhasil dihapus'
        ]);
    }
}
