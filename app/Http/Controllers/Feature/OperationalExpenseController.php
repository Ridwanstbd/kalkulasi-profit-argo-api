<?php

namespace App\Http\Controllers\Feature;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\OperationalExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class OperationalExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = JWTAuth::user();
        
        $query = OperationalExpense::with('category')
            ->where('user_id', $user->id);
        
        if ($request->has('category_id')) {
            $query->where('expense_category_id', $request->category_id);
        }
        
        if ($request->has('is_salary')) {
            $isSalary = filter_var($request->is_salary, FILTER_VALIDATE_BOOLEAN);
            $query->whereHas('category', function ($q) use ($isSalary) {
                $q->where('is_salary', $isSalary);
            });
        }
        
        $expenses = $query->orderBy('expense_category_id')->get();
        
        return response()->json([
            'success' => true,
            'data' => $expenses
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = JWTAuth::user();
        
        $validator = Validator::make($request->all(), [
            'expense_category_id' => 'required|exists:expense_categories,id',
            'name' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'unit' => 'required|string|max:50',
            'amount' => 'required|numeric|min:0',
            'conversion_factor' => 'required|numeric|min:0',
            'conversion_unit' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Verifikasi bahwa kategori milik user yang sama
        $categoryBelongsToUser = ExpenseCategory::where('id', $request->expense_category_id)
            ->where('user_id', $user->id)
            ->exists();
            
        if (!$categoryBelongsToUser) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori biaya tidak ditemukan'
            ], 404);
        }

        // Tambahkan user_id ke data yang akan disimpan
        $data = $request->all();
        $data['user_id'] = $user->id;
        
        $expense = OperationalExpense::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Item biaya operasional berhasil dibuat',
            'data' => $expense
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = JWTAuth::user();
        
        $expense = OperationalExpense::with('category')
            ->where('user_id', $user->id)
            ->find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Item biaya operasional tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $expense
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = JWTAuth::user();
        
        $expense = OperationalExpense::where('user_id', $user->id)->find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Item biaya operasional tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'expense_category_id' => 'sometimes|required|exists:expense_categories,id',
            'name' => 'sometimes|required|string|max:255',
            'quantity' => 'sometimes|required|integer|min:1',
            'unit' => 'sometimes|required|string|max:50',
            'amount' => 'sometimes|required|numeric|min:0',
            'conversion_factor' => 'sometimes|required|numeric|min:0',
            'conversion_unit' => 'sometimes|required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        if ($request->has('expense_category_id')) {
            $categoryBelongsToUser = ExpenseCategory::where('id', $request->expense_category_id)
                ->where('user_id', $user->id)
                ->exists();
                
            if (!$categoryBelongsToUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori biaya tidak ditemukan'
                ], 404);
            }
        }

        $expense->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Item biaya operasional berhasil diperbarui',
            'data' => $expense
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = JWTAuth::user();
        
        $expense = OperationalExpense::where('user_id', $user->id)->find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Item biaya operasional tidak ditemukan'
            ], 404);
        }

        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item biaya operasional berhasil dihapus'
        ]);
    }
}
