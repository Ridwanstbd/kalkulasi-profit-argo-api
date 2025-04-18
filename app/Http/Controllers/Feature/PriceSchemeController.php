<?php

namespace App\Http\Controllers\Feature;

use App\Http\Controllers\Controller;
use App\Models\PriceSchema;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class PriceSchemeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = JWTAuth::user();
        
        $query = Product::where('user_id', $user->id);
        
        $query->withCount('priceSchemas');
        
        $query->orderBy('created_at', 'desc');
        
        $products = $query->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Daftar produk',
            'data' => $products
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'level_name' => 'required|string|max:100',
            'level_order' => 'required|numeric',
            'discount_percentage' => 'nullable',
            'selling_price' => 'nullable',
            'purchase_price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = JWTAuth::user();
        $productId = $request->product_id;
        $product = Product::where('id',$productId)
                    ->where('user_id',$user->id)
                    ->first();
        if(!$product){
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan atau bukan milik anda'
            ],404);
        }
        
        try {
            DB::beginTransaction();
            $discountPercentage = null;
            $sellingPrice = null;
            $profitAmount = null;

            // Menentukan nilai diskon dan harga jual berdasarkan input
            if($request->discount_percentage) {
                // Jika diskon diisi, hitung harga jual
                $discountPercentage = $request->discount_percentage;
                $sellingPrice = $this->calculateSellingPrice($request->purchase_price, $discountPercentage);
            } elseif($request->selling_price) {
                // Jika harga jual diisi, hitung diskon
                $sellingPrice = $request->selling_price;
                $discountPercentage = $this->calculateDiscountPercentage($request->purchase_price, $sellingPrice);
            } else {
                // Jika keduanya tidak diisi, gunakan default (misalnya diskon 0%)
                $discountPercentage = 0;
                $sellingPrice = $this->calculateSellingPrice($request->purchase_price, $discountPercentage);
            }
            
            // Hitung profit amount
            $profitAmount = $sellingPrice - $request->purchase_price;
            $priceSchema = PriceSchema::create([
                'user_id' => $user->id,
                'product_id' => $productId,
                'level_name' => $request->level_name,
                'level_order' => $request->level_order,
                'discount_percentage' => $discountPercentage,
                'purchase_price' => $request->purchase_price,
                'selling_price' => $sellingPrice,
                'profit_amount' => $profitAmount,
                'notes' => $request->notes
            ]);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Skema harga berhasil disimpan',
                'data' => $priceSchema
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Skema harga gagal',
                'errors' => $e->getMessage()
            ],500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = JWTAuth::user();
        $priceSchema = PriceSchema::where('id',$id)->where('user_id',$user->id)->first();
        if(!$priceSchema){
            return response()->json([
                'success'=> false,
                'message' => 'Skema harga belum ditambahkan untuk produk ini'
            ],404);
        }
        return response()->json([
            'success' => true,
            'message' => 'Detail skema harga',
            'data' => $priceSchema
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'level_name' => 'nullable|string|max:100',
            'level_order' => 'nullable|numeric',
            'discount_percentage' => 'nullable',
            'selling_price' => 'nullable',
            'purchase_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
    
        $user = JWTAuth::user();
        $priceSchema = PriceSchema::where('id', $id)
                        ->where('user_id', $user->id)
                        ->first();
                        
        if(!$priceSchema) {
            return response()->json([
                'success' => false,
                'message' => 'Skema harga tidak ditemukan atau bukan milik anda'
            ], 404);
        }
        try {
            DB::beginTransaction();
            $purchasePrice = $request->has('purchase_price') ? $request->purchase_price : $priceSchema->purchase_price;

            $discountPercentage = $priceSchema->discount_percentage;
            $sellingPrice = $priceSchema->selling_price;

            if($request->has('discount_percentage') && !$request->has('selling_price')){
                $discountPercentage = $request->discount_percentage;
                $sellingPrice = $this->calculateSellingPrice($purchasePrice,$discountPercentage);
            }elseif(!$request->has('discount_percentage') && $request->has('selling_price')) {
                $sellingPrice = $request->selling_price;
                $discountPercentage = $this->calculateDiscountPercentage($purchasePrice, $sellingPrice);
            }elseif($request->has('discount_percentage') && $request->has('selling_price')) {
                $discountPercentage = $request->discount_percentage;
                $sellingPrice = $this->calculateSellingPrice($purchasePrice, $discountPercentage);
            }
            $profitAmount = $sellingPrice - $purchasePrice;
            $updateData = [
                'discount_percentage' => $discountPercentage,
                'purchase_price' => $purchasePrice,
                'selling_price' => $sellingPrice,
                'profit_amount' => $profitAmount,
            ];
            
            if($request->has('level_name')) {
                $updateData['level_name'] = $request->level_name;
            }
            
            if($request->has('level_order')) {
                $updateData['level_order'] = $request->level_order;
            }
            
            if($request->has('notes')) {
                $updateData['notes'] = $request->notes;
            }
            $priceSchema->update($updateData);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Skema harga berhasil diperbarui',
                'data' => $priceSchema->fresh()
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Skema harga gagal diperbarui',
                'errors' => $e->getMessage()
            ], 500);
        }        
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = JWTAuth::user();
        $priceSchema = PriceSchema::where('id', $id)
                        ->where('user_id', $user->id)
                        ->first();
                        
        if(!$priceSchema) {
            return response()->json([
                'success' => false,
                'message' => 'Skema harga tidak ditemukan atau bukan milik anda'
            ], 404);
        }
        
        try {
            DB::beginTransaction();
            $productId = $priceSchema->product_id;
            $currentLevelOrder = $priceSchema->level_order;
            $maxLevelOrder = PriceSchema::where('product_id', $productId)
                        ->where('user_id', $user->id)
                        ->max('level_order');
            $priceSchema->delete();
            if ($currentLevelOrder == 1 || $currentLevelOrder < $maxLevelOrder) {
                $remainingSchemas = PriceSchema::where('product_id', $productId)
                                  ->where('user_id', $user->id)
                                  ->orderBy('level_order', 'asc')
                                  ->get();
                
                if ($remainingSchemas->count() > 0) {
                    foreach ($remainingSchemas as $schema) {
                        $schema->selling_price = $this->calculateSellingPrice(
                            $schema->purchase_price, 
                            $schema->discount_percentage
                        );
                        
                        $schema->discount_percentage = $this->calculateDiscountPercentage(
                            $schema->purchase_price, 
                            $schema->selling_price
                        );
                        
                        $schema->profit_amount = $schema->selling_price - $schema->purchase_price;
                        
                        $schema->save();
                    }
                }
            }
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Skema harga berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Skema harga gagal dihapus',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    private function calculateSellingPrice($price, $discountValue)
    {
        $discountPercentage = min($discountValue,99.99);

        return $price / ((100 - $discountPercentage) /100);
    }
    private function calculateDiscountPercentage($price,$sellingPrice){
        if($sellingPrice <= 0 || $price <= 0){
            return 0;
        }
        $discountPercentage = 100 - (($price / $sellingPrice) * 100);
        return min($discountPercentage,99.99);
    }
}