<?php

namespace App\Http\Controllers\Feature;

use App\Http\Controllers\Controller;
use App\Models\SalesRecord;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalesRecordController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $inputYear = $request->input('year');
        $inputMonth = $request->input('month');

        $validator = Validator::make([
            'year' => $inputYear ?? Carbon::now()->year,
            'month' => $inputMonth,
        ], [
            'year' => 'required|integer|min:2000|max:2900',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();
        $year = $validatedData['year'];
        $month = $validatedData['month'] ?? null;
        if ($inputMonth === null && !isset($validatedData['month'])) {
            $month = Carbon::now()->month;
        } else {
            $month = $validatedData['month'] ?? null;
        }

        try {
            $salesRecords = $this->fetchSalesRecords($year, $month);
            $salesData = $this->processSalesData($salesRecords);
            $summary = $this->calculateSummary($salesData);
            $filters = $this->getAvailableFilters($year, $month);

            return response()->json([
                'success' => true,
                'data' => $salesData,
                'summary' => $summary,
                'filters' => $filters
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    private function fetchSalesRecords(int $year, ?int $month): EloquentCollection
    {
        $query = SalesRecord::with('service')
            ->where('year', $year);

        if ($month) {
            $query->where('month', $month);
        }

        return $query->get();
    }

    private function processSalesData(Collection $salesRecords): Collection
    {
        $salesDataArray = [];

        foreach ($salesRecords as $record) {
            $service = $record->service;
            if (!$service) {
                continue;
            }
            $profitPerUnit = $record->selling_price - $record->hpp;
            $profitPercentage = $this->calculateProfitPercentage($record->selling_price, $profitPerUnit);

            $salesDataArray[] = [
                'id' => $record->id,
                'name' => $record->name,
                'month' => $record->month,
                'year' => $record->year,
                'date' => $record->date,
                'profit_unit' => $profitPerUnit,
                'profit_percentage' => $profitPercentage,
                'sub_total' => $record->selling_price * 1,
                'profit' => $profitPerUnit * 1,
                'service_name' => $service->name,
                'service_sku' => $service->sku,
            ];
        }

        $salesData = collect($salesDataArray);

        $totalProfit = $salesData->sum('profit');
        return $salesData->map(function ($item) use ($totalProfit) {
            $item['profit_contribution_percentage'] = $totalProfit > 0 ?
                round(($item['profit'] / $totalProfit) * 100, 2) : 0;
            return $item;
        });
    }

    private function calculateProfitPercentage(float $sellingPrice, float $profitPerUnit): float
    {
        return $sellingPrice > 0 ? round(($profitPerUnit / $sellingPrice) * 100, 0) : 0;
    }

    private function calculateSummary(Collection $salesData): array
    {
        $totalSales = $salesData->sum('sub_total');
        $totalProfit = $salesData->sum('profit');
        $totalProfitPercentage = $totalSales > 0 ? round(($totalProfit / $totalSales) * 100, 2) : 0;

        return [
            'total_sales' => $totalSales,
            'total_profit' => $totalProfit,
            'total_profit_percentage' => $totalProfitPercentage
        ];
    }

    private function getAvailableFilters(int $year, ?int $month): array
    {
        $availableYears = SalesRecord::select(DB::raw('DISTINCT year'))
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        $availableMonths = [];
        if ($year) {
            $availableMonthsQuery = SalesRecord::where('year', $year)
                ->select(DB::raw('DISTINCT month'))
                ->orderBy('month');
            $availableMonths = $availableMonthsQuery->pluck('month')->toArray();
        }

        return [
            'available_years' => $availableYears,
            'available_months' => $availableMonths,
            'current_year' => $year,
            'current_month' => $month,
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:services,id',
            'name' => 'required|string',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'date' => 'required|integer|min:1|max:31',
            'hpp' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0|gte:hpp',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $record = SalesRecord::create($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Penjualan berhasil disimpan',
                'data' => $record
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $salesRecord = SalesRecord::find($id);

            if (!$salesRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Penjualan Tidak ditemukan!'
                ], 404);
            }

            $service = $salesRecord->service;
            if (!$service) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Data Layanan terkait tidak ditemukan!'
                ], 404);
            }

            $profitPerUnit = $salesRecord->selling_price - $salesRecord->hpp;
            $profitPercentage = $this->calculateProfitPercentage($salesRecord->selling_price, $profitPerUnit);

            $allSalesRecordsThisMonthYear = SalesRecord::where('year', $salesRecord->year)
                ->where('month', $salesRecord->month)
                ->get();

            $totalProfitAllSalesThisMonthYear = 0;
            foreach ($allSalesRecordsThisMonthYear as $record) {
                $totalProfitAllSalesThisMonthYear += ($record->selling_price - $record->hpp);
            }

            $profitContributionPercentage = $totalProfitAllSalesThisMonthYear > 0 ?
                round((($salesRecord->selling_price - $salesRecord->hpp) / $totalProfitAllSalesThisMonthYear) * 100, 2) : 0;

            $data = $salesRecord->toArray();
            $data['service_name'] = $service->name;
            $data['profit_unit'] = $profitPerUnit;
            $data['profit_percentage'] = $profitPercentage;
            $data['profit_contribution_percentage'] = $profitContributionPercentage;

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $salesRecord = SalesRecord::find($id);

        if (!$salesRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Penjualan tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'service_id' => 'sometimes|required|exists:services,id',
            'name' => 'sometimes|required|string',
            'month' => 'sometimes|required|integer|min:1|max:12',
            'year' => 'sometimes|required|integer|min:2000|max:2100',
            'date' => 'sometimes|required|integer|min:1|max:31',
            'hpp' => 'sometimes|required|numeric|min:0',
            'selling_price' => 'sometimes|required|numeric|min:0|gte:hpp',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $salesRecord->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Penjualan berhasil diperbarui',
                'data' => $salesRecord->fresh()
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $salesRecord = SalesRecord::find($id);

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
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
}