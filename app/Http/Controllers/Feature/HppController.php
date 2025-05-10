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
    public function index()
    {
        $user = JWTAuth::user();
        $products = Product::where('user_id',$user->id)
        ->with('costs.costComponent')
        ->get();
        if($products->isEmpty()){
            return response()->json([
                'success' => false,
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
            'costs.*.unit' => 'required|string',
            'costs.*.unit_price' => 'required|numeric|min:0',
            'costs.*.quantity' => 'required|numeric|min:0',
            'costs.*.conversion_qty' => 'required|numeric|min:0',
            'costs.*.description' => 'nullable|string',
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
                    'description' => $cost['description'] ?? null,
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
    public function show(string $id)
    {
        $productId = (int) $id;
        $user = JWTAuth::user();
        $product = Product::where('id',$productId)
                ->where('user_id',$user->id)
                ->with(['costs.costComponent'])
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
            'costs' => 'required|array',
            'costs.*.id' => 'sometimes|exists:product_costs,id',
            'costs.*.cost_component_id' => 'required|exists:cost_components,id',
            'costs.*.unit' => 'required|string',
            'costs.*.unit_price' => 'required|numeric|min:0',
            'costs.*.quantity' => 'required|numeric|min:0',
            'costs.*.conversion_qty' => 'required|numeric|min:0',
            'costs.*.description' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $user = JWTAuth::user();
        $productId = (int) $id;
        
        // Cek kepemilikan produk
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
            
            foreach ($request->costs as $cost) {
                $amount = ($cost['conversion_qty'] ?? 1) > 0
                    ? ($cost['unit_price'] / $cost['conversion_qty']) * $cost['quantity']
                    : $cost['unit_price'] * $cost['quantity'];
                
                // Cek apakah ada ID cost, jika ada , jika tidak create baru
                if (isset($cost['id'])) {
                    // Pastikan cost ID tersebut terkait dengan product ini
                    $productCost = ProductCost::where('id', $cost['id'])
                        ->where('product_id', $productId)
                        ->first();
                    
                    if ($productCost) {
                        $productCost->update([
                            'cost_component_id' => $cost['cost_component_id'],
                            'unit' => $cost['unit'],
                            'unit_price' => $cost['unit_price'],
                            'quantity' => $cost['quantity'],
                            'conversion_qty' => $cost['conversion_qty'],
                            'amount' => $amount,
                            'description' => $cost['description'] ?? null,
                        ]);
                    }
                } else {
                    // Buat biaya baru
                    ProductCost::create([
                        'product_id' => $productId,
                        'cost_component_id' => $cost['cost_component_id'],
                        'unit' => $cost['unit'],
                        'unit_price' => $cost['unit_price'],
                        'quantity' => $cost['quantity'],
                        'conversion_qty' => $cost['conversion_qty'],
                        'amount' => $amount,
                        'description' => $cost['description'] ?? null,
                    ]);
                }
            }
            
            $hpp = $this->calculateHpp($productId);
            $product->hpp = $hpp;
            $product->save();
            DB::commit();

            $updatedProduct = Product::with('costs.costComponent')->find($productId);
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
        
        return $totalCost;
    }
}
