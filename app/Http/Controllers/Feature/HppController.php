<?php

namespace App\Http\Controllers\Feature;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class HppController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = JWTAuth::user();
        
        $userProducts = Product::where('user_id', $user->id)->get();
        
        if($userProducts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Produk yang terhubung masih kosong'
            ]);
        }
        
        $productId = $request->input('product_id', $userProducts->first()->id);
        
        $productCosts = ProductCost::where('product_id', $productId)
            ->with(['product', 'costComponent'])
            ->get();
        
        $product = $userProducts->firstWhere('id', $productId);
        
        if($productCosts->isEmpty()){
            return response()->json([
                'success' => false,
                'message' => 'Komponen biaya untuk produk ini masih kosong',
                'product' => $product,
                'all_products' => $userProducts
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Komponen biaya produk berhasil ditampilkan!',
            'product' => $product,
            'all_products' => $userProducts,
            'data' => $productCosts
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
            'costs.*.unit' => 'required|string',
            'costs.*.unit_price' => 'required|numeric|min:0',
            'costs.*.quantity' => 'required|numeric|min:0',
            'costs.*.conversion_qty' => 'required|numeric|min:0',
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
        $costComponentIds = collect($request->costs)->pluck('cost_component_id')->toArray();
        $duplicates = array_count_values($costComponentIds);
        $duplicateIds = [];
        
        foreach ($duplicates as $id => $count) {
            if ($count > 1) {
                $duplicateIds[] = $id;
            }
        }
        
        if (!empty($duplicateIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Terdapat komponen biaya yang diinput lebih dari satu kali',
                'duplicate_components' => $duplicateIds
            ], 422);
        }

        $existingComponents = ProductCost::where('product_id', $productId)
            ->whereIn('cost_component_id', $costComponentIds)
            ->pluck('cost_component_id')
            ->toArray();
            
        if (!empty($existingComponents)) {
            return response()->json([
                'success' => false,
                'message' => 'Komponen biaya sudah ada dalam produk ini',
                'existing_components' => $existingComponents
            ], 422);
        }
        try {
            DB::beginTransaction();

            foreach ($request->costs as $cost){
                $amount = ($cost['conversion_qty'] ?? 1) > 0
                ? ($cost['unit_price'] / $cost['conversion_qty']) * $cost['quantity']
                : $cost['unit_price'] * $cost['quantity'];

                ProductCost::create([
                    'product_id' => $productId,
                    'cost_component_id' => $cost['cost_component_id'],
                    'unit' => $cost['unit'],
                    'unit_price' => $cost['unit_price'],
                    'quantity' => $cost['quantity'],
                    'conversion_qty' => $cost['conversion_qty'],
                    'amount' => $amount,
                ]);
            }

            $hpp = $this->calculateHpp($productId);
            $product->hpp = $hpp;
            $product->save();
            DB::commit();

            $updatedProduct = Product::with('costs.costComponent')->find($productId);
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
    public function show(Request $request, string $id)
    {
        $productCost = ProductCost::where('id', $id)
            ->with('product')    
            ->with('costComponent')
            ->first();
        
        if (!$productCost) {
            return response()->json([
                'success' => true,
                'message' => 'Produk belum memiliki komponen biaya',
                'data' => (object)[]
            ], 200);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Detail komponen biaya produk',
            'data' => $productCost
        ], 200);
    }

    /**
     * Update a single product cost record.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'cost_component_id' => 'required|exists:cost_components,id',
            'unit' => 'required|string',
            'unit_price' => 'required|numeric|min:0',
            'quantity' => 'required|numeric|min:0',
            'conversion_qty' => 'required|numeric|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $user = JWTAuth::user();
        $productCost = ProductCost::findOrFail($id);
        
        $product = Product::where('id', $productCost->product_id)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan atau bukan milik Anda'
            ], 403);
        }
        if ($request->cost_component_id != $productCost->cost_component_id) {
            $exists = ProductCost::where('product_id', $productCost->product_id)
                ->where('cost_component_id', $request->cost_component_id)
                ->where('id', '!=', $id)
                ->exists();
                
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Komponen biaya ini sudah ada dalam produk'
                ], 422);
            }
        }
        try {
            DB::beginTransaction();
            
            $amount = ($request->conversion_qty > 0)
                ? ($request->unit_price / $request->conversion_qty) * $request->quantity
                : $request->unit_price * $request->quantity;
            
            $productCost->update([
                'product_id' => $request->product_id,
                'cost_component_id' => $request->cost_component_id,
                'unit' => $request->unit,
                'unit_price' => $request->unit_price,
                'quantity' => $request->quantity,
                'conversion_qty' => $request->conversion_qty,
                'amount' => $amount,
            ]);
            
            $hpp = $this->calculateHpp($product->id);
            $product->hpp = $hpp;
            $product->save();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Komponen biaya berhasil diperbarui',
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
     * Remove the specified product cost.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id)
    {
        $user = JWTAuth::user();
        $productCost = ProductCost::findOrFail($id);
        
        $product = Product::where('id', $productCost->product_id)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan atau bukan milik Anda'
            ], 403);
        }
        
        try {
            DB::beginTransaction();
            
            // Delete the specific product cost
            $productCost->delete();
            
            // Recalculate and update HPP
            $hpp = $this->calculateHpp($product->id);
            $product->hpp = $hpp;
            $product->save();
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Komponen biaya berhasil dihapus'
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
        
        return $totalCost;
    }
}
