<?php

namespace App\Http\Controllers\Feature;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\ProductMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class HppController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = JWTAuth::user();
        $products = Product::where('user_id',$user->id)
        ->with(['costs.costComponent','materials.material'])
        ->get();
        if($products->isEmpty()){
            return response()->json([
                'success' => true,
                'message' => 'Produk yang terhubung masih kosong'
            ]);
        }
        return response()->json([
            'success' =>true,
            'message' => 'Produk berhasil ditampilkan!',
            'data' => $products
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'costs' => 'required|array',
            'costs.*.cost_component_id' => 'required|exists:cost_components,id',
            'costs.*.amount' => 'required|numeric|min:0',
            'costs.*.description' => 'nullable|string',
            'materials' => 'required|array',
            'materials.*.material_id' => 'required|exists:materials,id',
            'materials.*.quantity' => 'required|numeric|min:0'
        ]);
        if($validator->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ],422);
        }
        $user = JWTAuth::user();
        $productId = $request->product_id;
        
        $product = Product::where('id', $productId)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan atau bukan milik Anda'
            ], 404);
        }
        try {
            DB::beginTransaction();

            ProductCost::where('product_id', $productId)->delete();
            foreach ($request->costs as $cost){
                ProductCost::create([
                    'product_id' => $productId,
                    'cost_component_id' => $cost['cost_component_id'],
                    'amount' => $cost['amount'],
                    'description' => $cost['description'] ?? null,
                ]);
            }

            ProductMaterial::where('product_id',$productId)->delete();
            foreach ($request->materials as $material) {
                ProductMaterial::create([
                    'product_id' => $productId,
                    'material_id' => $material['material_id'],
                    'quantity' => $material['quantity'],
                ]);
            }

            $hpp = $this->calculateHpp($productId);
            $product->hpp = $hpp;
            $product->save();
            DB::commit();

            $updatedProduct = Product::with(['costs.costComponent', 'materials.material'])->find($productId);
            return response()->json([
                'success' => true,
                'message' => 'HPP berhasil disimpan',
                'data' => $updatedProduct
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi Kesalahan: '. $e->getMessage()
            ],500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $productId = (int) $id;
        $user = JWTAuth::user();
        $product = Product::where('id',$productId)
                ->where('user_id',$user->id)
                ->with(['costs.costComponent', 'materials.material'])
                ->first();
        if(!$product){
            return response()->json([
                'success' =>false,
                'message' => 'Produk tidak ditemukan'
            ],404);
        }
        return response()->json([
            'success' => true,
            'data' => $product
        ],200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'costs' => 'sometimes|required|array',
            'costs.*.cost_component_id' => 'required|exists:cost_components,id',
            'costs.*.amount' => 'required|numeric|min:0',
            'costs.*.description' => 'nullable|string',
            'materials' => 'sometimes|required|array',
            'materials.*.material_id' => 'required|exists:materials,id',
            'materials.*.quantity' => 'required|numeric|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $user = JWTAuth::user();
        
        // Cek kepemilikan produk
        $product = Product::where('id', $id)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan atau bukan milik Anda'
            ], 404);
        }
        try {
            DB::beginTransaction();
            $productId = (int) $id;
            if($request->has('costs')){
                ProductCost::where('product_id',$productId)->delete();
                foreach ($request->costs as $cost) {
                    ProductCost::create([
                        'product_id' => $id,
                        'cost_component_id' => $cost['cost_component_id'],
                        'amount' => $cost['amount'],
                        'description' => $cost['description'] ?? null,
                    ]);
                }
            }
            if($request->has('materials')){
                ProductMaterial::where('product_id', $productId)->delete();
                foreach ($request->materials as $material) {
                    ProductMaterial::create([
                        'product_id' => $id,
                        'material_id' => $material['material_id'],
                        'quantity' => $material['quantity'],
                    ]);
                }
            }
            $hpp = $this->calculateHpp($productId);
            $product->hpp = $hpp;
            $product->save();
            DB::commit();

            $updatedProduct = Product::with(['costs.costComponent', 'materials.material'])->find($productId);
            return response()->json([
                'success' => true,
                'message' => 'HPP berhasil diperbarui',
                'data' => $updatedProduct
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
        
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = JWTAuth::user();
        $productId = (int) $id;

        $product = Product::where('id', $productId)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan atau bukan milik Anda'
            ], 404);
        }
        try {
            DB::beginTransaction();
            ProductCost::where('product_id',$productId)->delete();
            ProductMaterial::where('product_id', $productId)->delete();
            $product->hpp = 0;
            $product->save();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'HPP berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    private function calculateHpp($productId)
    {
        $totalCost = ProductCost::where('product_id', $productId)->sum('amount');
        $materialCosts = 0;
        $productMaterials = ProductMaterial::where('product_id',$productId)->with('material')->get();

        foreach ($productMaterials as $productMaterial) {
            $materialCosts += $productMaterial->quantity * $productMaterial->material->price_per_unit;
        }
        return $totalCost + $materialCosts;
    }
}
