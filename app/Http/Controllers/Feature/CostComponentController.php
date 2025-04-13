<?php

namespace App\Http\Controllers\Feature;

use App\Http\Controllers\Controller;
use App\Models\CostComponent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class CostComponentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = CostComponent::query();
        $meta = [];
        
        $query->where('user_id', JWTAuth::user()->id);
        
        if ($request->has('type')) {
            $type = $request->query('type');
            $validTypes = ['direct_material', 'direct_labor', 'overhead', 'packaging', 'other'];
            
            if (!in_array($type, $validTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tipe komponen biaya tidak valid'
                ], 400);
            }
            
            $query->where('component_type', $type);
            $meta['type'] = $type;
        }
        
        if ($request->has('keyword')) {
            $keyword = $request->query('keyword');
            
            if (empty($keyword)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter pencarian (keyword) tidak boleh kosong'
                ], 400);
            }
            
            $query->where(function($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('description', 'like', "%{$keyword}%");
            });
            
            $meta['keyword'] = $keyword;
        }
        
        $costComponents = $query->get();
        
        $meta['total_count'] = $costComponents->count();
        
        $message = 'Daftar komponen biaya';
        if (isset($meta['type'])) {
            $message .= " tipe {$meta['type']}";
        }
        if (isset($meta['keyword'])) {
            $message .= " sesuai pencarian '{$meta['keyword']}'";
        }
        
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $costComponents,
            'meta' => $meta
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'component_type' => 'required|in:direct_material,direct_labor,overhead,packaging,other',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();
        $validatedData['user_id'] = JWTAuth::user()->id;

        $costComponent = CostComponent::create($validatedData);
        
        return response()->json([
            'success' => true,
            'message' => 'Komponen Biaya Berhasil dibuat',
            'data' => $costComponent
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $costComponent = CostComponent::where('id', $id)
                                      ->where('user_id', JWTAuth::user()->id)
                                      ->first();
        
        if (!$costComponent) {
            return response()->json([
                'success' => false,
                'message' => 'Komponen Biaya tidak ditemukan'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $costComponent
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $costComponent = CostComponent::where('id', $id)
                                      ->where('user_id', JWTAuth::user()->id)
                                      ->first();
        
        if (!$costComponent) {
            return response()->json([
                'success' => false,
                'message' => 'Komponen Biaya tidak ditemukan'
            ], 404);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string',
            'component_type' => 'sometimes|required|in:direct_material,direct_labor,overhead,packaging,other',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }
        
        $costComponent->update($validator->validated());
        
        return response()->json([
            'success' => true,
            'message' => 'Komponen Biaya berhasil diperbarui',
            'data' => $costComponent
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $costComponent = CostComponent::where('id', $id)
                                      ->where('user_id', JWTAuth::user()->id)
                                      ->first();
        
        if (!$costComponent) {
            return response()->json([
                'success' => false,
                'message' => 'Komponen Biaya tidak ditemukan'
            ], 404);
        }
        
        if ($costComponent->productCosts()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Komponen Biaya tidak dapat dihapus karena sedang digunakan'
            ], 400);
        }
        
        $costComponent->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Komponen Biaya berhasil dihapus'
        ]);
    }
}
