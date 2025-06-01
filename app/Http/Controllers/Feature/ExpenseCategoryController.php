<?php

namespace App\Http\Controllers\Feature;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\OperationalExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExpenseCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = ExpenseCategory::with('operationalExpenses')
            ->get();

        $categories->each(function ($category) {
            $category->total_amount = $category->total_amount_attribute;
            
            if ($category->is_salary) {
                $category->total_employees = $category->total_employee_count_attribute;
            }
        });

        return response()->json([
            'success' => true,
            'data' => $categories,
            'summary' => [
                'total_salary' => OperationalExpense::getTotalSalaryExpenses(),
                'total_operational' => OperationalExpense::getTotalOperationalExpenses(),
                'grand_total' => OperationalExpense::getGrandTotal()
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_salary' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $category = ExpenseCategory::create([
            'name' => $request['name'],
            'description' => $request['description'],
            'is_salary' => $request['is_salary'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori biaya berhasil dibuat',
            'data' => $category
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        
        $category = ExpenseCategory::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori biaya tidak ditemukan'
            ], 404);
        }

        $category->total_amount = $category->total_amount_attribute;
        
        if ($category->is_salary) {
            $category->total_employees = $category->total_employee_count_attribute;
        }

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        
        $category = ExpenseCategory::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori biaya tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_salary' => 'sometimes|required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $category->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Kategori biaya berhasil diperbarui',
            'data' => $category
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        
        $category = ExpenseCategory::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori biaya tidak ditemukan'
            ], 404);
        }

        $hasExpenses = $category->operationalExpenses()->exists();
        
        if ($hasExpenses) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori ini memiliki item biaya. Hapus semua item biaya terlebih dahulu.'
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori biaya berhasil dihapus'
        ]);
    }
}
