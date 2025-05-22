<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = JWTAuth::user();
        $products = Product::where('user_id', $user->id)->with(['costs'])->get();
        if($products->isEmpty()){
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan.'
            ]);
        }
        $stats = [
            'total_products' => $products->count(),
            'avg_selling_price' => $products->avg('selling_price'),
            'avg_hpp' => $products->avg('hpp'),
            'total_selling_value' => $products->sum('selling_price'),
            'total_hpp_value' => $products->sum('hpp'),
            'profit_margin' => $products->sum('selling_price') - $products->sum('hpp')
        ];
        return response()->json([
            'success' => true,
            'message' => 'Produk ditemukan',
            'stats' => $stats,
            'data' => $products
        ],200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'name' => 'required|string|max:100',
            'sku' => 'required|string|max:50',
            'description' => 'nullable|string',
            'hpp' => 'nullable|numeric',
            'selling_price' => 'nullable|numeric',
        ]);

        if($validator->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        $user = JWTAuth::user();
        if($request->user_id != $user->id){
            return response()->json([
                'success' => false,
                'message' => 'Tidak diizinkan membuat produk untuk pengguna lain'
            ], 403);
        }
        try {
            $product = Product::create($request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil dibuat',
                'data' => $product
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat produk',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = JWTAuth::user();
        try {
            $product = Product::where('id',$id)
                ->where('user_id',$user->id)
                ->with(['costs'])
                ->first();
            if(!$product){
                return response()->json([
                    'success' => false,
                    'message' => 'Product tidak ditemukan'
                ],404);
            }
            $hppBreakdown = $product->hpp_breakdown;

            return response()->json([
                'success' => true,
                'message' => 'Produk ditemukan',
                'data' => $product,
                'hpp_breakdown' => $hppBreakdown
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data produk',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(),[
            'name' => 'string|max:100',
            'sku' => 'string|max:50',
            'description' => 'nullable|string',
            'hpp' => 'numeric',
            'selling_price' => 'numeric',
        ]);

        if($validator->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = JWTAuth::user();
        
        try {
            $product = Product::where('id', $id)->where('user_id', $user->id)->first();
            
            if(!$product){
                return response()->json([
                    'success' => false,
                    'message' => 'Produk tidak ditemukan atau bukan milik pengguna'
                ], 404);
            }
            
            $product->update($request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil diperbarui',
                'data' => $product
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui produk',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = JWTAuth::user();
        
        try {
            $product = Product::where('id', $id)->where('user_id', $user->id)->first();
            
            if(!$product){
                return response()->json([
                    'success' => false,
                    'message' => 'Produk tidak ditemukan atau bukan milik pengguna'
                ], 404);
            }
            
            $product->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus produk',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
