<?php

namespace App\Http\Controllers\Feature;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\OperationalExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class ExpenseCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = JWTAuth::user();
        $categories = ExpenseCategory::with('operationalExpenses')
            ->where('user_id',$user->id)
            ->orderBy('order')
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
            'order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Jika order tidak diisi, gunakan order terbesar + 1
        if (!$request->has('order') || $request->order === null) {
            $maxOrder = ExpenseCategory::max('order') ?? 0;
            $request->merge(['order' => $maxOrder + 1]);
        }
        $user = JWTAuth::user();

        $category = ExpenseCategory::create([
            'user_id' => $user->id,
            'name' => $request['name'],
            'description' => $request['description'],
            'is_salary' => $request['is_salary'],
            'order' => $request['order'],
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
        $user = JWTAuth::user();
        
        $category = ExpenseCategory::with(['operationalExpenses' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }])
            ->where('user_id', $user->id)
            ->find($id);

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
        $user = JWTAuth::user();
        
        $category = ExpenseCategory::where('user_id', $user->id)->find($id);

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
            'order' => 'nullable|integer|min:0',
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
        $user = JWTAuth::user();
        
        $category = ExpenseCategory::where('user_id', $user->id)->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori biaya tidak ditemukan'
            ], 404);
        }

        // Periksa apakah kategori memiliki item biaya
        $hasExpenses = $category->operationalExpenses()
            ->where('user_id', $user->id)
            ->exists();
        
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
