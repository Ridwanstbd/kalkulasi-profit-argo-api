<?php

namespace App\Http\Controllers\Feature;

use App\Http\Controllers\Controller;
use App\Models\PricingSimulation;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class PricingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = JWTAuth::user();
        $simulations = PricingSimulation::where('user_id',$user->id)
                        ->with('product')
                        ->get();
        if($simulations->isEmpty()){
            return response()->json([
                'success' => true,
                'message' => 'Data simulasi penentuan harga masih kosong'
            ]);
        }
        return response()->json([
            'success'=> true,
            'data' => $simulations
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'name' => 'required|string|max:100',
            'margin_type' => 'required|in:percentage,fixed',
            'margin_value' => 'required|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'market_position' => 'nullable|in:premium,standard,economy',
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
        $baseHpp = $product->hpp;
        $marginType = $request->margin_type;
        $marginValue = $request->margin_value;
        $discountType = $request->discount_type;
        $discountValue = $request->discount_value ?? 0;
        try {
            DB::beginTransaction();
            $priceBeforeDiscount = $this->calculatePriceWithMargin($baseHpp,$marginType,$marginValue);
            $retailPrice = $priceBeforeDiscount;
            if($discountType && $discountValue>0){
                $retailPrice = $this->calculateRetailPrice($priceBeforeDiscount,$discountType,$discountValue);
            }
            $profit = $priceBeforeDiscount - $baseHpp;
            $profitPercentage = $baseHpp > 0 ? ($profit / $baseHpp) * 100: 0 ;
            $simulation = PricingSimulation::create([
                'user_id' => $user->id,
                'product_id' => $productId,
                'name' => $request->name,
                'base_hpp' => $baseHpp,
                'margin_type' => $marginType,
                'margin_value' => $marginValue,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'price_before_discount' => $priceBeforeDiscount,
                'retail_price' => $retailPrice,
                'profit' => $profit,
                'profit_percentage' => $profitPercentage,
                'market_position' => $request->market_position,
                'notes' => $request->notes,
                'is_applied' => false
            ]); 
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Simulasi harga berhasil disimpan',
                'data' => $simulation
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Simulasi harga gagal',
                'errors' => $e->getMessage()
            ],500);
        }

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $simulationId = (int) $id;
        $user = JWTAuth::user();

        $simulation = PricingSimulation::where('id', $simulationId)
                        ->where('user_id',$user->id)
                        ->with('product')
                        ->first();
        if (!$simulation) {
            return response()->json([
                'success' => false,
                'message' => 'Simulasi harga tidak ditemukan atau bukan milik Anda'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'message' => 'Simulasi harga ditemukan',
            'data' => $simulation 
        ],200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $simulationId = (int) $id;
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:100',
            'margin_type' => 'in:percentage,fixed',
            'margin_value' => 'numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'market_position' => 'nullable|in:premium,standard,economy',
            'notes' => 'nullable|string',
            'is_applied' => 'boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $user = JWTAuth::user();
        try {
            DB::beginTransaction();
            $simulation = PricingSimulation::where('id',$simulationId)->where('user_id',$user->id)->first();
            if (!$simulation) {
                return response()->json([
                    'success' => true,
                    'message' => 'Simulasi harga tidak ditemukan atau bukan milik anda'
                ],404);
            }
            if ($request->name) {
                $simulation->name = $request->name;
            }

            $recalculate = false;
            if ($request->has('margin_type') || $request->has('margin_value')) {
                $simulation->margin_type = $request->input('margin_type', $simulation->margin_type);
                $simulation->margin_value = $request->input('margin_value',$simulation->margin_value);
                $recalculate = true;
            }
            if ($request->has('discount_type') || $request->has('discount_value')) {
                $simulation->discount_type = $request->input('discount_type',$simulation->discount_type);
                $simulation->discount_value = $request->input('discount_value',$simulation->discount_value);
                $recalculate = true;
            }
            if ($request->has('market_position')) {
                $simulation->market_position = $request->market_position;
            }
            
            if ($request->has('notes')) {
                $simulation->notes = $request->notes;
            }
            if ($request->has('is_applied')) {
                $simulation->is_applied = $request->is_applied;
                if ($simulation->is_applied) {
                    $product = Product::find($simulation->product_id);
                    $product->selling_price = $simulation->retail_price;
                    $product->save();
                }
            }

            if ($recalculate){
                $product = Product::find($simulation->product_id);
                $baseHpp = $product->hpp;

                $priceBeforeDiscount = $this->calculatePriceWithMargin(
                    $baseHpp,
                    $simulation->margin_type,
                    $simulation->margin_value
                );

                $retailPrice = $priceBeforeDiscount;
                if ($simulation->discount_type && $simulation->discount_value > 0){
                    $retailPrice = $this->calculateRetailPrice(
                        $priceBeforeDiscount,
                        $simulation->discount_type,
                        $simulation->discount_value
                    );
                }

                $profit = $priceBeforeDiscount - $baseHpp;
                $profitPercentage = $baseHpp > 0 ? ($profit / $baseHpp) * 100 : 0;
                $simulation->base_hpp = $baseHpp;
                $simulation->price_before_discount = $priceBeforeDiscount;
                $simulation->retail_price = $retailPrice;
                $simulation->profit = $profit;
                $simulation->profit_percentage = $profitPercentage;
            }
            $simulation->save();
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Simulasi harga berhasil diperbarui',
                'data' => $simulation
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Simulasi harga gagal diperbarui',
                'data' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $simulationId = (int) $id;
        $user = JWTAuth::user();
        $simulation = PricingSimulation::where('id', $simulationId)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$simulation) {
            return response()->json([
                'success' => false,
                'message' => 'Simulasi harga tidak ditemukan atau bukan milik Anda'
            ], 404);
        }
        try {
            DB::beginTransaction();
            if ($simulation->is_applied) {
                return response()->json([
                    'success' => false,
                    'message' => 'Simulasi harga tidak dapat dihapus karena sedang diterapkan pada produk'
                ], 400);
            }
            
            $simulation->delete();
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Simulasi harga berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Simulasi harga gagal dihapus',
                'errors' => $e->getMessage()
            ], 500);
        }
    }
    public function applySimulation($id){
        $simulationId = (int) $id;
        $user = JWTAuth::user();

        $simulation = PricingSimulation::where('id',$simulationId)
                        ->where('user_id',$user->id)
                        ->first();
        if(!$simulation){
            return response()->json([
                'success' => false,
                'message' => 'Simulasi penentuan harga tidak ditemukan atau bukan milik anda'
            ],404);
        }
        try {
            DB::beginTransaction();
            $simulation->update([
                'is_applied' => true
            ]);
            $product = Product::find($simulation->product_id);
            $product->update([
                'selling_price' => $simulation->retail_price
            ]);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Simulasi harga berhasil diterapkan ke produk',
                'data' => [
                    'simulation' => $simulation,
                    'product' => $product
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
               'success' => false,
                'message' => 'Simulasi harga gagal diterapkan ke produk',
                'errors' => 'Terjadi Kesalahan :'. $e->getMessage() 
            ],500);
        }
    }
    private function calculatePriceWithMargin($hpp,$marginType,$marginValue)
    {
        if($marginType === 'fixed'){
            return $hpp + $marginValue;
        }else{
            $marginPercentage = min($marginValue,99.99);
            return $hpp / ((100 - $marginPercentage) / 100);
        }        
    }
    private function calculateRetailPrice($price, $discountType, $discountValue)
    {
        if($discountType === 'fixed'){
            return $price + $discountValue;
        }else{
            $discountPercentage = min($discountValue,99.99);

            return $price / ((100 - $discountPercentage) /100);
        }
    }
}
