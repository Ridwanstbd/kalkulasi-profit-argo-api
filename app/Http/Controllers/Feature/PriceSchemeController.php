<?php

namespace App\Http\Controllers\Feature;

use App\Http\Controllers\Controller;
use App\Models\PriceSchema;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PriceSchemeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $userServices = Service::all();

        if ($userServices->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Layanan yang terhubung masih kosong'
            ]);
        }

        $serviceId = $request->input('service_id', $userServices->first()->id);

        $service = $userServices->firstWhere('id', $serviceId);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Layanan tidak ditemukan'
            ]);
        }

        $priceSchemas = PriceSchema::where('service_id', $service->id)
            ->with(['service'])
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar skema harga berhasil ditampilkan',
            'data' => $priceSchemas,
            'service' => $service,
            'all_services' => $userServices
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:services,id',
            'level_name' => 'required|string|max:100',
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

        $serviceId = $request->service_id;
        $service = Service::find($serviceId);
        
        if(!$service){
            return response()->json([
                'success' => false,
                'message' => 'Layanan tidak ditemukan'
            ],404);
        }
        
        try {
            
            $maxLevelOrder = PriceSchema::where('service_id', $serviceId)
                            ->max('level_order') ?? 0;
            
            $nextLevelOrder = $maxLevelOrder + 1;
            
            $purchasePrice = null;
            
            if ($nextLevelOrder == 1) {
                $purchasePrice = $service->hpp ?? $request->purchase_price;
            } else {
                $previousSchema = PriceSchema::where('service_id', $serviceId)
                                ->where('level_order', $maxLevelOrder)
                                ->first();
                
                if ($previousSchema) {
                    $purchasePrice = $previousSchema->selling_price;
                } else {
                    $purchasePrice = $request->purchase_price ?? $service->hpp;
                }
            }
            
            if ($purchasePrice === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Harga pembelian (purchase price) diperlukan untuk skema harga pertama'
                ], 422);
            }
            
            $discountPercentage = null;
            $sellingPrice = null;
            $profitAmount = null;

            if($request->discount_percentage) {
                $discountPercentage = $request->discount_percentage;
                $sellingPrice = $this->calculateSellingPrice($purchasePrice, $discountPercentage);
            } elseif($request->selling_price) {
                $sellingPrice = $request->selling_price;
                $discountPercentage = $this->calculateDiscountPercentage($purchasePrice, $sellingPrice);
            } else {
                $discountPercentage = 0;
                $sellingPrice = $this->calculateSellingPrice($purchasePrice, $discountPercentage);
            }
            
            $profitAmount = $sellingPrice - $purchasePrice;
            $priceSchema = PriceSchema::create([
                'service_id' => $serviceId,
                'level_name' => $request->level_name,
                'level_order' => $nextLevelOrder, 
                'discount_percentage' => $discountPercentage,
                'purchase_price' => $purchasePrice,
                'selling_price' => $sellingPrice,
                'profit_amount' => $profitAmount,
                'notes' => $request->notes
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Skema harga berhasil disimpan',
                'data' => $priceSchema
            ], 201);
        } catch (\Exception $e) {
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
        $priceSchema = PriceSchema::find($id);
        
        if(!$priceSchema){
            return response()->json([
                'success'=> false,
                'message' => 'Skema harga belum ditambahkan untuk Layanan ini'
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

        $priceSchema = PriceSchema::find($id);
                                
        if(!$priceSchema) {
            return response()->json([
                'success' => false,
                'message' => 'Skema harga tidak ditemukan'
            ], 404);
        }
        
        try {
            return DB::transaction(function () use ($request, $priceSchema) {
                $oldLevelOrder = $priceSchema->level_order;
                $newLevelOrder = $request->has('level_order') ? (int)$request->level_order : $oldLevelOrder;
                
                if($request->has('level_order') && $newLevelOrder != $oldLevelOrder) {
                    $maxLevel = PriceSchema::where('service_id', $priceSchema->service_id)->count();
                    $newLevelOrder = max(1, min($newLevelOrder, $maxLevel));
                    
                    $otherSchemasCollection = PriceSchema::where('service_id', $priceSchema->service_id)
                                                    ->where('id', '!=', $priceSchema->id)
                                                    ->orderBy('level_order')
                                                    ->get();
                    
                    $tempList = $otherSchemasCollection->all();
                    $targetSpliceIndex = $newLevelOrder - 1;
                    array_splice($tempList, $targetSpliceIndex, 0, [$priceSchema]);
                    
                    $reorderedSchemas = [];
                    foreach ($tempList as $index => $schemaInNewOrder) {
                        $reorderedSchemas[] = [
                            'id' => $schemaInNewOrder->id,
                            'level_order' => $index + 1
                        ];
                    }
                    
                    foreach ($reorderedSchemas as $schemaData) {
                        DB::table('price_schemas')
                            ->where('id', $schemaData['id'])
                            ->update(['level_order' => $schemaData['level_order'] + 1000]);
                    }
                    
                    foreach ($reorderedSchemas as $schemaData) {
                        DB::table('price_schemas')
                            ->where('id', $schemaData['id'])
                            ->update(['level_order' => $schemaData['level_order']]);
                    }
                    
                    $priceSchema->refresh();
                }
                
                $purchasePrice = $priceSchema->purchase_price;
                
                if ($request->has('purchase_price') && $priceSchema->level_order == 1) {
                    $purchasePrice = $request->purchase_price;
                } elseif ($priceSchema->level_order == 1) {
                    $service = Service::find($priceSchema->service_id);
                    $purchasePrice = $service ? $service->hpp : $priceSchema->purchase_price;
                } else {
                    $previousSchema = PriceSchema::where('service_id', $priceSchema->service_id)
                                            ->where('level_order', $priceSchema->level_order - 1)
                                            ->first();
                    
                    if ($previousSchema) {
                        $purchasePrice = $previousSchema->selling_price;
                    }
                }
                $discountPercentage = $priceSchema->discount_percentage;
                $sellingPrice = $priceSchema->selling_price;

                if($request->has('discount_percentage') && !$request->has('selling_price')){
                    $discountPercentage = $request->discount_percentage;
                    $sellingPrice = $this->calculateSellingPrice($purchasePrice, $discountPercentage);
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
                
                if($request->has('notes')) {
                    $updateData['notes'] = $request->notes;
                }
                
                $priceSchema->update($updateData);
                
                $nextSchema = PriceSchema::where('service_id', $priceSchema->service_id)
                                ->where('level_order', $priceSchema->level_order + 1)
                                ->first();
                
                if ($nextSchema) {
                    $nextSchema->update([
                        'purchase_price' => $sellingPrice,
                        'profit_amount' => $nextSchema->selling_price - $sellingPrice
                    ]);
                }
                
                return response()->json([
                    'success' => true,
                    'message' => 'Skema harga berhasil diperbarui',
                    'data' => $priceSchema->fresh()
                ], 200);
            });
        } catch (\Exception $e) {
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
        $priceSchema = PriceSchema::find($id);
                        
        if(!$priceSchema) {
            return response()->json([
                'success' => false,
                'message' => 'Skema harga tidak ditemukan'
            ], 404);
        }
        
        try {
            $serviceId = $priceSchema->service_id;
            $deletedLevelOrder = $priceSchema->level_order;
            
            $nextSchema = PriceSchema::where('service_id', $serviceId)
                        ->where('level_order', $deletedLevelOrder + 1)
                        ->first();
            
            $previousSchema = PriceSchema::where('service_id', $serviceId)
                        ->where('level_order', $deletedLevelOrder - 1)
                        ->first();
            
            $priceSchema->delete();
            
            PriceSchema::where('service_id', $serviceId)
                ->where('level_order', '>', $deletedLevelOrder)
                ->decrement('level_order');
            
            if ($nextSchema && $previousSchema) {
                $nextSchema->purchase_price = $previousSchema->selling_price;
                $nextSchema->profit_amount = $nextSchema->selling_price - $previousSchema->selling_price;
                
                $nextSchema->discount_percentage = $this->calculateDiscountPercentage(
                    $previousSchema->selling_price, 
                    $nextSchema->selling_price
                );
                
                $nextSchema->save();
            } else if ($nextSchema && !$previousSchema && $deletedLevelOrder == 1) {
                $service = Service::find($serviceId);
                if ($service) {
                    $nextSchema->purchase_price = $service->hpp;
                    $nextSchema->profit_amount = $nextSchema->selling_price - $service->hpp;
                    
                    $nextSchema->discount_percentage = $this->calculateDiscountPercentage(
                        $service->hpp, 
                        $nextSchema->selling_price
                    );
                    
                    $nextSchema->save();
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Skema harga berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Skema harga gagal dihapus',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    private function calculateSellingPrice($price, $discountValue)
    {
        $discountPercentage = min((float)$discountValue, 99.99);
        if (($price === null || $price === '') || ($discountPercentage === null || $discountPercentage === '')) {
             return null;
        }
        if ((100 - $discountPercentage) == 0) return $price;
        $result = (float)$price / ((100 - $discountPercentage) / 100);
        
        return round($result, 2);
    }

    private function calculateDiscountPercentage($price, $sellingPrice)
    {
        if (($price === null || $price === '') || ($sellingPrice === null || $sellingPrice === '')) {
            return 0;
        }
        $price = (float)$price;
        $sellingPrice = (float)$sellingPrice;

        if($sellingPrice <= 0 || $price <= 0){
            return 0;
        }
        $discountPercentage = 100 - (($price / $sellingPrice) * 100);
        
        return round(min($discountPercentage, 99.99), 2);
    }
}